<?php
require 'db.php';

echo "<h2>Checking Reservations Table</h2>";

try {
    // Check if reservations table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'reservations'");
    $table_exists = $stmt->rowCount() > 0;

    if (!$table_exists) {
        echo "<p style='color: red;'>❌ Reservations table does NOT exist. Creating it now...</p>";
        
        $pdo->exec("CREATE TABLE reservations (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            date DATE NOT NULL,
            time_in TIME NOT NULL,
            purpose VARCHAR(100),
            lab_room VARCHAR(100),
            status VARCHAR(50) DEFAULT 'Pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        
        echo "<p style='color: green;'>✓ Reservations table created successfully!</p>";
    } else {
        echo "<p style='color: green;'>✓ Reservations table exists</p>";
        
        // Check columns
        $stmt = $pdo->query("SHOW COLUMNS FROM reservations");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Table Columns:</h3>";
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
        
        // Check if all required columns exist
        $required_cols = ['user_id', 'date', 'time_in', 'purpose', 'lab_room', 'status'];
        $existing_cols = array_map(function($col) { return $col['Field']; }, $columns);
        
        $missing_cols = array_diff($required_cols, $existing_cols);
        
        if (!empty($missing_cols)) {
            echo "<p style='color: orange;'>⚠ Missing columns: " . implode(', ', $missing_cols) . "</p>";
            
            foreach ($missing_cols as $col) {
                if ($col === 'lab_room') {
                    $pdo->exec("ALTER TABLE reservations ADD COLUMN lab_room VARCHAR(100) NULL");
                    echo "<p style='color: green;'>✓ Added lab_room column</p>";
                }
            }
        } else {
            echo "<p style='color: green;'>✓ All required columns exist</p>";
        }
    }
    
    echo "<h3>Testing Insert:</h3>";
    try {
        // Get a user ID for testing
        $user_stmt = $pdo->query("SELECT id FROM users LIMIT 1");
        $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $test_stmt = $pdo->prepare("INSERT INTO reservations (user_id, date, time_in, purpose, lab_room, status, created_at) VALUES (?, ?, ?, ?, ?, 'Pending', NOW())");
            $test_stmt->execute([
                $user['id'],
                date('Y-m-d', strtotime('+1 day')),
                '10:00',
                'Java',
                '524'
            ]);
            echo "<p style='color: green;'>✓ Test insert successful!</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Test insert failed: " . $e->getMessage() . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
