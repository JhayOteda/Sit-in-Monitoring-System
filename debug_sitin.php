<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit;
}
require 'db.php';

// Get ALL records from sit_in_logs
$all_records = [];
$active_count = 0;
$ended_count = 0;

try {
    $stmt = $pdo->query("
        SELECT 
            sl.id,
            sl.user_id,
            sl.purpose,
            sl.lab_room,
            sl.created_at,
            sl.time_out,
            u.id_number,
            u.first_name,
            u.last_name
        FROM sit_in_logs sl
        JOIN users u ON sl.user_id = u.id
        ORDER BY sl.created_at DESC
    ");
    $all_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count active
    $stmt = $pdo->query("SELECT COUNT(*) FROM sit_in_logs WHERE time_out IS NULL");
    $active_count = $stmt->fetchColumn();
    
    // Count ended
    $stmt = $pdo->query("SELECT COUNT(*) FROM sit_in_logs WHERE time_out IS NOT NULL");
    $ended_count = $stmt->fetchColumn();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug - Sit-In Logs</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        .stats { background: #f0f0f0; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #2f7a59; color: white; }
        tr:hover { background: #f5f5f5; }
        .active { background: #e6f4ea; color: green; font-weight: bold; }
        .ended { background: #fde8e8; color: red; }
    </style>
</head>
<body>
    <h1>🔍 Debug - Sit-In Logs Database</h1>
    
    <div class="stats">
        <p><strong>Active Sessions (time_out IS NULL):</strong> <span style="color: green; font-size: 1.5em;"><?= $active_count ?></span></p>
        <p><strong>Ended Sessions (time_out IS NOT NULL):</strong> <span style="color: red; font-size: 1.5em;"><?= $ended_count ?></span></p>
        <p><strong>Total Records:</strong> <span style="font-size: 1.5em;"><?= count($all_records) ?></span></p>
    </div>
    
    <h2>All Records in sit_in_logs:</h2>
    
    <?php if (empty($all_records)): ?>
        <p>No records found.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User ID</th>
                    <th>ID Number</th>
                    <th>Name</th>
                    <th>Purpose</th>
                    <th>Lab Room</th>
                    <th>Check-In</th>
                    <th>Check-Out</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_records as $record): ?>
                    <tr class="<?= $record['time_out'] ? 'ended' : 'active' ?>">
                        <td><?= $record['id'] ?></td>
                        <td><?= $record['user_id'] ?></td>
                        <td><?= htmlspecialchars($record['id_number']) ?></td>
                        <td><?= htmlspecialchars($record['first_name'] . ' ' . $record['last_name']) ?></td>
                        <td><?= htmlspecialchars($record['purpose']) ?></td>
                        <td><?= htmlspecialchars($record['lab_room']) ?></td>
                        <td><?= $record['created_at'] ?></td>
                        <td><?= $record['time_out'] ?: 'NULL' ?></td>
                        <td><?= $record['time_out'] ? '✓ ENDED' : '⏱️ ACTIVE' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <hr>
    <p><a href="admin.php">Back to Dashboard</a> | <a href="admin-records.php">Back to Records</a></p>
</body>
</html>
