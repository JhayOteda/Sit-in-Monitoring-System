<?php
require 'db.php';

try {
    // Check reservations table columns
    $stmt = $pdo->query("SHOW COLUMNS FROM reservations");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Reservations Table Columns:</h2>";
    echo "<table border='1' style='border-collapse:collapse; padding:10px;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . $col['Field'] . "</td>";
        echo "<td>" . $col['Type'] . "</td>";
        echo "<td>" . $col['Null'] . "</td>";
        echo "<td>" . $col['Key'] . "</td>";
        echo "<td>" . $col['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check if lab_room column exists
    $lab_room_exists = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'lab_room') {
            $lab_room_exists = true;
            break;
        }
    }
    
    if (!$lab_room_exists) {
        echo "<h3 style='color:red;'>⚠ lab_room column does NOT exist. Adding it now...</h3>";
        try {
            $pdo->exec("ALTER TABLE reservations ADD COLUMN lab_room VARCHAR(100) NULL AFTER purpose");
            echo "<p style='color:green;'>✓ lab_room column added successfully!</p>";
        } catch (Exception $e) {
            echo "<p style='color:red;'>Error adding column: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color:green;'>✓ lab_room column already exists!</p>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
