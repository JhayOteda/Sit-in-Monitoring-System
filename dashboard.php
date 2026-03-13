<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>CCS | Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <nav>
        <span class="nav-brand">College of Computer Studies Sit-in Monitoring System</span>
        <ul class="nav-links">
            <li><a href="#">Home</a></li>
            <li><a href="#">About</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>

    <div class="page-wrapper">
        <div
            style="background:#fff; border-radius:10px; padding:3rem; box-shadow:0 8px 40px rgba(13,34,64,0.14); text-align:center; max-width:500px; width:100%;">
            <h2 style="font-family:'Merriweather',serif; color:#0d2240; margin-bottom:0.5rem;">Welcome!</h2>
            <p style="color:#6b7a99; font-size:0.95rem; margin-bottom:1.5rem;">
                Logged in as <strong>
                    <?= htmlspecialchars($_SESSION["name"]) ?>
                </strong>
                (ID:
                <?= htmlspecialchars($_SESSION["id_number"]) ?>)
            </p>
            <a href="logout.php"
                style="display:inline-block; padding:0.6rem 2rem; background:#0d2240; color:#fff; border-radius:5px; text-decoration:none; font-weight:700; font-size:0.88rem; letter-spacing:0.06em;">
                Logout
            </a>
        </div>
    </div>

</body>

</html>