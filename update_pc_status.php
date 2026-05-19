<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
require 'db.php';

$data = json_decode(file_get_contents("php://input"), true);
if (!$data || !isset($data['lab_room']) || !isset($data['pcs']) || !isset($data['status'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$lab_room = $data['lab_room'];
$pcs = $data['pcs'];
$status = $data['status'];

if ($status !== 'Available' && $status !== 'Maintenance') {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO pc_statuses (lab_room, pc_number, status) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status = ?");
    foreach ($pcs as $pc) {
        $stmt->execute([$lab_room, (int)$pc, $status, $status]);
    }
    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
