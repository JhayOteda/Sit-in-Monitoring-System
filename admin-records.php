<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit;
}
require 'db.php';

$success_message = "";

// Handle delete record
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_record_id'])) {
    $record_id = $_POST['delete_record_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM sit_in_logs WHERE id = ?");
        $stmt->execute([$record_id]);
        $success_message = "Record deleted successfully!";
    } catch (Exception $e) {
        $success_message = "Error deleting record.";
    }
}

// Handle delete all records
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_all'])) {
    try {
        $pdo->query("DELETE FROM sit_in_logs");
        $success_message = "All records deleted successfully!";
    } catch (Exception $e) {
        $success_message = "Error deleting all records.";
    }
}

// Fetch completed sit-in records with student information
$records = [];
try {
    $stmt = $pdo->query("
        SELECT 
            sl.id,
            sl.user_id,
            sl.purpose,
            sl.lab_room,
            sl.created_at,
            sl.time_out,
            u.id_number,
            u.first_name,
            u.middle_name,
            u.last_name
        FROM sit_in_logs sl
        JOIN users u ON sl.user_id = u.id
        WHERE sl.time_out IS NOT NULL
        ORDER BY sl.created_at DESC
    ");
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS | Admin - Records</title>
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

        .no-data {
            color: var(--text-muted);
            text-align: center;
            padding: 2rem;
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

        .search-delete-container {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .search-field {
            flex: 1;
            min-width: 250px;
        }

        .search-field label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 0.4rem;
            letter-spacing: 0.5px;
        }

        .search-field input {
            width: 100%;
            padding: 0.6rem 0.8rem;
            border: 2px solid var(--border-soft);
            border-radius: 6px;
            font-size: 0.85rem;
            font-family: inherit;
            color: var(--text-primary);
            outline: none;
            background: var(--input-bg);
            transition: all 0.3s ease;
        }

        .search-field input:focus {
            border-color: var(--brand-1);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(47, 122, 89, 0.12);
        }

        .btn-delete-all {
            padding: 0.6rem 1.2rem;
            background: #dc3545;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-delete-all:hover {
            background: #c82333;
            transform: translateY(-1px);
        }

        .btn-delete-row {
            padding: 0.35rem 0.7rem;
            background: #dc3545;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .btn-delete-row:hover {
            background: #c82333;
        }

        .no-records-message {
            text-align: center;
            color: var(--text-muted);
            font-style: italic;
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
            <div class="card-head">📋 Sit-In Records</div>
            <div class="card-body">
                <?php if ($success_message): ?>
                    <div class="alert-success"><?= htmlspecialchars($success_message) ?></div>
                <?php endif; ?>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <!-- Search and Delete All Section -->
                <div class="search-delete-container">
                    <div class="search-field">
                        <label for="searchInput">Search ID Number or Name</label>
                        <input type="text" id="searchInput" placeholder="Enter ID number or student name...">
                    </div>
                    <?php if (!empty($records)): ?>
                        <form method="POST" style="display: inline;"
                            onsubmit="return confirm('Are you sure you want to delete ALL sit-in records? This cannot be undone.');">
                            <input type="hidden" name="delete_all" value="1">
                            <button type="submit" class="btn-delete-all">Delete All History</button>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if (empty($records)): ?>
                    <div class="no-data">No sit-in records found yet.</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID Number</th>
                                <th>Student Name</th>
                                <th>Purpose</th>
                                <th>Lab Room</th>
                                <th>Check-In Time</th>
                                <th>Check-Out Time</th>
                                <th>Duration</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="recordsTable">
                            <?php foreach ($records as $record): ?>
                                <tr class="record-row" data-id-number="<?= htmlspecialchars($record['id_number']) ?>"
                                    data-student-name="<?= htmlspecialchars($record['first_name'] . ($record['middle_name'] ? ' ' . $record['middle_name'] : '') . ' ' . $record['last_name']) ?>">
                                    <td><?= htmlspecialchars($record['id_number']) ?></td>
                                    <td><?= htmlspecialchars($record['first_name'] . ($record['middle_name'] ? ' ' . $record['middle_name'] : '') . ' ' . $record['last_name']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($record['purpose']) ?></td>
                                    <td><?= htmlspecialchars($record['lab_room']) ?></td>
                                    <td><?= date('M d, Y H:i', strtotime($record['created_at'])) ?></td>
                                    <td>
                                        <?php
                                        if ($record['time_out']) {
                                            echo date('M d, Y H:i', strtotime($record['time_out']));
                                        } else {
                                            echo '<span style="color: var(--brand-1); font-weight: 700;">Active</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        if ($record['time_out']) {
                                            $start = new DateTime($record['created_at']);
                                            $end = new DateTime($record['time_out']);
                                            $diff = $start->diff($end);
                                            echo $diff->format('%hh %im');
                                        } else {
                                            echo '—';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;"
                                            onsubmit="return confirm('Delete this record? This action cannot be undone.');">
                                            <input type="hidden" name="delete_record_id" value="<?= $record['id'] ?>">
                                            <button type="submit" class="btn-delete-row">Delete</button>
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

    <script>
        // Search functionality
        const searchInput = document.getElementById('searchInput');
        const recordsTable = document.getElementById('recordsTable');
        const recordRows = recordsTable ? recordsTable.querySelectorAll('.record-row') : [];

        if (searchInput) {
            searchInput.addEventListener('keyup', function () {
                const searchTerm = this.value.toLowerCase().trim();

                recordRows.forEach(row => {
                    const idNumber = row.getAttribute('data-id-number').toLowerCase();
                    const studentName = row.getAttribute('data-student-name').toLowerCase();

                    if (idNumber.includes(searchTerm) || studentName.includes(searchTerm) || searchTerm === '') {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });

                // Check if any rows are visible
                const visibleRows = Array.from(recordRows).some(row => row.style.display !== 'none');
                if (!visibleRows && searchTerm !== '') {
                    if (!document.querySelector('.no-records-message')) {
                        const message = document.createElement('tr');
                        message.className = 'no-records-message';
                        message.innerHTML = '<td colspan="8" style="text-align: center; color: var(--text-muted); padding: 2rem;">No records found matching your search.</td>';
                        recordsTable.appendChild(message);
                    }
                } else {
                    const message = document.querySelector('.no-records-message');
                    if (message) {
                        message.remove();
                    }
                }
            });
        }
    </script>
</body>

</html>