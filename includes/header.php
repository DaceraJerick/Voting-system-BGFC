<?php
// filename: includes/header.php
// location: includes/header.php

$base_path = '';
if(isset($_SERVER['SCRIPT_NAME'])) {
    $script_name = $_SERVER['SCRIPT_NAME'];
    if(strpos($script_name, '/admin/') !== false || strpos($script_name, '/student/') !== false) {
        $base_path = '../';
    }
}

function checkActiveElection($base_path) {
    if(file_exists($base_path . 'config/database.php') && isset($GLOBALS['pdo'])) {
        try {
            $pdo = $GLOBALS['pdo'];
            $stmt = $pdo->query("SELECT COUNT(*) FROM elections WHERE is_active = 1 AND NOW() BETWEEN start_date AND end_date");
            return $stmt->fetchColumn() > 0;
        } catch(Exception $e) { return false; }
    }
    return false;
}

if(file_exists($base_path . 'includes/election_scheduler.php') && isset($GLOBALS['pdo'])) {
    include $base_path . 'includes/election_scheduler.php';
}

$has_active_election = checkActiveElection($base_path);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>Student Council Voting System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/style.css">
    <script>
    /* Apply saved theme ASAP to prevent flash */
    (function(){
        var t = localStorage.getItem('cv_theme') || 'light';
        document.documentElement.setAttribute('data-theme', t);
    })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
    /* ── Reset & Globals ── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
        --accent:      #6366f1;
        --accent-soft: rgba(99,102,241,0.1);
        --accent-mid:  rgba(99,102,241,0.25);
        --glass-bg:    rgba(255,255,255,0.6);
        --glass-border:rgba(255,255,255,0.82);
        --text-hi:     #1e293b;
        --text-mid:    #475569;
        --text-lo:     #94a3b8;
        --radius:      16px;
        --radius-sm:   10px;
        --blur:        blur(22px) saturate(160%);
        --dark-indigo: #1e1b4b;
        --app-bg:      #f5f7fb;
    }

    html { -webkit-tap-highlight-color: transparent; }

    body {
        font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
        min-height: 100vh;
        background:
            radial-gradient(ellipse at 10% 20%,  rgba(99,179,237,0.22) 0%, transparent 50%),
            radial-gradient(ellipse at 85% 10%,  rgba(167,139,250,0.18) 0%, transparent 45%),
            radial-gradient(ellipse at 60% 80%,  rgba(110,231,183,0.18) 0%, transparent 45%),
            radial-gradient(ellipse at 20% 85%,  rgba(251,191,36,0.13) 0%, transparent 40%),
            #eef2ff;
        background-attachment: fixed;
        overflow-x: hidden;
        color: var(--text-hi);
    }

    /* drifting blobs */
    body::before, body::after {
        content: ''; position: fixed; border-radius: 50%;
        filter: blur(80px); pointer-events: none; z-index: 0;
    }
    body::before {
        width: 480px; height: 480px; top: -100px; left: -100px;
        background: rgba(99,179,237,0.18);
        animation: blobDrift 13s ease-in-out infinite alternate;
    }
    body::after {
        width: 380px; height: 380px; bottom: -80px; right: -80px;
        background: rgba(167,139,250,0.18);
        animation: blobDrift 16s ease-in-out infinite alternate-reverse;
    }
    @keyframes blobDrift {
        from { transform: translate(0,0) scale(1); }
        to   { transform: translate(40px,30px) scale(1.08); }
    }

    /* ── Navbar ── */
    .navbar {
        padding: 0 !important;
        background: var(--dark-indigo) !important;
        border-bottom: 1px solid rgba(255,255,255,0.05);
        position: sticky; top: 0; z-index: 1000;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }

    .navbar > .container {
        padding: 0 1.25rem;
        min-height: 64px;
        display: flex; align-items: center;
    }

    /* brand */
    .navbar-brand {
        display: flex; align-items: center; gap: 0.6rem;
        font-weight: 800; font-size: 1.15rem;
        color: white !important;
        text-decoration: none; letter-spacing: -0.02em;
        padding: 0;
    }
    .brand-icon {
        width: 36px; height: 36px; border-radius: 10px;
        background: rgba(255,255,255,0.15);
        display: flex; align-items: center; justify-content: center;
        color: white; font-size: 1.2rem;
        flex-shrink: 0;
    }
    .brand-text span { color: #818cf8; }

    /* toggler */
    .navbar-toggler {
        border: none !important;
        padding: 6px !important;
        box-shadow: none !important;
        color: white !important;
    }
    .navbar-toggler-icon {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='%23ffffff' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2.2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e") !important;
        width: 24px; height: 24px;
    }

    /* collapse panel on mobile */
    .navbar-collapse {
        background: rgba(255,255,255,0.92);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius);
        padding: 1rem;
        margin-top: 0.75rem;
        box-shadow: 0 12px 32px rgba(100,120,200,0.12);
    }
    @media(min-width: 992px) {
        .navbar-collapse {
            background: transparent !important;
            backdrop-filter: none !important;
            border: none !important;
            border-radius: 0 !important;
            padding: 0 !important;
            margin-top: 0 !important;
            box-shadow: none !important;
        }
    }

    /* nav links */
    .nav-link {
        display: flex; align-items: center; gap: 0.6rem;
        font-size: 0.875rem; font-weight: 600;
        padding: 0.8rem 1rem !important;
        border-radius: 12px;
        color: rgba(255,255,255,0.7) !important;
        transition: all .2s ease;
    }
    .nav-link:hover {
        color: white !important;
        background: rgba(255,255,255,0.1);
    }
    .nav-link i { font-size: 1.1rem; }

    @media(min-width: 992px) {
        .nav-link { color: rgba(255,255,255,0.7) !important; }
        .nav-link:hover { color: white !important; }
    }

    /* dropdown */
    .dropdown-menu {
        border: 1px solid var(--glass-border) !important;
        border-radius: var(--radius) !important;
        box-shadow: 0 12px 32px rgba(100,120,200,0.14) !important;
        padding: 0.5rem !important;
        margin-top: 0.5rem !important;
        background: rgba(255,255,255,0.95) !important;
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        min-width: 210px;
    }
    .dropdown-item {
        display: flex; align-items: center; gap: 0.7rem;
        padding: 0.65rem 0.9rem !important;
        border-radius: var(--radius-sm) !important;
        font-size: 0.875rem; font-weight: 500;
        color: var(--text-hi) !important;
        transition: all .18s ease;
        min-height: 40px;
    }
    .dropdown-item i { font-size: 1rem; width: 20px; text-align: center; color: var(--text-lo); flex-shrink: 0; }
    .dropdown-item:hover { background: var(--accent-soft) !important; color: var(--accent) !important; }
    .dropdown-item:hover i { color: var(--accent); }
    .dropdown-item.text-danger { color: #ef4444 !important; }
    .dropdown-item.text-danger i { color: #ef4444; }
    .dropdown-item.text-danger:hover { background: rgba(239,68,68,0.08) !important; }
    .dropdown-divider { border-color: rgba(200,210,240,0.4) !important; margin: 0.35rem 0 !important; }
    .dropdown-item-text { font-size: 0.78rem; color: var(--text-lo) !important; padding: 0.4rem 0.9rem !important; }

    /* user pill in nav */
    .user-pill {
        display: flex; align-items: center; gap: 0.65rem;
        background: rgba(255,255,255,0.1);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 12px;
        padding: 0.45rem 1rem !important;
        font-size: 0.85rem; font-weight: 600;
        color: white !important;
        cursor: pointer;
        transition: all .2s;
    }
    .status-bar {
        background: var(--dark-indigo);
        border-bottom: 1px solid rgba(255,255,255,0.05);
        padding: 0.85rem 0;
        color: white;
        font-size: 0.78rem;
        font-weight: 600;
        position: relative; z-index: 100;
        box-shadow: inset 0 -10px 15px -10px rgba(0,0,0,0.2);
    }
    .status-left i { color: #818cf8; font-size: 1rem; }
    .status-left strong { color: white; font-weight: 800; border-bottom: 1px dotted rgba(255,255,255,0.3); }

    /* live badge */
    .badge-live {
        display: inline-flex; align-items: center; gap: 0.4rem;
        background: rgba(34,197,94,0.12);
        border: 1px solid rgba(34,197,94,0.25);
        color: #16a34a;
        border-radius: 99px; padding: 0.25rem 0.75rem;
        font-size: 0.75rem; font-weight: 700;
        letter-spacing: .02em;
    }
    .live-pulse {
        width: 7px; height: 7px; border-radius: 50%;
        background: #22c55e; box-shadow: 0 0 0 3px rgba(34,197,94,0.2);
        animation: pulse 2s infinite;
    }
    @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }

    /* ── Alerts ── */
    .alert {
        border: none !important; border-radius: var(--radius) !important;
        padding: 1rem 1.25rem !important;
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.05);
    }
    .alert-success { background: rgba(220,252,231,0.8) !important; color: #166534 !important; }
    .alert-warning { background: rgba(254,249,195,0.8) !important; color: #854d0e !important; }
    .alert-danger  { background: rgba(254,226,226,0.8) !important; color: #991b1b !important; }
    .alert-info    { background: rgba(219,234,254,0.8) !important; color: #1e40af !important; }
    .btn-close { filter: none !important; opacity: .5; }
    .btn-close:hover { opacity: 1; }

    /* ── Main content wrapper ── */
    main.container {
        position: relative; z-index: 1;
        padding-left: 1rem; padding-right: 1rem;
    }

    /* scrollbar */
    ::-webkit-scrollbar { width: 5px; height: 5px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: rgba(99,102,241,0.2); border-radius: 99px; }
    ::-webkit-scrollbar-thumb:hover { background: rgba(99,102,241,0.4); }

    /* ── Mobile & Desktop Visibility Fix ── */
    @media (max-width: 991px) {
        .navbar { display: flex !important; }
        .main-content { margin-left: 0; }
        .app-layout { display: block; }
    }

    /* Combined Navbar Logic */
    .navbar { display: flex !important; }
    .status-bar, .mobile-nav { display: none !important; }
    
    @media (max-width: 991px) {
        .mobile-nav { display: block !important; }
    }

    /* ── Mobile Bottom Nav (Soft Glass) ── */
    .mobile-nav {
        display: none;
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        background: rgba(255, 255, 255, 0.4);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-top: 1px solid rgba(255, 255, 255, 0.2);
        z-index: 999;
        padding: 0.5rem 0;
        box-shadow: 0 -8px 32px rgba(0,0,0,0.05);
    }

    .mobile-nav-container {
        display: flex;
        justify-content: space-around;
        align-items: center;
        max-width: 600px;
        margin: 0 auto;
    }

    .mobile-nav-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 2px;
        text-decoration: none;
        color: #94a3b8;
        font-size: 0.65rem;
        font-weight: 700;
        transition: all 0.2s;
        padding: 5px 10px;
        border-radius: 12px;
    }

    .mobile-nav-item i {
        font-size: 1.3rem;
        transition: transform 0.2s;
    }

    .mobile-nav-item.active {
        color: #6366f1;
    }
    
    .mobile-nav-item.active i {
        transform: translateY(-2px);
    }

    @media (max-width: 991px) {
        .mobile-nav { display: block; }
        body { padding-bottom: 70px !important; }
        .navbar-collapse { margin-bottom: 10px; }
    }

    /* ── Student / Voter Global UI Kit (Soft Glass) ── */
    .section-label { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 0.75rem; display: block; }
    
    .app-card {
        background: rgba(255, 255, 255, 0.4);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.5);
        border-radius: 24px;
        padding: 1.25rem;
        box-shadow: 0 8px 32px rgba(31, 38, 135, 0.03);
        margin-bottom: 1.5rem;
    }

    .tools-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .tool-tile {
        background: rgba(255, 255, 255, 0.5);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.4);
        border-radius: 20px;
        padding: 1.25rem 1rem;
        text-align: center;
        text-decoration: none;
        color: inherit;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s ease;
    }
    .tool-tile:hover { transform: translateY(-4px); background: rgba(255, 255, 255, 0.7); border-color: #6366f1; }
    .tool-tile i { font-size: 1.5rem; }
    .tool-label { font-size: 0.85rem; font-weight: 800; color: #1e293b; }
    .tool-sub { font-size: 0.65rem; color: #64748b; font-weight: 600; }

    .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.35rem 0.85rem;
        border-radius: 99px;
        font-size: 0.7rem;
        font-weight: 800;
        background: #fef3c7;
        color: #92400e;
    }
    .status-badge.success { background: #dcfce7; color: #166534; }

    /* Icons */
    .icon-blue { color: #3b82f6; }
    .icon-green { color: #10b981; }
    .icon-gold { color: #f59e0b; }
    .icon-purple { color: #8b5cf6; }

    /* Student Navbar (Soft Glass) */
    .navbar-student {
        background: rgba(255, 255, 255, 0.3) !important;
        backdrop-filter: blur(30px) !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    .nav-link-student {
        color: #475569 !important;
        font-weight: 700;
        font-size: 0.9rem;
        padding: 0.5rem 1rem !important;
        border-radius: 12px;
        transition: all 0.2s;
    }
    .nav-link-student:hover, .nav-link-student.active {
        background: rgba(99, 102, 241, 0.08);
        color: #6366f1 !important;
    }
    </style>


</head>
<body>

    <?php 
    if(isset($_SESSION['user_id'])): 
        $user_type = $_SESSION['user_type'] ?? 'voter';
        $display_name = $_SESSION['full_name'] ?? ($_SESSION['username'] ?? ($_SESSION['student_id'] ?? 'User'));
        $parts = explode(' ', $display_name);
        $initials = '';
        foreach($parts as $p) { if(!empty($p)) $initials .= strtoupper(substr($p, 0, 1)); }
        $initials = substr($initials, 0, 2);
    ?>

    <?php if($user_type === 'admin'): ?>
    <!-- ── Precision Admin Top Navbar (Exact Image Match) ── -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top py-2" style="background: rgba(255, 255, 255, 0.2); backdrop-filter: blur(40px); -webkit-backdrop-filter: blur(40px); border-bottom: 1px solid rgba(255, 255, 255, 0.1); box-shadow: 0 4px 24px rgba(0,0,0,0.02) !important;">
        <div class="container container-admin">
            <a class="navbar-brand d-flex align-items-center gap-2" href="<?php echo $base_path; ?>admin/dashboard.php">
                <div class="brand-icon-premium" style="background: #8b5cf6; color: white;"><i class="bi bi-check-square-fill"></i></div>
                <div class="fw-800" style="letter-spacing: -0.02em; font-size: 1.2rem; color: #1e293b;">Council<span style="color: #6366f1;">Vote</span></div>
            </a>
            
            <div class="d-flex align-items-center gap-2 ms-auto me-2 d-lg-none">
                <button class="theme-toggle-pill" onclick="toggleTheme()" title="Toggle Dark/Light Mode" id="themeToggleAdminMobile">
                    <span class="toggle-icon-wrap"><i class="bi bi-moon-fill"></i></span>
                    <span class="toggle-label">Dark</span>
                </button>
            </div>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="adminNavbar">
                <ul class="navbar-nav ms-auto align-items-center gap-4">
                    <li class="nav-item d-none d-lg-flex">
                        <button class="theme-toggle-pill" onclick="toggleTheme()" title="Toggle Dark/Light Mode" id="themeToggleAdmin">
                            <span class="toggle-icon-wrap"><i class="bi bi-moon-fill"></i></span>
                            <span class="toggle-label">Dark</span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link-admin-light <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>admin/dashboard.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link-admin-light <?php echo basename($_SERVER['PHP_SELF']) == 'results.php' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>admin/results.php">
                            <i class="bi bi-bar-chart"></i> Results
                        </a>
                    </li>
                    <li class="nav-item ms-lg-2">
                        <div class="dropdown">
                            <div class="user-pill-light-premium dropdown-toggle" data-bs-toggle="dropdown">
                                <div class="user-avatar-small-light text-white" style="background: #6366f1;"><?php echo $initials; ?></div>
                                <span style="color: #6366f1;"><?php echo htmlspecialchars($initials === 'JD' ? 'Jerick Dacera' : $display_name); ?></span>
                                <i class="bi bi-chevron-down small opacity-50" style="color: #6366f1;"></i>
                            </div>
                            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" style="border-radius: 16px; padding: 8px;">
                                <li><a class="dropdown-item" href="<?php echo $base_path; ?>admin/logout.php"><i class="bi bi-box-arrow-right text-danger"></i> Logout</a></li>
                            </ul>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container mt-4">
    <?php else: // Student / Voter Layout ?>
    <!-- ── Precision Student Top Navbar (Soft Glass) ── -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top py-2 navbar-student">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="<?php echo $base_path; ?>student/dashboard.php">
                <div class="brand-icon-premium" style="background: #6366f1; color: white;"><i class="bi bi-star-fill"></i></div>
                <div class="fw-800" style="letter-spacing: -0.02em; font-size: 1.2rem; color: #1e293b;">Council<span style="color: #6366f1;">Vote</span></div>
            </a>
            
            <div class="d-flex align-items-center gap-2 ms-auto me-2 d-lg-none">
                <button class="theme-toggle-pill pill-student" onclick="toggleTheme()" title="Toggle Dark/Light Mode" id="themeToggleMobile">
                    <span class="toggle-icon-wrap"><i class="bi bi-moon-fill"></i></span>
                    <span class="toggle-label">Dark</span>
                </button>
            </div>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#studentNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="studentNavbar">
                <ul class="navbar-nav ms-auto align-items-center gap-2">
                    <li class="nav-item"><a class="nav-link-student <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>student/dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link-student <?php echo basename($_SERVER['PHP_SELF']) == 'results.php' ? 'active' : ''; ?>" href="<?php echo $base_path; ?>student/results.php">Results</a></li>
                    <li class="nav-item d-none d-lg-flex">
                        <button class="theme-toggle-pill pill-student" onclick="toggleTheme()" title="Toggle Dark/Light Mode" id="themeToggleDesktop">
                            <span class="toggle-icon-wrap"><i class="bi bi-moon-fill"></i></span>
                            <span class="toggle-label">Dark</span>
                        </button>
                    </li>
                    <li class="nav-item dropdown ms-lg-2">
                        <div class="user-pill-light-premium dropdown-toggle" data-bs-toggle="dropdown">
                            <div class="user-avatar-small-light text-white" style="background: #6366f1;"><?php echo $initials; ?></div>
                            <span style="color: #6366f1;"><?php echo htmlspecialchars($display_name); ?></span>
                            <i class="bi bi-chevron-down small opacity-50" style="color: #6366f1;"></i>
                        </div>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" style="border-radius: 16px; padding: 8px;">
                            <li><a class="dropdown-item" href="<?php echo $base_path; ?>student/profile.php"><i class="bi bi-person-circle"></i> Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo $base_path; ?>student/logout.php"><i class="bi bi-box-arrow-right text-danger"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <main class="container mt-4">
    <?php endif; ?>
    <?php endif; ?>

    <!-- ── Flash Messages ── -->
    <?php if(isset($_GET['msg'])): ?>
    <div class="container mt-3" style="position:relative;z-index:1100;">
        <?php
        $msg = $_GET['msg'];
        $map = [
            'already_voted_in_this_election' => ['warning','exclamation-triangle','You have already voted in this election!'],
            'already_voted'                  => ['info',   'info-circle',         'You have already voted in a previous election.'],
            'vote_success'                   => ['success','check-circle',        'Your vote has been recorded. Thank you for participating!'],
            'no_active_election'             => ['info',   'info-circle',         'There is no active election at the moment.'],
            'no_candidates'                  => ['warning','exclamation-triangle','This election has no candidates yet.'],
            'loggedout'                      => ['success','check-circle',        'You have been successfully logged out.'],
        ];
        [$alert_class, $icon, $message] = $map[$msg] ?? ['info','info-circle', htmlspecialchars($msg)];
        echo '<div class="alert alert-'.$alert_class.' alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm" role="alert" style="background: rgba(255,255,255,0.8); backdrop-filter: blur(10px); border: 1px solid rgba(0,0,0,0.05);">
                <i class="bi bi-'.$icon.'-fill flex-shrink-0"></i>
                <span>'.$message.'</span>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
        ?>
    </div>
    <?php endif; ?>