<?php
// filename: admin/create_election.php
// location: admin/create_election.php

require_once '../config/database.php';
require_once '../includes/auth_check.php';

if($_SESSION['user_type'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

$message = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title      = $_POST['title'];
    $start_date = $_POST['start_date'];
    $end_date   = $_POST['end_date'];
    $app_start  = $_POST['app_start'];
    
    // Application ends when the election voting starts
    $app_end    = $start_date;

    if(empty($title) || empty($start_date) || empty($end_date) || empty($app_start)) {
        $error = "Title, start date, end date, and application open date are required!";
    } elseif(strtotime($end_date) <= strtotime($start_date)) {
        $error = "End date must be after start date!";
    } elseif(strtotime($app_end) <= strtotime($app_start)) {
        $error = "Application start date must be before the election start date (application close date)!";
    } else {
        try {
            $pdo->query("UPDATE elections SET is_active = 0, status = 'completed' WHERE is_active = 1");
            $stmt = $pdo->prepare("INSERT INTO elections (title, start_date, end_date, application_start, application_end, status, is_active) VALUES (?, ?, ?, ?, ?, 'pending', 0)");
            if($stmt->execute([$title, $start_date, $end_date, $app_start, $app_end])) {
                $election_id = $pdo->lastInsertId();
                $message = "Election created successfully! Redirecting to manage positions…";
                header("Refresh:2; url=manage_positions.php?election_id=" . $election_id);
            } else {
                $error = "Error creating election.";
            }
        } catch(PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

$elections = $pdo->query("
    SELECT e.*,
        CASE
            WHEN e.is_active = 1 AND NOW() BETWEEN e.start_date AND e.end_date THEN 'active'
            WHEN NOW() > e.end_date THEN 'ended'
            WHEN NOW() < e.start_date THEN 'upcoming'
            ELSE COALESCE(e.status, 'pending')
        END as current_status,
        (SELECT COUNT(*) FROM positions p WHERE p.election_id = e.id) as position_count,
        (SELECT COUNT(*) FROM candidates c JOIN positions p ON c.position_id = p.id WHERE p.election_id = e.id) as candidate_count
    FROM elections e ORDER BY e.created_at DESC LIMIT 10
")->fetchAll();

include '../includes/header.php';
?>

<style>
  @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@600;700&display=swap');

  :root {
    --glass-bg: rgba(255, 255, 255, 0.88);
    --glass-border: rgba(15, 127, 99, 0.14);
    --text-main: #0f172a;
    --text-muted: #64748b;
    --accent: #0f7f63;
    --panel-shadow: 0 24px 70px rgba(15, 127, 99, 0.08);
  }

  body {
    font-family: 'Plus Jakarta Sans', sans-serif;
    min-height: 100vh;
    background:
      radial-gradient(at 0% 0%, rgba(15, 127, 99, 0.22) 0px, transparent 55%),
      radial-gradient(at 100% 0%, rgba(16, 185, 129, 0.18) 0px, transparent 45%),
      radial-gradient(at 100% 100%, rgba(15, 127, 99, 0.10) 0px, transparent 45%),
      radial-gradient(at 0% 100%, rgba(94, 234, 212, 0.09) 0px, transparent 40%),
      #f4fbf7;
    background-attachment: fixed;
    color: var(--text-main);
  }

  .page-wrap { position: relative; z-index: 1; padding-bottom: 4rem; max-width: 1320px; margin: 0 auto; padding: 1.5rem 1.75rem 4rem; }

  .glass {
    background: var(--glass-bg);
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    border: 1px solid var(--glass-border);
    border-radius: 24px;
    box-shadow: var(--panel-shadow);
    overflow: hidden;
  }

  .panel { overflow: hidden; animation: popIn .5s cubic-bezier(.34,1.56,.64,1) both; }
  .panel-head {
    padding: 1.2rem 1.5rem;
    border-bottom: 1px solid rgba(15,127,99,0.12);
    display: flex; align-items: center; gap: .65rem;
    background: rgba(255,255,255,0.85);
  }
  .panel-head h5 { font-size: .95rem; font-weight: 700; color: #0f172a; margin: 0; }
  .panel-body { padding: 1.5rem; }

  .alert-glass {
    display: flex; align-items: flex-start; gap: .75rem;
    padding: 1rem 1.1rem; border-radius: 18px; margin-bottom: 1.25rem;
    font-size: .875rem; animation: popIn .4s ease both;
    box-shadow: 0 12px 30px rgba(15,127,99,0.06);
  }
  .alert-glass.success { background: rgba(220,252,231,.9); border: 1px solid rgba(134,239,172,.5); color: #166534; }
  .alert-glass.danger  { background: rgba(254,226,226,.9); border: 1px solid rgba(252,165,165,.5); color: #991b1b; }
  .alert-glass.warning { background: rgba(254,249,195,.9); border: 1px solid rgba(253,224,71,.5); color: #854d0e; }
  .alert-glass i { font-size: 1rem; flex-shrink: 0; margin-top: 2px; }
  .alert-glass .xbtn { margin-left: auto; background: none; border: none; cursor: pointer; color: inherit; opacity: .55; font-size: 1rem; line-height: 1; padding: 0; }
  .alert-glass .xbtn:hover { opacity: 1; }

  .field { margin-bottom: 1.25rem; }
  .field-label {
    display: block; font-size: .82rem; font-weight: 700; color: #475569;
    margin-bottom: .45rem; letter-spacing: .01em;
  }
  .field-hint { font-size: .82rem; color: #94a3b8; margin-top: .35rem; }

  .input-glass {
    width: 100%; padding: .82rem 1rem;
    background: rgba(255,255,255,0.9);
    border: 1px solid rgba(15,127,99,0.12);
    border-radius: 14px; font-size: .92rem;
    color: #0f172a; font-family: inherit;
    transition: border-color .2s, box-shadow .2s;
    outline: none;
  }
  .input-glass:focus {
    border-color: rgba(99,102,241,0.45);
    box-shadow: 0 0 0 4px rgba(99,102,241,0.1);
    background: #fff;
  }
  .input-glass::placeholder { color: #cbd5e1; }

  .date-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
  @media(max-width:500px){ .date-row{ grid-template-columns: 1fr; } }

  .form-actions { display: flex; gap: .75rem; flex-wrap: wrap; margin-top: 1.75rem; }

  .btn-primary-glass {
    display: inline-flex; align-items: center; gap: .5rem;
    background: linear-gradient(135deg,#6366f1,#818cf8);
    color: #fff; border: none; border-radius: 99px;
    padding: .75rem 1.6rem; font-size: .92rem; font-weight: 700;
    cursor: pointer; box-shadow: 0 12px 34px rgba(99,102,241,.25);
    transition: transform .2s, opacity .2s;
  }
  .btn-primary-glass:hover { transform: translateY(-1px); opacity: .98; }

  .btn-outline-glass {
    display: inline-flex; align-items: center; gap: .5rem;
    background: rgba(255,255,255,.88); border: 1px solid rgba(15,127,99,0.16);
    color: #475569; border-radius: 99px;
    padding: .72rem 1.65rem; font-weight: 700;
    transition: all .2s ease; text-decoration:none;
  }
  .btn-outline-glass:hover { background: rgba(255,255,255,1); border-color: rgba(15,127,99,0.25); color: #0f172a; }

  .guide-list { list-style: none; padding: 0; margin: 0; }
  .guide-list li {
    display: flex; align-items: flex-start; gap: .75rem;
    padding: .75rem 0; font-size: .92rem; color: #475569;
    border-bottom: 1px solid rgba(15,127,99,0.1);
  }
  .guide-list li:last-child { border-bottom: none; }
  .guide-list i { color: #22c55e; margin-top: 3px; }
  .guide-list strong { color: #0f172a; }

  .el-list { max-height: 520px; overflow-y: auto; }
  .el-item {
    padding: 1rem 1.4rem; border-radius: 22px;
    border: 1px solid rgba(15,127,99,0.1);
    background: rgba(255,255,255,0.95);
    margin-bottom: 1rem;
    transition: transform .2s, box-shadow .2s;
  }
  .el-item:hover { transform: translateY(-2px); box-shadow: 0 16px 36px rgba(15,127,99,.08); }

  .el-title { font-size: .95rem; font-weight: 800; color: #0f172a; margin-bottom: .35rem; }
  .el-date { font-size: .75rem; color: #94a3b8; display: flex; align-items: center; gap: .35rem; margin-bottom: .6rem; }

  .status-pill {
    display: inline-flex; align-items: center; gap: .3rem;
    font-size: .68rem; font-weight: 700; padding: .2rem .65rem;
    border-radius: 99px; letter-spacing: .04em; text-transform: uppercase;
    white-space: nowrap;
  }
  .sp-active   { background: rgba(34,197,94,.12); border: 1px solid rgba(34,197,94,.25); color: #16a34a; }
  .sp-ended    { background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.2); color: #dc2626; }
  .sp-upcoming { background: rgba(251,191,36,.12); border: 1px solid rgba(251,191,36,.3); color: #92400e; }
  .sp-pending  { background: rgba(148,163,184,.1); border: 1px solid rgba(148,163,184,.2); color: #64748b; }

  .meta-badge {
    display: inline-flex; align-items: center; gap: .3rem;
    font-size: .72rem; font-weight: 600; padding: .2rem .65rem;
    border-radius: 99px;
  }
  .mb-blue  { background: rgba(99,102,241,.1); color: #6366f1; }
  .mb-amber { background: rgba(251,191,36,.12); color: #92400e; }

  .el-actions { display: flex; gap: .5rem; flex-wrap: wrap; }
  .action-link-sm {
    display: inline-flex; align-items: center; gap: .35rem;
    padding: .35rem .85rem; border-radius: 99px; font-size: .75rem; font-weight: 600;
    text-decoration: none; transition: all .2s ease;
    border: 1px solid rgba(200,210,240,.6); background: rgba(255,255,255,.85);
    color: #475569;
  }
  .action-link-sm:hover { background: rgba(99,102,241,.08); border-color: rgba(99,102,241,.3); color: #6366f1; }
  .action-link-sm.green:hover { background: rgba(34,197,94,.08); border-color: rgba(34,197,94,.3); color: #16a34a; }

  .empty-state { text-align: center; padding: 3rem 1rem; }
  .empty-state .ei { font-size: 3rem; opacity: .25; margin-bottom: .75rem; }
  .empty-state p { font-size: .875rem; color: #94a3b8; margin: 0; }

  .modal-glass .modal-content {
    background: rgba(255,255,255,.96);
    backdrop-filter: blur(24px) saturate(160%);
    -webkit-backdrop-filter: blur(24px) saturate(160%);
    border: 1px solid rgba(255,255,255,.95);
    border-radius: 24px;
    box-shadow: 0 24px 60px rgba(15,127,99,.12);
    overflow: hidden;
  }
  .modal-head-custom {
    display: flex; align-items: center; justify-content: space-between; gap: .75rem;
    padding: 1.1rem 1.25rem;
    border-bottom: 1px solid rgba(15,127,99,0.1);
    background: rgba(255,255,255,.95);
  }
  .modal-head-custom span { font-size:1.2rem; }
  .modal-head-custom h5 { margin:0; font-size:1rem; font-weight:800; color:#0f172a; }
  .modal-close { background:none; border:none; font-size:1.2rem; font-weight:700; color:#64748b; cursor:pointer; }
  .modal-close:hover { color:#0f172a; }
  .modal-body-custom { padding: 1.25rem 1.25rem 1.5rem; }
  .modal-foot-custom { display:flex; justify-content:flex-end; gap:.85rem; padding:1rem 1.25rem 1.25rem; border-top:1px solid rgba(15,127,99,0.1); }
  .btn-cancel, .btn-save, .btn-del { display:inline-flex; align-items:center; gap:.45rem; padding:.72rem 1.25rem; border-radius:99px; font-weight:700; font-size:.85rem; transition:all .2s; text-decoration:none; border:1px solid transparent; }
  .btn-cancel { background: rgba(255,255,255,.95); border-color: rgba(15,127,99,0.15); color:#475569; }
  .btn-cancel:hover { background: rgba(15,127,99,.08); color:#0f172a; }
  .btn-save { background: linear-gradient(135deg,#6366f1,#818cf8); color:#fff; }
  .btn-save:hover { opacity:.95; transform: translateY(-1px); }
  .btn-del { background: rgba(239,68,68,.1); color:#dc2626; border-color: rgba(239,68,68,.2); }
  .btn-del:hover { background: rgba(239,68,68,.15); }

  @keyframes popIn { from { opacity: 0; transform: translateY(14px) scale(.97); } to { opacity: 1; transform: translateY(0) scale(1); } }

  /* =========================================
     LIGHT MODE OVERRIDES (Local Specificity)
     ========================================= */
  [data-theme="light"] body {
      background: #f0f4f8 !important;
      color: #1e293b !important;
  }
  [data-theme="light"] .glass {
      background: rgba(255, 255, 255, 0.95) !important;
      border-color: rgba(0, 0, 0, 0.08) !important;
      box-shadow: 0 4px 24px rgba(0,0,0,0.06) !important;
  }
  [data-theme="light"] .panel-head {
      background: #f8fafc !important;
      border-bottom-color: rgba(0, 0, 0, 0.08) !important;
  }
  [data-theme="light"] .panel-head h5 { color: #1e293b !important; }
  [data-theme="light"] .panel-body { background: transparent !important; color: #1e293b !important; }
  [data-theme="light"] .field-label { color: #475569 !important; }
  [data-theme="light"] .field-hint { color: #64748b !important; }
  [data-theme="light"] .input-glass {
      background: #ffffff !important;
      border-color: rgba(0, 0, 0, 0.12) !important;
      color: #1e293b !important;
  }
  [data-theme="light"] .input-glass::placeholder { color: #94a3b8 !important; }
  [data-theme="light"] .btn-primary-glass { background: linear-gradient(135deg,#6366f1,#818cf8) !important; color: #ffffff !important; }
  [data-theme="light"] .btn-outline-glass { background: #ffffff !important; border-color: rgba(0,0,0,0.12) !important; color: #475569 !important; }
  [data-theme="light"] .guide-list li { border-bottom-color: rgba(0,0,0,0.05) !important; color: #475569 !important; }
  [data-theme="light"] .guide-list strong { color: #1e293b !important; }
  [data-theme="light"] .el-item { background: #ffffff !important; border-color: rgba(0,0,0,0.08) !important; box-shadow: 0 4px 12px rgba(0,0,0,0.03) !important; }
  [data-theme="light"] .el-title { color: #1e293b !important; }
  [data-theme="light"] .el-date { color: #64748b !important; }
  [data-theme="light"] .action-link-sm { background: #ffffff !important; border-color: rgba(0,0,0,0.1) !important; color: #475569 !important; }

  [data-theme="dark"] body {
      background:
          radial-gradient(at 0% 0%, rgba(99,102,241,0.08) 0px, transparent 50%),
          radial-gradient(at 100% 0%, rgba(139,92,246,0.06) 0px, transparent 50%),
          radial-gradient(at 100% 100%, rgba(16,185,129,0.05) 0px, transparent 50%),
          #0d0f1a !important;
      color: #e2e8f0 !important;
  }
  [data-theme="dark"] .glass { background: rgba(20,22,35,0.88) !important; border-color: rgba(99,102,241,0.15) !important; box-shadow: 0 24px 70px rgba(0,0,0,0.45) !important; }
  [data-theme="dark"] .panel-head { background: rgba(20,22,35,0.94) !important; border-color: rgba(99,102,241,0.18) !important; }
  [data-theme="dark"] .panel-body { color: #e2e8f0 !important; }
  [data-theme="dark"] .input-glass { background: rgba(15,17,23,0.82) !important; border-color: rgba(99,102,241,0.12) !important; color: #e2e8f0 !important; }
  [data-theme="dark"] .input-glass:focus { background: rgba(15,17,23,0.92) !important; }
  [data-theme="dark"] .alert-glass.success { background: rgba(34,197,94,.12) !important; border-color: rgba(34,197,94,.25) !important; color: #bbf7d0 !important; }
  [data-theme="dark"] .alert-glass.danger { background: rgba(255,255,255,.06) !important; border-color: rgba(239,68,68,.2) !important; color: #fecdd3 !important; }
  [data-theme="dark"] .alert-glass.warning { background: rgba(251,191,36,.08) !important; border-color: rgba(251,191,36,.25) !important; color: #facc15 !important; }
  [data-theme="dark"] .guide-list li { border-color: rgba(99,102,241,0.12) !important; color: #cbd5e1 !important; }
  [data-theme="dark"] .guide-list strong { color: #e2e8f0 !important; }
  [data-theme="dark"] .el-item { background: rgba(20,22,35,0.86) !important; border-color: rgba(99,102,241,0.12) !important; box-shadow: 0 18px 40px rgba(0,0,0,0.45) !important; }
  [data-theme="dark"] .el-title, [data-theme="dark"] .modal-head-custom h5, [data-theme="dark"] .action-link-sm, [data-theme="dark"] .btn-cancel { color: #e2e8f0 !important; }
  [data-theme="dark"] .el-date, [data-theme="dark"] .field-label, [data-theme="dark"] .field-hint, [data-theme="dark"] .action-link-sm, [data-theme="dark"] .btn-cancel { color: #94a3b8 !important; }
  [data-theme="dark"] .action-link-sm { background: rgba(15,17,23,.75) !important; border-color: rgba(99,102,241,.12) !important; }
  [data-theme="dark"] .modal-content { background: rgba(15,17,23,.92) !important; border-color: rgba(99,102,241,.16) !important; color: #e2e8f0 !important; box-shadow: 0 24px 60px rgba(0,0,0,.45) !important; }
  [data-theme="dark"] .modal-head-custom { border-color: rgba(99,102,241,0.15) !important; }
  [data-theme="dark"] .btn-cancel { background: rgba(15,17,23,.85) !important; color: #94a3b8 !important; border-color: rgba(99,102,241,.12) !important; }
  [data-theme="dark"] .btn-save { background: linear-gradient(135deg, #6366f1, #818cf8) !important; }
  [data-theme="dark"] .btn-del { background: rgba(255,255,255,.06) !important; color: #fecdd3 !important; border-color: rgba(239,68,68,.2) !important; }
  [data-theme="dark"] .status-pill { background: rgba(99,102,241,.12) !important; }
  [data-theme="dark"] .meta-badge { background: rgba(99,102,241,.08) !important; color: #c7d2fe !important; }
  [data-theme="dark"] .mb-blue { background: rgba(99,102,241,.15) !important; color: #c7d2fe !important; }
</style>

<div class="page-wrap">

    <!-- Page Header -->
    <div class="page-header">
        <h2>🗳️ Create Election</h2>
        <p>Set up a new election event with start and end dates.</p>
    </div>

    <div class="content-grid">

        <!-- LEFT: Form + Guidelines -->
        <div style="display:flex;flex-direction:column;gap:1.25rem;">

            <!-- Create Form Panel -->
            <div class="panel glass" style="animation-delay:.06s">
                <div class="panel-head">
                    <h5>✨ New Election Event</h5>
                </div>
                <div class="panel-body">

                    <!-- Alerts -->
                    <?php if($message): ?>
                    <div class="alert-glass success">
                        <i class="bi bi-check-circle-fill"></i>
                        <span><?php echo $message; ?></span>
                        <button class="xbtn" onclick="this.closest('.alert-glass').remove()">✕</button>
                    </div>
                    <?php endif; ?>
                    <?php if($error): ?>
                    <div class="alert-glass danger">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span><?php echo $error; ?></span>
                        <button class="xbtn" onclick="this.closest('.alert-glass').remove()">✕</button>
                    </div>
                    <?php endif; ?>

                    <form method="POST" id="createElectionForm">

                        <!-- Title -->
                        <div class="field">
                            <label class="field-label">Election Title</label>
                            <input type="text" name="title"
                                   class="input-glass"
                                   placeholder="e.g., SSC General Election 2026"
                                   value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                                   required>
                            <div class="field-hint">Give a clear, descriptive name for this election</div>
                        </div>

                        <!-- Date Row -->
                        <div class="date-row">
                            <div class="field" style="margin-bottom:0;">
                                <label class="field-label">
                                    <i class="bi bi-calendar-event" style="color:#6366f1;margin-right:4px;"></i>
                                    Start Date &amp; Time
                                </label>
                                <input type="datetime-local" name="start_date" id="start_date"
                                       class="input-glass" required>
                                <div class="field-hint">When voting opens</div>
                            </div>
                            <div class="field" style="margin-bottom:0;">
                                <label class="field-label">
                                    <i class="bi bi-calendar-x" style="color:#f472b6;margin-right:4px;"></i>
                                    End Date &amp; Time
                                </label>
                                <input type="datetime-local" name="end_date" id="end_date"
                                       class="input-glass" required>
                                <div class="field-hint">When voting closes</div>
                            </div>
                        </div>

                        <div class="field" style="margin-top: 1.5rem;">
                            <h6 style="font-size: 0.9rem; font-weight: 800; color: #475569; margin-bottom: 0.75rem;">Candidate Application Window</h6>
                            <div class="date-row" style="grid-template-columns: 1fr;">
                                <div class="field" style="margin-bottom:0;">
                                    <label class="field-label">
                                        <i class="bi bi-person-plus" style="color:#10b981;margin-right:4px;"></i>
                                        Applications Open Date &amp; Time
                                    </label>
                                    <input type="datetime-local" name="app_start" id="app_start" class="input-glass" required>
                                    <div class="field-hint">When students can start applying. The application window will automatically close when the election Voting Start Date &amp; Time begins.</div>
                                </div>
                            </div>
                        </div>

                        <!-- Duration preview -->
                        <div id="durationPreview" style="display:none;margin-top:.85rem;">
                            <div style="display:inline-flex;align-items:center;gap:.5rem;background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.18);border-radius:99px;padding:.3rem .9rem;font-size:.78rem;font-weight:600;color:#6366f1;">
                                <i class="bi bi-clock"></i>
                                <span id="durationText">Duration: —</span>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn-primary-glass">
                                <i class="bi bi-calendar-check"></i> Create Election
                            </button>
                            <a href="dashboard.php" class="btn-outline-glass">
                                <i class="bi bi-x-circle"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Guidelines Panel -->
            <div class="panel glass" style="animation-delay:.14s">
                <div class="panel-head">
                    <h5>📌 Election Guidelines</h5>
                </div>
                <div class="panel-body" style="padding-top:1rem;padding-bottom:1rem;">
                    <ul class="guide-list">
                        <li>
                            <i class="bi bi-check-circle-fill"></i>
                            <span><strong>Title:</strong> Include the year and type, e.g. "SSC Election 2026"</span>
                        </li>
                        <li>
                            <i class="bi bi-check-circle-fill"></i>
                            <span><strong>Duration:</strong> A minimum of 24 hours is recommended</span>
                        </li>
                        <li>
                            <i class="bi bi-check-circle-fill"></i>
                            <span><strong>Setup:</strong> Add positions and candidates after creation</span>
                        </li>
                        <li>
                            <i class="bi bi-check-circle-fill"></i>
                            <span><strong>Activation:</strong> Manually activate when everything is ready</span>
                        </li>
                        <li>
                            <i class="bi bi-check-circle-fill"></i>
                            <span><strong>Note:</strong> Creating a new election deactivates any currently active one</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- RIGHT: Recent Elections -->
        <div class="panel glass" style="animation-delay:.2s;align-self:start;">
            <div class="panel-head">
                <h5>🕐 Recent Elections</h5>
            </div>

            <?php if(count($elections) > 0): ?>
            <div class="el-list">
                <?php foreach($elections as $el):
                    $st = $el['current_status'];
                    $pill_class = 'sp-pending'; $pill_label = 'Pending';
                    if($st == 'active')   { $pill_class = 'sp-active';   $pill_label = '● Active'; }
                    elseif($st == 'ended')    { $pill_class = 'sp-ended';    $pill_label = 'Ended'; }
                    elseif($st == 'upcoming') { $pill_class = 'sp-upcoming'; $pill_label = 'Upcoming'; }
                ?>
                <div class="el-item">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:.75rem;margin-bottom:.5rem;">
                        <div class="el-title"><?php echo htmlspecialchars($el['title']); ?></div>
                        <span class="status-pill <?php echo $pill_class; ?>"><?php echo $pill_label; ?></span>
                    </div>
                    <div class="el-date">
                        <i class="bi bi-calendar3"></i>
                        <?php echo date('M d, Y', strtotime($el['start_date'])); ?>
                        &nbsp;→&nbsp;
                        <?php echo date('M d, Y', strtotime($el['end_date'])); ?>
                    </div>
                    <div style="display:flex;align-items:center;gap:.4rem;flex-wrap:wrap;margin-bottom:.65rem;">
                        <span class="meta-badge mb-blue">
                            <i class="bi bi-tag"></i> <?php echo $el['position_count'] ?? 0; ?> Positions
                        </span>
                        <span class="meta-badge mb-amber">
                            <i class="bi bi-people"></i> <?php echo $el['candidate_count'] ?? 0; ?> Candidates
                        </span>
                    </div>
                    <div class="el-actions">
                        <a href="manage_positions.php?election_id=<?php echo $el['id']; ?>" class="action-link-sm">
                            <i class="bi bi-gear"></i> Positions
                        </a>
                        <a href="add_candidates.php?election_id=<?php echo $el['id']; ?>" class="action-link-sm green">
                            <i class="bi bi-person-plus"></i> Candidates
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php else: ?>
            <div class="empty-state">
                <div class="ei">🗳️</div>
                <p style="font-weight:600;color:#475569;">No elections yet</p>
                <p>Create your first election using the form.</p>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
// Enforce min date = now
const today = new Date();
today.setMinutes(today.getMinutes() - today.getTimezoneOffset());
const todayStr = today.toISOString().slice(0,16);
document.getElementById('start_date').min = todayStr;
document.getElementById('end_date').min = todayStr;

// Auto-set end_date min when start_date changes + show duration
document.getElementById('start_date').addEventListener('change', updateDuration);
document.getElementById('end_date').addEventListener('change', updateDuration);

function updateDuration() {
    const s = document.getElementById('start_date').value;
    const e = document.getElementById('end_date').value;
    // keep end >= start
    if(s) document.getElementById('end_date').min = s;
    const preview = document.getElementById('durationPreview');
    const text    = document.getElementById('durationText');
    if(s && e) {
        const diff = (new Date(e) - new Date(s)) / 1000;
        if(diff <= 0) { preview.style.display='none'; return; }
        const d = Math.floor(diff/86400);
        const h = Math.floor((diff%86400)/3600);
        const m = Math.floor((diff%3600)/60);
        let out = 'Duration: ';
        if(d) out += d+'d ';
        if(h) out += h+'h ';
        if(m) out += m+'m';
        text.textContent = out.trim() || 'Duration: <1m';
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }
}

// Client-side validation
document.getElementById('createElectionForm').addEventListener('submit', function(e) {
    const s = new Date(document.getElementById('start_date').value);
    const en = new Date(document.getElementById('end_date').value);
    const appStart = new Date(document.getElementById('app_start').value);
    
    if(en <= s) {
        e.preventDefault();
        alert('End date must be after start date!');
    } else if (s <= appStart) {
        e.preventDefault();
        alert('Application open date must be before the election start date!');
    }
});
</script>

<?php include '../includes/footer.php'; ?>