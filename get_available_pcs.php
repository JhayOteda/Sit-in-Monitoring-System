<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}
require 'db.php';

header('Content-Type: application/json');

$lab_room = trim($_GET['lab_room'] ?? '');

if (empty($lab_room)) {
    echo json_encode(['success' => false, 'message' => 'No lab room specified']);
    exit;
}

// Default 40 PCs per lab
$total_pcs = 40;

// Try to get from lab_pcs table if it exists
try {
    $stmt = $pdo->prepare("SELECT total_pcs FROM lab_pcs WHERE lab_room = ?");
    $stmt->execute([$lab_room]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $total_pcs = (int)$result['total_pcs'];
    }
} catch (Exception $e) {
    // Table might not exist yet, use default
}

// Get occupied PCs (active sit-ins with pc_number set)
$occupied = [];
try {
    $stmt = $pdo->prepare("SELECT pc_number, u.first_name, u.last_name FROM sit_in_logs sl JOIN users u ON sl.user_id = u.id WHERE sl.lab_room = ? AND sl.time_out IS NULL AND sl.pc_number IS NOT NULL");
    $stmt->execute([$lab_room]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $occupied[$row['pc_number']] = $row['first_name'] . ' ' . $row['last_name'];
    }
} catch (Exception $e) {
    // pc_number column might not exist yet
}

// Get maintenance PCs
$maintenance = [];
try {
    $stmt = $pdo->prepare("SELECT pc_number FROM pc_statuses WHERE lab_room = ? AND status = 'Maintenance'");
    $stmt->execute([$lab_room]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $maintenance[] = (int)$row['pc_number'];
    }
} catch (Exception $e) {
}

echo json_encode([
    'success' => true,
    'lab_room' => $lab_room,
    'total_pcs' => $total_pcs,
    'occupied' => $occupied,
    'maintenance' => $maintenance
]);
?>
