<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit;
}
require 'db.php';

$students = [];
try {
    $stmt = $pdo->query("SELECT id, id_number, first_name, last_name, course, course_level, email FROM users WHERE role != 'admin' ORDER BY last_name ASC");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS | Admin - Students</title>
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@700&family=Nunito+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

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

        body { font-family: 'Nunito Sans', sans-serif; background: linear-gradient(135deg, var(--bg-start) 0%, var(--bg-end) 100%); min-height: 100vh; }
        
        nav { background: var(--nav-bg); display: flex; align-items: center; justify-content: space-between; padding: 0 1.5rem; height: 48px; box-shadow: 0 2px 8px rgba(0,0,0,0.25); }
        .nav-brand { color: var(--nav-text); font-size: 0.85rem; font-weight: 600; font-family: 'Merriweather', serif; letter-spacing: 0.02em; }
        .nav-links { display: flex; align-items: center; list-style: none; gap: 0.1rem; }
        .nav-links a { color: var(--nav-text); text-decoration: none; font-size: 0.75rem; padding: 0.3rem 0.6rem; border-radius: 4px; white-space: nowrap; display: block; transition: background 0.15s; }
        .nav-links a:hover { background: rgba(255,255,255,0.14); }
        .nav-links .logout-btn { background: var(--brand-1); border-radius: 4px; font-weight: 700; margin-left: 0.25rem; padding: 0.3rem 0.8rem; }
        .nav-links .logout-btn:hover { background: var(--brand-2); }

        .admin-wrap { padding: 1.5rem; max-width: 1400px; margin: 0 auto; }
        
        .card { background: var(--card-bg); border-radius: 6px; box-shadow: 0 1px 5px rgba(0,0,0,0.1); overflow: hidden; }
        .card-head { background: var(--brand-1); color: #fff; font-size: 0.9rem; font-weight: 700; padding: 0.6rem 1rem; letter-spacing: 0.02em; }
        .card-body { padding: 1.5rem; }

        table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
        table th { background: var(--border-soft); padding: 0.6rem 0.8rem; text-align: left; font-weight: 700; color: var(--text-primary); text-transform: uppercase; letter-spacing: 0.03em; }
        table td { padding: 0.6rem 0.8rem; border-bottom: 1px solid var(--border-soft); color: var(--text-primary); }
        table tr:hover { background: #f8faf9; }
        .no-data { padding: 2rem; text-align: center; color: var(--text-muted); }
    </style>
</head>
<body>

<nav>
    <span class="nav-brand">College of Computer Studies Admin</span>
    <ul class="nav-links">
        <li><a href="admin.php">Home</a></li>
        <li><a href="admin-search.php">Search</a></li>
        <li><a href="admin-students.php">Students</a></li>
        <li><a href="admin-sitin.php">SH-in</a></li>
        <li><a href="admin-records.php">View Sit-In Records</a></li>
        <li><a href="admin-reports.php">Sit-In Reports</a></li>
        <li><a href="admin-feedback.php">Feedback Reports</a></li>
        <li><a href="admin-reservations.php">Reservation</a></li>
        <li><a href="logout.php" class="logout-btn">Log out</a></li>
    </ul>
</nav>

<div class="admin-wrap">
    <div class="card">
        <div class="card-head">👥 Students</div>
        <div class="card-body">
            <?php if (empty($students)): ?>
                <div class="no-data">No students registered yet.</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID Number</th>
                            <th>Name</th>
                            <th>Course</th>
                            <th>Level</th>
                            <th>Email</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?= htmlspecialchars($student["id_number"]) ?></td>
                                <td><?= htmlspecialchars($student["first_name"] . " " . $student["last_name"]) ?></td>
                                <td><?= htmlspecialchars($student["course"]) ?></td>
                                <td><?= htmlspecialchars($student["course_level"]) ?></td>
                                <td><?= htmlspecialchars($student["email"]) ?></td>
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
