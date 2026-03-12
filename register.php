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
        <li><a href="#">About</a></li>
        <li><a href="login.php">Login</a></li>
        <li><a href="Register.php">Register</a></li>
    </ul>
</nav>

<!-- Main -->
<div class="page-wrapper">
    <div class="register-card">

        <!-- Back Button -->
        <a href="login.php" class="back-btn">Back</a>

        <!-- Title -->
        <h2 class="register-title">Sign up</h2>

        <div class="register-body">

            <!-- FORM -->
            <div class="register-form">

                <div class="form-group">
                    <input type="text" class="form-control" name="id_number">
                    <span class="form-label">ID Number</span>
                </div>

                <div class="form-group">
                    <input type="text" class="form-control" name="last_name">
                    <span class="form-label">Last Name</span>
                </div>

                <div class="form-group">
                    <input type="text" class="form-control" name="first_name">
                    <span class="form-label">First Name</span>
                </div>

                <div class="form-group">
                    <input type="text" class="form-control" name="middle_name">
                    <span class="form-label">Middle Name</span>
                </div>

                <div class="form-group">
                    <select class="form-control" name="course_level" required>
                    <option value="" disabled selected hidden>Select Course</option>
                    <option value="BSIT">BSIT</option>
                    <option value="BSCA">BSCA</option>
                    <option value="BSCS">BSCS</option>
                    </select>
                    <span class="form-label">Course</span>
                </div>

                <div class="form-group">
                    <select class="form-control" name="course_level" required>
                    <option value="" disabled selected hidden>Select Course Level</option>
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                    </select>
                    <span class="form-label">Course Level</span>
                </div>

                <div class="form-group">
                    <input type="password" class="form-control" name="password">
                    <span class="form-label">Password</span>
                </div>

                <div class="form-group">
                    <input type="password" class="form-control" name="repeat_password">
                    <span class="form-label">Repeat your password</span>
                </div>

                <div class="form-group">
                    <input type="email" class="form-control" name="email">
                    <span class="form-label">Email</span>
                </div>

                <div class="form-group">
                    <input type="text" class="form-control" name="address">
                    <span class="form-label">Address</span>
                </div>

                <button class="btn-register">Register</button>

                <p class="register-prompt" style="margin-top: 0.9rem;">
                    Already have an account? <a href="login.php">Login</a>
                </p>

            </div>
        </div>
    </div>
</div>

</body>
</html>