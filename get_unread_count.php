<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'unread_count' => 0]);
    exit;
}

require 'db.php';

$user_id = $_SESSION["user_id"];
$unread_count = 0;

try {
    // Count unread announcements for the current user
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM announcements a 
                          LEFT JOIN announcement_reads ar ON a.id = ar.announcement_id AND ar.user_id = ?
                          WHERE ar.id IS NULL");
    $stmt->execute([$user_id]);
    $unread_count = (int) $stmt->fetchColumn();

    echo json_encode(['success' => true, 'unread_count' => $unread_count]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'unread_count' => 0, 'error' => $e->getMessage()]);
}
?>