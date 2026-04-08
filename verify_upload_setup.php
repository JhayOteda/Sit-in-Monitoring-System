<?php
echo "<h2>Profile Picture Upload - Verification</h2>";

$uploads_dir = "uploads";

// Check if uploads directory exists
if (!is_dir($uploads_dir)) {
    echo "<p style='color:red;'>❌ Uploads directory does NOT exist</p>";
    echo "<p>Creating uploads directory...</p>";
    if (mkdir($uploads_dir, 0777, true)) {
        echo "<p style='color:green;'>✓ Uploads directory created successfully</p>";
    } else {
        echo "<p style='color:red;'>❌ Failed to create uploads directory</p>";
    }
} else {
    echo "<p style='color:green;'>✓ Uploads directory exists</p>";
}

// Check if directory is writable
if (is_writable($uploads_dir)) {
    echo "<p style='color:green;'>✓ Uploads directory is writable</p>";
} else {
    echo "<p style='color:orange;'>⚠ Uploads directory is NOT writable - Attempting to fix...</p>";
    if (chmod($uploads_dir, 0777)) {
        echo "<p style='color:green;'>✓ Fixed permissions</p>";
    } else {
        echo "<p style='color:red;'>❌ Could not fix permissions</p>";
    }
}

// List uploaded files
echo "<h3>Uploaded Files:</h3>";
$files = glob($uploads_dir . "/profile_*");
if (empty($files)) {
    echo "<p>No profile pictures uploaded yet.</p>";
} else {
    echo "<ul>";
    foreach ($files as $file) {
        echo "<li>" . basename($file) . " - " . filesize($file) . " bytes</li>";
    }
    echo "</ul>";
}

// Check database connection
require 'db.php';
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color:green;'>✓ Database column 'profile_picture' exists</p>";
    } else {
        echo "<p style='color:red;'>❌ Database column 'profile_picture' does NOT exist</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color:red;'>❌ Database error: " . $e->getMessage() . "</p>";
}
?>