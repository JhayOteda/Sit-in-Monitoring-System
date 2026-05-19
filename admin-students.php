<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit;
}
require 'db.php';

// Handle add student form submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_student'])) {
    $id_number = trim($_POST["id_number"]);
    $last_name = trim($_POST["last_name"]);
    $first_name = trim($_POST["first_name"]);
    $middle_name = trim($_POST["middle_name"]);
    $course = trim($_POST["course"]);
    $course_level = trim($_POST["course_level"]);
    $password = trim($_POST["password"]);
    $repeat_pass = trim($_POST["repeat_password"]);
    $email = trim($_POST["email"]);
    $address = trim($_POST["address"]);

    if (empty($id_number) || empty($last_name) || empty($first_name) || empty($course) || empty($course_level) || empty($password) || empty($repeat_pass) || empty($email)) {
        $_SESSION['error'] = "Please fill in all required fields.";
    } elseif ($password !== $repeat_pass) {
        $_SESSION['error'] = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $_SESSION['error'] = "Password must be at least 6 characters.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Please enter a valid email address.";
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE id_number = ? OR email = ?");
        $check->execute([$id_number, $email]);

        if ($check->rowCount() > 0) {
            $_SESSION['error'] = "ID Number or Email is already registered.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare("INSERT INTO users (id_number, last_name, first_name, middle_name, course_level, password, email, course, address, points) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
                $stmt->execute([$id_number, $last_name, $first_name, $middle_name, $course_level, $hashed, $email, $course, $address]);
                $_SESSION['success'] = "Student added successfully!";
                header("Location: admin-students.php");
                exit;
            } catch (PDOException $e) {
                $_SESSION['error'] = "Error adding student: " . $e->getMessage();
            }
        }
    }
}

// Handle edit form submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_student'])) {
    $student_id = intval($_POST['student_id']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $middle_name = trim($_POST['middle_name']);
    $email = trim($_POST['email']);
    $course = trim($_POST['course']);
    $course_level = trim($_POST['course_level']);
    $address = trim($_POST['address']);

    if (empty($first_name) || empty($last_name) || empty($email) || empty($course) || empty($course_level)) {
        $_SESSION['error'] = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Please enter a valid email address.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, middle_name = ?, email = ?, course = ?, course_level = ?, address = ? WHERE id = ?");
            $stmt->execute([$first_name, $last_name, $middle_name, $email, $course, $course_level, $address, $student_id]);
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
        // Delete related records first (to avoid foreign key constraint violation)
        // Delete from feedback
        $stmt = $pdo->prepare("DELETE FROM feedback WHERE user_id = ?");
        $stmt->execute([$student_id]);

        // Delete from announcement_reads
        $stmt = $pdo->prepare("DELETE FROM announcement_reads WHERE user_id = ?");
        $stmt->execute([$student_id]);

        // Delete from sit_in_logs
        $stmt = $pdo->prepare("DELETE FROM sit_in_logs WHERE user_id = ?");
        $stmt->execute([$student_id]);

        // Delete from reservations
        $stmt = $pdo->prepare("DELETE FROM reservations WHERE user_id = ?");
        $stmt->execute([$student_id]);

        // Now delete the student
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$student_id]);
        $_SESSION['success'] = "Student deleted successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error deleting student: " . $e->getMessage();
    }
    header("Location: admin-students.php");
    exit;
}

// Handle reset all sessions action
if (isset($_GET['action']) && $_GET['action'] === 'reset_all_sessions') {
    try {
        $stmt = $pdo->prepare("UPDATE users SET remaining_sessions = 30");
        $stmt->execute();
        $_SESSION['success'] = "All sessions have been reset successfully! All students now have 30 sessions remaining.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error resetting sessions: " . $e->getMessage();
    }
    header("Location: admin-students.php");
    exit;
}

// Handle award points submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['award_points_action'])) {
    $student_id = intval($_POST['student_id'] ?? 0);
    $points_amount = intval($_POST['points_amount'] ?? 0);
    $reason = trim($_POST['points_reason'] ?? "");
    $log_as_task = isset($_POST['log_as_task']) ? 1 : 0;

    if ($student_id > 0 && $points_amount !== 0) {
        try {
            $pdo->beginTransaction();

            // Update user points
            $stmt = $pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?");
            $stmt->execute([$points_amount, $student_id]);

            // Recalculate and update the student's total leaderboard score in the database
            $score_stmt = $pdo->prepare("
                UPDATE users u 
                SET score = COALESCE(u.points, 0) * 0.50 + 
                            COALESCE((SELECT SUM(TIMESTAMPDIFF(SECOND, created_at, time_out)) / 3600.0 FROM sit_in_logs WHERE user_id = u.id AND time_out IS NOT NULL), 0) * 0.30 + 
                            COALESCE((SELECT COUNT(*) FROM sit_in_logs WHERE user_id = u.id AND time_out IS NOT NULL), 0) * 0.20
                WHERE u.id = ?
            ");
            $score_stmt->execute([$student_id]);

            // Optionally log as a completed task if reason is provided
            if ($log_as_task && !empty($reason)) {
                $task_desc = "Admin Reward: " . $reason . " (" . ($points_amount > 0 ? "+" : "") . $points_amount . " pts)";
                $stmt_task = $pdo->prepare("INSERT INTO student_tasks (user_id, task_desc) VALUES (?, ?)");
                $stmt_task->execute([$student_id, $task_desc]);
            }

            $pdo->commit();
            $_SESSION['success'] = "Successfully updated student points! " . ($points_amount > 0 ? "+" : "") . $points_amount . " pts rewarded.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Error updating points: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Invalid points amount.";
    }
    header("Location: admin-students.php");
    exit;
}

$students = [];
$edit_student = null;

try {
    $stmt = $pdo->query("SELECT id, id_number, first_name, last_name, middle_name, course, course_level, email, address, points FROM users ORDER BY last_name ASC");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}

// Update session count for each student (remaining sessions from remaining_sessions column)
foreach ($students as &$student) {
    try {
        $count_stmt = $pdo->prepare("SELECT remaining_sessions FROM users WHERE id = ?");
        $count_stmt->execute([$student['id']]);
        $result = $count_stmt->fetch(PDO::FETCH_ASSOC);
        $student['remaining_sessions'] = $result ? $result['remaining_sessions'] : 30;
    } catch (Exception $e) {
        $student['remaining_sessions'] = 30;
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
            <style>
        /* Profile Dropdown Styles */
        .profile-dropdown-container {
            position: relative;
            margin-left: 0.5rem;
        }
        
        .profile-trigger {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            padding: 0.25rem 0.6rem;
            border-radius: 50px;
            background: rgba(255, 255, 255, 0.1);
            transition: all 0.2s ease;
            user-select: none;
        }
        
        .profile-trigger:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .profile-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--brand-1, #2f7a59);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
            border: 2px solid rgba(255,255,255,0.8);
            text-transform: uppercase;
        }
        
        .profile-info {
            display: flex;
            flex-direction: column;
            line-height: 1.1;
        }
        
        .profile-name {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--nav-text, #fff);
        }
        
        .profile-role {
            font-size: 0.65rem;
            color: rgba(255, 255, 255, 0.8);
            text-transform: capitalize;
        }
        
        .profile-caret {
            margin-left: 0.2rem;
            color: var(--nav-text, #fff);
            transition: transform 0.2s;
        }
        
        .profile-dropdown-container.active .profile-caret {
            transform: rotate(180deg);
        }
        
        .profile-menu {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            background: var(--card-bg, #fff);
            border-radius: 12px;
            min-width: 220px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            padding: 0.5rem;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            z-index: 1000;
        }
        
        html.dark-mode .profile-menu {
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .profile-dropdown-container.active .profile-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .profile-menu-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.6rem 0.8rem;
            color: var(--text-primary, #333) !important;
            text-decoration: none !important;
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: 8px;
            transition: background 0.15s;
            cursor: pointer;
            background: transparent !important;
            box-sizing: border-box;
            width: 100%;
        }
        
        .profile-menu-item:hover {
            background: var(--input-bg, #f4f4f4) !important;
        }
        
        .profile-menu-item svg {
            width: 18px;
            height: 18px;
            color: var(--text-muted, #666);
        }
        
        .profile-menu-divider {
            height: 1px;
            background: var(--border-soft, #eee);
            margin: 0.4rem 0;
        }
        
        .text-danger {
            color: #dc3545 !important;
        }
        
        .text-danger svg {
            color: #dc3545 !important;
        }
        
        .theme-item {
            justify-content: space-between;
        }
        
        .theme-label-wrap {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
    </style>
    <link rel="stylesheet" href="assets/dark-mode.css?v=1779200619">
    <link rel="stylesheet" href="assets/responsive.css">
    <script src="assets/dark-mode.js?v=1779200619" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
            backdrop-filter: blur(4px);
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
            background: var(--input-bg);
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

        .card-header-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }

        .card-header-title {
            flex: 1;
        }

        .card-header-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-add {
            background: #28a745;
            color: #fff;
            padding: 0.5rem 1rem;
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

        .btn-add:hover {
            background: #218838;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
        }

        .btn-reset {
            background: #ff9800;
            color: #fff;
            padding: 0.5rem 1rem;
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

        .btn-reset:hover {
            background: #e68900;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(255, 152, 0, 0.3);
        }
            <style>
        /* Profile Dropdown Styles */
        .profile-dropdown-container {
            position: relative;
            margin-left: 0.5rem;
        }
        
        .profile-trigger {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            padding: 0.25rem 0.6rem;
            border-radius: 50px;
            background: rgba(255, 255, 255, 0.1);
            transition: all 0.2s ease;
            user-select: none;
        }
        
        .profile-trigger:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .profile-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--brand-1, #2f7a59);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
            border: 2px solid rgba(255,255,255,0.8);
            text-transform: uppercase;
        }
        
        .profile-info {
            display: flex;
            flex-direction: column;
            line-height: 1.1;
        }
        
        .profile-name {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--nav-text, #fff);
        }
        
        .profile-role {
            font-size: 0.65rem;
            color: rgba(255, 255, 255, 0.8);
            text-transform: capitalize;
        }
        
        .profile-caret {
            margin-left: 0.2rem;
            color: var(--nav-text, #fff);
            transition: transform 0.2s;
        }
        
        .profile-dropdown-container.active .profile-caret {
            transform: rotate(180deg);
        }
        
        .profile-menu {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            background: var(--card-bg, #fff);
            border-radius: 12px;
            min-width: 220px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            padding: 0.5rem;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            z-index: 1000;
        }
        
        html.dark-mode .profile-menu {
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .profile-dropdown-container.active .profile-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .profile-menu-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.6rem 0.8rem;
            color: var(--text-primary, #333) !important;
            text-decoration: none !important;
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: 8px;
            transition: background 0.15s;
            cursor: pointer;
            background: transparent !important;
            box-sizing: border-box;
            width: 100%;
        }
        
        .profile-menu-item:hover {
            background: var(--input-bg, #f4f4f4) !important;
        }
        
        .profile-menu-item svg {
            width: 18px;
            height: 18px;
            color: var(--text-muted, #666);
        }
        
        .profile-menu-divider {
            height: 1px;
            background: var(--border-soft, #eee);
            margin: 0.4rem 0;
        }
        
        .text-danger {
            color: #dc3545 !important;
        }
        
        .text-danger svg {
            color: #dc3545 !important;
        }
        
        .theme-item {
            justify-content: space-between;
        }
        
        .theme-label-wrap {
            display: flex;
            align-items: center;
            gap: 0.75rem;
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
                        <li class="profile-dropdown-container" id="profileDropdownContainer">
                <div class="profile-trigger" onclick="toggleProfileDropdown(event)">
                    <div class="profile-avatar">
                        <?= strtoupper(substr($_SESSION['name'] ?? 'U', 0, 1)) ?>
                    </div>
                    <div class="profile-info">
                        <span class="profile-name"><?= htmlspecialchars($_SESSION['name'] ?? 'User') ?></span>
                        <span class="profile-role"><?= htmlspecialchars(ucfirst($_SESSION['role'] ?? 'Student')) ?></span>
                    </div>
                    <svg class="profile-caret" viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </div>
                <div class="profile-menu" id="profileMenu">
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <!-- Admin Profile (Optional, can point to settings if exists) -->
                    <?php else: ?>
                        <a href="dashboard.php?edit=true" class="profile-menu-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg> 
                            Edit Profile
                        </a>
                        <div class="profile-menu-divider"></div>
                    <?php endif; ?>
                    
                    <div class="profile-menu-item theme-item">
                        <div id="darkModeContainer" style="display:flex; justify-content:center; width:100%;"></div>
                    </div>
                    <div class="profile-menu-divider"></div>
                    <a href="logout.php" class="profile-menu-item text-danger">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg> 
                        Log out
                    </a>
                </div>
            </li>
        </ul>
    </nav>

    <div class="admin-wrap">
        <div class="card">
            <div class="card-head">
                <div class="card-header-wrapper">
                    <div class="card-header-title">Students</div>
                    <div class="card-header-buttons">
                        <button class="btn-add" onclick="openAddModal()">+ Add Student</button>
                        <a href="admin-students.php?action=reset_all_sessions" class="btn-reset"
                            onclick="return confirm('Are you sure you want to reset all student sessions to 30? This action cannot be undone.')">Reset
                            All Sessions</a>
                    </div>
                </div>
            </div>
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
                                    <td><?= htmlspecialchars($student["first_name"] . ($student["middle_name"] ? " " . $student["middle_name"] : "") . " " . $student["last_name"]) ?>
                                    </td>
                                    <td><?= htmlspecialchars($student["course"]) ?></td>
                                    <td><?= htmlspecialchars($student["course_level"]) ?></td>
                                    <td><?= htmlspecialchars($student["email"]) ?></td>

                                    <td style="text-align: center; font-weight: 700; color: var(--brand-1);">
                                        <?= ($student["remaining_sessions"] ?? 30) ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">

                                            <button class="btn btn-edit"
                                                onclick="openEditModal(<?= $student['id'] ?>, '<?= htmlspecialchars($student['first_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($student['last_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($student['middle_name'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($student['email'], ENT_QUOTES) ?>', '<?= htmlspecialchars($student['course'], ENT_QUOTES) ?>', '<?= htmlspecialchars($student['course_level'], ENT_QUOTES) ?>', '<?= htmlspecialchars($student['address'] ?? '', ENT_QUOTES) ?>')">Edit</button>
                                            <button type="button" class="btn btn-delete" onclick="confirmDeleteStudent(<?= $student['id'] ?>)">Delete</button>
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
                    <label class="form-label">Middle Name</label>
                    <input type="text" class="form-control" id="middle_name" name="middle_name">
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

                <div class="form-group">
                    <label class="form-label">Address</label>
                    <input type="text" class="form-control" id="address" name="address">
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn-save">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Award/Manage Points Modal -->
    <div id="pointsModal" class="modal">
        <div class="modal-content" style="max-width: 480px;">
            <div class="modal-header" style="border-bottom-color: #e6b800;">
                <h2>🏆 Award Points to Student</h2>
                <button class="close-btn" onclick="closePointsModal()">&times;</button>
            </div>
            <form method="POST" action="admin-students.php">
                <input type="hidden" name="award_points_action" value="1">
                <input type="hidden" name="student_id" id="points_student_id">

                <div class="form-group" style="margin-bottom: 1.5rem; text-align: center; background: rgba(230, 184, 0, 0.05); padding: 1rem; border-radius: 8px; border: 1px solid rgba(230, 184, 0, 0.15);">
                    <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.25rem;">Student Name</div>
                    <div id="points_student_name" style="font-size: 1.15rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.5rem;">Student Name</div>
                    <div style="font-size: 0.8rem; color: var(--text-muted);">Current Points: <strong id="points_current_val" style="color: #cda215;">0 pts</strong></div>
                </div>

                <div class="form-group">
                    <label class="form-label" style="color: #cda215;">Points Amount</label>
                    <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 0.5rem;">
                        <input type="number" class="form-control" id="points_amount" name="points_amount" placeholder="e.g. 50 or -20" required style="font-size: 1.1rem; font-weight: 700; text-align: center;">
                    </div>
                    <!-- Quick select buttons -->
                    <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 0.5rem;">
                        <button type="button" class="btn" style="background: rgba(230, 184, 0, 0.1); color: #cda215; border: 1px solid rgba(230, 184, 0, 0.2); padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; cursor: pointer;" onclick="setQuickPoints(10)">+10</button>
                        <button type="button" class="btn" style="background: rgba(230, 184, 0, 0.1); color: #cda215; border: 1px solid rgba(230, 184, 0, 0.2); padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; cursor: pointer;" onclick="setQuickPoints(20)">+20</button>
                        <button type="button" class="btn" style="background: rgba(230, 184, 0, 0.1); color: #cda215; border: 1px solid rgba(230, 184, 0, 0.2); padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; cursor: pointer;" onclick="setQuickPoints(50)">+50</button>
                        <button type="button" class="btn" style="background: rgba(230, 184, 0, 0.1); color: #cda215; border: 1px solid rgba(230, 184, 0, 0.2); padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; cursor: pointer;" onclick="setQuickPoints(100)">+100</button>
                        <button type="button" class="btn" style="background: rgba(220, 53, 69, 0.05); color: #dc3545; border: 1px solid rgba(220, 53, 69, 0.15); padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; cursor: pointer;" onclick="setQuickPoints(-10)">-10</button>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 1rem;">
                    <label class="form-label">Reason / Remark</label>
                    <input type="text" class="form-control" id="points_reason" name="points_reason" placeholder="e.g. Helped clean lab room, Java project assistance" required>
                </div>

                <div class="form-group" style="display: flex; align-items: center; gap: 8px; margin-top: 1rem;">
                    <input type="checkbox" id="log_as_task" name="log_as_task" value="1" checked style="width: 16px; height: 16px; cursor: pointer;">
                    <label for="log_as_task" style="font-size: 0.8rem; font-weight: 600; color: var(--text-primary); cursor: pointer;">Log reason as a completed task for student score (20% weight)</label>
                </div>

                <div class="modal-footer" style="margin-top: 1.5rem;">
                    <button type="button" class="btn-cancel" onclick="closePointsModal()">Cancel</button>
                    <button type="submit" class="btn-save" style="background: #e6b800; box-shadow: 0 2px 8px rgba(230, 184, 0, 0.2);">Confirm Award</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Student Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Student</h2>
                <button class="close-btn" onclick="closeAddModal()">&times;</button>
            </div>
            <form method="POST" action="admin-students.php">
                <input type="hidden" name="add_student" value="1">

                <div class="form-group">
                    <label class="form-label">ID Number</label>
                    <input type="text" class="form-control" name="id_number" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Last Name</label>
                    <input type="text" class="form-control" name="last_name" required>
                </div>

                <div class="form-group">
                    <label class="form-label">First Name</label>
                    <input type="text" class="form-control" name="first_name" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Middle Name</label>
                    <input type="text" class="form-control" name="middle_name">
                </div>

                <div class="form-group">
                    <label class="form-label">Course</label>
                    <select class="form-control" name="course" required>
                        <option value="">Select Course</option>
                        <option value="BSIT">Bachelor of Science in Information Technology (BSIT)</option>
                        <option value="BSCA">Bachelor of Science in Customs Administration (BSCA)</option>
                        <option value="BSCS">Bachelor of Science in Computer Science (BSCS)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Year Level</label>
                    <select class="form-control" name="course_level" required>
                        <option value="">Select Year Level</option>
                        <option value="1">1st Year</option>
                        <option value="2">2nd Year</option>
                        <option value="3">3rd Year</option>
                        <option value="4">4th Year</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Password (min. 6 characters)</label>
                    <input type="password" class="form-control" name="password" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Repeat Password</label>
                    <input type="password" class="form-control" name="repeat_password" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Address</label>
                    <input type="text" class="form-control" name="address">
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="btn-save">Add Student</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(id, firstName, lastName, middleName, email, course, courseLevel, address) {
            document.getElementById('student_id').value = id;
            document.getElementById('first_name').value = firstName;
            document.getElementById('last_name').value = lastName;
            document.getElementById('middle_name').value = middleName;
            document.getElementById('email').value = email;
            document.getElementById('course').value = course;
            document.getElementById('course_level').value = courseLevel;
            document.getElementById('address').value = address;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }

        function openPointsModal(id, name, currentPoints) {
            document.getElementById('points_student_id').value = id;
            document.getElementById('points_student_name').textContent = name;
            document.getElementById('points_current_val').textContent = currentPoints + " pts";
            document.getElementById('points_amount').value = '';
            document.getElementById('points_reason').value = '';
            document.getElementById('pointsModal').style.display = 'block';
        }

        function closePointsModal() {
            document.getElementById('pointsModal').style.display = 'none';
        }

        function setQuickPoints(amount) {
            document.getElementById('points_amount').value = amount;
        }

        // Close modal when clicking outside of it
        window.onclick = function (event) {
            var editModal = document.getElementById('editModal');
            var addModal = document.getElementById('addModal');
            var pointsModal = document.getElementById('pointsModal');
            if (event.target == editModal) {
                editModal.style.display = 'none';
            }
            if (event.target == addModal) {
                addModal.style.display = 'none';
            }
            if (event.target == pointsModal) {
                pointsModal.style.display = 'none';
            }
        };

        function confirmDeleteStudent(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!',
                background: document.documentElement.classList.contains('dark-mode') ? '#1f2f27' : '#fff',
                color: document.documentElement.classList.contains('dark-mode') ? '#fff' : '#1f2f27'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'admin-students.php?action=delete&id=' + id;
                }
            });
        }
    </script>

<script>
        function toggleProfileDropdown(event) {
            event.stopPropagation();
            const container = document.getElementById('profileDropdownContainer');
            if (container) {
                container.classList.toggle('active');
            }
        }

        window.addEventListener('click', function(event) {
            const container = document.getElementById('profileDropdownContainer');
            if (container && !container.contains(event.target)) {
                container.classList.remove('active');
            }
        });
</script>
</body>

</html>