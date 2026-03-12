<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS | Home</title>
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
        <li><a href="#">About</a></li>
        <li><a href="login.php">Login</a></li>
        <li><a href="Register.php">Register</a></li>
    </ul>
</nav>

<div class="page-wrapper">
    <div class="login-card">

        <div class="logo-section">
            <img src="CCS.jpg" alt="CSS-LOGO">
            <h2 class="login-title">Welcome Back!</h2>
            
        </div>

        <div class="form-section">
            <div class="form-group">
                <input type="text" class="form-control" placeholder="Enter a valid id number" name="id_number">
                <span class="form-label">ID Number</span>
            </div>
            <div class="form-group">
                <input type="password" class="form-control" placeholder="Enter password" name="password">
                <span class="form-label">Password</span>
            </div>
            <div class="form-options">
                <label class="remember-me">
                    <input type="checkbox" name="remember"> Remember me
                </label>
                <a href="#" class="forgot-link">Forgot password?</a>
            </div>
            <button class="btn-login">Login</button>
            <p class="register-prompt">
                Don't have an account? <a href="Register.php">Register</a>
            </p>
        </div>

    </div>
</div>

</body>
</html>