<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}
require 'db.php';
$user_id = $_SESSION["user_id"];
$success = $error = "";

// Check if reservation is enabled
$reservation_enabled = true;
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'reservation_enabled'");
    $stmt->execute();
    $res_setting = $stmt->fetchColumn();
    if ($res_setting === '0') {
        $reservation_enabled = false;
    }
} catch (Exception $e) {}

// Fetch user data to display ID and name
$user = null;
try {
    $stmt = $pdo->prepare("SELECT id_number, first_name, middle_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}

// Fetch announcements
$announcements = [];
$unread_count = 0;
try {
    // Create announcement_reads table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS announcement_reads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        announcement_id INT NOT NULL,
        read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_read (user_id, announcement_id),
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE
    )");

    // Get all announcements with read status for current user
    $ann_stmt = $pdo->prepare("SELECT a.id, a.title, a.content, a.created_at, 
                               IF(ar.id IS NOT NULL, 1, 0) as is_read
                               FROM announcements a 
                               LEFT JOIN announcement_reads ar ON a.id = ar.announcement_id AND ar.user_id = ?
                               ORDER BY a.created_at DESC LIMIT 10");
    $ann_stmt->execute([$user_id]);
    $announcements = $ann_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Count unread announcements
    foreach ($announcements as $ann) {
        if (!$ann['is_read']) {
            $unread_count++;
        }
    }
} catch (Exception $e) {
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $date = trim($_POST["date"] ?? "");
    $time_in = trim($_POST["time_in"] ?? "");
    $purpose = trim($_POST["purpose"] ?? "");
    $lab_room = trim($_POST["lab_room"] ?? "");
    $pc_number = isset($_POST["pc_number"]) && $_POST["pc_number"] !== "" ? intval($_POST["pc_number"]) : null;
    
    if (empty($date) || empty($time_in) || empty($purpose) || empty($lab_room) || !$pc_number) {
        $error = "Please fill in all fields and select a PC.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO reservations (user_id, date, time_in, purpose, lab_room, pc_number, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'Pending', NOW())");
            $stmt->execute([$user_id, $date, $time_in, $purpose, $lab_room, $pc_number]);
            $success = "Reservation submitted successfully! Status: Pending";
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

$reservations = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM reservations WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}

// Calculate Sit-in Summary Statistics
$summary_stats = [
    'total_hours' => 0,
    'sessions' => 0,
    'avg_duration' => '0h 0m',
    'largest_session' => '0h 0m'
];

try {
    $stmt = $pdo->prepare("SELECT created_at, time_out FROM sit_in_logs WHERE user_id = ? AND time_out IS NOT NULL");
    $stmt->execute([$user_id]);
    $all_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($all_logs) > 0) {
        $total_seconds = 0;
        $max_seconds = 0;
        foreach ($all_logs as $log) {
            $start = new DateTime($log['created_at']);
            $end = new DateTime($log['time_out']);
            $diff_seconds = $end->getTimestamp() - $start->getTimestamp();
            $total_seconds += $diff_seconds;
            if ($diff_seconds > $max_seconds) {
                $max_seconds = $diff_seconds;
            }
        }

        $summary_stats['sessions'] = count($all_logs);
        $hours = floor($total_seconds / 3600);
        $mins = floor(($total_seconds % 3600) / 60);
        $summary_stats['total_hours'] = $hours . "h " . $mins . "m";
        
        $avg_seconds = $total_seconds / $summary_stats['sessions'];
        $summary_stats['avg_duration'] = floor($avg_seconds / 3600) . "h " . str_pad(floor(($avg_seconds % 3600) / 60), 1, "0", STR_PAD_LEFT) . "m";
        
        $summary_stats['largest_session'] = floor($max_seconds / 3600) . "h " . str_pad(floor(($max_seconds % 3600) / 60), 1, "0", STR_PAD_LEFT) . "m";
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS | Reservation</title>
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
            --brand-1-strong: #2a6d4f;
            --brand-2-strong: #1f543d;
        }

        body {
            font-family: 'Nunito Sans', sans-serif;
            background: linear-gradient(135deg, var(--bg-start) 0%, var(--bg-end) 100%);
            min-height: 100vh;
        }

        .d-nav {
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

        .d-nav-brand {
            color: var(--nav-text);
            font-size: 0.95rem;
            font-weight: 600;
            font-family: 'Merriweather', serif;
            letter-spacing: 0.02em;
        }

        .d-nav-links {
            display: flex;
            align-items: center;
            list-style: none;
            gap: 0.1rem;
        }

        .d-nav-links a {
            color: var(--nav-text);
            text-decoration: none;
            font-size: 0.9rem;
            padding: 0.35rem 0.7rem;
            border-radius: 4px;
            white-space: nowrap;
            display: block;
            transition: background 0.15s;
        }

        .d-nav-links a:hover {
            background: rgba(255, 255, 255, 0.14);
        }

        .d-nav-links .d-logout {
            background: var(--brand-1);
            border-radius: 4px;
            font-weight: 700;
            margin-left: 0.25rem;
        }

        .d-nav-links .d-logout:hover {
            background: var(--brand-2);
        }

        .d-dropdown {
            position: relative;
        }

        .d-dd-menu {
            display: none;
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            min-width: 180px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.13);
            z-index: 999;
        }

        .d-dropdown:hover .d-dd-menu {
            display: block;
        }

        .d-notification-badge {
            display: inline-block;
            position: absolute;
            top: -6px;
            right: -8px;
            background: #dc3545;
            color: #fff;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.65rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 6px rgba(220, 53, 69, 0.4);
        }

        .d-dd-menu {
            display: none;
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            min-width: 280px;
            max-height: 400px;
            overflow-y: auto;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.13);
            z-index: 999;
        }

        .d-dropdown:hover .d-dd-menu {
            display: block;
        }

        .d-dd-menu .d-dd-header {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #eee;
            font-weight: 700;
            font-size: 0.8rem;
            color: var(--text-primary);
            background: #f8faf9;
        }

        .d-dd-item {
            padding: 0.9rem 1rem;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background 0.2s;
        }

        .d-dd-item:hover {
            background: #f8faf9;
        }

        .d-dd-item:last-child {
            border-bottom: none;
        }

        .d-dd-item-date {
            font-size: 0.7rem;
            color: var(--text-muted);
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .d-dd-item-title {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .d-dd-item-content {
            font-size: 0.75rem;
            color: var(--text-muted);
            line-height: 1.3;
        }

        .d-dd-item.is-read {
            opacity: 0.6;
            background: #f5f5f5;
        }

        .d-dd-item.is-read .d-dd-item-date {
            color: #999;
        }

        .d-dd-item.is-read .d-dd-item-title {
            color: #888;
        }

        .d-dd-read-badge {
            display: inline-block;
            margin-left: 0.5rem;
            color: #28a745;
            font-weight: 700;
            font-size: 0.8rem;
        }

        /* Summary Table Styles */
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .summary-table td {
            padding: 1rem 1.2rem;
            border-bottom: 1px solid #eee;
            font-size: 0.95rem;
            color: var(--text-primary);
        }

        .summary-table tr:last-child td {
            border-bottom: none;
        }

        .summary-table td:first-child {
            font-weight: 600;
            color: var(--text-muted);
            width: 60%;
        }

        .summary-table td:last-child {
            text-align: right;
            font-weight: 700;
            color: var(--brand-1);
        }

        /* Modal Styles for Summary */
        .s-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            animation: fadeIn 0.3s ease;
        }

        .s-modal-content {
            background-color: #fff;
            margin: 10% auto;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            animation: slideDown 0.3s ease;
        }

        .s-modal-header {
            background: var(--brand-1);
            color: #fff;
            padding: 1.2rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .s-modal-header h2 {
            font-size: 1.1rem;
            margin: 0;
            font-family: 'Merriweather', serif;
        }

        .s-close-btn {
            background: none;
            border: none;
            color: #fff;
            font-size: 1.5rem;
            cursor: pointer;
            opacity: 0.8;
            transition: opacity 0.2s;
        }

        .s-close-btn:hover {
            opacity: 1;
        }

        .s-modal-body {
            padding: 1rem;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideDown {
            from { transform: translateY(-30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .d-dropdown-menu p {
            padding: 0.7rem 1rem;
            font-size: 0.83rem;
            color: var(--text-muted);
        }

        .d-wrap {
            padding: 1.2rem 1.5rem;
            max-width: 1300px;
            margin: 0 auto;
        }

        .r-grid {
            display: grid;
            grid-template-columns: 450px 1fr;
            gap: 2rem;
            align-items: start;
            margin-top: 1rem;
        }

        @media (max-width: 992px) {
            .r-grid {
                grid-template-columns: 1fr;
            }
        }

        .d-card {
            background: var(--card-bg);
            border-radius: 6px;
            box-shadow: 0 1px 5px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .d-card-head {
            background: var(--brand-1);
            color: #fff;
            font-size: 0.9rem;
            font-weight: 700;
            padding: 0.6rem 1rem;
            letter-spacing: 0.02em;
        }

        .d-card-body {
            padding: 1.2rem;
        }

        .ef-group {
            margin-bottom: 1.2rem;
        }

        .ef-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
            letter-spacing: 0.5px;
        }

        .ef-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-soft);
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: inherit;
            color: var(--text-primary);
            outline: none;
            background: var(--input-bg);
            transition: all 0.3s ease;
        }

        .ef-control:focus {
            border-color: var(--brand-1);
            background: var(--input-bg);
            box-shadow: 0 0 0 4px rgba(47, 122, 89, 0.15);
        }

        textarea.ef-control {
            resize: vertical;
        }

        .ef-btn-save {
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, var(--brand-1) 0%, var(--brand-2) 100%);
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 0.9rem;
            font-family: inherit;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(47, 122, 89, 0.35);
        }

        .ef-btn-save:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, var(--brand-1-strong) 0%, var(--brand-2-strong) 100%);
            box-shadow: 0 6px 20px rgba(36, 95, 69, 0.45);
        }

        .alert {
            padding: 0.7rem 1rem;
            border-radius: 5px;
            font-size: 0.85rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .alert-error {
            background: #fde8e8;
            color: #a01a1a;
            border: 1px solid #f5b7b7;
        }

        .alert-success {
            background: #e6f4ea;
            color: #155724;
            border: 1px solid #b7dfbe;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.86rem;
            margin-top: 1.2rem;
        }

        table th {
            background: var(--brand-1);
            color: #fff;
            padding: 0.55rem 0.9rem;
            text-align: left;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        table td {
            padding: 0.55rem 0.9rem;
            border-bottom: 1px solid var(--border-soft);
            color: var(--text-primary);
        }

        table tr:hover td {
            background: #f8faf9;
        }

        .badge {
            display: inline-block;
            padding: 0.15rem 0.55rem;
            border-radius: 20px;
            font-size: 0.74rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }

        .badge-approved {
            background: #e6f4ea;
            color: #155724;
        }

        .badge-cancelled {
            background: #fde8e8;
            color: #a01a1a;
        }

        h4 {
            font-size: 0.95rem;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            margin-top: 1.5rem;
            font-family: 'Merriweather', serif;
            font-weight: 600;
        }

        /* PC Grid Styles */
        .pc-grid-container {
            margin-top: 0.5rem;
        }
        .pc-grid-header {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        .pc-grid-legend {
            display: flex;
            gap: 1rem;
            margin-bottom: 0.75rem;
            font-size: 0.75rem;
            color: var(--text-muted);
        }
        .pc-grid-legend span {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        .legend-dot {
            width: 12px;
            height: 12px;
            border-radius: 3px;
            display: inline-block;
        }
        .legend-dot.available { background: #28a745; }
        .legend-dot.occupied { background: #dc3545; }
        .legend-dot.selected { background: #007bff; }
        .pc-grid {
            display: grid;
            grid-template-columns: repeat(8, 1fr);
            gap: 6px;
            max-height: 260px;
            overflow-y: auto;
            padding: 4px;
        }
        .pc-cell {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 2px solid transparent;
            position: relative;
            min-height: 36px;
        }
        .pc-cell.available {
            background: #e6f4ea;
            color: #155724;
            border-color: #b7dfbe;
        }
        .pc-cell.available:hover {
            background: #c3e6cb;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
        }
        .pc-cell.occupied {
            background: #fde8e8;
            color: #a01a1a;
            border-color: #f5b7b7;
            cursor: not-allowed;
            opacity: 0.7;
        }
        .pc-cell.selected {
            background: #cce5ff;
            color: #004085;
            border-color: #007bff;
            box-shadow: 0 2px 10px rgba(0, 123, 255, 0.4);
            transform: translateY(-2px);
        }
        .pc-cell .pc-tooltip {
            display: none;
            position: absolute;
            bottom: calc(100% + 6px);
            left: 50%;
            transform: translateX(-50%);
            background: #333;
            color: #fff;
            font-size: 0.65rem;
            font-weight: 400;
            padding: 4px 8px;
            border-radius: 4px;
            white-space: nowrap;
            z-index: 10;
        }
        .pc-cell.occupied:hover .pc-tooltip {
            display: block;
        }
        .pc-grid-status {
            margin-top: 0.5rem;
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        .pc-grid-status strong {
            color: var(--brand-1);
        }
    </style>
    <link rel="stylesheet" href="assets/dark-mode.css">
    <link rel="stylesheet" href="assets/responsive.css">
    <script src="assets/dark-mode.js" defer></script>
</head>

<body>
    <nav class="d-nav">
        <span class="d-nav-brand">Reservation</span>
        <ul class="d-nav-links">
            <li class="d-dropdown">
                <a href="#" style="position: relative; padding: 0.35rem 0.5rem; display: flex; align-items: center;"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: block;"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg><?php if ($unread_count > 0): ?><span class="d-notification-badge"><?= $unread_count ?></span><?php endif; ?></a>
                <div class="d-dd-menu">
                    <?php if (empty($announcements)): ?>
                        <div class="d-dd-empty">No announcements</div>
                    <?php else: ?>
                        <div class="d-dd-header"> <?= $unread_count ?> New Announcement<?= $unread_count !== 1 ? 's' : '' ?>
                        </div>
                        <?php foreach ($announcements as $ann): ?>
                            <div class="d-dd-item<?= $ann['is_read'] ? ' is-read' : '' ?>" id="ann-item-<?= $ann['id'] ?>"
                                onclick="markAnnouncementAsRead(<?= $ann['id'] ?>)">
                                <div class="d-dd-item-date">CCS Admin |
                                    <?= date("M d, Y", strtotime($ann["created_at"])) ?>         <?php if ($ann['is_read']): ?><span
                                            class="d-dd-read-badge">✓ Read</span><?php endif; ?>
                                </div>
                                <?php if (!empty($ann['title'])): ?>
                                    <div class="d-dd-item-title"><?= htmlspecialchars($ann['title']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($ann['content'])): ?>
                                    <div class="d-dd-item-content"><?= htmlspecialchars(substr($ann['content'], 0, 100)) ?>...</div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </li>
            <li><a href="dashboard.php" <?php if (basename($_SERVER['PHP_SELF']) === 'dashboard.php' && !(isset($_GET['edit']) && $_GET['edit'] === 'true')) echo 'style="background: rgba(255,255,255,0.15)"'; ?>>Home</a></li>
            <li><a href="dashboard.php?edit=true" <?php if (basename($_SERVER['PHP_SELF']) === 'dashboard.php' && (isset($_GET['edit']) && $_GET['edit'] === 'true')) echo 'style="background: rgba(255,255,255,0.15)"'; ?>>Edit Profile</a></li>
            <li><a href="history.php" <?php if (basename($_SERVER['PHP_SELF']) === 'history.php') echo 'style="background: rgba(255,255,255,0.15)"'; ?>>History</a></li>
            <li><a href="reservation.php" <?php if (basename($_SERVER['PHP_SELF']) === 'reservation.php') echo 'style="background: rgba(255,255,255,0.15)"'; ?>>Reservation</a></li>
            <li><a href="leaderboard.php" <?php if (basename($_SERVER['PHP_SELF']) === 'leaderboard.php') echo 'style="background: rgba(255,255,255,0.15)"'; ?>>Leaderboard</a></li>
            <li><a href="logout.php" class="d-logout">Log out</a></li>
        </ul>
    </nav>
    <div class="d-wrap">
        <?php if (!$reservation_enabled): ?>
            <!-- Disabled Overlay/Message -->
            <div style="max-width: 600px; margin: 4rem auto; text-align: center; background: #fff; padding: 3rem 2rem; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
                <div style="font-size: 4rem; margin-bottom: 1rem;">🚫</div>
                <h2 style="color: #dc3545; font-family: 'Merriweather', serif; margin-bottom: 1rem;">Reservations are Disabled</h2>
                <p style="color: var(--text-muted); line-height: 1.6; margin-bottom: 2rem;">
                    The online laboratory reservation system is currently closed by the administrator. 
                    Please check back later or visit the CCS laboratory in person for sit-in inquiries.
                </p>
                <a href="dashboard.php" style="
                    display: inline-block; 
                    padding: 0.8rem 2rem; 
                    background: var(--brand-1); 
                    color: #fff; 
                    text-decoration: none; 
                    border-radius: 6px; 
                    font-weight: 700;
                    transition: all 0.3s;
                " onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                    Return to Dashboard
                </a>
            </div>
        <?php else: ?>
            <!-- Main Content Grid -->
            <div class="r-grid">
        <div class="d-card">
            <div class="d-card-head">Reserve a Sit-in Slot</div>
            <div class="d-card-body">
                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

                <?php if ($user): ?>
                    <div style="background: var(--input-bg); padding: 1rem; border-radius: 5px; margin-bottom: 0.8rem;">
                        <div
                            style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.3rem; text-transform: uppercase; letter-spacing: 0.5px;">
                            ID Number</div>
                        <div style="font-size: 1rem; font-weight: 600; color: var(--text-primary);">
                            <?= htmlspecialchars($user['id_number']) ?>
                        </div>
                    </div>
                    <div style="background: var(--input-bg); padding: 1rem; border-radius: 5px; margin-bottom: 1.2rem;">
                        <div
                            style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.3rem; text-transform: uppercase; letter-spacing: 0.5px;">
                            Name</div>
                        <div style="font-size: 1rem; font-weight: 600; color: var(--text-primary);">
                            <?= htmlspecialchars($user['first_name'] . ($user['middle_name'] ? ' ' . $user['middle_name'] : '') . ' ' . $user['last_name']) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="reservation.php" style="max-width:480px;">
                    <div class="ef-group">
                        <label class="ef-label">Date</label>
                        <input type="date" class="ef-control" name="date" min="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="ef-group">
                        <label class="ef-label">Preferred Time</label>
                        <input type="time" class="ef-control" name="time_in" required>
                    </div>
                    <div class="ef-group">
                        <label class="ef-label">Purpose</label>
                        <select class="ef-control" name="purpose" required>
                            <option value="" disabled selected>Select Programming Language</option>
                            <option value="C Programming">C Programming</option>
                            <option value="C++">C++</option>
                            <option value="Java">Java</option>
                            <option value="Python">Python</option>
                            <option value="JavaScript">JavaScript</option>
                            <option value="PHP">PHP</option>
                            <option value="C#">C#</option>
                        </select>
                    </div>
                    <div class="ef-group">
                        <label class="ef-label">Lab Room</label>
                        <select class="ef-control" name="lab_room" id="resLabRoom" required onchange="handleLabChange(this.value)">
                            <option value="" disabled selected>Select Laboratory</option>
                            <option value="524">524</option>
                            <option value="544">544</option>
                            <option value="526">526</option>
                            <option value="530">530</option>
                            <option value="528">528</option>
                        </select>
                    </div>

                    <!-- Software Availability Display -->
                    <div id="softwareDisplay" style="display: none; margin-bottom: 1.2rem; background: var(--input-bg); padding: 1rem; border-radius: 8px; border-left: 4px solid var(--brand-1);">
                        <div style="font-size: 0.7rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; margin-bottom: 0.5rem; letter-spacing: 0.5px;">Installed Software</div>
                        <div id="softwareList" style="display: flex; flex-wrap: wrap; gap: 0.4rem;">
                            <!-- Software tags will appear here -->
                        </div>
                    </div>
                    <div class="ef-group">
                        <label class="ef-label">PC Number</label>
                        <input type="hidden" name="pc_number" id="resPcNumber">
                        <div class="pc-grid-container" id="pcGridContainer">
                            <div style="color: var(--text-muted); font-size: 0.85rem; padding: 0.5rem 0;">Select a lab room first to see available PCs</div>
                        </div>
                    </div>
                    <button type="submit" class="ef-btn-save">Submit Reservation</button>
                </form>
            </div>
        </div>
        <?php if (!empty($reservations)): ?>
            <div class="d-card">
                <div class="d-card-head">My Reservations</div>
                <div class="d-card-body">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Purpose</th>
                                <th>Lab Room</th>
                                <th>PC #</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservations as $i => $r): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= htmlspecialchars(date("M d, Y", strtotime($r["date"]))) ?></td>
                                    <td><?= htmlspecialchars($r["time_in"]) ?></td>
                                    <td><?= htmlspecialchars($r["purpose"]) ?></td>
                                    <td><?= htmlspecialchars($r["lab_room"] ?? "N/A") ?></td>
                                    <td><?= $r["pc_number"] ? 'PC ' . htmlspecialchars($r["pc_number"]) : 'N/A' ?></td>
                                    <td><span
                                            class="badge badge-<?= strtolower($r['status']) ?>"><?= htmlspecialchars($r["status"]) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
            </div> <!-- /r-grid -->
        <?php endif; ?>
    </div> <!-- /d-wrap -->

    <!-- Sit-in Summary Modal -->
    <div id="summaryModal" class="s-modal">
        <div class="s-modal-content">
            <div class="s-modal-header">
                <h2>My Sit-in Summary</h2>
                <button class="s-close-btn" onclick="closeSummaryModal()">&times;</button>
            </div>
            <div class="s-modal-body">
                <table class="summary-table">
                    <tr>
                        <td>Total Sit-in Hours</td>
                        <td><?= $summary_stats['total_hours'] ?></td>
                    </tr>
                    <tr>
                        <td>Number of Sessions</td>
                        <td><?= $summary_stats['sessions'] ?></td>
                    </tr>
                    <tr>
                        <td>Average Session Duration</td>
                        <td><?= $summary_stats['avg_duration'] ?></td>
                    </tr>
                    <tr>
                        <td>Largest Session</td>
                        <td><?= $summary_stats['largest_session'] ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <script>
        function openSummaryModal() {
            document.getElementById('summaryModal').style.display = 'block';
        }

        function closeSummaryModal() {
            document.getElementById('summaryModal').style.display = 'none';
        }

        // Close summary modal if clicking outside of it
        window.addEventListener('click', function(event) {
            const summaryModal = document.getElementById('summaryModal');
            if (event.target == summaryModal) {
                summaryModal.style.display = 'none';
            }
        });

        function markAnnouncementAsRead(announcementId) {
            fetch('mark_announcements_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    announcement_id: announcementId
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update the UI without reloading
                        const annItem = document.getElementById('ann-item-' + announcementId);
                        if (annItem) {
                            annItem.classList.add('is-read');
                            // Update the date element to show read badge
                            const dateElement = annItem.querySelector('.d-dd-item-date');
                            if (dateElement && !dateElement.querySelector('.d-dd-read-badge')) {
                                const readBadge = document.createElement('span');
                                readBadge.className = 'd-dd-read-badge';
                                readBadge.textContent = '✓ Read';
                                dateElement.appendChild(readBadge);
                            }
                        }
                        // Update the notification badge count
                        updateNotificationBadge();
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function updateNotificationBadge() {
            // Re-fetch to get updated unread count
            fetch('get_unread_count.php')
                .then(response => response.json())
                .then(data => {
                    const badge = document.querySelector('.d-notification-badge');
                    if (data.unread_count > 0) {
                        if (!badge) {
                            // Create badge if it doesn't exist
                            const notifLink = document.querySelector('.d-dropdown a');
                            const newBadge = document.createElement('span');
                            newBadge.className = 'd-notification-badge';
                            newBadge.textContent = data.unread_count;
                            notifLink.appendChild(newBadge);
                        } else {
                            badge.textContent = data.unread_count;
                        }
                    } else if (badge) {
                        badge.remove();
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function handleLabChange(labRoom) {
            loadPcGrid(labRoom);
            loadSoftwareList(labRoom);
        }

        function loadSoftwareList(labRoom) {
            const display = document.getElementById('softwareDisplay');
            const list = document.getElementById('softwareList');
            
            if (!labRoom) {
                display.style.display = 'none';
                return;
            }

            fetch('get_lab_software.php?lab_room=' + labRoom)
                .then(response => response.json())
                .then(data => {
                    list.innerHTML = '';
                    if (data.length > 0) {
                        data.forEach(sw => {
                            const tag = document.createElement('span');
                            tag.style.cssText = 'background: rgba(47, 122, 89, 0.1); color: var(--brand-1); padding: 0.3rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: 700; border: 1px solid rgba(47, 122, 89, 0.2);';
                            tag.textContent = sw.name + (sw.version ? ' (' + sw.version + ')' : '');
                            list.appendChild(tag);
                        });
                        display.style.display = 'block';
                    } else {
                        list.innerHTML = '<span style="font-size: 0.8rem; color: var(--text-muted);">No software data available for this lab.</span>';
                        display.style.display = 'block';
                    }
                })
                .catch(() => {
                    display.style.display = 'none';
                });
        }

        function loadPcGrid(labRoom) {
            const container = document.getElementById('pcGridContainer');
            const pcInput = document.getElementById('resPcNumber');

            if (!labRoom) {
                container.innerHTML = '<div style="color: var(--text-muted); font-size: 0.85rem; padding: 0.5rem 0;">Select a lab room first to see available PCs</div>';
                return;
            }

            container.innerHTML = '<div style="color: var(--text-muted); font-size: 0.85rem; padding: 0.5rem 0;">Loading PCs...</div>';
            pcInput.value = '';

            fetch('get_available_pcs.php?lab_room=' + encodeURIComponent(labRoom))
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        container.innerHTML = '<div style="color:#a01a1a;">Error loading PCs</div>';
                        return;
                    }
                    let html = '<div class="pc-grid-legend">';
                    html += '<span><span class="legend-dot available"></span> Available</span>';
                    html += '<span><span class="legend-dot occupied"></span> Occupied</span>';
                    html += '<span><span class="legend-dot selected"></span> Selected</span>';
                    html += '</div>';
                    html += '<div class="pc-grid" id="pcGridInner"></div>';
                    html += '<div class="pc-grid-status" id="pcGridStatus"></div>';
                    container.innerHTML = html;

                    const grid = document.getElementById('pcGridInner');
                    const status = document.getElementById('pcGridStatus');
                    let availCount = 0;
                    for (let i = 1; i <= data.total_pcs; i++) {
                        const cell = document.createElement('div');
                        cell.className = 'pc-cell';
                        cell.textContent = i;
                        if (data.occupied[i]) {
                            cell.classList.add('occupied');
                            const tooltip = document.createElement('div');
                            tooltip.className = 'pc-tooltip';
                            tooltip.textContent = 'In use';
                            cell.appendChild(tooltip);
                        } else {
                            cell.classList.add('available');
                            availCount++;
                            cell.addEventListener('click', function() {
                                document.querySelectorAll('#pcGridInner .pc-cell.selected').forEach(c => {
                                    if (c.dataset.wasAvailable) c.className = 'pc-cell available';
                                });
                                this.classList.remove('available');
                                this.classList.add('selected');
                                this.dataset.wasAvailable = '1';
                                pcInput.value = i;
                            });
                        }
                        grid.appendChild(cell);
                    }
                    status.innerHTML = '<strong>' + availCount + '</strong> of ' + data.total_pcs + ' PCs available';
                })
                .catch(err => {
                    container.innerHTML = '<div style="color:#a01a1a;">Error loading PCs</div>';
                });
        }
    </script>
</body>

</html>