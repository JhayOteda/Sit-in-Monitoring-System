<?php

session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}
require 'db.php';

$user_id = $_SESSION["user_id"];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if in edit mode
$edit_mode = isset($_GET['edit']) && $_GET['edit'] === 'true';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS | Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <nav>
        <span class="nav-brand">Dashboard</span>
        <ul class="nav-links">
            <li><a href="dashboard.php">Home</a></li>
            <li><a href="#">Notification</a></li>
            <?php if ($edit_mode): ?>
                <li><a href="dashboard.php">Cancel</a></li>
            <?php else: ?>
                <li><a href="dashboard.php?edit=true">Edit Profile</a></li>
            <?php endif; ?>
            <li><a href="#">History</a></li>
            <li><a href="#">Reservation</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>

    <div class="page-wrapper">
        <div class="dashboard-container">

            <!-- Welcome Section -->
            <div class="dashboard-header">
                <h2 style="font-family:'Merriweather',serif; color:#0d2240; margin-bottom:0.5rem;">Welcome Back!</h2>
                <p style="color:#6b7a99; font-size:0.95rem;">
                    <strong><?= htmlspecialchars($user["first_name"] . " " . $user["last_name"]) ?></strong>
                </p>
            </div>

            <!-- Display Messages -->
            <?php if (isset($_SESSION["success"])): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($_SESSION["success"]) ?>
                </div>
                <?php unset($_SESSION["success"]); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION["error"])): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($_SESSION["error"]) ?>
                </div>
                <?php unset($_SESSION["error"]); ?>
            <?php endif; ?>

            <!-- User Information Card -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3>Profile Information</h3>

                </div>

                <!-- View Mode -->
                <?php if (!$edit_mode): ?>
                    <div id="viewMode" class="info-view">
                        <div class="info-row">
                            <div class="info-group">
                                <label>ID Number</label>
                                <p><?= htmlspecialchars($user["id_number"]) ?></p>
                            </div>
                            <div class="info-group">
                                <label>Course</label>
                                <p><?= htmlspecialchars($user["course"]) ?></p>
                            </div>
                        </div>

                        <div class="info-row">
                            <div class="info-group">
                                <label>First Name</label>
                                <p><?= htmlspecialchars($user["first_name"]) ?></p>
                            </div>
                            <div class="info-group">
                                <label>Last Name</label>
                                <p><?= htmlspecialchars($user["last_name"]) ?></p>
                            </div>
                        </div>

                        <div class="info-row">
                            <div class="info-group">
                                <label>Middle Name</label>
                                <p><?= htmlspecialchars($user["middle_name"] ?? "Not provided") ?></p>
                            </div>
                            <div class="info-group">
                                <label>Course Level</label>
                                <p><?= htmlspecialchars($user["course_level"]) ?></p>
                            </div>
                        </div>

                        <div class="info-row">
                            <div class="info-group">
                                <label>Email</label>
                                <p><?= htmlspecialchars($user["email"]) ?></p>
                            </div>
                            <div class="info-group">
                                <label>Address</label>
                                <p><?= htmlspecialchars($user["address"] ?? "Not provided") ?></p>
                            </div>
                        </div>

                        <div class="info-row">
                            <div class="info-group">
                                <label>Account Created</label>
                                <p><?= date("M d, Y", strtotime($user["created_at"])) ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Mode -->
                <?php else: ?>
                    <div id="editMode" class="info-edit">
                        <form method="POST" action="update_profile.php">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">ID Number</label>
                                    <input type="text" class="form-control" name="id_number"
                                        value="<?= htmlspecialchars($user["id_number"]) ?>" readonly>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Course</label>
                                    <select class="form-control" name="course" required>
                                        <option value="BSIT" <?= $user["course"] === "BSIT" ? "selected" : "" ?>>Bachelor of
                                            Science in Information Technology (BSIT)</option>
                                        <option value="BSCA" <?= $user["course"] === "BSCA" ? "selected" : "" ?>>Bachelor of
                                            Science in Customs Administration (BSCA)</option>
                                        <option value="BSCS" <?= $user["course"] === "BSCS" ? "selected" : "" ?>>Bachelor of
                                            Science in Computer Science (BSCS)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">First Name</label>
                                    <input type="text" class="form-control" name="first_name"
                                        value="<?= htmlspecialchars($user["first_name"]) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" class="form-control" name="last_name"
                                        value="<?= htmlspecialchars($user["last_name"]) ?>" required>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Middle Name</label>
                                    <input type="text" class="form-control" name="middle_name"
                                        value="<?= htmlspecialchars($user["middle_name"] ?? "") ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Course Level</label>
                                    <select class="form-control" name="course_level" required>
                                        <option value="1" <?= (string) $user["course_level"] === "1" ? "selected" : "" ?>>1
                                        </option>
                                        <option value="2" <?= (string) $user["course_level"] === "2" ? "selected" : "" ?>>2
                                        </option>
                                        <option value="3" <?= (string) $user["course_level"] === "3" ? "selected" : "" ?>>3
                                        </option>
                                        <option value="4" <?= (string) $user["course_level"] === "4" ? "selected" : "" ?>>4
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email"
                                        value="<?= htmlspecialchars($user["email"]) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Address</label>
                                    <input type="text" class="form-control" name="address"
                                        value="<?= htmlspecialchars($user["address"] ?? "") ?>">
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn-save">Save Changes</button>
                                <button type="button" class="btn-cancel"
                                    onclick="window.location.href='dashboard.php'">Cancel</button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

</body>

</html>