<?php
require 'db.php';

if (isset($_GET['lab_room'])) {
    $lab = $_GET['lab_room'];
    try {
        $stmt = $pdo->prepare("SELECT s.name, s.version 
                               FROM software s 
                               JOIN lab_software ls ON s.id = ls.software_id 
                               WHERE ls.lab_room = ? 
                               ORDER BY s.name ASC");
        $stmt->execute([$lab]);
        $software = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($software);
    } catch (Exception $e) {
        echo json_encode([]);
    }
}
