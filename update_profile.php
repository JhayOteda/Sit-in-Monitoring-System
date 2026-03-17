<?php

session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}
require 'db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $user_id = $_SESSION["user_id"];
    $first_name = trim($_POST["first_name"]);
    $last_name = trim($_POST["last_name"]);
    $middle_name = trim($_POST["middle_name"]);
    $email = trim($_POST["email"]);
    $course = trim($_POST["course"]);
    $course_level = trim($_POST["course_level"]);
    $address = trim($_POST["address"]);

    // Validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($course) || empty($course_level)) {
        $_SESSION["error"] = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION["error"] = "Please enter a valid email address.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, middle_name = ?, email = ?, course = ?, course_level = ?, address = ? WHERE id = ?");
            $stmt->execute([$first_name, $last_name, $middle_name, $email, $course, $course_level, $address, $user_id]);
            
            $_SESSION["name"] = $first_name . " " . $last_name;
            $_SESSION["success"] = "Profile updated successfully!";
        } catch (PDOException $e) {
            $_SESSION["error"] = "Error updating profile: " . $e->getMessage();
        }
    }
}

header("Location: dashboard.php");
exit;
?>