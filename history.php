<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}
require 'db.php';
$user_id = $_SESSION["user_id"];
$logs = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM sit_in_logs WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}

// Get feedback status for each log
$feedback_status = [];
try {
    $stmt = $pdo->prepare("SELECT log_id FROM feedback WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($feedbacks as $fb) {
        $feedback_status[$fb['log_id']] = true;
    }
} catch (Exception $e) {
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS | History</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Merriweather:wght@700&family=Nunito+Sans:wght@400;600;700&display=swap"
        rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --bg-start: #e7f2eb;
            --bg-end: #d3e7db;
            --nav-bg: #1f4f3c;
            --nav-text: #edf7f2;
            --card-bg: #ffffff;
            --text-primary: #1f2f27;
            --text-muted: #607367;
            --border-soft: #d0dfd6;
            --input-bg: #f5faf7;
            --brand-1: #2f7a59;
            --brand-2: #245f45;
            --brand-1-strong: #2a6d4f;
            --brand-2-strong: #1f543d;
        }

        body {
            font-family: 'Nunito Sans', sans-serif;
            background: linear-gradient(135deg, var(--bg-start) 0%, var(--bg-end) 100%);
            min-height: 100vh;
        }

        .d-nav {
            background: var(--nav-bg);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
            height: 48px;
            position: relative;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
        }

        .d-nav-brand {
            color: var(--nav-text);
            font-size: 0.95rem;
            font-weight: 600;
            font-family: 'Merriweather', serif;
            letter-spacing: 0.02em;
        }

        .d-nav-links {
            display: flex;
            align-items: center;
            list-style: none;
            gap: 0.1rem;
        }

        .d-nav-links a {
            color: var(--nav-text);
            text-decoration: none;
            font-size: 0.9rem;
            padding: 0.35rem 0.7rem;
            border-radius: 4px;
            white-space: nowrap;
            display: block;
            transition: background 0.15s;
        }

        .d-nav-links a:hover {
            background: rgba(255, 255, 255, 0.14);
        }

        .d-nav-links .d-logout {
            background: var(--brand-1);
            border-radius: 4px;
            font-weight: 700;
            margin-left: 0.25rem;
        }

        .d-nav-links .d-logout:hover {
            background: var(--brand-2);
        }

        .d-dropdown {
            position: relative;
        }

        .d-dropdown-menu {
            display: none;
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            min-width: 180px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.13);
            z-index: 999;
        }

        .d-dropdown:hover .d-dropdown-menu {
            display: block;
        }

        .d-dropdown-menu p {
            padding: 0.7rem 1rem;
            font-size: 0.83rem;
            color: var(--text-muted);
        }

        .d-wrap {
            padding: 1.2rem 1.5rem;
            max-width: 900px;
            margin: 0 auto;
        }

        .d-card {
            background: var(--card-bg);
            border-radius: 6px;
            box-shadow: 0 1px 5px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .d-card-head {
            background: var(--brand-1);
            color: #fff;
            font-size: 0.9rem;
            font-weight: 700;
            padding: 0.6rem 1rem;
            letter-spacing: 0.02em;
        }

        .d-card-body {
            padding: 1rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.86rem;
        }

        table th {
            background: var(--brand-1);
            color: #fff;
            padding: 0.55rem 0.9rem;
            text-align: left;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        table td {
            padding: 0.55rem 0.9rem;
            border-bottom: 1px solid var(--border-soft);
            color: var(--text-primary);
        }

        table tr:hover td {
            background: #f8faf9;
        }

        .badge {
            display: inline-block;
            padding: 0.15rem 0.55rem;
            border-radius: 20px;
            font-size: 0.74rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-done,
        .badge-approved {
            background: #e6f4ea;
            color: #155724;
        }

        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }

        .badge-cancelled,
        .badge-rejected {
            background: #fde8e8;
            color: #a01a1a;
        }

        .no-data {
            padding: 2rem;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .feedback-btn {
            padding: 0.4rem 0.8rem;
            background: var(--brand-1);
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .feedback-btn:hover {
            background: var(--brand-2);
            transform: translateY(-1px);
        }

        .feedback-btn:disabled {
            background: #999;
            cursor: not-allowed;
            transform: none;
        }

        .feedback-btn:disabled:hover {
            background: #999;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.4);
            animation: fadeIn 0.3s ease;
        }

        .modal.show {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 0;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            background: var(--brand-1);
            color: #fff;
            padding: 1rem;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 8px 8px 0 0;
        }

        .modal-header h2 {
            font-size: 1rem;
            margin: 0;
        }

        .close-btn {
            background: none;
            border: none;
            color: #fff;
            font-size: 1.5rem;
            cursor: pointer;
            transition: color 0.3s;
        }

        .close-btn:hover {
            color: #ddd;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .modal-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
            letter-spacing: 0.5px;
        }

        .modal-textarea {
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-soft);
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: inherit;
            color: var(--text-primary);
            outline: none;
            background: var(--input-bg);
            resize: vertical;
            min-height: 120px;
            transition: all 0.3s ease;
        }

        .modal-textarea:focus {
            border-color: var(--brand-1);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(47, 122, 89, 0.15);
        }

        .modal-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
            justify-content: flex-end;
        }

        .modal-btn-submit {
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, var(--brand-1) 0%, var(--brand-2) 100%);
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(47, 122, 89, 0.35);
        }

        .modal-btn-submit:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, var(--brand-1-strong) 0%, var(--brand-2-strong) 100%);
            box-shadow: 0 6px 20px rgba(36, 95, 69, 0.45);
        }

        .modal-btn-cancel {
            padding: 0.75rem 1.5rem;
            background: #e0e0e0;
            color: #333;
            border: none;
            border-radius: 5px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .modal-btn-cancel:hover {
            background: #d0d0d0;
            transform: translateY(-2px);
        }
    </style>
</head>

<body>
    <nav class="d-nav">
        <span class="d-nav-brand">History</span>
        <ul class="d-nav-links">
            <li class="d-dropdown">
                <a href="#">Notification ▾</a>
                <div class="d-dropdown-menu">
                    <p>No new notifications</p>
                </div>
            </li>
            <li><a href="dashboard.php">Home</a></li>
            <li><a href="dashboard.php?edit=true">Edit Profile</a></li>
            <li><a href="history.php">History</a></li>
            <li><a href="reservation.php">Reservation</a></li>
            <li><a href="logout.php" class="d-logout">Log out</a></li>
        </ul>
    </nav>
    <div class="d-wrap">
        <div class="d-card">
            <div class="d-card-head">Sit-in History</div>
            <div class="d-card-body">
                <?php if (empty($logs)): ?>
                    <div class="no-data">No sit-in history found.</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Time In</th>
                                <th>Time Out</th>
                                <th>Purpose</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $i => $log): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= htmlspecialchars(date("M d, Y", strtotime($log["created_at"]))) ?></td>
                                    <td><?= htmlspecialchars(date("h:i A", strtotime($log["created_at"]))) ?></td>
                                    <td><?= !empty($log["time_out"]) ? htmlspecialchars(date("h:i A", strtotime($log["time_out"]))) : "—" ?>
                                    </td>
                                    <td><?= htmlspecialchars($log["purpose"] ?? "—") ?></td>
                                    <td><span
                                            class="badge badge-<?= strtolower($log['status'] ?? 'done') ?>"><?= htmlspecialchars($log["status"] ?? "Done") ?></span>
                                    </td>
                                    <td>
                                        <?php if (isset($feedback_status[$log['id']])): ?>
                                            <button class="feedback-btn" disabled title="Feedback already submitted">✓ Sent</button>
                                        <?php else: ?>
                                            <button class="feedback-btn" onclick="openFeedbackModal(<?= $log['id'] ?>)">Feedback</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Feedback Modal -->
    <div id="feedbackModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Send Feedback</h2>
                <button class="close-btn" onclick="closeFeedbackModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="modal-group">
                    <label class="modal-label">Your Feedback</label>
                    <textarea id="feedbackText" class="modal-textarea" placeholder="Enter your feedback message here..."></textarea>
                </div>
                <div class="modal-actions">
                    <button class="modal-btn-cancel" onclick="closeFeedbackModal()">Cancel</button>
                    <button class="modal-btn-submit" onclick="submitFeedback()">Submit Feedback</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentLogId = null;

        function openFeedbackModal(logId) {
            currentLogId = logId;
            document.getElementById('feedbackModal').classList.add('show');
            document.getElementById('feedbackText').value = '';
            document.getElementById('feedbackText').focus();
        }

        function closeFeedbackModal() {
            document.getElementById('feedbackModal').classList.remove('show');
            currentLogId = null;
        }

        function submitFeedback() {
            const feedback = document.getElementById('feedbackText').value.trim();
            
            if (!feedback) {
                alert('Please enter your feedback message.');
                return;
            }

            // Submit feedback via AJAX
            fetch('submit_feedback.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    log_id: currentLogId,
                    message: feedback
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Feedback submitted successfully!');
                    closeFeedbackModal();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while submitting feedback.');
            });
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('feedbackModal');
            if (event.target == modal) {
                closeFeedbackModal();
            }
        }
    </script>
</body>

</html>