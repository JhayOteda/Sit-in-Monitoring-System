<?php
session_start();
require 'db.php';

$error = "";
$success = "";

// Get redirect parameter or default to login.php
$redirect_page = isset($_GET['redirect']) ? trim($_GET['redirect']) : 'login.php';
// Validate redirect is a safe page
$allowed_redirects = ['login.php', 'admin-students.php'];
if (!in_array($redirect_page, $allowed_redirects)) {
    $redirect_page = 'login.php';
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
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

    // Validation
    if (
        empty($id_number) || empty($last_name) || empty($first_name) ||
        empty($course) || empty($course_level) || empty($password) ||
        empty($repeat_pass) || empty($email)
    ) {
        $error = "Please fill in all required fields.";
    } elseif ($password !== $repeat_pass) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // Check if ID or email already exists
        $check = $pdo->prepare("SELECT id FROM users WHERE id_number = ? OR email = ?");
        $check->execute([$id_number, $email]);

        if ($check->rowCount() > 0) {
            $error = "ID Number or Email is already registered.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            try {
                // Try inserting with role column
                $stmt = $pdo->prepare("INSERT INTO users
                    (id_number, last_name, first_name, middle_name, course_level, password, email, course, address, role)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $id_number,
                    $last_name,
                    $first_name,
                    $middle_name,
                    $course_level,
                    $hashed,
                    $email,
                    $course,
                    $address,
                    'student'
                ]);
            } catch (PDOException $e) {
                // If role column doesn't exist, try without it
                $stmt = $pdo->prepare("INSERT INTO users
                    (id_number, last_name, first_name, middle_name, course_level, password, email, course, address)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $id_number,
                    $last_name,
                    $first_name,
                    $middle_name,
                    $course_level,
                    $hashed,
                    $email,
                    $course,
                    $address
                ]);
            }
            $success = "Account created successfully! Your account has been created.";
            // Clear form data after successful registration
            $_POST = [];

        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS | Register</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <!-- NAVBAR -->
    <nav>
        <span class="nav-brand">College of Computer Studies Sit-in Monitoring System</span>
        <ul class="nav-links">
            <li><a href="#">Home</a></li>
            <li class="dropdown">
                <button class="dropdown-toggle">Community</button>
                <div class="dropdown-menu">
                    <a href="#">Forums</a>
                    <a href="#">Members</a>
                </div>
            </li>
            <li><a href="login.php">Login</a></li>
            <li><a href="Register.php">Register</a></li>
        </ul>
    </nav>

    <!-- Main -->
    <div class="page-wrapper">
        <div class="register-card">

            <!-- Back Button -->
            <a href="<?= htmlspecialchars($redirect_page) ?>" class="back-btn">Back</a>

            <!-- Title -->
            <h2 class="register-title">Sign up</h2>

            <div class="register-body">

                <!-- FORM -->
                <div class="register-form">

                    <?php if ($error): ?>
                        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <?= htmlspecialchars($success) ?>
                            <a href="login.php" style="font-weight:700; color:#155724; margin-left:0.4rem;">Login now →</a>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="Register.php">

                        <div class="form-group">
                            <input type="text" class="form-control" name="id_number"
                                value="<?= htmlspecialchars($_POST['id_number'] ?? '') ?>">
                            <span class="form-label">ID Number</span>
                        </div>

                        <div class="form-group">
                            <input type="text" class="form-control" name="last_name"
                                value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                            <span class="form-label">Last Name</span>
                        </div>

                        <div class="form-group">
                            <input type="text" class="form-control" name="first_name"
                                value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                            <span class="form-label">First Name</span>
                        </div>

                        <div class="form-group">
                            <input type="text" class="form-control" name="middle_name"
                                value="<?= htmlspecialchars($_POST['middle_name'] ?? '') ?>">
                            <span class="form-label">Middle Name</span>
                        </div>

                        <div class="form-group">
                            <select class="form-control" name="course" required>
                                <option value="" disabled <?= empty($_POST['course']) ? 'selected' : '' ?> hidden>Select
                                    Course</option>
                                <option value="BSIT" <?= (($_POST['course'] ?? '') === 'BSIT') ? 'selected' : '' ?>>
                                    Bachelor of Science in Information Technology (BSIT)
                                </option>
                                <option value="BSCA" <?= (($_POST['course'] ?? '') === 'BSCA') ? 'selected' : '' ?>>
                                    Bachelor of Science in Customs Administration (BSCA)
                                </option>
                                <option value="BSCS" <?= (($_POST['course'] ?? '') === 'BSCS') ? 'selected' : '' ?>>
                                    Bachelor of Science in Computer Science (BSCS)
                                </option>
                            </select>
                            <span class="form-label">Course</span>
                        </div>

                        <div class="form-group">
                            <select class="form-control" name="course_level" required>
                                <option value="" disabled <?= empty($_POST['course_level']) ? 'selected' : '' ?> hidden>
                                    Select Course Level</option>
                                <option value="1" <?= (($_POST['course_level'] ?? '') === '1') ? 'selected' : '' ?>>1
                                </option>
                                <option value="2" <?= (($_POST['course_level'] ?? '') === '2') ? 'selected' : '' ?>>2
                                </option>
                                <option value="3" <?= (($_POST['course_level'] ?? '') === '3') ? 'selected' : '' ?>>3
                                </option>
                                <option value="4" <?= (($_POST['course_level'] ?? '') === '4') ? 'selected' : '' ?>>4
                                </option>
                            </select>
                            <span class="form-label">Course Level</span>
                        </div>

                        <div class="form-group">
                            <input type="password" class="form-control" name="password">
                            <span class="form-label">Password (min. 6 characters)</span>
                        </div>

                        <div class="form-group">
                            <input type="password" class="form-control" name="repeat_password">
                            <span class="form-label">Repeat your password</span>
                        </div>

                        <div class="form-group">
                            <input type="email" class="form-control" name="email"
                                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                            <span class="form-label">Email</span>
                        </div>

                        <div class="form-group">
                            <input type="text" class="form-control" name="address"
                                value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
                            <span class="form-label">Address</span>
                        </div>

                        <button type="submit" class="btn-register">Register</button>

                        <p class="register-prompt" style="margin-top: 0.9rem;">
                            Already have an account? <a href="login.php">Login</a>
                        </p>

                    </form>
                </div>
            </div>
        </div>
    </div>

</body>

</html>