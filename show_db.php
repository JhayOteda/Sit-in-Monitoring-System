<?php
require 'db.php';

try {
    $stmt = $pdo->query("SELECT id, id_number, first_name, last_name, course, course_level, email FROM users ORDER BY id ASC");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h2>Database Users (" . count($students) . " total)</h2>";
    echo "<table border='1' style='border-collapse:collapse; padding:10px;'>";
    echo "<tr><th>ID</th><th>ID Number</th><th>First Name</th><th>Last Name</th><th>Course</th><th>Level</th><th>Email</th></tr>";

    foreach ($students as $student) {
        echo "<tr>";
        echo "<td>" . $student['id'] . "</td>";
        echo "<td>" . $student['id_number'] . "</td>";
        echo "<td>" . $student['first_name'] . "</td>";
        echo "<td>" . $student['last_name'] . "</td>";
        echo "<td>" . $student['course'] . "</td>";
        echo "<td>" . $student['course_level'] . "</td>";
        echo "<td>" . $student['email'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>