<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit;
}
require 'db.php';

$success_message = "";

// Handle delete record
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_record_id'])) {
    $record_id = $_POST['delete_record_id'];
    try {
        // Fetch user_id first to recalculate their score after deletion
        $find_stmt = $pdo->prepare("SELECT user_id FROM sit_in_logs WHERE id = ?");
        $find_stmt->execute([$record_id]);
        $student_id = $find_stmt->fetchColumn();

        $stmt = $pdo->prepare("DELETE FROM sit_in_logs WHERE id = ?");
        $stmt->execute([$record_id]);

        if ($student_id) {
            // Recalculate score for the student
            $score_stmt = $pdo->prepare("
                UPDATE users u 
                SET score = COALESCE(u.points, 0) * 0.50 + 
                            COALESCE((SELECT SUM(TIMESTAMPDIFF(SECOND, created_at, time_out)) / 3600.0 FROM sit_in_logs WHERE user_id = u.id AND time_out IS NOT NULL), 0) * 0.30 + 
                            COALESCE((SELECT COUNT(*) FROM sit_in_logs WHERE user_id = u.id AND time_out IS NOT NULL), 0) * 0.20
                WHERE u.id = ?
            ");
            $score_stmt->execute([$student_id]);
        }

        $success_message = "Record deleted successfully!";
    } catch (Exception $e) {
        $success_message = "Error deleting record.";
    }
}

// Handle delete all records
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_all'])) {
    try {
        $pdo->query("DELETE FROM sit_in_logs");
        // Reset the score calculations (except points) for all users
        $pdo->query("
            UPDATE users u 
            SET score = COALESCE(u.points, 0) * 0.50
        ");
        $success_message = "All records deleted successfully!";
    } catch (Exception $e) {
        $success_message = "Error deleting all records.";
    }
}

// Fetch completed sit-in records with student information
$records = [];
try {
    $stmt = $pdo->query("
        SELECT 
            sl.id,
            sl.user_id,
            sl.purpose,
            sl.lab_room,
            sl.pc_number,
            sl.created_at,
            sl.time_out,
            u.id_number,
            u.first_name,
            u.middle_name,
            u.last_name
        FROM sit_in_logs sl
        JOIN users u ON sl.user_id = u.id
        WHERE sl.time_out IS NOT NULL
        ORDER BY sl.created_at DESC
    ");
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Use the 5 active, valid CCS laboratory rooms for filtering
    $lab_rooms = ['524', '526', '528', '530', '544'];
} catch (Exception $e) {
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS | Admin - Records</title>
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
    <link rel="stylesheet" href="assets/dark-mode.css?v=1779200619">
    <link rel="stylesheet" href="assets/responsive.css">
    <script src="assets/dark-mode.js?v=1779200619" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Merriweather:wght@700&family=Nunito+Sans:wght@400;600;700&display=swap"
        rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --bg-start: #e7f2eb;
            --bg-end: #d3e7db;
            --nav-bg: #1f4f3c;
            --nav-text: #edf7f2;
            --card-bg: #ffffff;
            --text-primary: #1f2f27;
            --text-muted: #607367;
            --border-soft: #d0dfd6;
            --input-bg: #f5faf7;
            --brand-1: #2f7a59;
            --brand-2: #245f45;
        }

        body {
            font-family: 'Nunito Sans', sans-serif;
            background: linear-gradient(135deg, var(--bg-start) 0%, var(--bg-end) 100%);
            min-height: 100vh;
        }

        nav {
            background: var(--nav-bg);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
            height: 48px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
        }

        .nav-brand {
            color: var(--nav-text);
            font-size: 0.85rem;
            font-weight: 600;
            font-family: 'Merriweather', serif;
        }

        .nav-links {
            display: flex;
            align-items: center;
            list-style: none;
            gap: 0.1rem;
        }

        .nav-links a {
            color: var(--nav-text);
            text-decoration: none;
            font-size: 0.75rem;
            padding: 0.3rem 0.6rem;
            border-radius: 4px;
            display: block;
            transition: background 0.15s;
        }

        .nav-links a:hover {
            background: rgba(255, 255, 255, 0.14);
        }

        .nav-links .logout-btn {
            background: var(--brand-1);
            border-radius: 4px;
            font-weight: 700;
            padding: 0.3rem 0.8rem;
        }

        .admin-wrap {
            padding: 1.5rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .card {
            background: var(--card-bg);
            border-radius: 6px;
            box-shadow: 0 1px 5px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .card-head {
            background: var(--brand-1);
            color: #fff;
            font-size: 0.9rem;
            font-weight: 700;
            padding: 0.6rem 1rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            margin-top: 1rem;
        }

        table th {
            background: var(--border-soft);
            padding: 0.6rem 0.8rem;
            text-align: left;
            font-weight: 700;
            color: var(--text-primary);
        }

        table td {
            padding: 0.6rem 0.8rem;
            border-bottom: 1px solid var(--border-soft);
            color: var(--text-primary);
        }

        table tr:hover {
            background: #f5faf7;
        }

        .no-data {
            color: var(--text-muted);
            text-align: center;
            padding: 2rem;
        }

        .alert-success {
            background: #e6f4ea;
            color: #155724;
            border: 1px solid #b7dfbe;
            padding: 0.8rem 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .search-delete-container {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .search-field {
            flex: 1;
            min-width: 250px;
        }

        .search-field label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 0.4rem;
            letter-spacing: 0.5px;
        }

        .search-field input {
            width: 100%;
            padding: 0.6rem 0.8rem;
            border: 2px solid var(--border-soft);
            border-radius: 6px;
            font-size: 0.85rem;
            font-family: inherit;
            color: var(--text-primary);
            outline: none;
            background: var(--input-bg);
            transition: all 0.3s ease;
        }

        .search-field input:focus {
            border-color: var(--brand-1);
            background: var(--input-bg);
            box-shadow: 0 0 0 3px rgba(47, 122, 89, 0.12);
        }

        .btn-delete-all {
            padding: 0.6rem 1.2rem;
            background: #dc3545;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-delete-all:hover {
            background: #c82333;
            transform: translateY(-1px);
        }

        .btn-generate {
            padding: 0.6rem 1.2rem;
            background: #007bff;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-generate:hover {
            background: #0069d9;
            transform: translateY(-1px);
        }

        .btn-delete-row {
            padding: 0.35rem 0.7rem;
            background: #dc3545;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .btn-delete-row:hover {
            background: #c82333;
        }

        .no-records-message {
            text-align: center;
            color: var(--text-muted);
            font-style: italic;
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
        <div class="card">
            <div class="card-head">Sit-In Records</div>
            <div class="card-body">
                <?php if ($success_message): ?>
                    <div class="alert-success"><?= htmlspecialchars($success_message) ?></div>
                <?php endif; ?>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <!-- Search and Delete All Section -->
                <div class="search-delete-container">
                    <div style="display: flex; gap: 1rem; flex: 1;">
                        <div class="search-field" style="margin-bottom: 0;">
                            <label for="searchInput">Search ID Number or Name</label>
                            <input type="text" id="searchInput" placeholder="Enter ID number or student name...">
                        </div>
                        <div class="search-field" style="margin-bottom: 0;">
                            <label for="dateFilter">Filter by Date</label>
                            <input type="date" id="dateFilter" style="padding: 0.6rem; border: 1px solid var(--border-soft); border-radius: 6px; outline: none; font-size: 0.9rem; color: var(--text-primary);">
                        </div>
                        <div class="search-field" style="margin-bottom: 0;">
                            <label for="labFilter">Filter by Lab Room</label>
                            <select id="labFilter" style="width: 100%; padding: 0.6rem; border: 1px solid var(--border-soft); border-radius: 6px; font-size: 0.9rem; color: var(--text-primary); outline: none; background: var(--input-bg); cursor: pointer;">
                                <option value="">All Lab Rooms</option>
                                <?php foreach ($lab_rooms as $room): ?>
                                    <option value="<?= htmlspecialchars($room) ?>"><?= htmlspecialchars($room) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php if (!empty($records)): ?>
                        <div style="display: flex; gap: 0.8rem;">
                            <button type="button" class="btn-generate" onclick="generatePDF()">
                                <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="display: block;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                Generate PDF Report
                            </button>
                            <form id="deleteAllForm" method="POST" style="display: inline;">
                                <input type="hidden" name="delete_all" value="1">
                                <button type="button" class="btn-delete-all" onclick="confirmDeleteAll()">Delete All History</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (empty($records)): ?>
                    <div class="no-data">No sit-in records found yet.</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID Number</th>
                                <th>Student Name</th>
                                <th>Purpose</th>
                                <th>Lab Room</th>
                                <th>PC #</th>
                                <th>Check-In Time</th>
                                <th>Check-Out Time</th>
                                <th>Duration</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="recordsTable">
                            <?php foreach ($records as $record): ?>
                                <tr class="record-row" data-id-number="<?= htmlspecialchars($record['id_number']) ?>"
                                    data-student-name="<?= htmlspecialchars($record['first_name'] . ($record['middle_name'] ? ' ' . $record['middle_name'] : '') . ' ' . $record['last_name']) ?>"
                                    data-date="<?= date('Y-m-d', strtotime($record['created_at'])) ?>"
                                    data-lab-room="<?= htmlspecialchars($record['lab_room']) ?>">
                                    <td><?= htmlspecialchars($record['id_number']) ?></td>
                                    <td><?= htmlspecialchars($record['first_name'] . ($record['middle_name'] ? ' ' . $record['middle_name'] : '') . ' ' . $record['last_name']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($record['purpose']) ?></td>
                                    <td><?= htmlspecialchars($record['lab_room']) ?></td>
                                    <td><?= $record['pc_number'] ? 'PC ' . htmlspecialchars($record['pc_number']) : '—' ?></td>
                                    <td><?= date('M d, Y H:i', strtotime($record['created_at'])) ?></td>
                                    <td>
                                        <?php
                                        if ($record['time_out']) {
                                            echo date('M d, Y H:i', strtotime($record['time_out']));
                                        } else {
                                            echo '<span style="color: var(--brand-1); font-weight: 700;">Active</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        if ($record['time_out']) {
                                            $start = new DateTime($record['created_at']);
                                            $end = new DateTime($record['time_out']);
                                            $diff = $start->diff($end);
                                            echo $diff->format('%hh %im');
                                        } else {
                                            echo '—';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <form class="delete-record-form" method="POST" style="display: inline;">
                                            <input type="hidden" name="delete_record_id" value="<?= $record['id'] ?>">
                                            <button type="button" class="btn-delete-row" onclick="confirmDeleteRecord(this.form)">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>

    <script>
        function generatePDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('l', 'mm', 'a4'); // Landscape orientation
            
            // Add Logo or Title
            doc.setFontSize(11);
            doc.setTextColor(120);
            doc.text("University of Cebu Main Campus", 14, 14);

            doc.setFontSize(18);
            doc.setTextColor(31, 79, 60);
            doc.text("College of Computer Studies", 14, 22);
            
            const labFilter = document.getElementById('labFilter');
            const selectedLab = labFilter ? labFilter.value : '';
            const subTitle = selectedLab ? "Sit-In Monitoring System - Laboratory Records (Lab " + selectedLab + ")" : "Sit-In Monitoring System - Laboratory Records";
            
            doc.setFontSize(12);
            doc.setTextColor(100);
            doc.text(subTitle, 14, 29);
            
            const date = new Date().toLocaleString();
            doc.setFontSize(10);
            doc.text("Generated on: " + date, 14, 35);

            // Get table data
            const table = document.getElementById("recordsTable");
            const rows = table.querySelectorAll("tr");
            const data = [];
            
            rows.forEach(row => {
                if (row.style.display !== 'none' && !row.classList.contains('no-records-message')) {
                    const cells = row.querySelectorAll("td");
                    const rowData = [];
                    // Extract data from columns 0 to 7 (excluding the Action column)
                    for (let i = 0; i <= 7; i++) {
                        rowData.push(cells[i].innerText.trim());
                    }
                    data.push(rowData);
                }
            });

            // Define columns
            const columns = ["ID Number", "Student Name", "Purpose", "Lab", "PC #", "Check-In", "Check-Out", "Duration"];

            // Generate Table
            doc.autoTable({
                head: [columns],
                body: data,
                startY: 42,
                theme: 'striped',
                headStyles: { fillColor: [47, 122, 89], textColor: [255, 255, 255] },
                alternateRowStyles: { fillColor: [240, 247, 244] },
                margin: { top: 42 },
                styles: { fontSize: 9 }
            });

            // Save PDF
            doc.save("CCS_SitIn_Report_" + new Date().getTime() + ".pdf");
        }

        // Search functionality
        const searchInput = document.getElementById('searchInput');
        const dateFilter = document.getElementById('dateFilter');
        const labFilter = document.getElementById('labFilter');
        const recordsTable = document.getElementById('recordsTable');
        const recordRows = recordsTable ? recordsTable.querySelectorAll('.record-row') : [];

        function applyFilters() {
            const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
            const dateTerm = dateFilter ? dateFilter.value : '';
            const labTerm = labFilter ? labFilter.value.trim() : '';

            recordRows.forEach(row => {
                const idNumber = row.getAttribute('data-id-number').toLowerCase();
                const studentName = row.getAttribute('data-student-name').toLowerCase();
                const rowDate = row.getAttribute('data-date');
                const rowLab = row.getAttribute('data-lab-room');

                const matchesSearch = idNumber.includes(searchTerm) || studentName.includes(searchTerm) || searchTerm === '';
                const matchesDate = dateTerm === '' || rowDate === dateTerm;
                const matchesLab = labTerm === '' || rowLab === labTerm;

                if (matchesSearch && matchesDate && matchesLab) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });

            // Check if any rows are visible
            const visibleRows = Array.from(recordRows).some(row => row.style.display !== 'none');
            if (!visibleRows && (searchTerm !== '' || dateTerm !== '' || labTerm !== '')) {
                if (!document.querySelector('.no-records-message')) {
                    const message = document.createElement('tr');
                    message.className = 'no-records-message';
                    message.innerHTML = '<td colspan="9" style="text-align: center; color: var(--text-muted); padding: 2rem;">No records found matching your filters.</td>';
                    recordsTable.appendChild(message);
                }
            } else {
                const message = document.querySelector('.no-records-message');
                if (message) {
                    message.remove();
                }
            }
        }

        if (searchInput) searchInput.addEventListener('keyup', applyFilters);
        if (dateFilter) dateFilter.addEventListener('change', applyFilters);
        if (labFilter) labFilter.addEventListener('change', applyFilters);

        function confirmDeleteRecord(form) {
            Swal.fire({
                title: 'Delete record?',
                text: "This action cannot be undone.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete!',
                background: document.documentElement.classList.contains('dark-mode') ? '#1f2f27' : '#fff',
                color: document.documentElement.classList.contains('dark-mode') ? '#fff' : '#1f2f27'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        }

        function confirmDeleteAll() {
            Swal.fire({
                title: 'Delete ALL records?',
                text: "This will wipe the entire sit-in history!",
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, clear everything!',
                background: document.documentElement.classList.contains('dark-mode') ? '#1f2f27' : '#fff',
                color: document.documentElement.classList.contains('dark-mode') ? '#fff' : '#1f2f27'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('deleteAllForm').submit();
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