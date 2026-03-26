<?php
session_start();
require 'db.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id_number = trim($_POST["id_number"]);
    $password = trim($_POST["password"]);

    if (empty($id_number) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        // Try to login from admin table only
        $stmt = $pdo->prepare("SELECT * FROM admin WHERE id_number = ?");
        $stmt->execute([$id_number]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = "Invalid ID number or password.";
        } else if (!password_verify($password, $user["password"])) {
            $error = "Invalid ID number or password.";
        } else {
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["id_number"] = $user["id_number"];
            $_SESSION["name"] = $user["first_name"] . " " . $user["last_name"];
            $_SESSION["role"] = "admin";
            header("Location: admin.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS | Admin Login</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>

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
            <li><a href="admin-login.php">Admin Login</a></li>
            <li><a href="Register.php">Register</a></li>
        </ul>
    </nav>

    <div class="page-wrapper">
        <div class="login-card">

            <!-- LEFT PANEL -->
            <div class="login-panel-left">
                <div class="logo-row">
                    <img src="CCS.jpg" alt="CCS Logo">
                </div>
                <div class="divider-line"></div>

            </div>

            <!-- RIGHT PANEL -->
            <div class="login-panel-right">
                <h2 class="login-heading">Admin Login</h2>
                <p class="login-subheading">Sign in to your admin account to continue</p>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="admin-login.php">
                    <div class="form-group">
                        <input type="text" class="form-control" name="id_number" placeholder="Enter your ID number"
                            value="<?= htmlspecialchars($_POST['id_number'] ?? '') ?>">
                        <span class="form-label">ID Number</span>
                    </div>

                    <div class="form-group">
                        <input type="password" class="form-control" name="password" placeholder="Enter your password">
                        <span class="form-label">Password</span>
                    </div>

                    <div class="form-options">
                        <label class="remember-me">
                            <input type="checkbox" name="remember"> Remember me
                        </label>
                        <a href="#" class="forgot-link">Forgot password?</a>
                    </div>

                    <button type="submit" class="btn-login">Sign In</button>
                </form>

                <p class="register-prompt" style="margin-top:1rem;">
                    Not an admin? <a href="login.php">Login as student</a>
                </p>
            </div>

        </div>
    </div>

</body>

</html>
