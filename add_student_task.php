<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require 'db.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['task_desc'])) {
    echo json_encode(['success' => false, 'message' => 'Task description is required']);
    exit;
}

$task_desc = trim($data['task_desc']);
$user_id = $_SESSION["user_id"];

try {
    $pdo->beginTransaction();

    // Insert task
    $stmt = $pdo->prepare("INSERT INTO student_tasks (user_id, task_desc) VALUES (?, ?)");
    $stmt->execute([$user_id, $task_desc]);

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Task logged successfully!']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
