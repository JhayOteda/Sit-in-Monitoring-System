<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit;
}
require 'db.php';

$success = $error = "";
$labs = ['524', '526', '528', '530', '544'];

// Helper function to parse software string into name and version
function parseSoftwareString($str) {
    $str = trim($str);
    if (empty($str)) return null;
    
    // Pattern matches a name followed by a version number (e.g. "Visual Studio Code 2024.1", "Python 3.10", "JDK 17")
    if (preg_match('/^(.*?)\s+(v?(?:\d+\.)*\d+\S*)$/i', $str, $matches)) {
        return [
            'name' => trim($matches[1]),
            'version' => trim($matches[2])
        ];
    }
    
    return [
        'name' => $str,
        'version' => ''
    ];
}

// Handle CSV Template Download
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=software_import_template.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, $labs); // Output headers: 524, 526, 528, 530, 544
    fputcsv($output, ['Visual Studio Code 1.85', 'Google Chrome v120', 'Python 3.10', 'MySQL Workbench 8.0', 'Node.js 20']);
    fputcsv($output, ['NetBeans 19', 'Firefox', 'Git 2.43', 'Eclipse', 'Postman']);
    fclose($output);
    exit;
}

// Handle CSV Import
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['import_csv'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['csv_file']['tmp_name'];
        
        if (($handle = fopen($file_tmp, "r")) !== FALSE) {
            $headers = fgetcsv($handle, 1000, ",");
            if ($headers !== FALSE) {
                // Map column index to lab room number
                $lab_mapping = [];
                foreach ($headers as $index => $header) {
                    $room = trim($header);
                    if (in_array($room, $labs)) {
                        $lab_mapping[$index] = $room;
                    }
                }
                
                if (empty($lab_mapping)) {
                    $error = "Invalid CSV headers. None of the columns matched valid laboratory rooms.";
                } else {
                    try {
                        $pdo->beginTransaction();
                        
                        $software_imported = 0;
                        $assignments_created = 0;
                        
                        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                            foreach ($row as $index => $cell_value) {
                                if (isset($lab_mapping[$index])) {
                                    $cell_value = trim($cell_value);
                                    if ($cell_value !== "") {
                                        $lab_room = $lab_mapping[$index];
                                        
                                        $parsed = parseSoftwareString($cell_value);
                                        if ($parsed) {
                                            $sw_name = $parsed['name'];
                                            $sw_ver = $parsed['version'];
                                            
                                            // Check if software already exists in software table (unique by name)
                                            $stmt_check = $pdo->prepare("SELECT id, version FROM software WHERE name = ?");
                                            $stmt_check->execute([$sw_name]);
                                            $existing_sw = $stmt_check->fetch(PDO::FETCH_ASSOC);
                                            
                                            if ($existing_sw) {
                                                $software_id = $existing_sw['id'];
                                                if (empty($existing_sw['version']) && !empty($sw_ver)) {
                                                    $stmt_update = $pdo->prepare("UPDATE software SET version = ? WHERE id = ?");
                                                    $stmt_update->execute([$sw_ver, $software_id]);
                                                }
                                            } else {
                                                $stmt_insert = $pdo->prepare("INSERT INTO software (name, version) VALUES (?, ?)");
                                                $stmt_insert->execute([$sw_name, $sw_ver]);
                                                $software_id = $pdo->lastInsertId();
                                                $software_imported++;
                                            }
                                            
                                            // Check assignment
                                            $stmt_check_assign = $pdo->prepare("SELECT id FROM lab_software WHERE lab_room = ? AND software_id = ?");
                                            $stmt_check_assign->execute([$lab_room, $software_id]);
                                            if (!$stmt_check_assign->fetch()) {
                                                $stmt_assign = $pdo->prepare("INSERT INTO lab_software (lab_room, software_id) VALUES (?, ?)");
                                                $stmt_assign->execute([$lab_room, $software_id]);
                                                $assignments_created++;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        
                        $pdo->commit();
                        $success = "Successfully imported software inventory! Registered $software_imported new software and created $assignments_created lab assignments.";
                        
                        // Refresh software list and assignments
                        $stmt = $pdo->query("SELECT * FROM software ORDER BY name ASC");
                        $software_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        $stmt = $pdo->query("SELECT ls.lab_room, ls.software_id, s.name, s.version FROM lab_software ls JOIN software s ON ls.software_id = s.id ORDER BY s.name ASC");
                        $lab_assets = [];
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $lab_assets[$row['lab_room']][] = $row;
                        }
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error = "Failed to import CSV: " . $e->getMessage();
                    }
                }
            } else {
                $error = "The uploaded CSV file is empty.";
            }
            fclose($handle);
        } else {
            $error = "Failed to read the uploaded CSV file.";
        }
    } else {
        $error = "Please upload a valid CSV file.";
    }
}

// Handle Export to CSV
if (isset($_GET['export_lab'])) {
    $lab_room = $_GET['export_lab'];
    
    if (in_array($lab_room, $labs)) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=Lab_' . $lab_room . '_Software_Inventory.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Software Name', 'Version', 'Assigned To']);
        
        try {
            $stmt = $pdo->prepare("SELECT s.name, s.version FROM software s JOIN lab_software ls ON s.id = ls.software_id WHERE ls.lab_room = ? ORDER BY s.name ASC");
            $stmt->execute([$lab_room]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [$row['name'], $row['version'] ? $row['version'] : 'N/A', 'Lab Room ' . $lab_room]);
            }
        } catch (Exception $e) {}
        
        fclose($output);
        exit;
    }
}

// Handle Software Addition with Lab Assignments
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_software'])) {
    $name = trim($_POST['software_name']);
    $version = trim($_POST['software_version']);
    $selected_labs = $_POST['assigned_labs'] ?? [];
    
    if (empty($name)) {
        $error = "Software name is required.";
    } else {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("INSERT INTO software (name, version) VALUES (?, ?)");
            $stmt->execute([$name, $version]);
            $software_id = $pdo->lastInsertId();
            
            if (!empty($selected_labs)) {
                $assign_stmt = $pdo->prepare("INSERT INTO lab_software (lab_room, software_id) VALUES (?, ?)");
                foreach ($selected_labs as $lab) {
                    if (in_array($lab, $labs)) {
                        $assign_stmt->execute([$lab, $software_id]);
                    }
                }
            }
            
            $pdo->commit();
            $success = "Software registered and assigned successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Software already exists or database error.";
        }
    }
}

// Handle Software Deletion
if (isset($_GET['delete_software'])) {
    $id = $_GET['delete_software'];
    try {
        $stmt = $pdo->prepare("DELETE FROM software WHERE id = ?");
        $stmt->execute([$id]);
        $success = "Software deleted successfully!";
    } catch (Exception $e) {
        $error = "Could not delete software.";
    }
}

// Handle Single Assignment Removal
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['remove_assignment'])) {
    $lab = $_POST['lab_room'];
    $software_id = $_POST['software_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM lab_software WHERE lab_room = ? AND software_id = ?");
        $stmt->execute([$lab, $software_id]);
        echo json_encode(['status' => 'success']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error']);
        exit;
    }
}

// Handle Adding Existing Software to a Lab
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['assign_existing'])) {
    $lab = $_POST['lab_room'];
    $software_id = $_POST['software_id'];
    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO lab_software (lab_room, software_id) VALUES (?, ?)");
        $stmt->execute([$lab, $software_id]);
        echo json_encode(['status' => 'success']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error']);
        exit;
    }
}

// Fetch all software
$software_list = [];
try {
    $stmt = $pdo->query("SELECT * FROM software ORDER BY name ASC");
    $software_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Fetch assignments with software names
$lab_assets = [];
try {
    $stmt = $pdo->query("SELECT ls.lab_room, ls.software_id, s.name, s.version 
                         FROM lab_software ls 
                         JOIN software s ON ls.software_id = s.id 
                         ORDER BY s.name ASC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $lab_assets[$row['lab_room']][] = $row;
    }
} catch (Exception $e) {}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS | Lab Assets Management</title>
    <link rel="stylesheet" href="assets/dark-mode.css?v=1779200619">
    <link rel="stylesheet" href="assets/responsive.css">
    <script src="assets/dark-mode.js?v=1779200619" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@700&family=Nunito+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --bg-start: #e7f2eb; --bg-end: #d3e7db; --nav-bg: #1f4f3c; --nav-text: #edf7f2;
            --card-bg: #ffffff; --text-primary: #1f2f27; --text-muted: #607367;
            --border-soft: #d0dfd6; --brand-1: #2f7a59; --brand-2: #245f45;
        }
        body { font-family: 'Nunito Sans', sans-serif; background: linear-gradient(135deg, var(--bg-start) 0%, var(--bg-end) 100%); min-height: 100vh; }
        
        nav { background: var(--nav-bg); display: flex; align-items: center; justify-content: space-between; padding: 0 1.5rem; height: 48px; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 8px rgba(0,0,0,0.25); }
        .nav-brand { color: var(--nav-text); font-size: 0.85rem; font-weight: 600; font-family: 'Merriweather', serif; }
        .nav-links { display: flex; align-items: center; list-style: none; gap: 0.1rem; }
        .nav-links a { color: var(--nav-text); text-decoration: none; font-size: 0.75rem; padding: 0.3rem 0.6rem; border-radius: 4px; transition: background 0.15s; }
        .nav-links a:hover { background: rgba(255,255,255,0.14); }
        .nav-links .logout-btn { background: var(--brand-1); font-weight: 700; margin-left: 0.25rem; padding: 0.3rem 0.8rem; }

        .admin-wrap { padding: 2rem; max-width: 1400px; margin: 0 auto; }
        .grid-2 { display: grid; grid-template-columns: 1fr 2.5fr; gap: 2rem; }
        .card { background: var(--card-bg); border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 2rem; }
        .card-head { background: var(--brand-1); color: #fff; padding: 1rem 1.5rem; font-weight: 700; display: flex; justify-content: space-between; align-items: center; }
        .card-body { padding: 1.5rem; }

        .form-group { margin-bottom: 1.2rem; }
        .form-label { display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem; }
        .form-control { width: 100%; padding: 0.7rem; border: 2px solid var(--border-soft); border-radius: 8px; font-size: 0.9rem; background: #f9fbf9; }
        .btn-submit { background: var(--brand-1); color: #fff; border: none; padding: 0.7rem 1.5rem; border-radius: 8px; font-weight: 700; cursor: pointer; width: 100%; }

        .lab-selection-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; margin-top: 0.5rem; }
        .lab-checkbox { display: flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; background: #f0f7f3; padding: 0.5rem; border-radius: 6px; cursor: pointer; }
        .lab-checkbox input { accent-color: var(--brand-1); }

        .software-item { display: flex; justify-content: space-between; align-items: center; padding: 0.8rem; border-bottom: 1px solid var(--border-soft); }
        .software-info { display: flex; flex-direction: column; }
        .software-name { font-weight: 700; color: var(--text-primary); }
        .software-version { font-size: 0.75rem; color: var(--text-muted); }
        .btn-delete-small { color: #dc3545; text-decoration: none; font-size: 0.8rem; font-weight: 700; }

        .lab-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem; }
        .lab-card { background: #f8faf9; border-radius: 10px; border: 1px solid var(--border-soft); padding: 1.2rem; display: flex; flex-direction: column; }
        .lab-title { font-family: 'Merriweather', serif; font-size: 1rem; color: var(--brand-1); margin-bottom: 1rem; border-bottom: 2px solid var(--brand-1); padding-bottom: 0.5rem; display: flex; justify-content: space-between; align-items: center; }
        
        .assigned-list { flex: 1; min-height: 100px; }
        .assigned-tag { display: flex; justify-content: space-between; align-items: center; background: #fff; border: 1px solid var(--border-soft); padding: 0.5rem 0.8rem; border-radius: 6px; margin-bottom: 0.4rem; font-size: 0.85rem; }
        .remove-tag { color: #dc3545; cursor: pointer; font-weight: 700; margin-left: 0.5rem; }
        
        .add-to-lab { margin-top: 1rem; display: flex; gap: 0.5rem; }
        .assign-select { flex: 1; padding: 0.4rem; border-radius: 4px; border: 1px solid var(--border-soft); font-size: 0.8rem; }
        .btn-assign { background: var(--brand-1); color: #fff; border: none; padding: 0.4rem 0.8rem; border-radius: 4px; font-size: 0.75rem; cursor: pointer; }

        html.dark-mode .lab-card { background: #252d33; border-color: #30363b; }
        html.dark-mode .lab-checkbox { background: #2a3238; color: #fff; }
        html.dark-mode .assigned-tag { background: #1e2328; border-color: #30363b; color: #fff; }
        html.dark-mode .software-name { color: #fff; }
        html.dark-mode .form-label { color: #fff !important; }
        html.dark-mode .form-control, html.dark-mode .assign-select { background: #262c31; border-color: #30363b; color: #fff; }

        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; font-weight: 600; font-size: 0.85rem; }
        .alert-success { background: #e6f4ea; color: #1e7e34; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        /* CSV Import & Badge Pill Styles */
        .badge-pill {
            display: inline-flex;
            align-items: center;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            color: #fff;
            box-shadow: 0 4px 6px rgba(0,0,0,0.06);
        }
        .blue-pill { background: #0f2c59; }
        .red-pill { background: #a91d22; }

        .drag-drop-zone {
            border: 2px dashed var(--border-soft);
            border-radius: 12px;
            padding: 2.2rem 1.5rem;
            text-align: center;
            background: #fdfdfd;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.5rem;
        }
        .drag-drop-zone:hover, .drag-drop-zone.dragover {
            border-color: var(--brand-1);
            background: #f0f7f3;
        }
        .zone-text {
            font-size: 0.85rem;
            color: var(--text-muted);
            line-height: 1.4;
        }
        
        html.dark-mode .drag-drop-zone {
            background: #252d33;
            border-color: #30363b;
        }
        html.dark-mode .drag-drop-zone:hover, html.dark-mode .drag-drop-zone.dragover {
            background: #1c272d;
            border-color: var(--brand-1);
        }
            <style>
        /* Profile Dropdown Styles */
        .profile-dropdown-container {
            position: relative;
            margin-left: 0.5rem;
        }
        
        .profile-trigger {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            padding: 0.25rem 0.6rem;
            border-radius: 50px;
            background: rgba(255, 255, 255, 0.1);
            transition: all 0.2s ease;
            user-select: none;
        }
        
        .profile-trigger:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .profile-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--brand-1, #2f7a59);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
            border: 2px solid rgba(255,255,255,0.8);
            text-transform: uppercase;
        }
        
        .profile-info {
            display: flex;
            flex-direction: column;
            line-height: 1.1;
        }
        
        .profile-name {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--nav-text, #fff);
        }
        
        .profile-role {
            font-size: 0.65rem;
            color: rgba(255, 255, 255, 0.8);
            text-transform: capitalize;
        }
        
        .profile-caret {
            margin-left: 0.2rem;
            color: var(--nav-text, #fff);
            transition: transform 0.2s;
        }
        
        .profile-dropdown-container.active .profile-caret {
            transform: rotate(180deg);
        }
        
        .profile-menu {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            background: var(--card-bg, #fff);
            border-radius: 12px;
            min-width: 220px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            padding: 0.5rem;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            z-index: 1000;
        }
        
        html.dark-mode .profile-menu {
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .profile-dropdown-container.active .profile-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .profile-menu-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.6rem 0.8rem;
            color: var(--text-primary, #333) !important;
            text-decoration: none !important;
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: 8px;
            transition: background 0.15s;
            cursor: pointer;
            background: transparent !important;
            box-sizing: border-box;
            width: 100%;
        }
        
        .profile-menu-item:hover {
            background: var(--input-bg, #f4f4f4) !important;
        }
        
        .profile-menu-item svg {
            width: 18px;
            height: 18px;
            color: var(--text-muted, #666);
        }
        
        .profile-menu-divider {
            height: 1px;
            background: var(--border-soft, #eee);
            margin: 0.4rem 0;
        }
        
        .text-danger {
            color: #dc3545 !important;
        }
        
        .text-danger svg {
            color: #dc3545 !important;
        }
        
        .theme-item {
            justify-content: space-between;
        }
        
        .theme-label-wrap {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
    </style>
</head>
<body>
    <nav>
        <span class="nav-brand">College of Computer Studies Admin</span>
        <ul class="nav-links">
            <li><a href="admin.php" <?php if (basename($_SERVER['PHP_SELF']) === 'admin.php') echo 'style="background: rgba(255,255,255,0.15)"'; ?>>Home</a></li>
            <li><a href="admin-search.php" <?php if (basename($_SERVER['PHP_SELF']) === 'admin-search.php') echo 'style="background: rgba(255,255,255,0.15)"'; ?>>Search</a></li>
            <li><a href="admin-students.php" <?php if (basename($_SERVER['PHP_SELF']) === 'admin-students.php') echo 'style="background: rgba(255,255,255,0.15)"'; ?>>Students</a></li>
            <li><a href="admin-sitin.php" <?php if (basename($_SERVER['PHP_SELF']) === 'admin-sitin.php') echo 'style="background: rgba(255,255,255,0.15)"'; ?>>Active Sit-In</a></li>
            <li><a href="admin-records.php" <?php if (basename($_SERVER['PHP_SELF']) === 'admin-records.php') echo 'style="background: rgba(255,255,255,0.15)"'; ?>>View Sit-In Records</a></li>
            <li><a href="admin-feedback.php" <?php if (basename($_SERVER['PHP_SELF']) === 'admin-feedback.php') echo 'style="background: rgba(255,255,255,0.15)"'; ?>>Feedback Reports</a></li>
            <li><a href="admin-reservations.php" <?php if (basename($_SERVER['PHP_SELF']) === 'admin-reservations.php') echo 'style="background: rgba(255,255,255,0.15)"'; ?>>Reservation</a></li>
            <li><a href="admin-lab-assets.php" <?php if (basename($_SERVER['PHP_SELF']) === 'admin-lab-assets.php') echo 'style="background: rgba(255,255,255,0.15)"'; ?>>Lab Assets</a></li>
            <li><a href="leaderboard.php" <?php if (basename($_SERVER['PHP_SELF']) === 'leaderboard.php') echo 'style="background: rgba(255,255,255,0.15)"'; ?>>Leaderboard</a></li>
                        <li class="profile-dropdown-container" id="profileDropdownContainer">
                <div class="profile-trigger" onclick="toggleProfileDropdown(event)">
                    <div class="profile-avatar">
                        <?= strtoupper(substr($_SESSION['name'] ?? 'U', 0, 1)) ?>
                    </div>
                    <div class="profile-info">
                        <span class="profile-name"><?= htmlspecialchars($_SESSION['name'] ?? 'User') ?></span>
                        <span class="profile-role"><?= htmlspecialchars(ucfirst($_SESSION['role'] ?? 'Student')) ?></span>
                    </div>
                    <svg class="profile-caret" viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </div>
                <div class="profile-menu" id="profileMenu">
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <!-- Admin Profile (Optional, can point to settings if exists) -->
                    <?php else: ?>
                        <a href="dashboard.php?edit=true" class="profile-menu-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg> 
                            Edit Profile
                        </a>
                        <div class="profile-menu-divider"></div>
                    <?php endif; ?>
                    
                    <div class="profile-menu-item theme-item">
                        <div id="darkModeContainer" style="display:flex; justify-content:center; width:100%;"></div>
                    </div>
                    <div class="profile-menu-divider"></div>
                    <a href="logout.php" class="profile-menu-item text-danger">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg> 
                        Log out
                    </a>
                </div>
            </li>
        </ul>
    </nav>

    <div class="admin-wrap">
        <div class="admin-header" style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
            <h1 style="font-family: 'Merriweather', serif; font-size: 1.8rem; color: var(--brand-1); display: flex; align-items: center; gap: 0.6rem; margin: 0;">
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color: var(--brand-1);"><rect x="3" y="3" width="7" height="9" rx="1"></rect><rect x="14" y="3" width="7" height="5" rx="1"></rect><rect x="14" y="12" width="7" height="9" rx="1"></rect><rect x="3" y="16" width="7" height="5" rx="1"></rect></svg>
                Software & Lab Management
            </h1>
            <a href="admin-pc-control.php" style="background: var(--brand-1); color: white; padding: 0.6rem 1.2rem; border-radius: 6px; text-decoration: none; font-weight: bold; display: flex; align-items: center; gap: 0.5rem; transition: background 0.2s;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"></path><line x1="12" y1="2" x2="12" y2="12"></line></svg>
                PC Control Center
            </a>
        </div>

        <?php if ($success): ?><div class="alert alert-success">✅ <?= $success ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error">❌ <?= $error ?></div><?php endif; ?>

        <div class="grid-2">
            <div class="side-panel">
                <div class="card">
                    <div class="card-head">Register & Assign Software</div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="add_software" value="1">
                            <div class="form-group">
                                <label class="form-label">Software Name</label>
                                <input type="text" name="software_name" class="form-control" placeholder="e.g. Visual Studio Code" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Version (Optional)</label>
                                <input type="text" name="software_version" class="form-control" placeholder="e.g. 2024.1">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Assign to Labs (Select all that apply)</label>
                                <div class="lab-selection-grid">
                                    <?php foreach ($labs as $lab): ?>
                                        <label class="lab-checkbox">
                                            <input type="checkbox" name="assigned_labs[]" value="<?= $lab ?>"> Lab <?= $lab ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <button type="submit" class="btn-submit">Register and Assign</button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-head">
                        <span style="display: flex; align-items: center; gap: 0.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                            Import Software via CSV
                        </span>
                    </div>
                    <div class="card-body">
                        <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1.2rem; line-height: 1.5;">
                            Upload a CSV file where each column header is a lab room number (e.g. <strong>524</strong>, <strong>526</strong>, <strong>528</strong>) and rows below list the software installed in that lab.
                        </p>
                        
                        <form method="POST" enctype="multipart/form-data" id="importForm">
                            <input type="hidden" name="import_csv" value="1">
                            <div class="drag-drop-zone" id="dropZone">
                                <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--brand-1); margin-bottom: 0.6rem;">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="17 8 12 3 7 8"></polyline>
                                    <line x1="12" y1="3" x2="12" y2="15"></line>
                                </svg>
                                <div class="zone-text" id="zoneText">Drag & drop your CSV file here, or <span style="color: var(--brand-1); font-weight: 700; text-decoration: underline;">click to browse</span></div>
                                <input type="file" name="csv_file" id="fileInput" accept=".csv" style="display: none;" required>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 0.6rem; margin-top: 1rem;">
                                <button type="submit" class="btn-submit" style="display: flex; align-items: center; justify-content: center; gap: 0.4rem; padding: 0.5rem;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                                    Upload & Import
                                </button>
                                <a href="admin-lab-assets.php?download_template=1" class="btn-cancel" style="display: flex; align-items: center; justify-content: center; gap: 0.4rem; text-decoration: none; font-size: 0.8rem; font-weight: 700; background: #e0e0e0; color: #333; border-radius: 8px; cursor: pointer; text-align: center; padding: 0.5rem; transition: background 0.2s;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                    Download Template
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-head">Master Software List</div>
                    <div class="card-body" style="padding: 0;">
                        <?php if (empty($software_list)): ?>
                            <p style="padding: 1.5rem; text-align: center; color: var(--text-muted);">No software registered yet.</p>
                        <?php else: ?>
                            <?php foreach ($software_list as $sw): ?>
                                <div class="software-item">
                                    <div class="software-info">
                                        <span class="software-name"><?= htmlspecialchars($sw['name']) ?></span>
                                        <span class="software-version"><?= htmlspecialchars($sw['version'] ?: 'No version') ?></span>
                                    </div>
                                    <a href="javascript:void(0)" class="btn-delete-small" onclick="confirmDeleteSoftware(<?= $sw['id'] ?>)">Delete</a>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="main-panel">
                <div class="card">
                    <div class="card-head">
                        <span style="display: flex; align-items: center; gap: 0.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color: #fff;"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                            Current Software by Lab
                        </span>
                        <span style="font-size: 0.7rem; font-weight: normal; opacity: 0.8;">Manage specific lab tools</span>
                    </div>
                    <div class="card-body">
                        <div class="lab-grid">
                            <?php foreach ($labs as $lab): ?>
                                <div class="lab-card">
                                    <div class="lab-title">
                                        <span>Lab Room <?= $lab ?></span>
                                        <a href="admin-lab-assets.php?export_lab=<?= $lab ?>" style="font-size: 0.7rem; font-family: 'Nunito Sans', sans-serif; background: #1f543d; color: #fff; padding: 0.25rem 0.6rem; border-radius: 4px; text-decoration: none; transition: 0.2s;" onmouseover="this.style.opacity=0.8" onmouseout="this.style.opacity=1">Export CSV</a>
                                    </div>
                                    
                                    <div class="assigned-list">
                                        <?php if (!isset($lab_assets[$lab]) || empty($lab_assets[$lab])): ?>
                                            <p style="font-size: 0.8rem; color: var(--text-muted); text-align: center; padding: 1rem;">No software assigned.</p>
                                        <?php else: ?>
                                            <?php foreach ($lab_assets[$lab] as $asset): ?>
                                                <div class="assigned-tag">
                                                    <span><?= htmlspecialchars($asset['name']) ?></span>
                                                    <span class="remove-tag" onclick="removeAssignment('<?= $lab ?>', <?= $asset['software_id'] ?>)">&times;</span>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>

                                    <div class="add-to-lab">
                                        <select class="assign-select" id="select_<?= $lab ?>">
                                            <option value="">+ Add existing...</option>
                                            <?php foreach ($software_list as $sw): ?>
                                                <option value="<?= $sw['id'] ?>"><?= htmlspecialchars($sw['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn-assign" onclick="assignExisting('<?= $lab ?>')">Add</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function removeAssignment(lab, swId) {
            Swal.fire({
                title: 'Remove Software?',
                text: "Removing this from Lab " + lab,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Yes, remove'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('remove_assignment', '1');
                    formData.append('lab_room', lab);
                    formData.append('software_id', swId);

                    fetch('admin-lab-assets.php', { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(data => {
                            if (data.status === 'success') window.location.reload();
                            else Swal.fire('Error', 'Failed to remove assignment.', 'error');
                        });
                }
            });
        }

        function assignExisting(lab) {
            const swId = document.getElementById('select_' + lab).value;
            if (!swId) return;

            const formData = new FormData();
            formData.append('assign_existing', '1');
            formData.append('lab_room', lab);
            formData.append('software_id', swId);

            fetch('admin-lab-assets.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') window.location.reload();
                    else Swal.fire('Error', 'Assignment failed.', 'error');
                });
        }

        function confirmDeleteSoftware(id) {
            Swal.fire({
                title: 'Delete from Master List?',
                text: "This will remove this software from ALL laboratories!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Yes, delete permanently'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'admin-lab-assets.php?delete_software=' + id;
                }
            });
        }

        // Drag and Drop CSV Import Interaction
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        const zoneText = document.getElementById('zoneText');

        if (dropZone && fileInput) {
            dropZone.addEventListener('click', () => fileInput.click());

            fileInput.addEventListener('change', () => {
                if (fileInput.files.length > 0) {
                    zoneText.innerHTML = `Selected file: <strong style="color: var(--brand-1);">${fileInput.files[0].name}</strong>`;
                }
            });

            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropZone.classList.add('dragover');
            });

            dropZone.addEventListener('dragleave', () => {
                dropZone.classList.remove('dragover');
            });

            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.classList.remove('dragover');
                
                if (e.dataTransfer.files.length > 0) {
                    fileInput.files = e.dataTransfer.files;
                    zoneText.innerHTML = `Selected file: <strong style="color: var(--brand-1);">${fileInput.files[0].name}</strong>`;
                }
            });
        }
    </script>
<script>
        function toggleProfileDropdown(event) {
            event.stopPropagation();
            const container = document.getElementById('profileDropdownContainer');
            if (container) {
                container.classList.toggle('active');
            }
        }

        window.addEventListener('click', function(event) {
            const container = document.getElementById('profileDropdownContainer');
            if (container && !container.contains(event.target)) {
                container.classList.remove('active');
            }
        });
</script>
</body>
</html>
