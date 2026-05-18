<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit;
}
require 'db.php';

$success = $error = "";
$labs = ['524', '526', '528', '530', '542', '544'];

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
    <link rel="stylesheet" href="assets/dark-mode.css">
    <link rel="stylesheet" href="assets/responsive.css">
    <script src="assets/dark-mode.js" defer></script>
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
    </style>
</head>
<body>
    <nav>
        <span class="nav-brand">CCS Admin | Lab Assets</span>
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
            <li><a href="logout.php" class="logout-btn">Log out</a></li>
        </ul>
    </nav>

    <div class="admin-wrap">
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
                        <span>Laboratory Software Inventory</span>
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
    </script>
</body>
</html>
