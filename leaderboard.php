<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}
require 'db.php';

$user_id = $_SESSION["user_id"];
$role = $_SESSION["role"] ?? "student";

// Fetch unread notifications for student role
$announcements = [];
$unread_count = 0;
if ($role === "student") {
    try {
        $ann_stmt = $pdo->prepare("SELECT a.id, a.title, a.content, a.created_at, 
                                   IF(ar.id IS NOT NULL, 1, 0) as is_read
                                   FROM announcements a 
                                   LEFT JOIN announcement_reads ar ON a.id = ar.announcement_id AND ar.user_id = ?
                                   ORDER BY a.created_at DESC LIMIT 10");
        $ann_stmt->execute([$user_id]);
        $announcements = $ann_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($announcements as $ann) {
            if (!$ann['is_read']) {
                $unread_count++;
            }
        }
    } catch (Exception $e) {}
}

// Fetch logged in user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$logged_user = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch leaderboard data
$leaderboard = [];
try {
    $q = "SELECT 
            u.id, 
            u.first_name, 
            u.middle_name, 
            u.last_name, 
            u.id_number,
            u.course,
            u.course_level,
            u.profile_picture,
            COALESCE(u.points, 0) as raw_points,
            COALESCE(
                (SELECT SUM(TIMESTAMPDIFF(SECOND, created_at, time_out)) / 3600.0 
                 FROM sit_in_logs 
                 WHERE user_id = u.id AND time_out IS NOT NULL), 0
            ) as total_hours,
            COALESCE(
                (SELECT COUNT(*) 
                 FROM sit_in_logs 
                 WHERE user_id = u.id AND time_out IS NOT NULL), 0
            ) as completed_sessions
          FROM users u
          GROUP BY u.id";
          
    $stmt = $pdo->query($q);
    $raw_ranks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate final weighted score: 50% points + 30% hours + 20% sessions
    foreach ($raw_ranks as &$student) {
        $points_part = $student['raw_points'] * 0.50;
        $hours_part = $student['total_hours'] * 0.30;
        $sessions_part = $student['completed_sessions'] * 0.20;
        
        $student['score'] = round($points_part + $hours_part + $sessions_part, 2);
    }
    unset($student);
    
    // Sort by score DESC, then raw_points DESC
    usort($raw_ranks, function($a, $b) {
        if ($b['score'] == $a['score']) {
            return $b['raw_points'] - $a['raw_points'];
        }
        return ($b['score'] > $a['score']) ? 1 : -1;
    });
    
    $leaderboard = $raw_ranks;
} catch (Exception $e) {
    // Fail-safe
}

// Find logged-in student's ranking
$my_rank = 0;
$my_stats = null;
if ($role === 'student') {
    foreach ($leaderboard as $index => $student) {
        if ((int)$student['id'] === (int)$user_id) {
            $my_rank = $index + 1;
            $my_stats = $student;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS | Leaderboard</title>
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
        
        /* NAVBAR Styles */
        nav { background: var(--nav-bg); display: flex; align-items: center; justify-content: space-between; padding: 0 1.5rem; height: 48px; position: sticky; top: 0; z-index: 200; box-shadow: 0 2px 8px rgba(0,0,0,0.25); }
        .nav-brand { color: var(--nav-text); font-size: 0.85rem; font-weight: 600; font-family: 'Merriweather', serif; }
        .nav-links { display: flex; align-items: center; list-style: none; gap: 0.1rem; }
        .nav-links a { color: var(--nav-text); text-decoration: none; font-size: 0.75rem; padding: 0.3rem 0.6rem; border-radius: 4px; transition: background 0.15s; }
        .nav-links a:hover { background: rgba(255,255,255,0.14); }
        .logout-btn { background: var(--brand-1) !important; font-weight: 700 !important; margin-left: 0.25rem; padding: 0.3rem 0.8rem; border-radius: 4px; }
        .logout-btn:hover { background: var(--brand-2) !important; }

        /* Notification dropdown */
        .d-dropdown {
            position: relative;
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
            top: calc(100% + 4px);
            left: 0;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            min-width: 280px;
            max-height: 400px;
            overflow-y: auto;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.14);
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

        .d-dd-empty {
            padding: 1rem;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.8rem;
        }

        .lb-container { max-width: 1100px; margin: 2rem auto; padding: 0 1rem; }
        
        /* Leaderboard Header Card */
        .lb-header-card {
            background: linear-gradient(135deg, var(--brand-1) 0%, var(--brand-2) 100%);
            color: #fff; border-radius: 12px; padding: 2rem; margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(47, 122, 89, 0.2); text-align: center;
        }
        .lb-header-card h1 { font-family: 'Merriweather', serif; font-size: 2rem; margin-bottom: 0.5rem; }
        .lb-header-card p { font-size: 0.95rem; opacity: 0.9; max-width: 600px; margin: 0 auto 1.5rem; }
        
        .lb-formula {
            display: inline-flex; gap: 1.5rem; background: rgba(255, 255, 255, 0.15);
            padding: 0.6rem 1.5rem; border-radius: 50px; font-size: 0.8rem; font-weight: 700;
            backdrop-filter: blur(5px); border: 1px solid rgba(255, 255, 255, 0.25);
        }

        /* PODIUM */
        .podium-container {
            display: flex; justify-content: center; align-items: flex-end;
            gap: 1.5rem; margin-bottom: 3rem; padding-top: 2rem;
        }
        .podium-spot {
            background: var(--card-bg); border-radius: 12px; display: flex;
            flex-direction: column; align-items: center; padding: 1.5rem 1rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.06); text-align: center;
            border: 1px solid var(--border-soft); position: relative;
        }
        
        /* Height based on rankings */
        .spot-1 { width: 230px; height: 320px; order: 2; z-index: 3; border-color: #ffd700; box-shadow: 0 12px 35px rgba(255, 215, 0, 0.15); }
        .spot-2 { width: 200px; height: 280px; order: 1; z-index: 2; border-color: #c0c0c0; }
        .spot-3 { width: 200px; height: 250px; order: 3; z-index: 1; border-color: #cd7f32; }

        .podium-badge {
            position: absolute; top: -20px; width: 40px; height: 40px;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; font-weight: 900; color: #fff;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        .spot-1 .podium-badge { background: linear-gradient(135deg, #ffe066, #f5b041); border: 2px solid #fff; }
        .spot-2 .podium-badge { background: linear-gradient(135deg, #e5e8e8, #bdc3c7); border: 2px solid #fff; }
        .spot-3 .podium-badge { background: linear-gradient(135deg, #edbb99, #dc7633); border: 2px solid #fff; }

        .podium-avatar {
            width: 80px; height: 80px; border-radius: 50%; overflow: hidden;
            border: 4px solid var(--border-soft); margin-bottom: 1rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .spot-1 .podium-avatar { border-color: #ffd700; width: 95px; height: 95px; }
        .spot-2 .podium-avatar { border-color: #c0c0c0; }
        .spot-3 .podium-avatar { border-color: #cd7f32; }

        .podium-name { font-weight: 700; font-size: 1rem; color: var(--text-primary); margin-bottom: 0.2rem; }
        .podium-score { font-size: 1.4rem; font-weight: 900; color: var(--brand-1); margin-bottom: 0.5rem; }
        .podium-stats { font-size: 0.75rem; color: var(--text-muted); line-height: 1.4; }

        /* My Personal Rank Stats Banner */
        .my-stats-banner {
            background: rgba(47, 122, 89, 0.08); border: 2px solid var(--brand-1);
            border-radius: 10px; padding: 1rem 1.5rem; margin-bottom: 2rem;
            display: flex; justify-content: space-between; align-items: center;
        }
        .my-stats-left { display: flex; align-items: center; gap: 1.5rem; }
        .my-rank-badge {
            background: var(--brand-1); color: #fff; font-size: 1.5rem; font-weight: 900;
            padding: 0.5rem 1rem; border-radius: 8px; min-width: 60px; text-align: center;
        }
        .my-stats-details h3 { font-size: 1.1rem; color: var(--text-primary); margin-bottom: 0.2rem; }
        .my-stats-details p { font-size: 0.8rem; color: var(--text-muted); }
        .my-stats-right { text-align: right; }
        .my-score-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); }
        .my-score-val { font-size: 1.8rem; font-weight: 900; color: var(--brand-1); }

        /* LEADERBOARD TABLE */
        .lb-card { background: var(--card-bg); border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); overflow: hidden; border: 1px solid var(--border-soft); }
        .lb-table { width: 100%; border-collapse: collapse; text-align: left; }
        .lb-table th { background: var(--brand-1); color: #fff; padding: 1rem 1.2rem; font-weight: 700; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; }
        .lb-table td { padding: 1.2rem; border-bottom: 1px solid var(--border-soft); font-size: 0.9rem; color: var(--text-primary); vertical-align: middle; }
        .lb-table tr:hover td { background: rgba(47, 122, 89, 0.02); }
        .lb-table tr.my-row td { background: rgba(47, 122, 89, 0.06); font-weight: 600; }
        
        .rank-num { font-weight: 900; font-size: 1.1rem; color: var(--text-muted); text-align: center; width: 60px; }
        .student-cell { display: flex; align-items: center; gap: 1rem; }
        .student-avatar { width: 40px; height: 40px; border-radius: 50%; overflow: hidden; background: #eee; }
        .student-details { display: flex; flex-direction: column; }
        .student-name { font-weight: 700; color: var(--text-primary); }
        .student-id { font-size: 0.75rem; color: var(--text-muted); }
        
        .score-cell { font-weight: 900; font-size: 1.1rem; color: var(--brand-1); }
        .detail-cell { font-size: 0.8rem; color: var(--text-muted); }

        /* Dark Mode fixes */
        html.dark-mode .podium-spot { background: var(--card-bg); border-color: #30363b; }
        html.dark-mode .podium-name { color: #fff; }
        html.dark-mode .my-stats-details h3 { color: #fff; }
        html.dark-mode .student-name { color: #fff; }
        
        @media (max-width: 768px) {
            .podium-container { flex-direction: column; align-items: center; gap: 3rem; }
            .podium-spot { width: 100% !important; height: auto !important; order: unset !important; }
            .podium-badge { top: -15px; }
            .my-stats-banner { flex-direction: column; text-align: center; gap: 1rem; }
            .my-stats-left { flex-direction: column; gap: 0.5rem; }
            .my-stats-right { text-align: center; }
            .lb-table th:nth-child(4), .lb-table td:nth-child(4),
            .lb-table th:nth-child(5), .lb-table td:nth-child(5) { display: none; }
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <nav>
        <span class="nav-brand"><?= $role === 'admin' ? 'CCS Admin' : 'CCS Student' ?> | Leaderboards</span>
        <ul class="nav-links">
            <?php if ($role === 'admin'): ?>
                <li><a href="admin.php" <?php if (basename($_SERVER['PHP_SELF']) === 'admin.php') echo 'style="background: rgba(255,255,255,0.15)"'; ?>>Home</a></li>
                <li><a href="admin-search.php" <?php if (basename($_SERVER['PHP_SELF']) === 'admin-search.php') echo 'style="background: rgba(255,255,255,0.15)"'; ?>>Search</a></li>
                <li><a href="admin-students.php" <?php if (basename($_SERVER['PHP_SELF']) === 'admin-students.php') echo 'style="background: rgba(255,255,255,0.15)"'; ?>>Students</a></li>
                <li><a href="admin-sitin.php" <?php if (basename($_SERVER['PHP_SELF']) === 'admin-sitin.php') echo 'style="background: rgba(255,255,255,0.15)"'; ?>>Active Sit-In</a></li>
                <li><a href="admin-records.php" <?php if (basename($_SERVER['PHP_SELF']) === 'admin-records.php') echo 'style="background: rgba(255,255,255,0.15)"'; ?>>View Sit-In Records</a></li>
                <li><a href="admin-feedback.php" <?php if (basename($_SERVER['PHP_SELF']) === 'admin-feedback.php') echo 'style="background: rgba(255,255,255,0.15)"'; ?>>Feedback Reports</a></li>
                <li><a href="admin-reservations.php" <?php if (basename($_SERVER['PHP_SELF']) === 'admin-reservations.php') echo 'style="background: rgba(255,255,255,0.15)"'; ?>>Reservation</a></li>
                <li><a href="admin-lab-assets.php" <?php if (basename($_SERVER['PHP_SELF']) === 'admin-lab-assets.php') echo 'style="background: rgba(255,255,255,0.15)"'; ?>>Lab Assets</a></li>
                <li><a href="leaderboard.php" <?php if (basename($_SERVER['PHP_SELF']) === 'leaderboard.php') echo 'style="background: rgba(255,255,255,0.15)"'; ?>>Leaderboard</a></li>
            <?php else: ?>
                <li class="d-dropdown">
                    <a href="#" style="position: relative; padding: 0.35rem 0.5rem; display: flex; align-items: center;"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: block;"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg><?php if ($unread_count > 0): ?><span class="d-notification-badge"><?= $unread_count ?></span><?php endif; ?></a>
                    <div class="d-dd-menu">
                        <?php if (empty($announcements)): ?>
                            <div class="d-dd-empty">No announcements</div>
                        <?php else: ?>
                            <div class="d-dd-header"> <?= $unread_count ?> New Announcement<?= $unread_count !== 1 ? 's' : '' ?></div>
                            <?php foreach ($announcements as $ann): ?>
                                <div class="d-dd-item<?= $ann['is_read'] ? ' is-read' : '' ?>" id="ann-item-<?= $ann['id'] ?>" onclick="markAnnouncementAsRead(<?= $ann['id'] ?>)">
                                    <div class="d-dd-item-date">CCS Admin | <?= date("M d, Y", strtotime($ann["created_at"])) ?><?php if ($ann['is_read']): ?><span class="d-dd-read-badge">✓ Read</span><?php endif; ?></div>
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
            <?php endif; ?>
            <li><a href="logout.php" class="logout-btn">Log out</a></li>
        </ul>
    </nav>

    <div class="lb-container">
        
        <!-- Header banner card -->
        <div class="lb-header-card">
            <h1>🏆 Student Rankings & Leaderboard</h1>
            <p>Compete, complete sessions, and earn your way to the top of the College of Information & Computer Studies podium!</p>

        </div>

        <?php if ($role === 'student' && $my_stats): ?>
            <!-- Personal stats banner -->
            <div class="my-stats-banner">
                <div class="my-stats-left">
                    <div class="my-rank-badge">#<?= $my_rank ?></div>
                    <div class="my-stats-details">
                        <h3><?= htmlspecialchars($my_stats['first_name'] . ' ' . $my_stats['last_name']) ?></h3>
                        <p><?= htmlspecialchars($my_stats['course'] . ' ' . $my_stats['course_level']) ?> • <?= htmlspecialchars($my_stats['id_number']) ?></p>
                    </div>
                </div>
                <div class="my-stats-right">
                    <div class="my-score-label">My Leaderboard Score</div>
                    <div class="my-score-val"><?= $my_stats['score'] ?></div>
                </div>
            </div>
        <?php endif; ?>

        <!-- PODIUM: TOP 3 -->
        <?php if (count($leaderboard) > 0): ?>
            <div class="podium-container">
                <!-- Spot 1 -->
                <?php if (isset($leaderboard[0])): 
                    $p1 = $leaderboard[0];
                ?>
                    <div class="podium-spot spot-1">
                        <div class="podium-badge">1</div>
                        <div class="podium-avatar">
                            <?php if (!empty($p1['profile_picture']) && file_exists('uploads/' . $p1['profile_picture'])): ?>
                                <img src="uploads/<?= htmlspecialchars($p1['profile_picture']) ?>" alt="Gold" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%"><rect width="100" height="100" fill="#f1c40f"/><circle cx="50" cy="35" r="22" fill="#fff"/><ellipse cx="50" cy="82" rx="30" ry="18" fill="#fff"/></svg>
                            <?php endif; ?>
                        </div>
                        <div class="podium-score"><?= $p1['score'] ?></div>
                        <div class="podium-name"><?= htmlspecialchars($p1['first_name'] . ' ' . $p1['last_name']) ?></div>
                        <div class="podium-stats">
                            <div>🌟 Points: <?= $p1['raw_points'] ?></div>
                            <div>⏱️ Hours: <?= round($p1['total_hours'], 1) ?>h</div>
                            <div>✅ Sessions: <?= $p1['completed_sessions'] ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Spot 2 -->
                <?php if (isset($leaderboard[1])): 
                    $p2 = $leaderboard[1];
                ?>
                    <div class="podium-spot spot-2">
                        <div class="podium-badge">2</div>
                        <div class="podium-avatar">
                            <?php if (!empty($p2['profile_picture']) && file_exists('uploads/' . $p2['profile_picture'])): ?>
                                <img src="uploads/<?= htmlspecialchars($p2['profile_picture']) ?>" alt="Silver" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%"><rect width="100" height="100" fill="#bdc3c7"/><circle cx="50" cy="35" r="22" fill="#fff"/><ellipse cx="50" cy="82" rx="30" ry="18" fill="#fff"/></svg>
                            <?php endif; ?>
                        </div>
                        <div class="podium-score"><?= $p2['score'] ?></div>
                        <div class="podium-name"><?= htmlspecialchars($p2['first_name'] . ' ' . $p2['last_name']) ?></div>
                        <div class="podium-stats">
                            <div>🌟 Points: <?= $p2['raw_points'] ?></div>
                            <div>⏱️ Hours: <?= round($p2['total_hours'], 1) ?>h</div>
                            <div>✅ Sessions: <?= $p2['completed_sessions'] ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Spot 3 -->
                <?php if (isset($leaderboard[2])): 
                    $p3 = $leaderboard[2];
                ?>
                    <div class="podium-spot spot-3">
                        <div class="podium-badge">3</div>
                        <div class="podium-avatar">
                            <?php if (!empty($p3['profile_picture']) && file_exists('uploads/' . $p3['profile_picture'])): ?>
                                <img src="uploads/<?= htmlspecialchars($p3['profile_picture']) ?>" alt="Bronze" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%"><rect width="100" height="100" fill="#e59866"/><circle cx="50" cy="35" r="22" fill="#fff"/><ellipse cx="50" cy="82" rx="30" ry="18" fill="#fff"/></svg>
                            <?php endif; ?>
                        </div>
                        <div class="podium-score"><?= $p3['score'] ?></div>
                        <div class="podium-name"><?= htmlspecialchars($p3['first_name'] . ' ' . $p3['last_name']) ?></div>
                        <div class="podium-stats">
                            <div>🌟 Points: <?= $p3['raw_points'] ?></div>
                            <div>⏱️ Hours: <?= round($p3['total_hours'], 1) ?>h</div>
                            <div>✅ Sessions: <?= $p3['completed_sessions'] ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- MAIN TABLE -->
        <div class="lb-card">
            <table class="lb-table">
                <thead>
                    <tr>
                        <th style="text-align: center; text-transform: uppercase;">Rank</th>
                        <th style="text-transform: uppercase;">Student</th>
                        <th style="text-align: center; text-transform: uppercase;">ID</th>
                        <th style="text-align: center; text-transform: uppercase;">Earned Points</th>
                        <th style="text-align: center; text-transform: uppercase;">Total Hours</th>
                        <th style="text-align: center; text-transform: uppercase;">Sessions Completed</th>
                        <th style="text-align: right; text-transform: uppercase;">Score</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($leaderboard)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-muted);">No student rankings available yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($leaderboard as $index => $row): 
                            $is_current = ((int)$row['id'] === (int)$user_id && $role === 'student');
                        ?>
                            <tr class="<?= $is_current ? 'my-row' : '' ?>">
                                <td class="rank-num">
                                    <?php if ($index === 0): ?>🥇<?php elseif ($index === 1): ?>🥈<?php elseif ($index === 2): ?>🥉<?php else: ?><span style="color:var(--text-muted);font-size:0.8rem;">#</span><?= $index + 1 ?><?php endif; ?>
                                </td>
                                <td>
                                    <div class="student-cell">
                                        <div class="student-avatar">
                                            <?php if (!empty($row['profile_picture']) && file_exists('uploads/' . $row['profile_picture'])): ?>
                                                <img src="uploads/<?= htmlspecialchars($row['profile_picture']) ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                                            <?php else: ?>
                                                <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%"><rect width="100" height="100" fill="#9aafc7"/><circle cx="50" cy="35" r="22" fill="#fff"/><ellipse cx="50" cy="82" rx="30" ry="18" fill="#fff"/></svg>
                                            <?php endif; ?>
                                        </div>
                                        <div class="student-details">
                                            <span class="student-name" style="font-weight: 700; color: var(--text-primary);"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="detail-cell" style="text-align: center; color: var(--text-muted); font-weight: 600;"><?= htmlspecialchars($row['id_number']) ?></td>
                                <td class="detail-cell" style="text-align: center; font-weight: 700; color: var(--text-primary);"><?= $row['raw_points'] ?></td>
                                <td class="detail-cell" style="text-align: center; font-weight: 700; color: var(--text-muted);"><?= round($row['total_hours'], 2) ?>h</td>
                                <td class="detail-cell" style="text-align: center; font-weight: 700; color: var(--text-muted);"><?= $row['completed_sessions'] ?></td>
                                <td class="score-cell" style="text-align: right; font-weight: 800; color: var(--brand-1);"><?= $row['score'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <script>
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
                } else {
                    if (badge) {
                        badge.remove();
                    }
                }
            })
            .catch(error => console.error('Error updating badge:', error));
        }
    </script>

</body>
</html>
