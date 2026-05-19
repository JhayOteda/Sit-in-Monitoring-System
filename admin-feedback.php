<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit;
}
require 'db.php';

$feedback_list = [];
try {
    $stmt = $pdo->query("SELECT f.id, f.log_id, f.user_id, u.first_name, u.middle_name, u.last_name, u.id_number, f.message, f.rating, f.created_at 
                         FROM feedback f 
                         JOIN users u ON f.user_id = u.id 
                         ORDER BY f.created_at DESC");
    $feedback_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS | Admin - Feedback</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn-report {
            background: var(--brand-1);
            color: #fff;
            border: 2px solid #fff;
            padding: 0.4rem 0.9rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
        }

        .btn-report:hover {
            background: #fff;
            color: var(--brand-1);
        }

        .btn-report:hover .btn-report-icon-wrapper {
            background: var(--brand-1);
            border-color: var(--brand-1);
        }

        .btn-report:hover .btn-report-icon {
            stroke: #fff;
        }

        .btn-report-icon-wrapper {
            background: #fff;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--border-soft);
            transition: all 0.2s ease;
        }

        .btn-report-icon {
            stroke: var(--brand-1);
            transition: stroke 0.2s ease;
        }

        .icon-wrapper {
            background: #fff;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid var(--border-soft);
            margin-right: 0.25rem;
        }

        .card-body {
            padding: 1.5rem;
            color: var(--text-muted);
        }

        @media print {
            nav, .btn-report {
                display: none !important;
            }
            body {
                background: #fff !important;
            }
            .admin-wrap {
                padding: 0 !important;
                max-width: 100% !important;
            }
            .card {
                box-shadow: none !important;
                border: none !important;
            }
            .card-head {
                background: #1f4f3c !important;
                color: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .feedback-card {
                page-break-inside: avoid;
                box-shadow: none !important;
                border: 1px solid #ccc !important;
            }
            .feedback-text {
                border-left: 3px solid #1f4f3c !important;
                background: #f8faf9 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }

        .feedback-message {
            max-width: 400px;
            word-wrap: break-word;
            white-space: pre-wrap;
            line-height: 1.5;
        }

        .no-data {
            text-align: center;
            padding: 2rem;
            color: var(--text-muted);
        }

        .feedback-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }

        .feedback-card {
            background: #fff;
            border: 1px solid var(--border-soft);
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .feedback-card:hover {
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
        }

        .feedback-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-soft);
        }

        .feedback-student-info {
            flex: 1;
        }

        .feedback-student-name {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .feedback-student-id {
            font-size: 0.8rem;
            color: var(--text-muted);
            letter-spacing: 0.5px;
        }

        .feedback-rating {
            display: flex;
            gap: 0.25rem;
            align-items: center;
        }

        .star-display {
            font-size: 1.2rem;
            color: #ffc107;
        }

        .rating-text {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-left: 0.5rem;
            font-weight: 600;
        }

        .feedback-body {
            margin-bottom: 1rem;
        }

        .feedback-label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .feedback-text {
            font-size: 0.9rem;
            color: var(--text-primary);
            line-height: 1.6;
            white-space: pre-wrap;
            word-wrap: break-word;
            background: var(--input-bg);
            padding: 1rem;
            border-radius: 6px;
            border-left: 3px solid var(--brand-1);
        }

        .feedback-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            color: var(--text-muted);
            padding-top: 1rem;
            border-top: 1px solid var(--border-soft);
        }

        .feedback-date {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .feedback-counter {
            background: var(--brand-1);
            color: #fff;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
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
            <div class="card-head">
                <span>Feedback Reports</span>
                <button class="btn-report" onclick="window.print()">
                    <span class="btn-report-icon-wrapper">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="btn-report-icon"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    </span>
                    Generate Report
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($feedback_list)): ?>
                    <div class="no-data">No feedback submitted yet.</div>
                <?php else: ?>
                    <div class="feedback-container">
                        <?php foreach ($feedback_list as $i => $fb): ?>
                            <div class="feedback-card">
                                <div class="feedback-header">
                                    <div class="feedback-student-info">
                                        <div class="feedback-student-name">
                                            <?= htmlspecialchars($fb['first_name'] . ($fb['middle_name'] ? ' ' . $fb['middle_name'] : '') . ' ' . $fb['last_name']) ?>
                                        </div>
                                        <div class="feedback-student-id">ID: <?= htmlspecialchars($fb['id_number']) ?></div>
                                    </div>
                                    <div class="feedback-rating">
                                        <?php
                                        $rating = isset($fb['rating']) ? (int) $fb['rating'] : 0;
                                        for ($j = 1; $j <= 5; $j++) {
                                            echo '<span class="star-display">' . ($j <= $rating ? '★' : '☆') . '</span>';
                                        }
                                        ?>
                                        <span class="rating-text"><?= $rating ?>/5</span>
                                    </div>
                                </div>

                                <div class="feedback-body">
                                    <div class="feedback-label">Message</div>
                                    <div class="feedback-text"><?= htmlspecialchars($fb['message']) ?></div>
                                </div>

                                <div class="feedback-footer">
                                    <div class="feedback-date">
                                        <span class="icon-wrapper">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--brand-1)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                        </span>
                                        <?= htmlspecialchars(date("M d, Y", strtotime($fb['created_at']))) ?> at
                                        <?= htmlspecialchars(date("h:i A", strtotime($fb['created_at']))) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
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