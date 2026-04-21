<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}
require 'db.php';

$user_id = $_SESSION["user_id"];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$edit_mode = isset($_GET['edit']) && $_GET['edit'] === 'true';

$announcements = [];
$unread_count = 0;
$unread_announcements = [];

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
    $unread_count = 0;
    foreach ($announcements as $ann) {
        if (!$ann['is_read']) {
            $unread_count++;
        }
    }
} catch (Exception $e) {
    $announcements = [
        ["id" => 1, "title" => "", "content" => "", "created_at" => "2026-02-11"],
        ["id" => 2, "title" => "", "content" => "Important Announcement We are excited to announce the launch of our new website! 🎉 Explore our latest products and services now!", "created_at" => "2024-05-08"],
    ];
}

$session_count = 0;
try {
    $sc = $pdo->prepare("SELECT COUNT(*) FROM sit_in_logs WHERE user_id = ?");
    $sc->execute([$user_id]);
    $session_count = $sc->fetchColumn();
} catch (Exception $e) {
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS | Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Merriweather:wght@700&family=Nunito+Sans:wght@400;600;700&display=swap');

        *,
        *::before,
        *::after {
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

        html,
        body {
            height: 100%;
            font-family: 'Nunito Sans', sans-serif;
            background: linear-gradient(135deg, var(--bg-start) 0%, var(--bg-end) 100%);
        }

        /* ── NAVBAR ── */
        .d-nav {
            background: var(--nav-bg);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
            height: 48px;
            position: sticky;
            top: 0;
            z-index: 200;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
        }

        .d-nav-brand {
            color: var(--nav-text);
            font-size: 0.95rem;
            font-weight: 600;
            white-space: nowrap;
            font-family: 'Merriweather', serif;
            letter-spacing: 0.02em;
        }

        .d-nav-links {
            display: flex;
            align-items: center;
            list-style: none;
            gap: 0.1rem;
        }

        .d-nav-links li a {
            color: var(--nav-text);
            text-decoration: none;
            font-size: 0.9rem;
            padding: 0.35rem 0.7rem;
            border-radius: 4px;
            white-space: nowrap;
            display: block;
            transition: background 0.15s;
        }

        .d-nav-links li a:hover {
            background: rgba(255, 255, 255, 0.14);
        }

        .d-logout {
            background: var(--brand-1) !important;
            border-radius: 4px !important;
            font-weight: 700 !important;
            margin-left: 0.25rem;
        }

        .d-logout:hover {
            background: var(--brand-2) !important;
        }

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

        /* Flash alerts */
        .d-flash {
            padding: 0.7rem 1rem;
            border-radius: 5px;
            font-size: 0.85rem;
            margin-bottom: 1rem;
            font-weight: 600;
            text-align: center;
        }

        .d-flash-ok {
            background: #e6f4ea;
            color: #155724;
            border: 1px solid #b7dfbe;
        }

        .d-flash-err {
            background: #fde8e8;
            color: #a01a1a;
            border: 1px solid #f5b7b7;
        }

        /* ── LAYOUT ── */
        .d-grid {
            display: grid;
            grid-template-columns: 240px 1fr 300px;
            gap: 12px;
            padding: 12px;
            min-height: calc(100vh - 48px);
            align-items: start;
        }

        /* ── CARD ── */

        .d-card-head {
            background: var(--brand-1);
            color: #fff;
            font-size: 0.9rem;
            font-weight: 700;
            padding: 0.6rem 1rem;
            letter-spacing: 0.02em;
        }

        .d-card {
            background: var(--card-bg);
            border-radius: 6px;
            box-shadow: 0 1px 5px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            height: 100%;
        }

        .d-card-body {
            padding: 1rem;
        }

        /* ── LEFT: Student Info ── */
        .d-avatar {
            display: flex;
            justify-content: center;
            padding: 1rem 0 0.75rem;
        }

        .d-avatar-box {
            width: 140px;
            height: 140px;
            background: #f5FAF7;
            border: 2px solid var(--border-soft);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(47, 122, 89, 0.15);
        }

        .d-divider {
            height: 1px;
            background: var(--border-soft);
            margin: 0.5rem 0 0.85rem;
        }

        .d-info-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
        }

        .d-info-list li {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            font-size: 0.87rem;
            color: var(--text-primary);
            line-height: 1.4;
        }

        .d-info-list li b {
            font-weight: 700;
        }

        /* ── CENTER: Announcements ── */
        .d-ann-item {
            padding: 1rem 1.2rem;
            border-bottom: 1px solid #eaeef5;
        }

        .d-ann-item:last-child {
            border-bottom: none;
        }

        .d-ann-meta {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-muted);
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .d-ann-text {
            font-size: 0.85rem;
            color: var(--text-primary);
            line-height: 1.5;
            background: #f8faf9;
            border-left: 4px solid var(--brand-1);
            border-radius: 0 8px 8px 0;
            padding: 1rem 1rem;
        }

        /* ── RIGHT: Rules ── */
        .d-rules-body {
            font-size: 0.82rem;
            color: var(--text-muted);
            line-height: 1.7;
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
            height: calc(100vh - 120px);
            overflow-y: auto;
        }

        .d-rules-uni {
            text-align: center;
            font-weight: 700;
            font-size: 0.92rem;
            font-style: italic;
            color: var(--text-primary);
            font-family: 'Merriweather', serif;
        }

        .d-rules-col {
            text-align: center;
            font-size: 0.76rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: var(--text-primary);
        }

        .d-rules-title {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text-primary);
            margin-top: 0.15rem;
        }

        .d-rules-intro {
            font-size: 0.81rem;
            color: var(--text-muted);
        }

        .d-rules-body p {
            font-size: 0.81rem;
            color: var(--text-muted);
        }

        /* ── EDIT PROFILE (full width) ── */
        .d-edit-wrap {
            padding: 12px;
            min-height: calc(100vh - 48px);
        }

        .d-edit-card {
            background: #fff;
            border-radius: 6px;
            box-shadow: 0 1px 5px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 700px;
            margin: 0 auto;
        }

        .ef-body {
            padding: 1.5rem 2rem;
        }

        .ef-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem 1.5rem;
        }

        .ef-grid .ef-group.ef-full {
            grid-column: 1 / -1;
        }

        .ef-group {
            display: flex;
            flex-direction: column;
        }

        .ef-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
            letter-spacing: 0.5px;
        }

        .ef-control {
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
            background: #fff;
            box-shadow: 0 0 0 4px rgba(47, 122, 89, 0.15);
        }

        .ef-control[readonly] {
            background: #f4f4f4;
            cursor: not-allowed;
            color: var(--text-muted);
        }

        select.ef-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236b7a99' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            padding-right: 2rem;
        }

        .ef-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
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

        .ef-btn-cancel {
            padding: 0.75rem 1.5rem;
            background: #e0e0e0;
            color: #333;
            border: none;
            border-radius: 5px;
            font-size: 0.9rem;
            font-family: inherit;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: all 0.3s ease;
        }

        .ef-btn-cancel:hover {
            background: #d0d0d0;
            transform: translateY(-2px);
        }

        .ef-file-input {
            display: none;
        }

        .ef-file-label {
            display: inline-block;
            padding: 10px;
            background: linear-gradient(135deg, var(--brand-1) 0%, var(--brand-2) 100%);
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 0.75rem;
            font-family: inherit;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(47, 122, 89, 0.25);
            white-space: nowrap;
        }

        .ef-file-label:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, var(--brand-1-strong) 0%, var(--brand-2-strong) 100%);
            box-shadow: 0 6px 20px rgba(36, 95, 69, 0.45);
        }
    </style>
</head>

<body>

    <!-- NAVBAR -->
    <nav class="d-nav">
        <span class="d-nav-brand"><?= $edit_mode ? "Edit Profile" : "Dashboard" ?></span>
        <ul class="d-nav-links">
            <li class="d-dropdown">
                <a href="#">Notification ▾<?php if ($unread_count > 0): ?><span class="d-notification-badge"><?= $unread_count ?></span><?php endif; ?></a>
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
            <li><a href="dashboard.php">Home</a></li>
            <li><a href="dashboard.php?edit=true">Edit Profile</a></li>
            <li><a href="history.php">History</a></li>
            <li><a href="reservation.php">Reservation</a></li>
            <li><a href="logout.php" class="d-logout">Log out</a></li>
        </ul>
    </nav>

    <?php if (isset($_SESSION["success"])): ?>
        <div class="d-flash d-flash-ok"><?= htmlspecialchars($_SESSION["success"]) ?><?php unset($_SESSION["success"]); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION["error"])): ?>
        <div class="d-flash d-flash-err"><?= htmlspecialchars($_SESSION["error"]) ?><?php unset($_SESSION["error"]); ?>
        </div>
    <?php endif; ?>

    <?php if ($edit_mode): ?>
        <!-- ── EDIT PROFILE MODE: only the edit card, full width ── -->
        <div class="d-edit-wrap">
            <div class="d-edit-card">
                <div class="d-card-head">Edit Profile</div>
                <div class="ef-body">
                    <form method="POST" action="update_profile.php" enctype="multipart/form-data">
                        <div class="ef-grid">
                            <div class="ef-group ef-full" style="text-align: center; padding: 1.5rem 1rem;">
                                <div class="ef-avatar-preview" style="width: 140px; height: 140px; background: #f5FAF7; border: 2px solid var(--border-soft); border-radius: 12px; display: flex; align-items: center; justify-content: center; overflow: hidden; margin: 0 auto 1rem; box-shadow: 0 8px 25px rgba(47, 122, 89, 0.15);">
                                    <?php if (!empty($user["profile_picture"]) && file_exists("uploads/" . $user["profile_picture"])): ?>
                                        <img src="uploads/<?= htmlspecialchars($user["profile_picture"]) ?>" alt="Profile Picture" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" width="110" height="110">
                                            <circle cx="50" cy="35" r="22" fill="#9aafc7" />
                                            <ellipse cx="50" cy="82" rx="30" ry="18" fill="#9aafc7" />
                                        </svg>
                                    <?php endif; ?>
                                </div>
                                <label class="ef-label" style="display: block; margin-bottom: 0.5rem;">Profile Picture</label>
                                <input type="file" name="profile_picture" accept="image/jpeg,image/png,image/gif,image/webp" id="profile_picture_input" class="ef-file-input">
                                <label for="profile_picture_input" class="ef-file-label">Choose File</label>
                                <script>
                                    document.getElementById('profile_picture_input').addEventListener('change', function(e) {
                                        const file = e.target.files[0];
                                        if (file) {
                                            const reader = new FileReader();
                                            reader.onload = function(event) {
                                                const preview = document.querySelector('.ef-avatar-preview');
                                                preview.innerHTML = '<img src="' + event.target.result + '" alt="Preview" style="width: 100%; height: 100%; object-fit: cover;">';
                                            };
                                            reader.readAsDataURL(file);
                                        }
                                    });
                                </script>
                            </div>
                            <div class="ef-group ef-full">
                                <label class="ef-label">ID Number</label>
                                <input class="ef-control" type="text" value="<?= htmlspecialchars($user["id_number"]) ?>"
                                    readonly>
                            </div>
                            <div class="ef-group ef-full">
                                <label class="ef-label">First Name</label>
                                <input class="ef-control" type="text" name="first_name"
                                    value="<?= htmlspecialchars($user["first_name"]) ?>" required>
                            </div>
                            <div class="ef-group ef-full">
                                <label class="ef-label">Last Name</label>
                                <input class="ef-control" type="text" name="last_name"
                                    value="<?= htmlspecialchars($user["last_name"]) ?>" required>
                            </div>
                            <div class="ef-group ef-full">
                                <label class="ef-label">Middle Name</label>
                                <input class="ef-control" type="text" name="middle_name"
                                    value="<?= htmlspecialchars($user["middle_name"] ?? "") ?>">
                            </div>
                            <div class="ef-group">
                                <label class="ef-label">Course</label>
                                <select class="ef-control" name="course" required>
                                    <option value="BSIT" <?= $user["course"] === "BSIT" ? "selected" : "" ?>>Bachelor of Science in
                                        Information Technology (BSIT)</option>
                                    <option value="BSCA" <?= $user["course"] === "BSCA" ? "selected" : "" ?>>Bachelor of Science in
                                        Customs Administration (BSCA)</option>
                                    <option value="BSCS" <?= $user["course"] === "BSCS" ? "selected" : "" ?>>Bachelor of Science in
                                        Computer Science (BSCS)</option>
                                </select>
                            </div>
                            <div class="ef-group">
                                <label class="ef-label">Year Level</label>
                                <select class="ef-control" name="course_level" required>
                                    <?php for ($i = 1; $i <= 4; $i++): ?>
                                        <option value="<?= $i ?>" <?= (string) $user["course_level"] === (string) $i ? "selected" : "" ?>>
                                            <?= $i ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="ef-group ef-full">
                                <label class="ef-label">Email</label>
                                <input class="ef-control" type="email" name="email"
                                    value="<?= htmlspecialchars($user["email"]) ?>" required>
                            </div>
                            <div class="ef-group ef-full">
                                <label class="ef-label">Address</label>
                                <input class="ef-control" type="text" name="address"
                                    value="<?= htmlspecialchars($user["address"] ?? "") ?>">
                            </div>
                        </div>
                        <div class="ef-actions">
                            <button type="submit" class="ef-btn-save">Save Changes</button>
                            <a href="dashboard.php" class="ef-btn-cancel">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- ── HOME MODE: 3-column grid ── -->
        <div class="d-grid">

            <!-- LEFT: Student Info -->
            <div class="d-card">
                <div class="d-card-head">Student Information</div>
                <div class="d-card-body">
                    <div class="d-avatar">
                        <div class="d-avatar-box">
                            <?php if (!empty($user["profile_picture"]) && file_exists("uploads/" . $user["profile_picture"])): ?>
                                <img src="uploads/<?= htmlspecialchars($user["profile_picture"]) ?>" alt="Profile Picture" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" width="110" height="110">
                                    <circle cx="50" cy="35" r="22" fill="#9aafc7" />
                                    <ellipse cx="50" cy="82" rx="30" ry="18" fill="#9aafc7" />
                                </svg>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="d-divider"></div>
                    <ul class="d-info-list">
                        <li>👤 <span><b>Name:</b>
                                <?= htmlspecialchars($user["first_name"] . " " . $user["last_name"]) ?></span></li>
                        <li>🎓 <span><b>Course:</b> <?= htmlspecialchars($user["course"]) ?></span></li>
                        <li>↕️ <span><b>Year:</b> <?= htmlspecialchars($user["course_level"]) ?></span></li>
                        <li>✉️ <span><b>Email:</b> <?= htmlspecialchars($user["email"]) ?></span></li>
                        <li>🪪 <span><b>Address:</b> <?= htmlspecialchars($user["address"] ?? "Not provided") ?></span></li>
                        <li>🖥️ <span><b>Remaining Session:</b> <?= (30 - $session_count) ?></span></li>
                    </ul>
                </div>
            </div>

            <!-- CENTER: Announcements -->
            <div class="d-card">
                <div class="d-card-head">Announcement</div>
                <?php if (empty($announcements)): ?>
                    <div class="d-card-body" style="color:#888;font-size:0.9rem;">No announcements at this time.</div>
                <?php else: ?>
                    <?php foreach ($announcements as $ann): ?>
                        <div class="d-ann-item">
                            <div class="d-ann-meta">CCS Admin | <?= date("Y-M-d", strtotime($ann["created_at"])) ?></div>
                            <?php if (!empty($ann["title"])): ?>
                                <div style="font-size: 0.9rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.5rem;"><?= htmlspecialchars($ann["title"]) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($ann["content"])): ?>
                                <p class="d-ann-text"><?= nl2br(htmlspecialchars($ann["content"])) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- RIGHT: Rules & Regulations -->
            <div class="d-card">
                <div class="d-card-head">Rules and Regulation</div>
                <div class="d-card-body">
                    <div class="d-rules-body">
                        <p class="d-rules-uni">University of Cebu</p>
                        <p class="d-rules-col">COLLEGE OF INFORMATION &amp; COMPUTER STUDIES</p>
                        <p class="d-rules-title">LABORATORY RULES AND REGULATIONS</p>
                        <p class="d-rules-intro">To avoid embarrassment and maintain camaraderie with your friends and
                            superiors at our laboratories, please observe the following:</p>
                        <p>1. Maintain silence, proper decorum, and discipline inside the laboratory. Mobile phones,
                            walkmans and other personal pieces of equipment must be switched off.</p>
                        <p>2. Games are not allowed inside the lab. This includes computer-related games, card games and
                            other games that may disturb the operation of the lab.</p>
                        <p>3. Surfing the Internet is allowed only with the permission of the instructor. Downloading and
                            installing of software are strictly prohibited.</p>
                        <p>4. Food and drinks are strictly prohibited inside the lab.</p>
                        <p>5. Students are not allowed to change the settings of the computer without permission.</p>
                        <p>6. Students are responsible for the proper care of the equipment assigned to them.</p>
                    </div>
                </div>
            </div>

        </div>
    <?php endif; ?>

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