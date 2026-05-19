<?php
$host = "localhost";
$dbname = "ccs_sitin";
$username = "root";
$password = "";  // leave blank if you have no phpMyAdmin password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Auto-setup leaderboard database upgrades
    $check_points = $pdo->query("SHOW COLUMNS FROM users LIKE 'points'");
    if (!$check_points->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN points INT DEFAULT 0");
    } else {
        $pdo->exec("ALTER TABLE users ALTER COLUMN points SET DEFAULT 0");
    }

    $check_score = $pdo->query("SHOW COLUMNS FROM users LIKE 'score'");
    if (!$check_score->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN score DECIMAL(10,2) DEFAULT 0.00");
        // Compute and populate initial score for all existing students
        $pdo->exec("
            UPDATE users u 
            SET score = COALESCE(u.points, 0) * 0.50 + 
                        COALESCE((SELECT SUM(TIMESTAMPDIFF(SECOND, created_at, time_out)) / 3600.0 FROM sit_in_logs WHERE user_id = u.id AND time_out IS NOT NULL), 0) * 0.30 + 
                        COALESCE((SELECT COUNT(*) FROM sit_in_logs WHERE user_id = u.id AND time_out IS NOT NULL), 0) * 0.20
        ");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS student_tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        task_desc VARCHAR(255) NOT NULL,
        completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>