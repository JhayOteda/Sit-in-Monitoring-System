<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require 'db.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['announcement_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$announcement_id = (int) $data['announcement_id'];
$user_id = $_SESSION["user_id"];

try {
    // Create announcement_reads table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS announcement_reads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        announcement_id INT NOT NULL,
        read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_read (user_id, announcement_id),
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE
    )");

    // Insert or ignore (in case it already exists)
    $stmt = $pdo->prepare("INSERT IGNORE INTO announcement_reads (user_id, announcement_id) VALUES (?, ?)");
    $stmt->execute([$user_id, $announcement_id]);

    echo json_encode(['success' => true, 'message' => 'Announcement marked as read']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>