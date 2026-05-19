<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit;
}
require 'db.php';

$labs = ['524', '526', '528', '530', '544'];
$active_lab = $_GET['lab'] ?? '524';
if (!in_array($active_lab, $labs)) $active_lab = '524';

// Default 40 PCs per lab
$total_pcs = 40;
try {
    $stmt = $pdo->prepare("SELECT total_pcs FROM lab_pcs WHERE lab_room = ?");
    $stmt->execute([$active_lab]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $total_pcs = (int)$result['total_pcs'];
    }
} catch (Exception $e) {}

// Get PC statuses
$pc_statuses = [];
try {
    $stmt = $pdo->prepare("SELECT pc_number, status FROM pc_statuses WHERE lab_room = ?");
    $stmt->execute([$active_lab]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $pc_statuses[$row['pc_number']] = $row['status'];
    }
} catch (Exception $e) {}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PC Control Center</title>
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@700&family=Nunito+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/dark-mode.css">
    <script src="assets/dark-mode.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --bg-start: #e7f2eb; --bg-end: #d3e7db;
            --nav-bg: #1f4f3c; --nav-text: #edf7f2;
            --card-bg: #ffffff; --text-primary: #1f2f27;
            --text-muted: #607367; --border-soft: #d0dfd6;
            --input-bg: #f5faf7; --brand-1: #2f7a59;
            --brand-2: #245f45;
        }
        body {
            font-family: 'Nunito Sans', sans-serif;
            background: linear-gradient(135deg, var(--bg-start) 0%, var(--bg-end) 100%);
            min-height: 100vh;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        .header h1 {
            font-family: 'Merriweather', serif;
            color: var(--brand-1);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.8rem;
        }
        .btn-back {
            background: var(--card-bg);
            border: 1px solid var(--border-soft);
            color: var(--text-primary);
            padding: 0.6rem 1.2rem;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }
        .btn-back:hover {
            background: var(--border-soft);
        }
        .card {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid var(--border-soft);
            padding-bottom: 1rem;
            overflow-x: auto;
        }
        .tab {
            padding: 0.6rem 1.2rem;
            border-radius: 6px;
            text-decoration: none;
            color: var(--text-primary);
            font-weight: 600;
            border: 1px solid var(--border-soft);
            background: var(--card-bg);
            transition: all 0.2s;
            white-space: nowrap;
        }
        .tab.active {
            background: var(--nav-bg);
            color: white;
            border-color: var(--nav-bg);
        }
        .controls {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .btn-control {
            padding: 0.6rem 1.2rem;
            border-radius: 6px;
            font-weight: 600;
            border: 1px solid var(--border-soft);
            background: var(--card-bg);
            color: var(--text-primary);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }
        .btn-control.multi-select.active {
            background: var(--nav-bg);
            color: white;
            border-color: var(--nav-bg);
        }
        .btn-control.available { color: #155724; border-color: #c3e6cb; background: #d4edda; }
        .btn-control.maintenance { color: #856404; border-color: #ffeeba; background: #fff3cd; }
        .pc-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 1rem;
        }
        .pc-cell {
            aspect-ratio: 1;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 2px solid var(--border-soft);
            transition: all 0.2s;
            position: relative;
            background: var(--card-bg);
        }
        .pc-cell.available { border-color: #c3e6cb; background: #e6f4ea; }
        .pc-cell.maintenance { border-color: #ffeeba; background: #fff8e1; }
        .pc-cell.selected { box-shadow: 0 0 0 3px var(--brand-1); transform: scale(1.05); z-index: 10; }
        .pc-cell.selected::after {
            content: "✓";
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--brand-1);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }
        .pc-icon {
            width: 32px;
            height: 32px;
            margin-bottom: 0.5rem;
        }
        .pc-cell.available .pc-icon { color: #28a745; }
        .pc-cell.maintenance .pc-icon { color: #ffc107; }
        .pc-number {
            font-weight: 700;
            font-size: 0.9rem;
            color: var(--text-primary);
        }
        .pc-status-text {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            margin-top: 0.2rem;
        }
        .pc-cell.available .pc-status-text { color: #28a745; }
        .pc-cell.maintenance .pc-status-text { color: #ffc107; }
        
        .floating-action-bar {
            position: fixed;
            bottom: -100px;
            left: 50%;
            transform: translateX(-50%);
            background: #1e293b;
            padding: 0.75rem 2rem;
            border-radius: 50px;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            transition: bottom 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            z-index: 1000;
            width: max-content;
            max-width: 95vw;
        }
        .floating-action-bar.visible {
            bottom: 30px;
        }
        .fab-text {
            color: white;
            font-weight: 700;
            font-size: 0.9rem;
            white-space: nowrap;
        }
        .fab-btn {
            padding: 0.6rem 1.4rem;
            border-radius: 50px;
            border: none;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            white-space: nowrap;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .fab-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        .fab-btn.avail { background: #10b981; color: white; }
        .fab-btn.avail:hover { background: #059669; }
        .fab-btn.maint { background: #f59e0b; color: white; }
        .fab-btn.maint:hover { background: #d97706; }
        .fab-btn.cancel { background: transparent; color: #cbd5e1; border: 1px solid rgba(255,255,255,0.25); }
        .fab-btn.cancel:hover { background: rgba(255,255,255,0.1); color: #white; }
        
        html.dark-mode .card { box-shadow: 0 4px 15px rgba(0,0,0,0.3); }
        html.dark-mode .btn-back { background: var(--input-bg); }
        html.dark-mode .btn-back:hover { background: var(--border-soft); }
        html.dark-mode .pc-cell.available { background: rgba(40, 167, 69, 0.1); border-color: rgba(40, 167, 69, 0.3); }
        html.dark-mode .pc-cell.maintenance { background: rgba(255, 193, 7, 0.1); border-color: rgba(255, 193, 7, 0.3); }
        html.dark-mode .pc-number { color: #fff; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"></path><line x1="12" y1="2" x2="12" y2="12"></line></svg>
                PC Control Center
            </h1>
            <a href="admin-lab-assets.php" class="btn-back">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                Back to Lab Assets
            </a>
        </div>

        <div class="card">
            <div class="tabs">
                <?php foreach ($labs as $lab): ?>
                    <a href="?lab=<?= $lab ?>" class="tab <?= $lab === $active_lab ? 'active' : '' ?>">Room <?= $lab ?></a>
                <?php endforeach; ?>
            </div>

            <h2 style="margin-bottom: 0.5rem; color: var(--text-primary);">Room <?= $active_lab ?> Computer Layout</h2>
            <p style="color: var(--text-muted); margin-bottom: 2rem; font-size: 0.9rem;">Select a PC below to manage or update its current status. Any changes will automatically appear on the student dashboard in real time.</p>

            <div class="controls">
                <button class="btn-control multi-select" id="multiSelectBtn" onclick="toggleMultiSelect()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
                    Multi-Select Mode
                </button>
                <button class="btn-control available" onclick="setAll('Available')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                    Set All Available
                </button>
                <button class="btn-control maintenance" onclick="setAll('Maintenance')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>
                    Set All Maintenance
                </button>
            </div>

            <div class="pc-grid" id="pcGrid">
                <?php for ($i = 1; $i <= $total_pcs; $i++): 
                    $status = $pc_statuses[$i] ?? 'Available';
                    $is_avail = $status === 'Available';
                ?>
                    <div class="pc-cell <?= $is_avail ? 'available' : 'maintenance' ?>" data-pc="<?= $i ?>" data-status="<?= $status ?>" onclick="pcClick(this)">
                        <?php if ($is_avail): ?>
                            <svg class="pc-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                        <?php else: ?>
                            <svg class="pc-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>
                        <?php endif; ?>
                        <div class="pc-number">PC <?= $i ?></div>
                        <div class="pc-status-text"><?= $is_avail ? 'Available' : 'Under Maintenance' ?></div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <div class="floating-action-bar" id="fab">
        <div class="fab-text"><span id="selCount">0</span> PC(s) selected</div>
        <button class="fab-btn avail" onclick="updateSelected('Available')">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            Set Available
        </button>
        <button class="fab-btn maint" onclick="updateSelected('Maintenance')">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>
            Set Maintenance
        </button>
        <button class="fab-btn cancel" onclick="clearSelection()">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            Cancel
        </button>
    </div>

    <script>
        let multiSelectMode = false;
        let selectedPcs = new Set();
        const labRoom = '<?= $active_lab ?>';

        function toggleMultiSelect() {
            multiSelectMode = !multiSelectMode;
            const btn = document.getElementById('multiSelectBtn');
            if (multiSelectMode) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
                clearSelection();
            }
        }

        function pcClick(el) {
            const pc = el.getAttribute('data-pc');
            if (multiSelectMode) {
                if (selectedPcs.has(pc)) {
                    selectedPcs.delete(pc);
                    el.classList.remove('selected');
                } else {
                    selectedPcs.add(pc);
                    el.classList.add('selected');
                }
                updateFab();
            } else {
                // Single click to toggle status
                const currentStatus = el.getAttribute('data-status');
                const newStatus = currentStatus === 'Available' ? 'Maintenance' : 'Available';
                updateStatus([pc], newStatus);
            }
        }

        function updateFab() {
            const fab = document.getElementById('fab');
            const count = document.getElementById('selCount');
            count.innerText = selectedPcs.size;
            if (selectedPcs.size > 0) {
                fab.classList.add('visible');
            } else {
                fab.classList.remove('visible');
            }
        }

        function clearSelection() {
            selectedPcs.clear();
            document.querySelectorAll('.pc-cell').forEach(el => el.classList.remove('selected'));
            updateFab();
        }

        function setAll(status) {
            Swal.fire({
                title: 'Set All ' + status + '?',
                text: "This will update all PCs in Room " + labRoom + " to " + status + ".",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: status === 'Available' ? '#10b981' : '#f59e0b',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, update all'
            }).then((result) => {
                if (result.isConfirmed) {
                    const allPcs = [];
                    for(let i=1; i<=<?= $total_pcs ?>; i++) allPcs.push(i.toString());
                    updateStatus(allPcs, status);
                }
            });
        }

        function updateSelected(status) {
            if (selectedPcs.size === 0) return;
            updateStatus(Array.from(selectedPcs), status);
            clearSelection();
        }

        function updateStatus(pcs, status) {
            fetch('update_pc_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ lab_room: labRoom, pcs: pcs, status: status })
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    // Update UI immediately without reload
                    pcs.forEach(pc => {
                        const el = document.querySelector('.pc-cell[data-pc="'+pc+'"]');
                        if(el) {
                            el.setAttribute('data-status', status);
                            if(status === 'Available') {
                                el.className = 'pc-cell available';
                                el.querySelector('.pc-icon').outerHTML = '<svg class="pc-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>';
                                el.querySelector('.pc-status-text').innerText = 'Available';
                            } else {
                                el.className = 'pc-cell maintenance';
                                el.querySelector('.pc-icon').outerHTML = '<svg class="pc-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>';
                                el.querySelector('.pc-status-text').innerText = 'Under Maintenance';
                            }
                        }
                    });
                    
                    const Toast = Swal.mixin({
                        toast: true, position: 'bottom-end',
                        showConfirmButton: false, timer: 2000,
                        timerProgressBar: true
                    });
                    Toast.fire({ icon: 'success', title: 'PC Status Updated' });
                } else {
                    Swal.fire('Error', data.message || 'Failed to update', 'error');
                }
            })
            .catch(err => {
                Swal.fire('Error', 'Network error occurred', 'error');
            });
        }
    </script>
</body>
</html>
