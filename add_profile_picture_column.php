<?php
require 'db.php';

try {
    // Check if profile_picture column exists
    $checkColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
    $columnExists = $checkColumn->rowCount() > 0;

    if (!$columnExists) {
        // Add profile_picture column if it doesn't exist
        $pdo->exec("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) NULL AFTER address");
        echo "✓ Profile picture column added successfully!";
    } else {
        echo "✓ Profile picture column already exists!";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>