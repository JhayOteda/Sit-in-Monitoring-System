<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}
require 'db.php';
$user_id = $_SESSION["user_id"];
$logs = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM sit_in_logs WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS | History</title>
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
            position: relative;
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
            padding: 1rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.86rem;
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

        .badge-done,
        .badge-approved {
            background: #e6f4ea;
            color: #155724;
        }

        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }

        .badge-cancelled,
        .badge-rejected {
            background: #fde8e8;
            color: #a01a1a;
        }

        .no-data {
            padding: 2rem;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.9rem;
        }
    </style>
</head>

<body>
    <nav class="d-nav">
        <span class="d-nav-brand">History</span>
        <ul class="d-nav-links">
            <li class="d-dropdown">
                <a href="#">Notification ▾</a>
                <div class="d-dropdown-menu">
                    <p>No new notifications</p>
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
            <div class="d-card-head">Sit-in History</div>
            <div class="d-card-body">
                <?php if (empty($logs)): ?>
                    <div class="no-data">No sit-in history found.</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Time In</th>
                                <th>Time Out</th>
                                <th>Purpose</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $i => $log): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= htmlspecialchars(date("M d, Y", strtotime($log["created_at"]))) ?></td>
                                    <td><?= htmlspecialchars(date("h:i A", strtotime($log["created_at"]))) ?></td>
                                    <td><?= !empty($log["time_out"]) ? htmlspecialchars(date("h:i A", strtotime($log["time_out"]))) : "—" ?>
                                    </td>
                                    <td><?= htmlspecialchars($log["purpose"] ?? "—") ?></td>
                                    <td><span
                                            class="badge badge-<?= strtolower($log['status'] ?? 'done') ?>"><?= htmlspecialchars($log["status"] ?? "Done") ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>

</html>