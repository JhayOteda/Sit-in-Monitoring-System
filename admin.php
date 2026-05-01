<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit;
}
require 'db.php';

// Get statistics
$total_students = 0;
$current_sitin = 0;
$total_sitin = 0;
$lab_room_data = [];
$programming_language_data = [];

try {
    // Count all registered students (real-time from users table)
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $total_students = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM sit_in_logs WHERE time_out IS NULL");
    $current_sitin = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM sit_in_logs");
    $total_sitin = $stmt->fetchColumn();

    // Get lab room analytics
    $stmt = $pdo->query("SELECT lab_room, COUNT(*) as count FROM sit_in_logs GROUP BY lab_room ORDER BY count DESC");
    $lab_room_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get programming language analytics
    $stmt = $pdo->query("SELECT purpose, COUNT(*) as count FROM sit_in_logs WHERE purpose IS NOT NULL AND purpose != '' GROUP BY purpose ORDER BY count DESC");
    $programming_language_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}

// Handle announcement submission and deletion
$ann_success = $ann_error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $delete_id = $_POST["delete_id"] ?? null;

    if ($delete_id) {
        // Handle announcement deletion
        try {
            $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
            $stmt->execute([$delete_id]);
            $ann_success = "Announcement deleted successfully!";
        } catch (Exception $e) {
            $ann_error = "Could not delete announcement.";
        }
    } else {
        // Handle announcement submission
        $title = trim($_POST["title"] ?? "");
        $content = trim($_POST["content"] ?? "");

        if (empty($title) || empty($content)) {
            $ann_error = "Please fill in all fields.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO announcements (title, content, created_at) VALUES (?, ?, NOW())");
                $stmt->execute([$title, $content]);
                $ann_success = "Announcement posted successfully!";
            } catch (Exception $e) {
                $ann_error = "Could not save announcement.";
            }
        }
    }
}

$announcements = [];
try {
    $ann = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 5");
    $announcements = $ann->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS | Admin Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        nav {
            background: var(--nav-bg);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
            height: 48px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
        }

        .nav-brand {
            color: var(--nav-text);
            font-size: 0.85rem;
            font-weight: 600;
            font-family: 'Merriweather', serif;
            letter-spacing: 0.02em;
        }

        .nav-links {
            display: flex;
            align-items: center;
            list-style: none;
            gap: 0.1rem;
        }

        .nav-links a {
            color: var(--nav-text);
            text-decoration: none;
            font-size: 0.75rem;
            padding: 0.3rem 0.6rem;
            border-radius: 4px;
            white-space: nowrap;
            display: block;
            transition: background 0.15s;
        }

        .nav-links a:hover {
            background: rgba(255, 255, 255, 0.14);
        }

        .nav-links .logout-btn {
            background: var(--brand-1);
            border-radius: 4px;
            font-weight: 700;
            margin-left: 0.25rem;
            padding: 0.3rem 0.8rem;
        }

        .nav-links .logout-btn:hover {
            background: var(--brand-2);
        }

        .admin-wrap {
            padding: 1.5rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .admin-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .card {
            background: var(--card-bg);
            border-radius: 6px;
            box-shadow: 0 1px 5px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .card-head {
            background: var(--brand-1);
            color: #fff;
            font-size: 0.9rem;
            font-weight: 700;
            padding: 0.6rem 1rem;
            letter-spacing: 0.02em;
        }

        .card-body {
            padding: 1.5rem;
        }

        .stat-box {
            padding: 1.2rem;
            text-align: center;
            border-bottom: 1px solid var(--border-soft);
        }

        .stat-box:last-child {
            border-bottom: none;
        }

        .stat-label {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 0.3rem;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 1.8rem;
            color: var(--brand-1);
            font-weight: 700;
        }

        .stat-chart {
            width: 100%;
            height: 200px;
            margin-top: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 0.4rem;
            letter-spacing: 0.5px;
        }

        .form-control {
            width: 100%;
            padding: 0.6rem 0.8rem;
            border: 2px solid var(--border-soft);
            border-radius: 6px;
            font-size: 0.85rem;
            font-family: inherit;
            color: var(--text-primary);
            outline: none;
            background: var(--input-bg);
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--brand-1);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(47, 122, 89, 0.12);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }

        .btn-submit {
            padding: 0.6rem 1.5rem;
            background: linear-gradient(135deg, var(--brand-1) 0%, var(--brand-2) 100%);
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(47, 122, 89, 0.25);
        }

        .btn-submit:hover {
            transform: translateY(-1px);
            background: linear-gradient(135deg, var(--brand-1-strong) 0%, var(--brand-2-strong) 100%);
        }

        .alert {
            padding: 0.6rem 1rem;
            border-radius: 5px;
            font-size: 0.8rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .alert-error {
            background: #fde8e8;
            color: #a01a1a;
            border: 1px solid #f5b7b7;
        }

        .alert-success {
            background: #e6f4ea;
            color: #155724;
            border: 1px solid #b7dfbe;
        }

        .ann-item {
            padding: 0.8rem 0;
            border-bottom: 1px solid var(--border-soft);
        }

        .ann-item:last-child {
            border-bottom: none;
        }

        .ann-date {
            font-size: 0.7rem;
            color: var(--text-muted);
            font-weight: 700;
            margin-bottom: 0.2rem;
        }

        .ann-text {
            font-size: 0.8rem;
            color: var(--text-primary);
            line-height: 1.4;
        }

        .ann-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 0.5rem;
        }

        .ann-content {
            flex: 1;
        }

        .btn-delete {
            padding: 0.3rem 0.6rem;
            background: #dc3545;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            transition: background 0.2s;
        }

        .btn-delete:hover {
            background: #c82333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
            margin-top: 1rem;
        }

        table th {
            background: var(--border-soft);
            padding: 0.5rem;
            text-align: left;
            font-weight: 700;
            color: var(--text-primary);
        }

        table td {
            padding: 0.5rem;
            border-bottom: 1px solid var(--border-soft);
        }

        table tr:hover {
            background: #f8faf9;
        }

        .chart-container {
            position: relative;
            width: 100%;
            height: 250px;
            padding: 0.5rem 0;
        }

        .chart-wrapper {
            position: relative;
            width: 100%;
            height: 100%;
        }

        canvas {
            max-height: 350px;
        }

        .chart-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
            justify-content: center;
            font-size: 0.75rem;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
    </style>
</head>

<body>

    <!-- NAVBAR -->
    <nav>
        <span class="nav-brand">College of Computer Studies Admin</span>
        <ul class="nav-links">
            <li><a href="admin.php">Home</a></li>
            <li><a href="admin-search.php">Search</a></li>
            <li><a href="admin-students.php">Students</a></li>
            <li><a href="admin-sitin.php">Active Sit-In</a></li>
            <li><a href="admin-records.php">View Sit-In Records</a></li>
            <li><a href="admin-reports.php">Sit-In Reports</a></li>
            <li><a href="admin-feedback.php">Feedback Reports</a></li>
            <li><a href="admin-reservations.php">Reservation</a></li>
            <li><a href="logout.php" class="logout-btn">Log out</a></li>
        </ul>
    </nav>

    <div class="admin-wrap">
        <div class="admin-grid">

            <!-- LEFT: Statistics -->
            <div class="card">
                <div class="card-head">📊 Statistics</div>
                <div class="card-body">
                    <div class="stat-box">
                        <div class="stat-label">Students Registered</div>
                        <div class="stat-value"><?= $total_students ?></div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label">Currently Sit-In</div>
                        <div class="stat-value"><?= $current_sitin ?></div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label">Total Sit-In</div>
                        <div class="stat-value"><?= $total_sitin ?></div>
                    </div>

                    <!-- Analytics Charts -->
                    <div style="border-top: 1px solid var(--border-soft); padding-top: 1.5rem; margin-top: 1.5rem;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                            <!-- Lab Room Analytics -->
                            <div>
                                <h3
                                    style="font-size: 0.85rem; margin-bottom: 0.8rem; font-family: 'Merriweather', serif; font-weight: 600; color: var(--text-primary);">
                                    📍 Lab Room</h3>
                                <?php if (empty($lab_room_data)): ?>
                                    <div
                                        style="text-align: center; color: var(--text-muted); padding: 1rem; font-size: 0.8rem;">
                                        No data available
                                    </div>
                                <?php else: ?>
                                    <div class="chart-container" style="height: 250px;">
                                        <div class="chart-wrapper">
                                            <canvas id="labRoomChart"></canvas>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Programming Language Analytics -->
                            <div>
                                <h3
                                    style="font-size: 0.85rem; margin-bottom: 0.8rem; font-family: 'Merriweather', serif; font-weight: 600; color: var(--text-primary);">
                                    💻 Programming Languages</h3>
                                <?php if (empty($programming_language_data)): ?>
                                    <div
                                        style="text-align: center; color: var(--text-muted); padding: 1rem; font-size: 0.8rem;">
                                        No data available
                                    </div>
                                <?php else: ?>
                                    <div class="chart-container" style="height: 250px;">
                                        <div class="chart-wrapper">
                                            <canvas id="languageChart"></canvas>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT: Announcements -->
            <div class="card">
                <div class="card-head">📢 Announcement</div>
                <div class="card-body">
                    <?php if ($ann_error): ?>
                        <div class="alert alert-error"><?= htmlspecialchars($ann_error) ?></div><?php endif; ?>
                    <?php if ($ann_success): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($ann_success) ?></div><?php endif; ?>

                    <form method="POST" action="admin.php">
                        <div class="form-group">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Message</label>
                            <textarea class="form-control" name="content" required></textarea>
                        </div>
                        <button type="submit" class="btn-submit">Submit</button>
                    </form>

                    <div style="margin-top: 1.5rem;">
                        <h3
                            style="font-size: 0.85rem; margin-bottom: 0.8rem; font-family: 'Merriweather', serif; font-weight: 600; color: var(--text-primary);">
                            Posted Announcement</h3>
                        <?php if (empty($announcements)): ?>
                            <div style="color: var(--text-muted); font-size: 0.8rem;">No announcements yet.</div>
                        <?php else: ?>
                            <?php foreach ($announcements as $ann): ?>
                                <div class="ann-item"
                                    style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem; padding: 0.8rem 0; border-bottom: 1px solid var(--border-soft);">
                                    <div class="ann-content">
                                        <div class="ann-date">CCS Admin | <?= date("Y-M-d", strtotime($ann["created_at"])) ?>
                                        </div>
                                        <div class="ann-text"><?= nl2br(htmlspecialchars($ann["content"])) ?></div>
                                    </div>
                                    <form method="POST" action="admin.php" style="display: inline;"
                                        onsubmit="return confirm('Are you sure you want to delete this announcement?');">
                                        <input type="hidden" name="delete_id" value="<?= $ann["id"] ?>">
                                        <button type="submit" class="btn-delete">Delete</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        // Lab Room Chart Data
        const labRoomLabels = <?= json_encode(array_column($lab_room_data, 'lab_room')) ?>;
        const labRoomCounts = <?= json_encode(array_column($lab_room_data, 'count')) ?>;

        // Programming Language Chart Data
        const languageLabels = <?= json_encode(array_column($programming_language_data, 'purpose')) ?>;
        const languageCounts = <?= json_encode(array_column($programming_language_data, 'count')) ?>;

        // Lab Room Color Map
        const labRoomColorMap = {
            '524': '#FF6B6B',    // Red
            '544': '#4ECDC4',    // Teal
            '526': '#45B7D1',    // Blue
            '530': '#FFA07A',    // Light Salmon
            '528': '#98D8C8'     // Mint
        };

        // Programming Language Color Map
        const languageColorMap = {
            'C Programming': '#2E86AB',     // Navy Blue
            'C++': '#A23B72',               // Purple
            'Java': '#F18F01',              // Orange
            'Python': '#3A86FF',            // Bright Blue
            'JavaScript': '#FB5607',        // Red Orange
            'PHP': '#7209B7',               // Deep Purple
            'C#': '#06A77D'                 // Teal
        };

        // Get lab room colors
        function getLabRoomColors() {
            return labRoomLabels.map(lab => labRoomColorMap[lab] || '#95E1D3');
        }

        // Get language colors
        function getLanguageColors() {
            return languageLabels.map(lang => languageColorMap[lang] || '#95E1D3');
        }

        // Lab Room Chart
        if (labRoomLabels.length > 0) {
            const labRoomCtx = document.getElementById('labRoomChart').getContext('2d');
            const labRoomChart = new Chart(labRoomCtx, {
                type: 'doughnut',
                data: {
                    labels: labRoomLabels.map(lab => 'Lab ' + lab),
                    datasets: [{
                        data: labRoomCounts,
                        backgroundColor: getLabRoomColors(),
                        borderColor: '#ffffff',
                        borderWidth: 2,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                font: {
                                    size: 12,
                                    weight: '600'
                                },
                                color: '#1f2f27',
                                padding: 15,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return label + ': ' + value + ' (' + percentage + '%)';
                                }
                            },
                            backgroundColor: 'rgba(0,0,0,0.8)',
                            titleFont: { size: 13, weight: 'bold' },
                            bodyFont: { size: 12 },
                            padding: 10,
                            displayColors: true
                        }
                    }
                }
            });
        }

        // Programming Language Chart
        if (languageLabels.length > 0) {
            const languageCtx = document.getElementById('languageChart').getContext('2d');
            const languageChart = new Chart(languageCtx, {
                type: 'doughnut',
                data: {
                    labels: languageLabels,
                    datasets: [{
                        data: languageCounts,
                        backgroundColor: getLanguageColors(),
                        borderColor: '#ffffff',
                        borderWidth: 2,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                font: {
                                    size: 12,
                                    weight: '600'
                                },
                                color: '#1f2f27',
                                padding: 15,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return label + ': ' + value + ' (' + percentage + '%)';
                                }
                            },
                            backgroundColor: 'rgba(0,0,0,0.8)',
                            titleFont: { size: 13, weight: 'bold' },
                            bodyFont: { size: 12 },
                            padding: 10,
                            displayColors: true
                        }
                    }
                }
            });
        }

        // Real-time refresh - Update charts every 30 seconds
        setInterval(function () {
            fetch(window.location.href)
                .then(response => response.text())
                .then(html => {
                    // Parse the new data from the page
                    const parser = new DOMParser();
                    const newDoc = parser.parseFromString(html, 'text/html');

                    // You can add logic here to update charts if needed
                    // For now, page refresh happens automatically
                })
                .catch(error => console.log('Auto-refresh error:', error));
        }, 30000); // Refresh every 30 seconds
    </script>