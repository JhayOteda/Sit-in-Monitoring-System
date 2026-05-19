<?php
require 'db.php';
$stmt = $pdo->query("SELECT id_number, first_name, last_name, role FROM users LIMIT 5");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT) . "\n";
unlink(__FILE__);
