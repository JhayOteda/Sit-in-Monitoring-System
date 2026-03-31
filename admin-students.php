<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit;
}
require 'db.php';

// Handle edit form submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_student'])) {
    $student_id = intval($_POST['student_id']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $course = trim($_POST['course']);
    $course_level = trim($_POST['course_level']);

    if (empty($first_name) || empty($last_name) || empty($email) || empty($course) || empty($course_level)) {
        $_SESSION['error'] = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Please enter a valid email address.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, course = ?, course_level = ? WHERE id = ?");
            $stmt->execute([$first_name, $last_name, $email, $course, $course_level, $student_id]);
            $_SESSION['success'] = "Student information updated successfully!";
            header("Location: admin-students.php");
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = "Error updating student: " . $e->getMessage();
        }
    }
}

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $student_id = intval($_GET['id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$student_id]);
        $_SESSION['success'] = "Student deleted successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error deleting student: " . $e->getMessage();
    }
    header("Location: admin-students.php");
    exit;
}

$students = [];
$edit_student = null;

try {
    $stmt = $pdo->query("SELECT id, id_number, first_name, last_name, course, course_level, email FROM users ORDER BY last_name ASC");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}

// Add session count for each student
foreach ($students as &$student) {
    try {
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM sit_in_logs WHERE user_id = ?");
        $count_stmt->execute([$student['id']]);
        $student['session_count'] = $count_stmt->fetchColumn();
    } catch (Exception $e) {
        $student['session_count'] = 0;
    }
}
unset($student); // Important: unset the reference to prevent issues
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS | Admin - Students</title>
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
            letter-spacing: 0.02em;
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
            white-space: nowrap;
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
            margin-left: 0.25rem;
            padding: 0.3rem 0.8rem;
        }

        .nav-links .logout-btn:hover {
            background: var(--brand-2);
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
            letter-spacing: 0.02em;
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
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        table td {
            padding: 0.6rem 0.8rem;
            border-bottom: 1px solid var(--border-soft);
            color: var(--text-primary);
        }

        table tr:hover {
            background: #f8faf9;
        }

        .no-data {
            padding: 2rem;
            text-align: center;
            color: var(--text-muted);
        }

        .alert {
            padding: 0.8rem 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            font-weight: 600;
            font-size: 0.8rem;
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

        .btn {
            padding: 0.4rem 0.8rem;
            border: none;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-edit {
            background: #087FD8;
            color: #fff;
        }

        .btn-edit:hover {
            background: #0567B8;
            transform: translateY(-1px);
        }

        .btn-delete {
            background: #DC143C;
            color: #fff;
        }

        .btn-delete:hover {
            background: #B81030;
            transform: translateY(-1px);
        }

        .action-buttons {
            display: flex;
            gap: 0.4rem;
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
            max-width: 450px;
            max-height: 80vh;
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
            border-bottom: 2px solid var(--border-soft);
            padding-bottom: 1rem;
        }

        .modal-header h2 {
            color: var(--text-primary);
            font-size: 1.3rem;
            margin: 0;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-muted);
            transition: color 0.2s;
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
            margin-bottom: 0.4rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
            transition: all 0.3s ease;
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

        .btn-save {
            background: var(--brand-1);
            color: #fff;
            padding: 0.7rem 1.5rem;
            border: none;
            border-radius: 4px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-save:hover {
            background: var(--brand-2);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(47, 122, 89, 0.3);
        }

        .btn-cancel {
            background: var(--text-muted);
            color: #fff;
            padding: 0.7rem 1.5rem;
            border: none;
            border-radius: 4px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-cancel:hover {
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
            <div class="card-head">👥 Students</div>
            <div class="card-body">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($_SESSION['error']) ?></div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

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
                                <th>Remaining Session</th>
                                <th>Actions</th>
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
                                    <td style="text-align: center; font-weight: 700; color: var(--brand-1);">
                                        <?= (30 - ($student["session_count"] ?? 0)) ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-edit"
                                                onclick="openEditModal(<?= $student['id'] ?>, '<?= htmlspecialchars($student['first_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($student['last_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($student['email'], ENT_QUOTES) ?>', '<?= htmlspecialchars($student['course'], ENT_QUOTES) ?>', '<?= htmlspecialchars($student['course_level'], ENT_QUOTES) ?>')">Edit</button>
                                            <a href="admin-students.php?action=delete&id=<?= $student['id'] ?>"
                                                class="btn btn-delete"
                                                onclick="return confirm('Are you sure you want to delete this student?')">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Student Information</h2>
                <button class="close-btn" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" action="admin-students.php">
                <input type="hidden" name="edit_student" value="1">
                <input type="hidden" name="student_id" id="student_id">

                <div class="form-group">
                    <label class="form-label">First Name</label>
                    <input type="text" class="form-control" id="first_name" name="first_name" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Last Name</label>
                    <input type="text" class="form-control" id="last_name" name="last_name" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Course</label>
                    <select class="form-control" id="course" name="course" required>
                        <option value="">Select Course</option>
                        <option value="BSIT">Bachelor of Science in Information Technology (BSIT)</option>
                        <option value="BSCA">Bachelor of Science in Customs Administration (BSCA)</option>
                        <option value="BSCS">Bachelor of Science in Computer Science (BSCS)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Year Level</label>
                    <select class="form-control" id="course_level" name="course_level" required>
                        <option value="">Select Year Level</option>
                        <option value="1">1st Year</option>
                        <option value="2">2nd Year</option>
                        <option value="3">3rd Year</option>
                        <option value="4">4th Year</option>
                    </select>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn-save">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(id, firstName, lastName, email, course, courseLevel) {
            document.getElementById('student_id').value = id;
            document.getElementById('first_name').value = firstName;
            document.getElementById('last_name').value = lastName;
            document.getElementById('email').value = email;
            document.getElementById('course').value = course;
            document.getElementById('course_level').value = courseLevel;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Close modal when clicking outside of it
        window.onclick = function (event) {
            var modal = document.getElementById('editModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>

</body>

</html>