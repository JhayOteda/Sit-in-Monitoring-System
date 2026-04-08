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
    $profile_picture = null;

    // Validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($course) || empty($course_level)) {
        $_SESSION["error"] = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION["error"] = "Please enter a valid email address.";
    } else {
        // Handle file upload
        if (isset($_FILES["profile_picture"]) && $_FILES["profile_picture"]["error"] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES["profile_picture"]["tmp_name"];
            $file_name = $_FILES["profile_picture"]["name"];
            $file_size = $_FILES["profile_picture"]["size"];
            $file_type = mime_content_type($file_tmp);

            // Validate file
            $allowed_types = ["image/jpeg", "image/png", "image/gif", "image/webp"];
            $max_size = 5 * 1024 * 1024; // 5MB

            if (!in_array($file_type, $allowed_types)) {
                $_SESSION["error"] = "Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.";
                header("Location: dashboard.php");
                exit;
            }

            if ($file_size > $max_size) {
                $_SESSION["error"] = "File size exceeds 5MB limit.";
                header("Location: dashboard.php");
                exit;
            }

            // Create uploads directory if it doesn't exist
            if (!is_dir("uploads")) {
                if (!mkdir("uploads", 0777, true)) {
                    $_SESSION["error"] = "Failed to create uploads directory.";
                    header("Location: dashboard.php");
                    exit;
                }
            }

            // Check if directory is writable
            if (!is_writable("uploads")) {
                chmod("uploads", 0777);
            }

            // Generate unique filename
            $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
            $new_filename = "profile_" . $user_id . "_" . time() . "." . $file_ext;
            $upload_path = "uploads/" . $new_filename;

            // Move uploaded file
            if (move_uploaded_file($file_tmp, $upload_path)) {
                // Make sure file is readable
                chmod($upload_path, 0644);
                $profile_picture = $new_filename;

                // Delete old profile picture if exists
                try {
                    $old_pic = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
                    $old_pic->execute([$user_id]);
                    $old_data = $old_pic->fetch(PDO::FETCH_ASSOC);
                    if ($old_data && $old_data["profile_picture"] && file_exists("uploads/" . $old_data["profile_picture"])) {
                        unlink("uploads/" . $old_data["profile_picture"]);
                    }
                } catch (PDOException $e) {
                    // Continue even if old file deletion fails
                }
            } else {
                $_SESSION["error"] = "Failed to upload profile picture.";
                header("Location: dashboard.php");
                exit;
            }
        }

        try {
            if ($profile_picture) {
                $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, middle_name = ?, email = ?, course = ?, course_level = ?, address = ?, profile_picture = ? WHERE id = ?");
                $stmt->execute([$first_name, $last_name, $middle_name, $email, $course, $course_level, $address, $profile_picture, $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, middle_name = ?, email = ?, course = ?, course_level = ?, address = ? WHERE id = ?");
                $stmt->execute([$first_name, $last_name, $middle_name, $email, $course, $course_level, $address, $user_id]);
            }

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