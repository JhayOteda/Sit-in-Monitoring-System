<?php
// Migration script to add remaining_sessions column to users table
require 'db.php';

try {
    // Check if column already exists
    $check = $pdo->query("SHOW COLUMNS FROM users LIKE 'remaining_sessions'");
    if ($check->rowCount() === 0) {
        // Add the column
        $pdo->exec("ALTER TABLE users ADD COLUMN remaining_sessions INT DEFAULT 30");
        echo "✅ Migration successful: remaining_sessions column added to users table with default value 30";
    } else {
        echo "✅ Column remaining_sessions already exists.";
    }
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage();
}
?>