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

    if ($user_id <= 0 || empty($purpose) || empty($lab_room)) {
        $error_message = "✗ Invalid data provided.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO sit_in_logs (user_id, purpose, lab_room, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$user_id, $purpose, $lab_room]);
            $success_message = "✓ Sit-In session created successfully!";
        } catch (Exception $e) {
            $error_message = "✗ Error creating sit-in: " . $e->getMessage();
        }
    }
}

// Handle end sit-in action
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "end_sitin") {
    $log_id = intval($_POST["log_id"] ?? 0);

    if ($log_id > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE sit_in_logs SET time_out = NOW() WHERE id = ? AND time_out IS NULL");
            $stmt->execute([$log_id]);
            $affected = $stmt->rowCount();
            if ($affected > 0) {
                $success_message = "✓ Sit-In session ended successfully!";
                header("Refresh: 2; url=admin-records.php");
            } else {
                $error_message = "⚠ Session already ended or not found.";
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
            sl.created_at,
            u.id_number,
            u.first_name,
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
            <div class="card-head">🖥️ Active Sit-In Sessions</div>
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
                                <th>Check-In Time</th>
                                <th>Duration</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($active_sitins as $sitin): ?>
                                <tr>
                                    <td><?= htmlspecialchars($sitin['id_number']) ?></td>
                                    <td><?= htmlspecialchars($sitin['first_name'] . ' ' . $sitin['last_name']) ?></td>
                                    <td><?= htmlspecialchars($sitin['purpose']) ?></td>
                                    <td><?= htmlspecialchars($sitin['lab_room']) ?></td>
                                    <td><?= date('M d, Y H:i', strtotime($sitin['created_at'])) ?></td>
                                    <td>
                                        <?php
                                        $start = new DateTime($sitin['created_at']);
                                        $now = new DateTime();
                                        $diff = $start->diff($now);
                                        echo $diff->format('%hh %im');
                                        ?>
                                    </td>
                                    <td>
                                        <form method="POST" action="admin-sitin.php" style="display: inline;">
                                            <input type="hidden" name="action" value="end_sitin">
                                            <input type="hidden" name="log_id" value="<?= $sitin['id'] ?>">
                                            <button type="submit" class="btn-end"
                                                onclick="return confirm('End sit-in for this student?')">End</button>
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

</body>

</html>