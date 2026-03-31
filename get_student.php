<?php
require 'db.php';

header('Content-Type: application/json');

$id_number = trim($_GET['id_number'] ?? '');

if (empty($id_number)) {
    echo json_encode(['success' => false, 'message' => 'No ID number provided']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, id_number, first_name, last_name FROM users WHERE id_number = ?");
    $stmt->execute([$id_number]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo json_encode([
            'success' => true,
            'user_id' => $user['id'],
            'id_number' => $user['id_number'],
            'name' => $user['first_name'] . ' ' . $user['last_name']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
