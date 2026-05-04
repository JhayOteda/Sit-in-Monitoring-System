<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}
require 'db.php';
$user_id = $_SESSION["user_id"];
$success = $error = "";

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
    if (empty($date) || empty($time_in) || empty($purpose) || empty($lab_room)) {
        $error = "Please fill in all fields.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO reservations (user_id, date, time_in, purpose, lab_room, status, created_at) VALUES (?, ?, ?, ?, ?, 'Pending', NOW())");
            $stmt->execute([$user_id, $date, $time_in, $purpose, $lab_room]);
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

        .d-dropdown-menu {
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

        .d-dropdown:hover .d-dropdown-menu {
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

        .d-dropdown-menu {
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

        .d-dropdown:hover .d-dropdown-menu {
            display: block;
        }

        .d-dropdown-menu .d-dd-header {
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

        .d-dropdown-menu p {
            padding: 0.7rem 1rem;
            font-size: 0.83rem;
            color: var(--text-muted);
        }

        .d-wrap {
            padding: 1.2rem 1.5rem;
            max-width: 900px;
            margin: 0 auto;
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
            background: #fff;
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
    </style>
</head>

<body>
    <nav class="d-nav">
        <span class="d-nav-brand">Reservation</span>
        <ul class="d-nav-links">
            <li class="d-dropdown">
                <a href="#">Notification ▾<?php if ($unread_count > 0): ?><span
                            class="d-notification-badge"><?= $unread_count ?></span><?php endif; ?></a>
                <div class="d-dropdown-menu">
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
            <li><a href="dashboard.php">Home</a></li>
            <li><a href="dashboard.php?edit=true">Edit Profile</a></li>
            <li><a href="history.php">History</a></li>
            <li><a href="reservation.php">Reservation</a></li>
            <li><a href="logout.php" class="d-logout">Log out</a></li>
        </ul>
    </nav>
    <div class="d-wrap">
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
                        <select class="ef-control" name="lab_room" required>
                            <option value="" disabled selected>Select Laboratory</option>
                            <option value="524">524</option>
                            <option value="544">544</option>
                            <option value="526">526</option>
                            <option value="530">530</option>
                            <option value="528">528</option>
                        </select>
                    </div>
                    <button type="submit" class="ef-btn-save">Submit Reservation</button>
                </form>
            </div>
        </div>
        <?php if (!empty($reservations)): ?>
            <div class="d-card" style="margin-top: 1.5rem;">
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
                    } else if (badge) {
                        badge.remove();
                    }
                })
                .catch(error => console.error('Error:', error));
        }
    </script>
</body>

</html>