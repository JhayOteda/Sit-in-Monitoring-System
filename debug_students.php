<?php
require 'db.php';

try {
    echo "<h2>Debug: All Students in Database</h2>";

    // Show all users
    $stmt = $pdo->query("SELECT id, id_number, first_name, last_name FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($users)) {
        echo "<p style='color:red;'><strong>✗ No students found in database!</strong></p>";
        echo "<p>Please register students first.</p>";
    } else {
        echo "<p style='color:green;'><strong>✓ Found " . count($users) . " student(s)</strong></p>";
        echo "<table border='1' style='border-collapse:collapse; padding:10px; margin:10px 0;'>";
        echo "<tr><th>ID</th><th>ID Number</th><th>First Name</th><th>Last Name</th></tr>";

        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . $user['id'] . "</td>";
            echo "<td>" . htmlspecialchars($user['id_number']) . "</td>";
            echo "<td>" . htmlspecialchars($user['first_name']) . "</td>";
            echo "<td>" . htmlspecialchars($user['last_name']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";

        echo "<h3>Try searching with one of these ID Numbers in admin-search.php</h3>";
    }

} catch (Exception $e) {
    echo "<p style='color:red;'><strong>Error: " . $e->getMessage() . "</strong></p>";
}
?>
<p><a href="admin-search.php">Go back to Search</a></p>