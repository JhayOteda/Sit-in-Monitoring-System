<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit;
}
require 'db.php';

// Handle Reservation Toggle
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['toggle_reservation'])) {
    $current_val = $_POST['current_reservation_status'];
    $new_status = ($current_val === '1') ? '0' : '1';
    try {
        $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'reservation_enabled'");
        $stmt->execute([$new_status]);
        $_SESSION['sys_success'] = "Reservation system " . ($new_status === '1' ? 'enabled' : 'disabled') . " successfully!";
        header("Location: admin-reservations.php");
        exit;
    } catch (Exception $e) {
        $error_msg = "Could not update reservation status.";
    }
}

// Fetch reservation status
$reservation_enabled = true;
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'reservation_enabled'");
    $stmt->execute();
    $reservation_enabled = ($stmt->fetchColumn() === '1');
} catch (Exception $e) {}

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
            $get_res = $pdo->prepare("SELECT user_id, purpose, lab_room, pc_number FROM reservations WHERE id = ?");
            $get_res->execute([$reservation_id]);
            $reservation = $get_res->fetch(PDO::FETCH_ASSOC);

            if ($reservation && $action === "approve") {
                // Create sit-in log entry when approving
                $user_id = $reservation['user_id'];
                $purpose = $reservation['purpose'];
                $lab_room = $reservation['lab_room'];
                $pc_number = $reservation['pc_number'] ?? null;

                try {
                    // Check if student already has active sit-in
                    $check_stmt = $pdo->prepare("SELECT id FROM sit_in_logs WHERE user_id = ? AND time_out IS NULL");
                    $check_stmt->execute([$user_id]);

                    if ($check_stmt->rowCount() > 0) {
                        // User already has an active sit-in session
                        $error_msg = "Cannot approve this reservation. This student already has an active sit-in session. The admin must end the current session first.";
                    } else {
                        // Check if PC is still available
                        if ($pc_number) {
                            $pc_check = $pdo->prepare("SELECT id FROM sit_in_logs WHERE lab_room = ? AND pc_number = ? AND time_out IS NULL");
                            $pc_check->execute([$lab_room, $pc_number]);
                            if ($pc_check->rowCount() > 0) {
                                $error_msg = "Cannot approve. PC #$pc_number in Lab $lab_room is already occupied.";
                                // Skip the rest
                                goto skip_approve;
                            }
                        }
                        // Create new sit-in entry
                        $sitin_stmt = $pdo->prepare("INSERT INTO sit_in_logs (user_id, purpose, lab_room, pc_number, created_at) VALUES (?, ?, ?, ?, NOW())");
                        $sitin_stmt->execute([$user_id, $purpose, $lab_room, $pc_number]);

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
                skip_approve:
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
    </style>
    <link rel="stylesheet" href="assets/dark-mode.css">
    <link rel="stylesheet" href="assets/responsive.css">
    <script src="assets/dark-mode.js" defer></script>
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

        /* Floating Alert */
        .sys-alert {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--brand-1);
            color: #fff;
            padding: 0.8rem 1.5rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 700;
            z-index: 2000;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            animation: alertSlideDown 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        @keyframes alertSlideDown {
            from { top: -100px; opacity: 0; }
            to { top: 20px; opacity: 1; }
        }

        .system-control-box {
            background: #fff;
            transition: background 0.3s ease;
        }

        html.dark-mode .system-control-box {
            background: #252d33 !important;
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
            <li><a href="logout.php" class="logout-btn">Log out</a></li>
        </ul>
    </nav>
    <?php if (isset($_SESSION['sys_success'])): ?>
        <div class="sys-alert" id="sysAlert">
            <span>✅</span>
            <?= htmlspecialchars($_SESSION['sys_success']) ?>
            <?php unset($_SESSION['sys_success']); ?>
        </div>
        <script>
            setTimeout(() => {
                const alert = document.getElementById('sysAlert');
                if (alert) {
                    alert.style.transition = 'all 0.5s ease';
                    alert.style.top = '-100px';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }
            }, 3000);
        </script>
    <?php endif; ?>

    <div class="admin-wrap">
        <!-- Reservation System Control Card -->
        <div class="card system-control-box" style="margin-bottom: 1.5rem; overflow: hidden;">
            <div class="card-head" style="background: var(--brand-1); color: #fff; padding: 1rem 1.5rem; font-weight: 700;">Reservation System Control</div>
            <div class="card-body" style="padding: 1.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <span style="font-size: 1.05rem; font-weight: 700; color: <?= $reservation_enabled ? 'var(--brand-1)' : '#dc3545' ?>;">
                        Status: <?= $reservation_enabled ? 'Active (Enabled)' : 'Inactive (Disabled)' ?>
                    </span>
                    <p style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0.3rem;">
                        <?= $reservation_enabled ? 'Students are currently allowed to reserve computer lab seats in advance.' : 'Seat reservations are currently disabled. Students cannot create new reservations.' ?>
                    </p>
                </div>
                <form method="POST" style="margin: 0;">
                    <input type="hidden" name="toggle_reservation" value="1">
                    <input type="hidden" name="current_reservation_status" value="<?= $reservation_enabled ? '1' : '0' ?>">
                    <button type="submit" style="
                        padding: 0.7rem 1.5rem; 
                        background: <?= $reservation_enabled ? '#dc3545' : 'var(--brand-1)' ?>; 
                        color: #fff; 
                        border: none; 
                        border-radius: 6px; 
                        font-size: 0.85rem; 
                        font-weight: 700; 
                        cursor: pointer; 
                        transition: all 0.3s ease;
                        box-shadow: 0 4px 12px <?= $reservation_enabled ? 'rgba(220, 53, 69, 0.3)' : 'rgba(47, 122, 89, 0.3)' ?>;
                    ">
                        <?= $reservation_enabled ? 'Disable Reservation System' : 'Enable Reservation System' ?>
                    </button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-head">Reservations</div>
            <div class="card-body">
                <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                    <input type="text" id="searchInput" placeholder="Search by Name or ID Number..." style="flex: 1; padding: 0.6rem; border: 1px solid var(--border-soft); border-radius: 5px; outline: none; font-size: 0.9rem;">
                    <input type="date" id="dateFilter" style="padding: 0.6rem; border: 1px solid var(--border-soft); border-radius: 5px; outline: none; font-size: 0.9rem; color: var(--text-primary);">
                </div>
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
                                <th>PC #</th>
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
                                    <td data-date="<?= htmlspecialchars($r["date"]) ?>"><?= htmlspecialchars(date("M d, Y", strtotime($r["date"]))) ?></td>
                                    <td><?= htmlspecialchars($r["time_in"]) ?></td>
                                    <td><?= htmlspecialchars($r["purpose"]) ?></td>
                                    <td><?= htmlspecialchars($r["lab_room"] ?? "N/A") ?></td>
                                    <td><?= $r["pc_number"] ? 'PC ' . htmlspecialchars($r["pc_number"]) : 'N/A' ?></td>
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
    <script>
        function filterTable() {
            let searchFilter = document.getElementById('searchInput').value.toLowerCase();
            let dateFilter = document.getElementById('dateFilter').value;
            let rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                if(row.cells.length > 1) { 
                    let idCol = row.cells[0].textContent.toLowerCase();
                    let nameCol = row.cells[1].textContent.toLowerCase();
                    let dateCol = row.cells[2].getAttribute('data-date');
                    
                    let matchesSearch = idCol.includes(searchFilter) || nameCol.includes(searchFilter);
                    let matchesDate = dateFilter === '' || dateCol === dateFilter;
                    
                    if (matchesSearch && matchesDate) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
        }

        document.getElementById('searchInput').addEventListener('keyup', filterTable);
        document.getElementById('dateFilter').addEventListener('change', filterTable);
    </script>
</body>

</html>