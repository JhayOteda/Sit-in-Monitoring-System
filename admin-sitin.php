<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit;
}
require 'db.php';

$success_message = "";
$error_message = "";

// Handle new sit-in creation
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["user_id"]) && isset($_POST["purpose"]) && isset($_POST["lab_room"])) {
    $user_id = intval($_POST["user_id"] ?? 0);
    $purpose = trim($_POST["purpose"] ?? "");
    $lab_room = trim($_POST["lab_room"] ?? "");
    $pc_number = intval($_POST["pc_number"] ?? 0);

    if ($user_id <= 0 || empty($purpose) || empty($lab_room) || $pc_number <= 0) {
        $error_message = "✗ Invalid data provided. Please select a lab room and PC.";
    } else {
        // Check if student already has an active sit-in
        try {
            $check_stmt = $pdo->prepare("SELECT id FROM sit_in_logs WHERE user_id = ? AND time_out IS NULL");
            $check_stmt->execute([$user_id]);

            if ($check_stmt->rowCount() > 0) {
                $error_message = "✗ This student already has an active sit-in session. Please end the current session first.";
            } else {
                // Check if PC is already occupied
                $pc_check = $pdo->prepare("SELECT id FROM sit_in_logs WHERE lab_room = ? AND pc_number = ? AND time_out IS NULL");
                $pc_check->execute([$lab_room, $pc_number]);
                if ($pc_check->rowCount() > 0) {
                    $error_message = "✗ PC #$pc_number in Lab $lab_room is already occupied. Please select another PC.";
                } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO sit_in_logs (user_id, purpose, lab_room, pc_number, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([$user_id, $purpose, $lab_room, $pc_number]);

                    // Decrement remaining_sessions
                    $decrement_stmt = $pdo->prepare("UPDATE users SET remaining_sessions = remaining_sessions - 1 WHERE id = ? AND remaining_sessions > 0");
                    $decrement_stmt->execute([$user_id]);

                    $success_message = "✓ Sit-In session created successfully!";
                } catch (Exception $e) {
                    $error_message = "✗ Error creating sit-in: " . $e->getMessage();
                }
                }
            }
        } catch (Exception $e) {
            $error_message = "✗ Error checking active session: " . $e->getMessage();
        }
    }
}

// Handle end sit-in action
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "end_sitin") {
    $log_id = intval($_POST["log_id"] ?? 0);

    if ($log_id > 0) {
        try {
            // Find student user_id associated with this log entry
            $find_user = $pdo->prepare("SELECT user_id FROM sit_in_logs WHERE id = ?");
            $find_user->execute([$log_id]);
            $log_entry = $find_user->fetch(PDO::FETCH_ASSOC);

            if ($log_entry) {
                $student_id = $log_entry['user_id'];

                // Calculate duration in seconds using TIMESTAMPDIFF
                $dur_stmt = $pdo->prepare("SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) as seconds FROM sit_in_logs WHERE id = ?");
                $dur_stmt->execute([$log_id]);
                $duration = $dur_stmt->fetch(PDO::FETCH_ASSOC);
                $seconds = $duration ? intval($duration['seconds']) : 0;

                $stmt = $pdo->prepare("UPDATE sit_in_logs SET time_out = NOW() WHERE id = ? AND time_out IS NULL");
                $stmt->execute([$log_id]);
                $affected = $stmt->rowCount();

                if ($affected > 0) {
                    // Convert to hours and award points (10 points per hour, minimum 1 point)
                    $hours = $seconds / 3600.0;
                    $earned_points = max(1, round($hours * 10));

                    // Award calculated points to student
                    $points_stmt = $pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?");
                    $points_stmt->execute([$earned_points, $student_id]);

                    // Recalculate and update the student's total leaderboard score in the database
                    $score_stmt = $pdo->prepare("
                        UPDATE users u 
                        SET score = COALESCE(u.points, 0) * 0.50 + 
                                    COALESCE((SELECT SUM(TIMESTAMPDIFF(SECOND, created_at, time_out)) / 3600.0 FROM sit_in_logs WHERE user_id = u.id AND time_out IS NOT NULL), 0) * 0.30 + 
                                    COALESCE((SELECT COUNT(*) FROM sit_in_logs WHERE user_id = u.id AND time_out IS NOT NULL), 0) * 0.20
                        WHERE u.id = ?
                    ");
                    $score_stmt->execute([$student_id]);

                    $success_message = "✓ Sit-In session ended successfully! Student earned +" . $earned_points . " points. 🏆";
                    header("Refresh: 2; url=admin-records.php");
                } else {
                    $error_message = "⚠ Session already ended or not found.";
                }
            } else {
                $error_message = "⚠ Sit-in log entry not found.";
            }
        } catch (Exception $e) {
            $error_message = "✗ Error ending session: " . $e->getMessage();
        }
    }
}

// Fetch active sit-in records
$active_sitins = [];
try {
    $stmt = $pdo->query("
        SELECT 
            sl.id,
            sl.user_id,
            sl.purpose,
            sl.lab_room,
            sl.pc_number,
            sl.created_at,
            u.id_number,
            u.first_name,
            u.middle_name,
            u.last_name
        FROM sit_in_logs sl
        JOIN users u ON sl.user_id = u.id
        WHERE sl.time_out IS NULL
        ORDER BY sl.created_at DESC
    ");
    $active_sitins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS | Admin - Sit-In</title>
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

        .alert-error {
            background: #fde8e8;
            color: #a01a1a;
            border: 1px solid #f5858e;
            padding: 0.8rem 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
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

        .no-data {
            color: var(--text-muted);
            text-align: center;
            padding: 2rem;
        }

        .btn-end {
            background: var(--brand-1);
            color: #fff;
            padding: 0.4rem 0.8rem;
            border: none;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.75rem;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-end:hover {
            background: var(--brand-2);
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
            <div class="card-head">Active Sit-In Sessions</div>
            <div class="card-body">
                <?php if ($success_message): ?>
                    <div class="alert-success"><?= htmlspecialchars($success_message) ?></div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert-error"><?= htmlspecialchars($error_message) ?></div>
                <?php endif; ?>

                <?php if (empty($active_sitins)): ?>
                    <div class="no-data">No active sit-ins at the moment.</div>
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
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($active_sitins as $sitin): ?>
                                <tr>
                                    <td><?= htmlspecialchars($sitin['id_number']) ?></td>
                                    <td><?= htmlspecialchars($sitin['first_name'] . ($sitin['middle_name'] ? ' ' . $sitin['middle_name'] : '') . ' ' . $sitin['last_name']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($sitin['purpose']) ?></td>
                                    <td><?= htmlspecialchars($sitin['lab_room']) ?></td>
                                    <td><?= $sitin['pc_number'] ? 'PC ' . htmlspecialchars($sitin['pc_number']) : '—' ?></td>
                                    <td><?= date('M d, Y H:i', strtotime($sitin['created_at'])) ?></td>
                                    <td>
                                        <form method="POST" action="admin-sitin.php" onsubmit="confirmEndSitin(event, this)" style="display: inline;">
                                            <input type="hidden" name="action" value="end_sitin">
                                            <input type="hidden" name="log_id" value="<?= $sitin['id'] ?>">
                                            <button type="submit" class="btn-end">End</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>    <script>
        function confirmEndSitin(event, form) {
            event.preventDefault(); // Prevent default immediate submit
            
            Swal.fire({
                title: 'End Sit-In Session?',
                text: "Are you sure you want to end this student's sit-in session? Points will be dynamically calculated based on duration.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#2f7a59',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, end session!',
                cancelButtonText: 'Cancel',
                background: document.documentElement.classList.contains('dark-mode') ? '#1f2f27' : '#fff',
                color: document.documentElement.classList.contains('dark-mode') ? '#fff' : '#1f2f27'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit(); // Submit the form
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