<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit;
}
require 'db.php';

$success_msg = "";
$error_msg = "";

// Handle Approve/Reject actions
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && isset($_POST["reservation_id"])) {
    $reservation_id = intval($_POST["reservation_id"]);
    $action = trim($_POST["action"]);

    if ($action === "approve" || $action === "reject") {
        $new_status = ($action === "approve") ? "Approved" : "Rejected";
        try {
            // Get reservation details for creating sit-in log
            $get_res = $pdo->prepare("SELECT user_id, purpose, lab_room FROM reservations WHERE id = ?");
            $get_res->execute([$reservation_id]);
            $reservation = $get_res->fetch(PDO::FETCH_ASSOC);

            if ($reservation && $action === "approve") {
                // Create sit-in log entry when approving
                $user_id = $reservation['user_id'];
                $purpose = $reservation['purpose'];
                $lab_room = $reservation['lab_room'];

                try {
                    // Check if student already has active sit-in
                    $check_stmt = $pdo->prepare("SELECT id FROM sit_in_logs WHERE user_id = ? AND time_out IS NULL");
                    $check_stmt->execute([$user_id]);

                    if ($check_stmt->rowCount() > 0) {
                        // User already has an active sit-in session
                        $error_msg = "Cannot approve this reservation. This student already has an active sit-in session. The admin must end the current session first.";
                    } else {
                        // Create new sit-in entry
                        $sitin_stmt = $pdo->prepare("INSERT INTO sit_in_logs (user_id, purpose, lab_room, created_at) VALUES (?, ?, ?, NOW())");
                        $sitin_stmt->execute([$user_id, $purpose, $lab_room]);

                        // Decrement remaining_sessions
                        $decrement_stmt = $pdo->prepare("UPDATE users SET remaining_sessions = remaining_sessions - 1 WHERE id = ? AND remaining_sessions > 0");
                        $decrement_stmt->execute([$user_id]);

                        // Update reservation status
                        $stmt = $pdo->prepare("UPDATE reservations SET status = ? WHERE id = ?");
                        $stmt->execute([$new_status, $reservation_id]);
                        $success_msg = "Reservation has been " . $new_status . "! A sit-in session has been created.";
                    }
                } catch (Exception $e) {
                    $error_msg = "Error creating sit-in session: " . $e->getMessage();
                }
            } else if ($action === "reject") {
                // Update reservation status for rejection
                $stmt = $pdo->prepare("UPDATE reservations SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $reservation_id]);
                $success_msg = "Reservation has been " . $new_status . "!";
            }
        } catch (Exception $e) {
            $error_msg = "Error updating reservation: " . $e->getMessage();
        }
    }
}

$reservations = [];
try {
    $stmt = $pdo->query("SELECT r.*, u.id_number, u.first_name, u.middle_name, u.last_name FROM reservations r JOIN users u ON r.user_id = u.id ORDER BY r.created_at DESC");
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS | Admin - Reservations</title>
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
            font-size: 0.8rem;
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
            background: #f8faf9;
        }

        .badge {
            display: inline-block;
            padding: 0.2rem 0.5rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 700;
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

        .no-data {
            padding: 2rem;
            text-align: center;
            color: var(--text-muted);
        }

        .alert {
            padding: 0.7rem 1rem;
            border-radius: 5px;
            font-size: 0.85rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .alert-success {
            background: #e6f4ea;
            color: #155724;
            border: 1px solid #b7dfbe;
        }

        .alert-error {
            background: #fde8e8;
            color: #a01a1a;
            border: 1px solid #f5b7b7;
        }

        .action-btns {
            display: flex;
            gap: 0.3rem;
        }

        .btn-approve,
        .btn-reject {
            padding: 0.3rem 0.7rem;
            border: none;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-approve {
            background: #e6f4ea;
            color: #155724;
            border: 1px solid #b7dfbe;
        }

        .btn-approve:hover {
            background: #d0f0e1;
            transform: translateY(-1px);
        }

        .btn-reject {
            background: #fde8e8;
            color: #a01a1a;
            border: 1px solid #f5b7b7;
        }

        .btn-reject:hover {
            background: #fcd3d3;
            transform: translateY(-1px);
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
            <div class="card-head">📅 Reservations</div>
            <div class="card-body">
                <?php if ($success_msg): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div><?php endif; ?>
                <?php if ($error_msg): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>

                <?php if (empty($reservations)): ?>
                    <div class="no-data">No reservations found.</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID Number</th>
                                <th>Student Name</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Purpose</th>
                                <th>Lab Room</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservations as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r["id_number"]) ?></td>
                                    <td><?= htmlspecialchars($r["first_name"] . ($r["middle_name"] ? " " . $r["middle_name"] : "") . " " . $r["last_name"]) ?>
                                    </td>
                                    <td><?= htmlspecialchars(date("M d, Y", strtotime($r["date"]))) ?></td>
                                    <td><?= htmlspecialchars($r["time_in"]) ?></td>
                                    <td><?= htmlspecialchars($r["purpose"]) ?></td>
                                    <td><?= htmlspecialchars($r["lab_room"] ?? "N/A") ?></td>
                                    <td><span
                                            class="badge badge-<?= strtolower($r['status']) ?>"><?= htmlspecialchars($r["status"]) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($r["status"] === "Pending"): ?>
                                            <div class="action-btns">
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="reservation_id" value="<?= $r['id'] ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="btn-approve">Approve</button>
                                                </form>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="reservation_id" value="<?= $r['id'] ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" class="btn-reject">Reject</button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-size: 0.7rem;">No actions</span>
                                        <?php endif; ?>
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