<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit;
}
require 'db.php';

$search_result = null;
$error = "";

// Handle search
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id_number = trim($_POST["id_number"] ?? "");

    if (empty($id_number)) {
        $error = "Please enter an ID number.";
    } else {
        try {
            // Get user info - simple direct search
            $stmt = $pdo->prepare("SELECT id, id_number, first_name, middle_name, last_name FROM users WHERE id_number = ?");
            $stmt->execute([$id_number]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $error = "No student found with ID number: " . htmlspecialchars($id_number);
            } else {
                // Get sit-in logs for this user
                try {
                    $logs_stmt = $pdo->prepare("SELECT * FROM sit_in_logs WHERE user_id = ? ORDER BY created_at DESC");
                    $logs_stmt->execute([$user['id']]);
                    $logs = $logs_stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $log_e) {
                    // Table doesn't exist yet
                    $logs = [];
                }

                // Calculate remaining sessions (30 - total logs)
                $sessions_remaining = 30 - count($logs);
                if ($sessions_remaining < 0)
                    $sessions_remaining = 0;

                $search_result = [
                    'user' => $user,
                    'logs' => $logs,
                    'total_sessions' => count($logs),
                    'sessions_remaining' => $sessions_remaining
                ];
            }
        } catch (Exception $e) {
            $error = "Error searching: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS | Admin - Search</title>
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

        .search-form {
            display: flex;
            gap: 0.8rem;
            margin-bottom: 1.5rem;
            align-items: flex-end;
        }

        .search-form input {
            flex: 1;
            padding: 0.6rem 0.8rem;
            border: 1px solid var(--border-soft);
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .search-form button {
            background: var(--brand-1);
            color: #fff;
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 4px;
            font-weight: 700;
            cursor: pointer;
        }

        .search-form button:hover {
            background: var(--brand-2);
        }

        .alert {
            padding: 0.8rem 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }

        .alert-error {
            background: #fde8e8;
            color: #a01a1a;
            border: 1px solid #f5858e;
        }

        .alert-success {
            background: #e6f4ea;
            color: #155724;
            border: 1px solid #34a853;
        }

        .result-card {
            background: #f8faf9;
            padding: 1.2rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--brand-1);
        }

        .result-header {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.8rem;
        }

        .result-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.2rem;
        }

        .info-item {}

        .info-label {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 0.3rem;
        }

        .info-value {
            font-size: 0.95rem;
            color: var(--text-primary);
            font-weight: 600;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
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
            background: #ffffff;
        }

        .select-dropdown {
            padding: 0.4rem 0.6rem;
            border: 1px solid var(--border-soft);
            border-radius: 3px;
            font-size: 0.8rem;
            cursor: pointer;
        }

        .badge {
            display: inline-block;
            padding: 0.2rem 0.5rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 700;
        }

        .badge-active {
            background: #e6f4ea;
            color: #155724;
        }

        .badge-completed {
            background: #f3e5f5;
            color: #6a1b9a;
        }

        .no-logs {
            color: var(--text-muted);
            text-align: center;
            padding: 1rem;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .modal-content {
            background-color: var(--card-bg);
            margin: 5% auto;
            padding: 2rem;
            border-radius: 8px;
            width: 90%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-30px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-header h2 {
            font-size: 1.2rem;
            color: var(--text-primary);
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-muted);
        }

        .close-btn:hover {
            color: var(--text-primary);
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
            text-transform: uppercase;
        }

        .form-control {
            width: 100%;
            padding: 0.7rem 0.8rem;
            border: 1.5px solid var(--border-soft);
            border-radius: 4px;
            font-size: 0.85rem;
            font-family: inherit;
            color: var(--text-primary);
            background: var(--input-bg);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--brand-1);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(47, 122, 89, 0.12);
        }

        .modal-footer {
            display: flex;
            gap: 0.8rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-soft);
        }

        .btn-submit {
            background: var(--brand-1);
            color: #fff;
            padding: 0.7rem 1.5rem;
            border: none;
            border-radius: 4px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            background: var(--brand-2);
            transform: translateY(-1px);
        }

        .btn-close {
            background: var(--text-muted);
            color: #fff;
            padding: 0.7rem 1.5rem;
            border: none;
            border-radius: 4px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-close:hover {
            background: #556063;
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
            <div class="card-head">🔍 Student Search</div>
            <div class="card-body">
                <!-- Search Form -->
                <form method="POST" class="search-form">
                    <input type="text" name="id_number" placeholder="Enter Student ID Number" required>
                    <button type="submit">Search</button>
                </form>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if ($search_result): ?>
                    <!-- Auto-open modal with search results -->
                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            openSearchModal(<?= $search_result['user']['id'] ?>, '<?= htmlspecialchars($search_result['user']['id_number'], ENT_QUOTES) ?>', '<?= htmlspecialchars($search_result['user']['first_name'] . ($search_result['user']['middle_name'] ? ' ' . $search_result['user']['middle_name'] : '') . ' ' . $search_result['user']['last_name'], ENT_QUOTES) ?>', <?= $search_result['total_sessions'] ?>, <?= $search_result['sessions_remaining'] ?>, JSON.parse('<?= json_encode($search_result['logs']) ?>'));
                        });
                    </script>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Search Result Modal -->
    <div id="sitInModal" class="modal">
        <div class="modal-content" style="max-width: 1000px;">
            <div class="modal-header">
                <h2>👤 Student Information</h2>
                <button type="button" class="close-btn" onclick="closeSitInModal()">&times;</button>
            </div>

            <!-- Student Info Section -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
                <div>
                    <label class="form-label">ID Number</label>
                    <div style="font-size: 0.95rem; color: var(--text-primary); font-weight: 600;"
                        id="modalIdNumberDisplay"></div>
                </div>
                <div>
                    <label class="form-label">Student Name</label>
                    <div style="font-size: 0.95rem; color: var(--text-primary); font-weight: 600;"
                        id="modalStudentNameDisplay"></div>
                </div>
                <div>
                    <label class="form-label">Total Sessions</label>
                    <div style="font-size: 0.95rem; color: var(--text-primary); font-weight: 600;"
                        id="modalTotalSessions"></div>
                </div>
                <div>
                    <label class="form-label">Remaining Sessions</label>
                    <div style="font-size: 0.95rem; color: var(--brand-1); font-weight: 600; font-size: 1.2rem;"
                        id="modalRemainingSessions">0</div>
                </div>
            </div>

            <!-- Sit-In History Section -->
            <div style="margin-bottom: 2rem;">
                <h3 style="font-size: 0.95rem; margin-bottom: 1rem; color: var(--text-primary); font-weight: 700;">📍
                    Sit-In History</h3>
                <div id="sitInHistoryContainer"></div>
            </div>

            <!-- New Sit-In Form -->
            <div style="border-top: 1px solid var(--border-soft); padding-top: 1.5rem;">
                <h3 style="font-size: 0.95rem; margin-bottom: 1rem; color: var(--text-primary); font-weight: 700;">Add
                    New Sit-In</h3>
                <form method="POST" action="admin-sitin.php"
                    style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Purpose</label>
                        <select class="form-control" name="purpose" required>
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

                    <div class="form-group">
                        <label class="form-label">Lab</label>
                        <select class="form-control" name="lab_room" required>
                            <option value="" disabled selected>Select Laboratory</option>
                            <option value="524">524</option>
                            <option value="544">544</option>
                            <option value="526">526</option>
                            <option value="530">530</option>
                            <option value="528">528</option>
                        </select>
                    </div>

                    <input type="hidden" name="user_id" id="modalUserId">

                    <div
                        style="grid-column: 1 / -1; display: flex; gap: 0.8rem; justify-content: flex-end; margin-top: 1rem;">
                        <button type="button" class="btn-close" onclick="closeSitInModal()">Close</button>
                        <button type="submit" class="btn-submit">Sit In</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openSearchModal(userId, idNumber, studentName, totalSessions, sessionsRemaining, logs) {
            document.getElementById('modalUserId').value = userId;
            document.getElementById('modalIdNumberDisplay').textContent = idNumber;
            document.getElementById('modalStudentNameDisplay').textContent = studentName;
            document.getElementById('modalTotalSessions').textContent = totalSessions;
            document.getElementById('modalRemainingSessions').textContent = sessionsRemaining;

            // Build sit-in history table
            if (logs && logs.length > 0) {
                let html = '<table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">';
                html += '<thead><tr style="background: var(--border-soft);"><th style="padding: 0.6rem; text-align: left; font-weight: 700;">Lab Room</th><th style="padding: 0.6rem; text-align: left; font-weight: 700;">Purpose</th><th style="padding: 0.6rem; text-align: left; font-weight: 700;">Check-In</th><th style="padding: 0.6rem; text-align: left; font-weight: 700;">Check-Out</th><th style="padding: 0.6rem; text-align: left; font-weight: 700;">Duration</th></tr></thead>';
                html += '<tbody>';

                logs.forEach(log => {
                    const checkIn = new Date(log.created_at).toLocaleString();
                    const checkOut = log.time_out ? new Date(log.time_out).toLocaleString() : '<span style="color: var(--brand-1); font-weight: 700;">Active</span>';
                    html += '<tr style="border-bottom: 1px solid var(--border-soft);">';
                    html += '<td style="padding: 0.6rem; color: var(--text-primary);">' + log.lab_room + '</td>';
                    html += '<td style="padding: 0.6rem; color: var(--text-primary);">' + log.purpose + '</td>';
                    html += '<td style="padding: 0.6rem; color: var(--text-primary);">' + checkIn + '</td>';
                    html += '<td style="padding: 0.6rem; color: var(--text-primary);">' + checkOut + '</td>';
                    html += '<td style="padding: 0.6rem; color: var(--text-primary);">—</td>';
                    html += '</tr>';
                });

                html += '</tbody></table>';
                document.getElementById('sitInHistoryContainer').innerHTML = html;
            } else {
                document.getElementById('sitInHistoryContainer').innerHTML = '<p style="color: var(--text-muted); text-align: center; padding: 1rem;">No sit-in records found for this student.</p>';
            }

            document.getElementById('sitInModal').style.display = 'block';
        }

        function openSitInModal(userId, idNumber, studentName) {
            document.getElementById('modalUserId').value = userId;
            document.getElementById('modalIdNumberDisplay').value = idNumber;
            document.getElementById('modalStudentNameDisplay').value = studentName;
            document.getElementById('sitInModal').style.display = 'block';
        }

        function closeSitInModal() {
            document.getElementById('sitInModal').style.display = 'none';
        }

        window.onclick = function (event) {
            const modal = document.getElementById('sitInModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>

</body>

</html>