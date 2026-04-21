<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require 'db.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['log_id']) || empty($data['message']) || empty($data['rating'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$log_id = $data['log_id'];
$message = trim($data['message']);
$rating = (int) $data['rating'];
$user_id = $_SESSION["user_id"];

// Validate rating is between 1 and 5
if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Invalid rating']);
    exit;
}

try {
    // Check if feedback already exists
    $check = $pdo->prepare("SELECT id FROM feedback WHERE log_id = ? AND user_id = ?");
    $check->execute([$log_id, $user_id]);
    if ($check->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Feedback already submitted for this entry']);
        exit;
    }

    // Check if feedback table exists, if not create it
    $pdo->exec("CREATE TABLE IF NOT EXISTS feedback (
        id INT AUTO_INCREMENT PRIMARY KEY,
        log_id INT NOT NULL,
        user_id INT NOT NULL,
        message LONGTEXT NOT NULL,
        rating INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (log_id) REFERENCES sit_in_logs(id),
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    // Check if rating column exists, if not add it
    $columns = $pdo->query("SHOW COLUMNS FROM feedback WHERE Field = 'rating'")->fetchAll();
    if (empty($columns)) {
        $pdo->exec("ALTER TABLE feedback ADD COLUMN rating INT DEFAULT 0 AFTER message");
    }

    // Insert feedback with rating
    $stmt = $pdo->prepare("INSERT INTO feedback (log_id, user_id, message, rating) VALUES (?, ?, ?, ?)");
    $stmt->execute([$log_id, $user_id, $message, $rating]);

    echo json_encode(['success' => true, 'message' => 'Feedback submitted successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>