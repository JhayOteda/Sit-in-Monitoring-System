<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit;
}
require 'db.php';

$feedback_list = [];
try {
    $stmt = $pdo->query("SELECT f.id, f.log_id, f.user_id, u.first_name, u.last_name, u.id_number, f.message, f.rating, f.created_at 
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
            color: var(--text-muted);
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
    </style>
</head>

<body>
    <nav>
        <span class="nav-brand">College of Computer Studies Admin</span>
        <ul class="nav-links">
            <li><a href="admin.php">Home</a></li>
            <li><a href="admin-search.php">Search</a></li>
            <li><a href="admin-students.php">Students</a></li>
            <li><a href="admin-sitin.php">Active Sit-In</a></li>
            <li><a href="admin-records.php">View Sit-In Records</a></li>
            <li><a href="admin-reports.php">Sit-In Reports</a></li>
            <li><a href="admin-feedback.php">Feedback Reports</a></li>
            <li><a href="admin-reservations.php">Reservation</a></li>
            <li><a href="logout.php" class="logout-btn">Log out</a></li>
        </ul>
    </nav>
    <div class="admin-wrap">
        <div class="card">
            <div class="card-head">💬 Feedback Reports</div>
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
                                            <?= htmlspecialchars($fb['first_name'] . ' ' . $fb['last_name']) ?></div>
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
                                        📅 <?= htmlspecialchars(date("M d, Y", strtotime($fb['created_at']))) ?> at
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
</body>

</html>