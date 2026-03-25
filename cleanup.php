<?php
require 'db.php';

try {
    // Delete all non-admin users (keep clean database for testing)
    $stmt = $pdo->prepare("DELETE FROM users WHERE role IS NULL OR role != 'admin'");
    $stmt->execute();
    echo "✓ All student records deleted. Database is now clean.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
