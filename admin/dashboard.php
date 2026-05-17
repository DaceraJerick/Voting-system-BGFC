<?php
// filename: admin/dashboard.php
require_once '../config/database.php';
require_once '../includes/auth_check.php';

if($_SESSION['user_type'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

// Fetch stats
$eligible_count   = $pdo->query("SELECT COUNT(*) FROM eligible_ids")->fetchColumn();
$registered_count = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$voted_count      = $pdo->query("SELECT COUNT(*) FROM students WHERE has_voted = 1")->fetchColumn();
$active_election  = $pdo->query("SELECT * FROM elections WHERE is_active = 1")->fetch();

// System Overview stats
$admin_count      = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
$turnout = $registered_count > 0 ? round(($voted_count / $registered_count) * 100, 1) : 0;

include '../includes/header.php';
?>

<style>
  @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@600;700&display=swap');
  
  :root {
    --glass-bg: rgba(255, 255, 255, 0.6);
    --glass-border: rgba(255, 255, 255, 0.8);
    --text-main: #1e293b;
    --text-muted: #64748b;
    --accent: #6366f1;
  }

  body {
    font-family: 'Plus Jakarta Sans', sans-serif;
    min-height: 100vh;
    background: 
      radial-gradient(at 0% 0%, rgba(232, 232, 255, 0.5) 0px, transparent 50%),
      radial-gradient(at 100% 0%, rgba(245, 243, 255, 0.6) 0px, transparent 50%),
      radial-gradient(at 100% 100%, rgba(238, 242, 255, 0.5) 0px, transparent 50%),
      radial-gradient(at 0% 100%, rgba(240, 249, 255, 0.6) 0px, transparent 50%),
      #f8fafc;
    background-attachment: fixed;
    color: var(--text-main);
  }

  .glass-card {
    background: var(--glass-bg);
    backdrop-filter: blur(16px) saturate(180%);
    -webkit-backdrop-filter: blur(16px) saturate(180%);
    border: 1px solid var(--glass-border);
    border-radius: 24px;
    padding: 24px;
    box-shadow: 0 4px 24px rgba(100, 116, 139, 0.04);
  }

  /* Welcome Banner */
  .welcome-banner {
    padding: 40px 48px;
    margin-bottom: 30px;
    position: relative;
    overflow: hidden;
  }

  .banner-title {
    font-family: 'Sora', sans-serif;
    font-size: 2.5rem;
    font-weight: 800;
    margin-bottom: 8px;
    background: linear-gradient(135deg, #1e293b, #6366f1);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
  }

  .banner-subtitle {
    font-size: 1.1rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .system-status-pill {
    position: absolute;
    top: 24px;
    right: 32px;
    background: rgba(255, 255, 255, 0.8);
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
    border: 1px solid rgba(0,0,0,0.03);
  }

  .status-dot {
    width: 6px; height: 6px;
    background: #22c55e;
    border-radius: 50%;
    box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.1);
  }

  /* Stats Grid */
  .stat-card-new {
    position: relative;
    height: 100%;
    transition: transform 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    background: rgba(255,255,255,0.7);
  }
  .stat-card-new:hover { transform: translateY(-5px); }

  .stat-tag {
    position: absolute;
    top: 20px;
    right: 20px;
    padding: 4px 12px;
    border-radius: 10px;
    font-size: 0.65rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
  }

  .tag-blue { background: #e0f2fe; color: #0369a1; }
  .tag-green { background: #dcfce7; color: #15803d; }
  .tag-pink { background: #fdf2f8; color: #be185d; }
  .tag-orange { background: #ffedd5; color: #c2410c; }

  .stat-icon-box {
    width: 48px; height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    margin-bottom: 20px;
  }

  .icon-blue { background: rgba(59, 130, 246, 0.08); color: #3b82f6; }
  .icon-green { background: rgba(34, 197, 94, 0.08); color: #22c55e; }
  .icon-pink { background: rgba(236, 72, 153, 0.08); color: #ec4899; }
  .icon-orange { background: rgba(249, 115, 22, 0.08); color: #f97316; }

  .stat-number { font-size: 2.25rem; font-weight: 800; color: #1e293b; line-height: 1; margin-bottom: 4px; }
  .stat-label { font-size: 0.9rem; font-weight: 600; color: #64748b; }

  /* Panels */
  .panel-header-new {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
  }

  .panel-title { font-family: 'Sora', sans-serif; font-size: 1.1rem; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 10px; }
  .panel-badge { background: #f1f5f9; color: #64748b; font-size: 0.65rem; font-weight: 800; padding: 4px 10px; border-radius: 8px; text-transform: uppercase; }

  .quick-actions-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
  
  .action-btn-new {
    background: #ffffff;
    border: 1px solid rgba(0,0,0,0.03);
    border-radius: 16px;
    padding: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    color: var(--text-main);
    transition: all 0.2s;
  }
  .action-btn-new:hover { 
    background: #f8fafc;
    border-color: #6366f1;
    transform: translateX(4px);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.05);
  }

  .action-icon-small { 
    width: 32px; height: 32px; border-radius: 8px; background: #f1f5f9;
    display: flex; align-items: center; justify-content: center; font-size: 0.9rem;
    color: #64748b;
  }
  .action-btn-new:hover .action-icon-small { background: #6366f1; color: white; }

  .action-info { display: flex; flex-direction: column; }
  .action-name { font-weight: 700; font-size: 0.9rem; }
  .action-detail { font-size: 0.75rem; color: #94a3b8; }

  /* System Overview */
  .overview-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid rgba(0,0,0,0.03); }
  .overview-row:last-child { border-bottom: none; }
  .overview-text { font-size: 0.9rem; color: #64748b; font-weight: 500; }
  .overview-num { font-size: 0.9rem; font-weight: 700; color: #1e293b; }

  /* =========================================
     DARK MODE OVERRIDES (Local Specificity)
     ========================================= */
  [data-theme="dark"] body {
      background:
          radial-gradient(at 0% 0%,   rgba(99,102,241,0.08) 0px, transparent 50%),
          radial-gradient(at 100% 0%, rgba(139,92,246,0.06) 0px, transparent 50%),
          radial-gradient(at 100% 100%, rgba(16,185,129,0.05) 0px, transparent 50%),
          #0d0f1a !important;
      color: #e2e8f0 !important;
  }
  
  [data-theme="dark"] .glass-card {
      background: rgba(20,22,35,0.85) !important;
      border-color: rgba(99,102,241,0.15) !important;
      box-shadow: 0 4px 24px rgba(0,0,0,0.25) !important;
  }

  [data-theme="dark"] .stat-card-new { background: rgba(20,22,35,0.8) !important; }
  [data-theme="dark"] .stat-number { color: #e2e8f0 !important; }
  [data-theme="dark"] .stat-label { color: #94a3b8 !important; }
  
  [data-theme="dark"] .banner-title {
      background: linear-gradient(135deg, #e2e8f0, #818cf8) !important;
      -webkit-background-clip: text !important;
      -webkit-text-fill-color: transparent !important;
  }
  [data-theme="dark"] .banner-subtitle { color: #94a3b8 !important; }
  [data-theme="dark"] .system-status-pill {
      background: rgba(20,22,35,0.9) !important;
      border-color: rgba(99,102,241,0.2) !important;
      color: #94a3b8 !important;
  }

  [data-theme="dark"] .panel-title { color: #e2e8f0 !important; }
  [data-theme="dark"] .panel-badge { background: rgba(30,32,48,0.8) !important; color: #94a3b8 !important; }

  [data-theme="dark"] .action-btn-new {
      background: rgba(20,22,35,0.7) !important;
      border-color: rgba(99,102,241,0.12) !important;
      color: #e2e8f0 !important;
  }
  [data-theme="dark"] .action-btn-new:hover {
      background: rgba(99,102,241,0.12) !important;
      border-color: #6366f1 !important;
  }
  [data-theme="dark"] .action-icon-small { background: rgba(30,32,48,0.8) !important; color: #94a3b8 !important; }
  [data-theme="dark"] .action-name { color: #e2e8f0 !important; }
  [data-theme="dark"] .action-detail { color: #64748b !important; }

  [data-theme="dark"] .overview-row { border-bottom-color: rgba(99,102,241,0.08) !important; }
  [data-theme="dark"] .overview-text { color: #94a3b8 !important; }
  [data-theme="dark"] .overview-num { color: #e2e8f0 !important; }
</style>

<div class="container py-4">
    <!-- Welcome Banner/Brand Header -->
    <div class="glass-card welcome-banner">
        <div class="system-status-pill">
            <span class="status-dot"></span> System Online
        </div>
        <h1 class="banner-title">Admin Dashboard</h1>
        <div class="banner-subtitle">
            Welcome back, <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?> 👋
        </div>
    </div>

    <!-- Quick Stats Grid -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="glass-card stat-card-new">
                <span class="stat-tag tag-blue">Total</span>
                <div class="stat-icon-box icon-blue">
                    <i class="bi bi-person-badge"></i>
                </div>
                <div class="stat-number"><?php echo $eligible_count; ?></div>
                <div class="stat-label">Eligible IDs</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="glass-card stat-card-new">
                <span class="stat-tag tag-green">Active</span>
                <div class="stat-icon-box icon-green">
                    <i class="bi bi-check2-square"></i>
                </div>
                <div class="stat-number"><?php echo $registered_count; ?></div>
                <div class="stat-label">Registered Students</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="glass-card stat-card-new">
                <span class="stat-tag tag-pink">Cast</span>
                <div class="stat-icon-box icon-pink">
                    <i class="bi bi-archive"></i>
                </div>
                <div class="stat-number"><?php echo $voted_count; ?></div>
                <div class="stat-label">Votes Submitted</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="glass-card stat-card-new">
                <span class="stat-tag tag-orange">Rate</span>
                <div class="stat-icon-box icon-orange">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <div class="stat-number"><?php echo $turnout; ?>%</div>
                <div class="stat-label">Voter Turnout</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Quick Actions Panel -->
        <div class="col-lg-8">
            <div class="glass-card h-100">
                <div class="panel-header-new">
                    <div class="panel-title">⚡ Quick Actions</div>
                    <span class="panel-badge">10 Tools</span>
                </div>
                <div class="quick-actions-row">
                    <a href="upload_ids.php" class="action-btn-new">
                        <div class="action-icon-small"><i class="bi bi-file-earmark-arrow-up"></i></div>
                        <div class="action-info">
                            <span class="action-name">Upload Eligible IDs</span>
                            <span class="action-detail">Import student IDs</span>
                        </div>
                    </a>
                    <a href="manage_elections.php" class="action-btn-new">
                        <div class="action-icon-small"><i class="bi bi-lightning-charge"></i></div>
                        <div class="action-info">
                            <span class="action-name">Activate Election</span>
                            <span class="action-detail">Start voting session</span>
                        </div>
                    </a>
                    <a href="create_election.php" class="action-btn-new">
                        <div class="action-icon-small"><i class="bi bi-folder-plus"></i></div>
                        <div class="action-info">
                            <span class="action-name">Create Election</span>
                            <span class="action-detail">Set up voting event</span>
                        </div>
                    </a>
                    <a href="results.php" class="action-btn-new">
                        <div class="action-icon-small"><i class="bi bi-bar-chart"></i></div>
                        <div class="action-info">
                            <span class="action-name">View Results</span>
                            <span class="action-detail">Election statistics</span>
                        </div>
                    </a>
                    <a href="manage_positions.php" class="action-btn-new">
                        <div class="action-icon-small"><i class="bi bi-list-task"></i></div>
                        <div class="action-info">
                            <span class="action-name">Manage Positions</span>
                            <span class="action-detail">Add/remove positions</span>
                        </div>
                    </a>
                    <a href="manage_admins.php" class="action-btn-new">
                        <div class="action-icon-small"><i class="bi bi-shield-check"></i></div>
                        <div class="action-info">
                            <span class="action-name">Manage Admins</span>
                            <span class="action-detail">Admin accounts</span>
                        </div>
                    </a>
                    <a href="add_candidates.php" class="action-btn-new">
                        <div class="action-info">
                            <span class="action-name">Add Candidates</span>
                            <span class="action-detail">Register candidates</span>
                        </div>
                    </a>
                    <a href="winners.php" class="action-btn-new">
                        <div class="action-info">
                            <span class="action-name">View Winners</span>
                            <span class="action-detail">Completed elections</span>
                        </div>
                    </a>
                    <a href="manage_elections.php" class="action-btn-new">
                        <div class="action-info">
                            <span class="action-name">Manage Elections</span>
                            <span class="action-detail">All events</span>
                        </div>
                    </a>
                    <a href="manage_candidates.php" class="action-btn-new">
                        <div class="action-info">
                            <span class="action-name">Manage Candidates</span>
                            <span class="action-detail">Edit/Delete candidates</span>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- System Overview Panel -->
        <div class="col-lg-4">
            <div class="glass-card h-100">
                <div class="panel-header-new">
                    <div class="panel-title">🖥️ System Overview</div>
                </div>
                <div class="overview-list">
                    <div class="overview-row">
                        <span class="overview-text">Total Administrators</span>
                        <span class="overview-num"><?php echo $admin_count; ?></span>
                    </div>
                    <div class="overview-row">
                        <span class="overview-text">Eligible Voters</span>
                        <span class="overview-num"><?php echo $eligible_count; ?></span>
                    </div>
                    <div class="overview-row">
                        <span class="overview-text">Registered Voters</span>
                        <span class="overview-num"><?php echo $registered_count; ?></span>
                    </div>
                    <div class="overview-row">
                        <span class="overview-text">Votes Submitted</span>
                        <span class="overview-num"><?php echo $voted_count; ?></span>
                    </div>
                    <div class="overview-row">
                        <span class="overview-text">System Status</span>
                        <span class="overview-num" style="color: #22c55e;">Online</span>
                    </div>
                </div>

                <hr style="margin: 24px 0; opacity: 0.1;">

                <div class="panel-title mb-3" style="font-size: 0.9rem;">🗳️ Active Election</div>
                <?php if($active_election): ?>
                    <div style="background: rgba(99, 102, 241, 0.05); padding: 16px; border-radius: 16px; border: 1px dashed rgba(99, 102, 241, 0.2);">
                        <div style="font-weight: 800; font-size: 0.95rem; margin-bottom: 4px;"><?php echo htmlspecialchars($active_election['title']); ?></div>
                        <div style="font-size: 0.75rem; color: #64748b;">Active until <?php echo date('M d, Y', strtotime($active_election['end_date'])); ?></div>
                        <a href="manage_elections.php" style="display: block; margin-top: 12px; font-size: 0.8rem; font-weight: 700; color: #6366f1; text-decoration: none;">Manage Event →</a>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 15px; background: rgba(0,0,0,0.02); border-radius: 12px; color: #94a3b8; font-size: 0.8rem;">
                        No active election currently.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>