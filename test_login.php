<?php
require 'db.php';

echo "<h3>Removing user 23768914 from admin table...</h3>";

$stmt = $pdo->prepare("DELETE FROM admin WHERE id_number = ? AND first_name = 'Admin' AND last_name = 'User'");
$stmt->execute(["23768914"]);

echo "Deleted " . $stmt->rowCount() . " record(s)<br>";

echo "<h3>Admin table now contains:</h3>";
$stmt = $pdo->query("SELECT id, id_number, first_name, last_name FROM admin");
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' style='border-collapse:collapse; padding:10px;'>";
echo "<tr><th>ID</th><th>ID Number</th><th>First Name</th><th>Last Name</th></tr>";
foreach ($admins as $admin) {
    echo "<tr><td>" . $admin['id'] . "</td><td>" . $admin['id_number'] . "</td><td>" . $admin['first_name'] . "</td><td>" . $admin['last_name'] . "</td></tr>";
}
echo "</table>";

echo "<br><strong>Try logging in now!</strong>";
?>