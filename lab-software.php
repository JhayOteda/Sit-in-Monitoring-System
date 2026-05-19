<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}
require 'db.php';
$user_id = $_SESSION["user_id"];
$role = $_SESSION["role"] ?? "student";

// Fetch unread notifications for student role
$announcements = [];
$unread_count = 0;
if ($role === "student" && $user_id) {
    try {
        $ann_stmt = $pdo->prepare("SELECT a.id, a.title, a.content, a.created_at, 
                                   IF(ar.id IS NOT NULL, 1, 0) as is_read
                                   FROM announcements a 
                                   LEFT JOIN announcement_reads ar ON a.id = ar.announcement_id AND ar.user_id = ?
                                   ORDER BY a.created_at DESC LIMIT 10");
        $ann_stmt->execute([$user_id]);
        $announcements = $ann_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($announcements as $ann) {
            if (!$ann['is_read']) {
                $unread_count++;
            }
        }
    } catch (Exception $e) {}
}

// Fetch dynamic laboratory software
$labs = ['524', '526', '528', '530', '544'];
$lab_software = [];
foreach ($labs as $lab) {
    $lab_software[$lab] = [];
}

try {
    $stmt = $pdo->query("SELECT ls.lab_room, s.name, s.version FROM lab_software ls JOIN software s ON ls.software_id = s.id ORDER BY s.name ASC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (isset($lab_software[$row['lab_room']])) {
            $lab_software[$row['lab_room']][] = $row;
        }
    }
} catch (Exception $e) {
    // Fail-safe
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS | Lab Software Availability</title>
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@700&family=Nunito+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/dark-mode.css?v=1779200619">
    <link rel="stylesheet" href="assets/responsive.css">
    <script src="assets/dark-mode.js?v=1779200619" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --bg-start: #e7f2eb; --bg-end: #d3e7db; --nav-bg: #1f4f3c; --nav-text: #edf7f2;
            --card-bg: rgba(255, 255, 255, 0.85); --card-border: 1px solid rgba(255, 255, 255, 0.4);
            --card-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.08); --text-primary: #1f2f27;
            --text-muted: #607367; --border-soft: #d0dfd6; --input-bg: #f5faf7;
            --brand-1: #2f7a59; --brand-2: #245f45;
        }
        html.dark-mode {
            --card-bg: rgba(30, 35, 40, 0.85);
            --card-border: 1px solid rgba(255, 255, 255, 0.08);
            --card-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
            --text-primary: #f0f5f2;
            --text-muted: #a0b2a7;
            --border-soft: #303d36;
            --input-bg: #1d2220;
        }
        body {
            font-family: 'Nunito Sans', sans-serif;
            background: linear-gradient(135deg, var(--bg-start) 0%, var(--bg-end) 100%);
            min-height: 100vh;
        }
        
        /* NAVBAR Styles */
        .d-nav { background: var(--nav-bg); display: flex; align-items: center; justify-content: space-between; padding: 0 1.5rem; height: 48px; position: sticky; top: 0; z-index: 200; box-shadow: 0 2px 8px rgba(0,0,0,0.25); }
        .d-nav-brand { color: var(--nav-text); font-size: 0.95rem; font-weight: 600; font-family: 'Merriweather', serif; }
        .d-nav-links { display: flex; align-items: center; list-style: none; gap: 0.1rem; }
        .d-nav-links a { color: var(--nav-text); text-decoration: none; font-size: 0.9rem; padding: 0.35rem 0.7rem; border-radius: 4px; transition: background 0.15s; white-space: nowrap; display: block; }
        .d-nav-links a:hover { background: rgba(255,255,255,0.14); }
        .d-logout { background: var(--brand-1) !important; font-weight: 700 !important; margin-left: 0.25rem; }
        .d-logout:hover { background: var(--brand-2) !important; }

        /* Notification dropdown */
        .d-dropdown { position: relative; }
        .d-notification-badge {
            display: inline-block; position: absolute; top: -6px; right: -8px; background: #dc3545; color: #fff;
            border-radius: 50%; width: 20px; height: 20px; font-size: 0.65rem; font-weight: 700; display: flex;
            align-items: center; justify-content: center; box-shadow: 0 2px 6px rgba(220, 53, 69, 0.4);
        }
        .d-dd-menu {
            display: none; position: absolute; top: calc(100% + 4px); left: 0; background: var(--card-bg);
            border: 1px solid var(--border-soft); border-radius: 4px; min-width: 280px; max-height: 400px;
            overflow-y: auto; box-shadow: 0 4px 14px rgba(0, 0, 0, 0.14); z-index: 999;
        }
        .d-dropdown:hover .d-dd-menu { display: block; }
        .d-dd-menu .d-dd-header { padding: 0.75rem 1rem; border-bottom: 1px solid var(--border-soft); font-weight: 700; font-size: 0.8rem; color: var(--text-primary); }
        .d-dd-menu .d-dd-item { padding: 0.75rem 1rem; border-bottom: 1px solid var(--border-soft); cursor: pointer; transition: background 0.2s; }
        .d-dd-menu .d-dd-item:hover { background: rgba(0, 0, 0, 0.04); }
        .d-dd-item-date { font-size: 0.7rem; color: var(--text-muted); margin-bottom: 0.2rem; }
        .d-dd-item-title { font-size: 0.8rem; font-weight: 700; color: var(--text-primary); }
        .d-dd-item-content { font-size: 0.75rem; color: var(--text-muted); }
        .d-dd-empty { padding: 1.5rem; text-align: center; font-size: 0.8rem; color: var(--text-muted); }

        .d-wrap {
            max-width: 1200px; margin: 2.2rem auto; padding: 0 1.5rem; min-height: calc(100vh - 120px);
        }

        /* Header Card */
        .lb-header-card {
            background: var(--card-bg); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            border: var(--card-border); border-radius: 16px; box-shadow: var(--card-shadow);
            padding: 2.2rem; margin-bottom: 2rem; position: relative; overflow: hidden;
        }
        .lb-header-card h1 { font-family: 'Merriweather', serif; color: var(--brand-1); font-size: 1.8rem; margin-bottom: 0.5rem; }
        .lb-header-card p { color: var(--text-muted); font-size: 0.95rem; line-height: 1.5; }

        /* Search Section */
        .search-container {
            background: var(--card-bg); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            border: var(--card-border); border-radius: 16px; box-shadow: var(--card-shadow);
            padding: 1.5rem; margin-bottom: 2rem;
        }
        .search-wrapper { position: relative; display: flex; align-items: center; }
        .search-input {
            width: 100%; padding: 0.75rem 1rem 0.75rem 2.8rem; border: 1.5px solid var(--border-soft);
            border-radius: 10px; background: var(--input-bg); font-family: inherit; font-size: 0.95rem;
            color: var(--text-primary); transition: all 0.3s ease;
        }
        .search-input:focus {
            outline: none; border-color: var(--brand-1); box-shadow: 0 0 0 3px rgba(47, 122, 89, 0.15);
        }
        .search-icon {
            position: absolute; left: 1rem; color: var(--text-muted); display: flex; align-items: center; pointer-events: none;
        }

        /* Laboratory Grid Layout */
        .lab-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 1.5rem;
        }
        .lab-card {
            background: var(--card-bg); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            border: var(--card-border); border-radius: 16px; box-shadow: var(--card-shadow);
            padding: 1.5rem; transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
            display: flex; flex-direction: column; height: 100%;
        }
        .lab-card:hover {
            transform: translateY(-4px); box-shadow: 0 12px 40px rgba(31, 38, 135, 0.15); border-color: rgba(47, 122, 89, 0.4);
        }
        .lab-card-header {
            display: flex; align-items: center; justify-content: space-between; border-bottom: 1.5px solid var(--border-soft);
            padding-bottom: 0.8rem; margin-bottom: 1rem;
        }
        .lab-title-group { display: flex; align-items: center; gap: 0.6rem; }
        .lab-icon {
            background: #ffffff; 
            border: 1.5px solid var(--border-soft);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            color: var(--brand-1); 
            width: 40px; 
            height: 40px;
            border-radius: 10px; 
            display: flex; 
            align-items: center; 
            justify-content: center;
        }
        html.dark-mode .lab-icon {
            background: #252d33;
            border-color: #303d36;
        }
        .lab-name { font-family: 'Merriweather', serif; font-size: 1.2rem; color: var(--brand-1); font-weight: 700; }
        .lab-badge {
            background: #edf7f2; color: var(--brand-1); font-size: 0.75rem; font-weight: 700;
            padding: 0.25rem 0.6rem; border-radius: 12px;
        }
        html.dark-mode .lab-badge { background: rgba(47, 122, 89, 0.2); color: #72cca1; }

        /* Software List */
        .software-list {
            list-style: none; display: flex; flex-direction: column; gap: 0.6rem; flex-grow: 1;
            max-height: 250px; overflow-y: auto; padding-right: 0.3rem;
        }
        .software-list::-webkit-scrollbar { width: 4px; }
        .software-list::-webkit-scrollbar-track { background: transparent; }
        .software-list::-webkit-scrollbar-thumb { background: var(--border-soft); border-radius: 10px; }

        .software-item {
            display: flex; align-items: center; justify-content: space-between; padding: 0.55rem 0.7rem;
            background: rgba(255, 255, 255, 0.4); border: 1px solid rgba(255, 255, 255, 0.6);
            border-radius: 8px; font-size: 0.85rem; transition: all 0.2s ease;
        }
        html.dark-mode .software-item { background: rgba(255, 255, 255, 0.03); border-color: rgba(255, 255, 255, 0.05); }
        .software-item:hover { background: rgba(47, 122, 89, 0.06); border-color: rgba(47, 122, 89, 0.2); }
        .software-name { font-weight: 600; color: var(--text-primary); }
        .software-ver {
            font-size: 0.72rem; color: var(--text-muted); background: rgba(0, 0, 0, 0.05);
            padding: 0.1rem 0.4rem; border-radius: 4px; font-weight: 700;
        }
        html.dark-mode .software-ver { background: rgba(255, 255, 255, 0.08); color: #ccc; }
        
        .no-software { font-size: 0.8rem; color: var(--text-muted); text-align: center; padding: 1.5rem 0; font-style: italic; }

        /* Highlight Matches */
        .software-item.highlight-match {
            background: rgba(47, 122, 89, 0.18) !important;
            border-color: var(--brand-1) !important;
            box-shadow: 0 0 6px rgba(47, 122, 89, 0.25);
        }
        .software-item.dim-item { opacity: 0.35; }
        .lab-card.hide-card { display: none; }

        @media (max-width: 768px) {
            .lab-grid { grid-template-columns: 1fr; }
            .d-nav-links { display: none; }
        }
            <style>
        /* Profile Dropdown Styles */
        .profile-dropdown-container {
            position: relative;
            margin-left: 0.5rem;
        }
        
        .profile-trigger {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            padding: 0.25rem 0.6rem;
            border-radius: 50px;
            background: rgba(255, 255, 255, 0.1);
            transition: all 0.2s ease;
            user-select: none;
        }
        
        .profile-trigger:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .profile-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--brand-1, #2f7a59);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
            border: 2px solid rgba(255,255,255,0.8);
            text-transform: uppercase;
        }
        
        .profile-info {
            display: flex;
            flex-direction: column;
            line-height: 1.1;
        }
        
        .profile-name {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--nav-text, #fff);
        }
        
        .profile-role {
            font-size: 0.65rem;
            color: rgba(255, 255, 255, 0.8);
            text-transform: capitalize;
        }
        
        .profile-caret {
            margin-left: 0.2rem;
            color: var(--nav-text, #fff);
            transition: transform 0.2s;
        }
        
        .profile-dropdown-container.active .profile-caret {
            transform: rotate(180deg);
        }
        
        .profile-menu {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            background: var(--card-bg, #fff);
            border-radius: 12px;
            min-width: 220px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            padding: 0.5rem;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            z-index: 1000;
        }
        
        html.dark-mode .profile-menu {
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .profile-dropdown-container.active .profile-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .profile-menu-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.6rem 0.8rem;
            color: var(--text-primary, #333) !important;
            text-decoration: none !important;
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: 8px;
            transition: background 0.15s;
            cursor: pointer;
            background: transparent !important;
            box-sizing: border-box;
            width: 100%;
        }
        
        .profile-menu-item:hover {
            background: var(--input-bg, #f4f4f4) !important;
        }
        
        .profile-menu-item svg {
            width: 18px;
            height: 18px;
            color: var(--text-muted, #666);
        }
        
        .profile-menu-divider {
            height: 1px;
            background: var(--border-soft, #eee);
            margin: 0.4rem 0;
        }
        
        .text-danger {
            color: #dc3545 !important;
        }
        
        .text-danger svg {
            color: #dc3545 !important;
        }
        
        .theme-item {
            justify-content: space-between;
        }
        
        .theme-label-wrap {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <nav class="d-nav">
        <span class="d-nav-brand">CCS Student | Lab Software</span>
        <ul class="d-nav-links">
            <li class="d-dropdown">
                <a href="#" style="position: relative; padding: 0.35rem 0.5rem; display: flex; align-items: center;"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: block;"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg><?php if ($unread_count > 0): ?><span class="d-notification-badge"><?= $unread_count ?></span><?php endif; ?></a>
                <div class="d-dd-menu">
                    <?php if (empty($announcements)): ?>
                        <div class="d-dd-empty">No announcements</div>
                    <?php else: ?>
                        <div class="d-dd-header"> <?= $unread_count ?> New Announcement<?= $unread_count !== 1 ? 's' : '' ?></div>
                        <?php foreach ($announcements as $ann): ?>
                            <div class="d-dd-item" id="ann-item-<?= $ann['id'] ?>">
                                <div class="d-dd-item-date">CCS Admin | <?= date("M d, Y", strtotime($ann["created_at"])) ?></div>
                                <?php if (!empty($ann['title'])): ?>
                                    <div class="d-dd-item-title"><?= htmlspecialchars($ann['title']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($ann['content'])): ?>
                                    <div class="d-dd-item-content"><?= htmlspecialchars(substr($ann['content'], 0, 100)) ?>...</div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </li>
            <li><a href="dashboard.php">Home</a></li>
            
            <li><a href="history.php">History</a></li>
            <li><a href="reservation.php">Reservation</a></li>
            <li><a href="lab-software.php" style="background: rgba(255,255,255,0.15)">Lab Software</a></li>
            <li><a href="leaderboard.php">Leaderboard</a></li>
                        <li class="profile-dropdown-container" id="profileDropdownContainer">
                <div class="profile-trigger" onclick="toggleProfileDropdown(event)">
                    <div class="profile-avatar">
                        <?= strtoupper(substr($_SESSION['name'] ?? 'U', 0, 1)) ?>
                    </div>
                    <div class="profile-info">
                        <span class="profile-name"><?= htmlspecialchars($_SESSION['name'] ?? 'User') ?></span>
                        <span class="profile-role"><?= htmlspecialchars(ucfirst($_SESSION['role'] ?? 'Student')) ?></span>
                    </div>
                    <svg class="profile-caret" viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </div>
                <div class="profile-menu" id="profileMenu">
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <!-- Admin Profile (Optional, can point to settings if exists) -->
                    <?php else: ?>
                        <a href="dashboard.php?edit=true" class="profile-menu-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg> 
                            Edit Profile
                        </a>
                        <div class="profile-menu-divider"></div>
                    <?php endif; ?>
                    
                    <div class="profile-menu-item theme-item">
                        <div id="darkModeContainer" style="display:flex; justify-content:center; width:100%;"></div>
                    </div>
                    <div class="profile-menu-divider"></div>
                    <a href="logout.php" class="profile-menu-item text-danger">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg> 
                        Log out
                    </a>
                </div>
            </li>
        </ul>
    </nav>

    <div class="d-wrap">
        
        <!-- Header banner card -->
        <div class="lb-header-card">
            <h1><svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 0.6rem; color: var(--brand-1);"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>Laboratory Software & Tools</h1>
            <p>Explore software, compilers, development tools, and database frameworks currently set up and active across each laboratory room.</p>
        </div>

        <!-- Live Search Bar -->
        <div class="search-container">
            <div class="search-wrapper">
                <span class="search-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                </span>
                <input type="text" id="softwareSearch" class="search-input" placeholder="Search software names or versions (e.g., Chrome, VS Code, Python)..." onkeyup="filterSoftware()">
            </div>
        </div>

        <!-- Laboratory Grid -->
        <div class="lab-grid">
            <?php foreach ($labs as $lab): ?>
                <?php $list = $lab_software[$lab]; ?>
                <div class="lab-card" data-room="<?= $lab ?>">
                    <div class="lab-card-header">
                        <div class="lab-title-group">
                            <span class="lab-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                            </span>
                            <span class="lab-name">Lab Room <?= $lab ?></span>
                        </div>
                        <span class="lab-badge"><?= count($list) ?> Installed</span>
                    </div>
                    
                    <ul class="software-list">
                        <?php if (empty($list)): ?>
                            <div class="no-software">No software listed for this room.</div>
                        <?php else: ?>
                            <?php foreach ($list as $sw): ?>
                                <li class="software-item" data-name="<?= htmlspecialchars(strtolower($sw['name'])) ?>">
                                    <span class="software-name"><?= htmlspecialchars($sw['name']) ?></span>
                                    <?php if (!empty($sw['version'])): ?>
                                        <span class="software-ver"><?= htmlspecialchars($sw['version']) ?></span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>

    </div>

    <script>
        function filterSoftware() {
            const query = document.getElementById('softwareSearch').value.toLowerCase().trim();
            const labCards = document.querySelectorAll('.lab-card');
            
            labCards.forEach(card => {
                const items = card.querySelectorAll('.software-list .software-item');
                let matchesInCard = 0;
                let hasItems = items.length > 0;
                
                if (query === '') {
                    // Reset all
                    card.classList.remove('hide-card');
                    items.forEach(item => {
                        item.classList.remove('highlight-match', 'dim-item');
                    });
                    return;
                }
                
                items.forEach(item => {
                    const swName = item.getAttribute('data-name');
                    if (swName.includes(query)) {
                        item.classList.add('highlight-match');
                        item.classList.remove('dim-item');
                        matchesInCard++;
                    } else {
                        item.classList.remove('highlight-match');
                        item.classList.add('dim-item');
                    }
                });
                
                // Hide card if searching and no matches in this laboratory
                if (hasItems && matchesInCard === 0) {
                    card.classList.add('hide-card');
                } else {
                    card.classList.remove('hide-card');
                }
            });
        }
    </script>
<script>
        function toggleProfileDropdown(event) {
            event.stopPropagation();
            const container = document.getElementById('profileDropdownContainer');
            if (container) {
                container.classList.toggle('active');
            }
        }

        window.addEventListener('click', function(event) {
            const container = document.getElementById('profileDropdownContainer');
            if (container && !container.contains(event.target)) {
                container.classList.remove('active');
            }
        });
</script>
</body>
</html>
