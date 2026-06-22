<?php
// filename: admin/manage_elections.php
// location: admin/manage_elections.php

require_once '../config/database.php';
require_once '../includes/auth_check.php';

if($_SESSION['user_type'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

$message = '';
$error = '';

if(isset($_GET['delete_election'])) {
    $election_id = $_GET['delete_election'];
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT title FROM elections WHERE id = ?");
        $stmt->execute([$election_id]);
        $election = $stmt->fetch();
        if($election) {
            // Delete votes first to avoid foreign key constraints
            $pdo->prepare("DELETE FROM votes WHERE election_id = ?")->execute([$election_id]);

            // Delete candidates and their photos
            $stmt = $pdo->prepare("SELECT c.id, c.photo FROM candidates c JOIN positions p ON c.position_id = p.id WHERE p.election_id = ?");
            $stmt->execute([$election_id]);
            foreach($stmt->fetchAll() as $cand) {
                if(!empty($cand['photo']) && file_exists('../' . $cand['photo'])) unlink('../' . $cand['photo']);
                $pdo->prepare("DELETE FROM candidates WHERE id = ?")->execute([$cand['id']]);
            }

            // Delete positions
            $pdo->prepare("DELETE FROM positions WHERE election_id = ?")->execute([$election_id]);

            // Delete election
            $pdo->prepare("DELETE FROM elections WHERE id = ?")->execute([$election_id]);
            $pdo->commit();
            header("Location: manage_elections.php?msg=deleted");
            exit();
        }
    } catch(PDOException $e) {
        $pdo->rollBack();
        $error = "Error deleting election: " . $e->getMessage();
    }
}

if(isset($_POST['edit_election'])) {
    try {
        $start_date = str_replace('T', ' ', $_POST['start_date']);
        $end_date   = str_replace('T', ' ', $_POST['end_date']);
        if (strlen($start_date) == 16) $start_date .= ':00';
        if (strlen($end_date)   == 16) $end_date   .= ':00';

        // application_end always equals start_date (app window closes when voting opens)
        $app_end = $start_date;

        // If admin provided a new app_start, use it; otherwise keep the existing one
        $app_start = null;
        if (!empty($_POST['app_start'])) {
            $app_start = str_replace('T', ' ', $_POST['app_start']);
            if (strlen($app_start) == 16) $app_start .= ':00';
        }

        if ($app_start !== null) {
            $pdo->prepare("UPDATE elections SET title=?, start_date=?, end_date=?, application_start=?, application_end=? WHERE id=?")
                ->execute([trim($_POST['title']), $start_date, $end_date, $app_start, $app_end, $_POST['election_id']]);
        } else {
            // No app_start submitted — still sync application_end to new start_date
            $pdo->prepare("UPDATE elections SET title=?, start_date=?, end_date=?, application_end=? WHERE id=?")
                ->execute([trim($_POST['title']), $start_date, $end_date, $app_end, $_POST['election_id']]);
        }
        $message = "Election updated successfully!";
    } catch(PDOException $e) { $error = "Error updating election: " . $e->getMessage(); }
}

if(isset($_GET['activate'])) {
    try {
        $pdo->query("UPDATE elections SET is_active=0, status='completed' WHERE is_active=1");
        $pdo->prepare("UPDATE elections SET is_active=1, status='active' WHERE id=?")->execute([$_GET['activate']]);
        header("Location: manage_elections.php?msg=activated"); exit();
    } catch(PDOException $e) { $error = "Error: " . $e->getMessage(); }
}

if(isset($_GET['deactivate'])) {
    try {
        $pdo->prepare("UPDATE elections SET is_active=0, status='pending' WHERE id=?")->execute([$_GET['deactivate']]);
        header("Location: manage_elections.php?msg=deactivated"); exit();
    } catch(PDOException $e) { $error = "Error: " . $e->getMessage(); }
}

if(isset($_GET['pause'])) {
    try {
        $pdo->prepare("UPDATE elections SET is_paused=1 WHERE id=? AND is_active=1")->execute([$_GET['pause']]);
        header("Location: manage_elections.php?msg=paused"); exit();
    } catch(PDOException $e) { $error = "Error: " . $e->getMessage(); }
}

if(isset($_GET['resume'])) {
    try {
        $pdo->prepare("UPDATE elections SET is_paused=0 WHERE id=?")->execute([$_GET['resume']]);
        header("Location: manage_elections.php?msg=resumed"); exit();
    } catch(PDOException $e) { $error = "Error: " . $e->getMessage(); }
}

if(isset($_GET['msg'])) {
    $msgs = ['deleted'=>'Election deleted successfully!','activated'=>'Election activated!','deactivated'=>'Election deactivated.','paused'=>'Election paused! Voting is temporarily suspended.','resumed'=>'Election resumed! Voting is active again.'];
    if(isset($msgs[$_GET['msg']])) $message = $msgs[$_GET['msg']];
}

$elections = $pdo->query("
    SELECT e.*,
        CASE
            WHEN e.is_active=1 AND e.is_paused=1 AND NOW() BETWEEN e.start_date AND e.end_date THEN 'paused'
            WHEN e.is_active=1 AND NOW() BETWEEN e.start_date AND e.end_date THEN 'active'
            WHEN NOW() > e.end_date THEN 'ended'
            WHEN NOW() < e.start_date THEN 'upcoming'
            ELSE COALESCE(e.status,'pending')
        END as current_status,
        (SELECT COUNT(*) FROM positions p WHERE p.election_id=e.id) as position_count,
        (SELECT COUNT(*) FROM candidates c JOIN positions p ON c.position_id=p.id WHERE p.election_id=e.id) as candidate_count,
        (SELECT COUNT(DISTINCT student_id) FROM votes v WHERE v.election_id=e.id) as voter_count
    FROM elections e
    ORDER BY CASE WHEN e.is_active=1 THEN 0 WHEN NOW()<e.start_date THEN 1 WHEN NOW() BETWEEN e.start_date AND e.end_date THEN 2 ELSE 3 END, e.created_at DESC
")->fetchAll();

include '../includes/header.php';
?>

<style>
/* ── Manage Elections ── */
.page-wrap { position:relative;z-index:1;padding-bottom:4rem; }

.glass {
    background:rgba(255,255,255,0.55);
    backdrop-filter:blur(20px) saturate(160%);
    -webkit-backdrop-filter:blur(20px) saturate(160%);
    border:1px solid rgba(255,255,255,0.82);
    border-radius:20px;
    box-shadow:0 4px 24px rgba(100,120,200,0.08);
}

/* page header */
.page-hdr {
    display:flex;align-items:flex-start;justify-content:space-between;
    flex-wrap:wrap;gap:1rem;margin-bottom:1.75rem;
}
.page-hdr h2 { font-size:clamp(1.4rem,2.5vw,1.9rem);font-weight:800;color:#0f172a;letter-spacing:-.025em;margin:0; }
.page-hdr p  { color:#64748b;font-size:.9rem;margin:.3rem 0 0; }

/* btn */
.btn-primary-glass {
    display:inline-flex;align-items:center;gap:.5rem;
    background:linear-gradient(135deg,#6366f1,#818cf8);
    color:#fff;border:none;border-radius:99px;
    padding:.65rem 1.5rem;font-size:.875rem;font-weight:700;
    cursor:pointer;text-decoration:none;
    box-shadow:0 4px 14px rgba(99,102,241,.3);
    transition:all .2s ease;white-space:nowrap;
}
.btn-primary-glass:hover{transform:scale(1.03);opacity:.9;color:#fff;text-decoration:none;}

/* alerts */
.alert-glass {
    display:flex;align-items:flex-start;gap:.75rem;
    padding:1rem 1.1rem;border-radius:14px;margin-bottom:1.25rem;
    font-size:.875rem;animation:popIn .4s ease both;
}
.alert-glass.success{background:rgba(220,252,231,.8);border:1px solid rgba(134,239,172,.5);color:#166534;}
.alert-glass.danger {background:rgba(254,226,226,.8);border:1px solid rgba(252,165,165,.5);color:#991b1b;}
.alert-glass.warning{background:rgba(254,249,195,.8);border:1px solid rgba(253,224,71,.5);color:#854d0e;}
.alert-glass i{font-size:1rem;flex-shrink:0;margin-top:2px;}
.xbtn{margin-left:auto;background:none;border:none;cursor:pointer;color:inherit;opacity:.55;font-size:1rem;line-height:1;padding:0;}
.xbtn:hover{opacity:1;}

/* warning banner */
.warn-banner {
    display:flex;align-items:center;gap:1rem;
    padding:1rem 1.4rem;margin-bottom:1.75rem;
    background:rgba(254,249,195,.75);
    border:1px solid rgba(253,224,71,.5);
    border-left:4px solid #fbbf24;
    border-radius:16px;
    backdrop-filter:blur(12px);
}
.warn-banner .wi{font-size:1.75rem;flex-shrink:0;}
.warn-banner h6{font-size:.875rem;font-weight:700;color:#92400e;margin:0 0 .2rem;}
.warn-banner p {font-size:.8rem;color:#78350f;margin:0;}

/* election card */
.el-card {
    overflow:hidden;margin-bottom:1.1rem;
    animation:popIn .5s cubic-bezier(.34,1.56,.64,1) both;
    transition:transform .25s ease, box-shadow .25s ease;
}
.el-card:hover{transform:translateY(-3px);box-shadow:0 12px 36px rgba(100,120,200,.13);}

/* left accent */
.el-card.st-active  { border-left:4px solid #22c55e; }
.el-card.st-upcoming{ border-left:4px solid #fbbf24; }
.el-card.st-ended   { border-left:4px solid #94a3b8; }
.el-card.st-pending { border-left:4px solid #818cf8; }

.el-head {
    display:flex;align-items:flex-start;justify-content:space-between;
    flex-wrap:wrap;gap:.75rem;
    padding:1.1rem 1.4rem;
    border-bottom:1px solid rgba(200,210,240,.3);
}
.el-title { font-size:1rem;font-weight:800;color:#0f172a;margin:0 0 .3rem;letter-spacing:-.02em; }
.el-date  { font-size:.75rem;color:#94a3b8;display:flex;align-items:center;gap:.35rem; }

/* status pills */
.sp {
    display:inline-flex;align-items:center;gap:.3rem;
    font-size:.68rem;font-weight:700;padding:.22rem .75rem;
    border-radius:99px;letter-spacing:.04em;text-transform:uppercase;white-space:nowrap;
}
.sp-active  {background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.25);color:#16a34a;}
.sp-ended   {background:rgba(148,163,184,.1);border:1px solid rgba(148,163,184,.2);color:#64748b;}
.sp-upcoming{background:rgba(251,191,36,.12);border:1px solid rgba(251,191,36,.3);color:#92400e;}
.sp-pending {background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.2);color:#6366f1;}
.sp-paused  {background:rgba(249,115,22,.12);border:1px solid rgba(249,115,22,.28);color:#ea580c;}
.live-dot   {width:7px;height:7px;border-radius:50%;background:#22c55e;box-shadow:0 0 0 3px rgba(34,197,94,.2);animation:pulse 2s infinite;}
.pause-dot  {width:7px;height:7px;border-radius:50%;background:#f97316;box-shadow:0 0 0 3px rgba(249,115,22,.2);animation:pulse 2s infinite;}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}

.el-body { padding:1.1rem 1.4rem; }
.el-inner { display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1rem; }

/* stat boxes */
.stat-row { display:flex;gap:.65rem;flex-wrap:wrap; }
.sbox {
    display:flex;flex-direction:column;align-items:center;justify-content:center;
    background:rgba(255,255,255,.65);border:1px solid rgba(255,255,255,.9);
    border-radius:12px;padding:.6rem .9rem;min-width:72px;
    backdrop-filter:blur(10px);
}
.sbox-num { font-size:1.3rem;font-weight:800;color:#0f172a;line-height:1; }
.sbox-lbl { font-size:.68rem;color:#94a3b8;font-weight:500;margin-top:.2rem;white-space:nowrap; }

/* progress */
.prog-wrap { margin-top:.75rem;min-width:200px;max-width:300px; }
.prog-meta { display:flex;justify-content:space-between;font-size:.72rem;color:#94a3b8;margin-bottom:.35rem; }
.prog-track { height:6px;background:rgba(0,0,0,.07);border-radius:99px;overflow:hidden; }
.prog-fill  { height:100%;border-radius:99px;background:linear-gradient(90deg,#6366f1,#a78bfa);transition:width 1.2s ease; }

/* action buttons */
.action-row { display:flex;gap:.45rem;flex-wrap:wrap;align-items:center; }

.abtn {
    display:inline-flex;align-items:center;gap:.35rem;
    padding:.38rem .85rem;border-radius:99px;font-size:.75rem;font-weight:600;
    text-decoration:none;cursor:pointer;border:1px solid;
    transition:all .18s ease;white-space:nowrap;background:none;
}
.abtn:hover{transform:translateY(-1px);text-decoration:none;}

.abtn-blue  {border-color:rgba(99,102,241,.3);color:#6366f1;}
.abtn-blue:hover{background:rgba(99,102,241,.08);border-color:rgba(99,102,241,.5);color:#6366f1;}

.abtn-teal  {border-color:rgba(20,184,166,.3);color:#0f766e;}
.abtn-teal:hover{background:rgba(20,184,166,.08);border-color:rgba(20,184,166,.5);color:#0f766e;}

.abtn-green {border-color:rgba(34,197,94,.3);color:#16a34a;}
.abtn-green:hover{background:rgba(34,197,94,.08);border-color:rgba(34,197,94,.5);color:#16a34a;}

.abtn-amber {border-color:rgba(251,191,36,.4);color:#92400e;}
.abtn-amber:hover{background:rgba(251,191,36,.1);border-color:rgba(251,191,36,.6);color:#92400e;}

.abtn-slate {border-color:rgba(100,116,139,.25);color:#475569;}
.abtn-slate:hover{background:rgba(100,116,139,.08);border-color:rgba(100,116,139,.4);color:#334155;}

.abtn-red   {border-color:rgba(239,68,68,.25);color:#dc2626;}
.abtn-red:hover{background:rgba(239,68,68,.08);border-color:rgba(239,68,68,.45);color:#dc2626;}

/* empty */
.empty-state{text-align:center;padding:4rem 1rem;}
.empty-state .ei{font-size:4rem;opacity:.2;margin-bottom:1rem;}
.empty-state p{font-size:.9rem;color:#94a3b8;}

/* ── MODAL ── */
.modal-glass .modal-content {
    background:rgba(255,255,255,.92);
    backdrop-filter:blur(24px) saturate(160%);
    -webkit-backdrop-filter:blur(24px) saturate(160%);
    border:1px solid rgba(255,255,255,.9);
    border-radius:24px;
    box-shadow:0 20px 60px rgba(0,0,0,.15);
    overflow:hidden;
}
.modal-head-custom {
    padding:1.25rem 1.5rem;
    display:flex;align-items:center;gap:.75rem;
    border-bottom:1px solid rgba(200,210,240,.3);
}
.modal-head-custom h5{font-size:1rem;font-weight:800;color:#1e293b;margin:0;flex:1;}
.modal-close{background:none;border:none;cursor:pointer;color:#94a3b8;font-size:1.25rem;line-height:1;padding:0;transition:color .2s;}
.modal-close:hover{color:#1e293b;}
.modal-body-custom{padding:1.5rem;}
.modal-foot-custom{padding:1rem 1.5rem;border-top:1px solid rgba(200,210,240,.3);display:flex;gap:.6rem;justify-content:flex-end;}

.input-glass{
    width:100%;padding:.7rem 1rem;
    background:rgba(255,255,255,.75)!important;
    border:1px solid rgba(200,210,240,.65)!important;
    border-radius:12px!important;font-size:.875rem!important;
    color:#1e293b!important;font-family:inherit;
    transition:border-color .2s,box-shadow .2s;outline:none;
}
.input-glass:focus{border-color:rgba(99,102,241,.5)!important;box-shadow:0 0 0 3px rgba(99,102,241,.1)!important;}
.field-label{display:block;font-size:.78rem;font-weight:700;color:#475569;margin-bottom:.4rem;}

.date-row{display:grid;grid-template-columns:1fr 1fr;gap:.85rem;}
@media(max-width:480px){.date-row{grid-template-columns:1fr;}}

.btn-save{
    display:inline-flex;align-items:center;gap:.5rem;
    background:linear-gradient(135deg,#6366f1,#818cf8);
    color:#fff;border:none;border-radius:99px;
    padding:.6rem 1.5rem;font-size:.85rem;font-weight:700;cursor:pointer;
    box-shadow:0 4px 14px rgba(99,102,241,.3);transition:all .2s ease;
}
.btn-save:hover{transform:scale(1.03);opacity:.9;}

.btn-cancel{
    display:inline-flex;align-items:center;gap:.5rem;
    background:rgba(255,255,255,.7);border:1px solid rgba(200,210,240,.7);
    color:#475569;border-radius:99px;
    padding:.6rem 1.5rem;font-size:.85rem;font-weight:600;cursor:pointer;
    transition:all .2s ease;
}
.btn-cancel:hover{background:rgba(239,68,68,.07);border-color:rgba(239,68,68,.3);color:#dc2626;}

.btn-del{
    display:inline-flex;align-items:center;gap:.5rem;
    background:linear-gradient(135deg,#ef4444,#f87171);
    color:#fff;border:none;border-radius:99px;
    padding:.6rem 1.5rem;font-size:.85rem;font-weight:700;cursor:pointer;
    text-decoration:none;
    box-shadow:0 4px 14px rgba(239,68,68,.28);transition:all .2s ease;
}
.btn-del:hover{transform:scale(1.03);opacity:.9;color:#fff;text-decoration:none;}

/* delete list */
.del-list{list-style:none;padding:0;margin:0;}
.del-list li{display:flex;align-items:center;gap:.6rem;padding:.5rem 0;font-size:.85rem;color:#475569;border-bottom:1px solid rgba(200,210,240,.25);}
.del-list li:last-child{border-bottom:none;}
.del-list li i{color:#ef4444;font-size:.9rem;}
.del-list li strong{color:#1e293b;}

@keyframes popIn{from{opacity:0;transform:translateY(14px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}
</style>

<style>
  :root {
    --glass-bg: rgba(255, 255, 255, 0.88);
    --glass-border: rgba(15, 127, 99, 0.14);
    --text-main: #0f172a;
    --text-muted: #64748b;
    --accent: #0f7f63;
  }

  body {
    background:
      radial-gradient(at 0% 0%, rgba(15,127,99,0.22) 0px, transparent 55%),
      radial-gradient(at 100% 0%, rgba(16,185,129,0.18) 0px, transparent 45%),
      radial-gradient(at 100% 100%, rgba(15,127,99,0.10) 0px, transparent 45%),
      radial-gradient(at 0% 100%, rgba(94,234,212,0.09) 0px, transparent 40%),
      #f4fbf7;
    background-attachment: fixed;
    color: var(--text-main) !important;
  }

  .page-wrap {
    max-width: 1320px;
    margin: 0 auto;
    padding: 1.5rem 1.75rem 4rem;
  }

  .glass {
    background: var(--glass-bg) !important;
    border: 1px solid var(--glass-border) !important;
    box-shadow: 0 22px 60px rgba(15,127,99,0.08) !important;
  }

  .page-hdr {
    display: flex !important;
    align-items: flex-start !important;
    justify-content: space-between !important;
    flex-wrap: wrap !important;
    gap: 1rem !important;
    margin-bottom: 1.75rem !important;
  }
  .page-hdr h2 { color: #0f172a !important; }
  .page-hdr p { color: #64748b !important; }

  .warn-banner {
    background: rgba(255,255,255,0.95) !important;
    border-color: rgba(15,127,99,0.12) !important;
    box-shadow: 0 20px 45px rgba(15,127,99,0.08) !important;
  }

  .btn-primary-glass {
    color: #fff !important;
    background: linear-gradient(135deg, #6366f1, #818cf8) !important;
  }

  .alert-glass { background: rgba(255,255,255,0.95) !important; border-color: rgba(15,127,99,0.12) !important; color: #0f172a !important; }
  .alert-glass.success { background: rgba(220,252,231,0.96) !important; }
  .alert-glass.danger { background: rgba(254,226,226,0.96) !important; }
  .alert-glass.warning { background: rgba(254,249,195,0.96) !important; }

  .el-card { background: rgba(255,255,255,0.86) !important; border-color: rgba(15,127,99,0.12) !important; }
  .el-head { background: rgba(255,255,255,0.92) !important; border-color: rgba(15,127,99,0.12) !important; }
  .el-title { color: #0f172a !important; }
  .el-date { color: #64748b !important; }

  .sbox { background: rgba(255,255,255,0.95) !important; border-color: rgba(255,255,255,0.95) !important; }
  .sbox-num { color: #0f172a !important; }
  .sbox-lbl { color: #64748b !important; }

  .btn-save { background: linear-gradient(135deg, #6366f1, #818cf8) !important; color: #fff !important; }
  .btn-cancel { background: rgba(255,255,255,0.95) !important; color: #475569 !important; border-color: rgba(15,127,99,0.12) !important; }
  .btn-del { background: linear-gradient(135deg, #ef4444, #f87171) !important; color: #fff !important; }

  .modal-glass .modal-content { background: rgba(255,255,255,0.95) !important; border-color: rgba(255,255,255,0.95) !important; }
  .modal-head-custom { background: rgba(255,255,255,0.95) !important; border-color: rgba(15,127,99,0.12) !important; }
  .modal-head-custom h5 { color: #0f172a !important; }

  [data-theme="dark"] body {
    background:
      radial-gradient(at 0% 0%, rgba(99,102,241,0.08) 0px, transparent 50%),
      radial-gradient(at 100% 0%, rgba(139,92,246,0.06) 0px, transparent 50%),
      radial-gradient(at 100% 100%, rgba(16,185,129,0.05) 0px, transparent 50%),
      #0d0f1a !important;
    color: #e2e8f0 !important;
  }

  [data-theme="dark"] .glass { background: rgba(20,22,35,0.88) !important; border-color: rgba(99,102,241,0.14) !important; box-shadow: 0 18px 44px rgba(0,0,0,0.45) !important; }
  [data-theme="dark"] .el-card { background: rgba(15,17,23,0.9) !important; border-color: rgba(99,102,241,0.12) !important; }
  [data-theme="dark"] .el-head { background: rgba(20,22,35,0.94) !important; border-color: rgba(99,102,241,0.12) !important; }
  [data-theme="dark"] .el-title { color: #e2e8f0 !important; }
  [data-theme="dark"] .el-date, [data-theme="dark"] .warn-banner p, [data-theme="dark"] .warn-banner h6, [data-theme="dark"] .sbox-lbl { color: #94a3b8 !important; }
  [data-theme="dark"] .sbox { background: rgba(20,22,35,0.78) !important; border-color: rgba(99,102,241,0.12) !important; }
  [data-theme="dark"] .prog-track { background: rgba(255,255,255,0.08) !important; }
  [data-theme="dark"] .prog-fill { background: linear-gradient(90deg, #6366f1, #a78bfa) !important; }
  [data-theme="dark"] .btn-cancel { background: rgba(15,17,23,0.86) !important; color: #94a3b8 !important; border-color: rgba(99,102,241,0.12) !important; }
  [data-theme="dark"] .btn-save { background: linear-gradient(135deg, #6366f1, #818cf8) !important; }
  [data-theme="dark"] .btn-del { background: rgba(255,255,255,0.08) !important; color: #fecdd3 !important; border-color: rgba(239,68,68,0.28) !important; }
  [data-theme="dark"] .modal-content { background: rgba(15,17,23,0.92) !important; border-color: rgba(99,102,241,0.16) !important; }
  [data-theme="dark"] .modal-head-custom { background: rgba(20,22,35,0.93) !important; }
</style>

<div class="page-wrap">

    <!-- Page Header -->
    <div class="page-hdr">
        <div>
            <h2>📅 Manage Elections</h2>
            <p>View, edit, activate, deactivate, or delete elections.</p>
        </div>
        <a href="create_election.php" class="btn-primary-glass">
            <i class="bi bi-plus-circle"></i> Create New Election
        </a>
    </div>

    <!-- Alerts -->
    <?php if($message): ?>
    <div class="alert-glass success">
        <i class="bi bi-check-circle-fill"></i><span><?php echo $message; ?></span>
        <button class="xbtn" onclick="this.closest('.alert-glass').remove()">✕</button>
    </div>
    <?php endif; ?>
    <?php if($error): ?>
    <div class="alert-glass danger">
        <i class="bi bi-exclamation-triangle-fill"></i><span><?php echo $error; ?></span>
        <button class="xbtn" onclick="this.closest('.alert-glass').remove()">✕</button>
    </div>
    <?php endif; ?>

    <!-- Warning Banner -->
    <div class="warn-banner">
        <div class="wi">⚠️</div>
        <div>
            <h6>Deleting an election is permanent</h6>
            <p>All positions, candidates, photos, and votes will be <strong>permanently erased</strong>. This cannot be undone.</p>
        </div>
    </div>

    <!-- Election Cards -->
    <?php if(count($elections) > 0):
        foreach($elections as $i => $el):
            $st = $el['current_status'];
            $pill_class = 'sp-pending'; $pill_label = 'Pending';
            $card_class = 'st-pending';
            if($st=='active')    { $pill_class='sp-active';   $pill_label='Active';   $card_class='st-active'; }
            elseif($st=='paused'){ $pill_class='sp-paused';   $pill_label='Paused';   $card_class='st-upcoming'; }
            elseif($st=='ended') { $pill_class='sp-ended';    $pill_label='Ended';    $card_class='st-ended'; }
            elseif($st=='upcoming'){ $pill_class='sp-upcoming'; $pill_label='Upcoming'; $card_class='st-upcoming'; }

            $progress = 0;
            if($st=='active') {
                $now = time(); $start = strtotime($el['start_date']); $end = strtotime($el['end_date']);
                $progress = min(100, max(0, (($now-$start)/($end-$start))*100));
            }
    ?>
    <div class="el-card glass <?php echo $card_class; ?>" style="animation-delay:<?php echo $i*0.06; ?>s">

        <!-- Head -->
        <div class="el-head">
            <div>
                <div class="el-title">
                    <?php echo htmlspecialchars($el['title']); ?>
                    <?php if($el['is_active'] && !$el['is_paused']): ?>
                        <span class="sp sp-active ms-2" style="vertical-align:middle;">
                            <span class="live-dot"></span> Live
                        </span>
                    <?php elseif($el['is_active'] && $el['is_paused']): ?>
                        <span class="sp sp-paused ms-2" style="vertical-align:middle;">
                            <span class="pause-dot"></span> Paused
                        </span>
                    <?php endif; ?>
                </div>
                <div class="el-date">
                    <i class="bi bi-calendar3"></i>
                    <?php echo date('M d, Y h:i A', strtotime($el['start_date'])); ?>
                    &nbsp;→&nbsp;
                    <?php echo date('M d, Y h:i A', strtotime($el['end_date'])); ?>
                </div>
            </div>
            <span class="sp <?php echo $pill_class; ?>"><?php echo $pill_label; ?></span>
        </div>

        <!-- Body -->
        <div class="el-body">
            <div class="el-inner">

                <!-- Stats + Progress -->
                <div>
                    <div class="stat-row">
                        <div class="sbox">
                            <div class="sbox-num"><?php echo $el['position_count']; ?></div>
                            <div class="sbox-lbl">Positions</div>
                        </div>
                        <div class="sbox">
                            <div class="sbox-num"><?php echo $el['candidate_count']; ?></div>
                            <div class="sbox-lbl">Candidates</div>
                        </div>
                        <div class="sbox">
                            <div class="sbox-num"><?php echo $el['voter_count']; ?></div>
                            <div class="sbox-lbl">Voted</div>
                        </div>
                    </div>

                    <?php if($st=='active'): ?>
                    <div class="prog-wrap">
                        <div class="prog-meta">
                            <span>Time elapsed</span>
                            <span><?php echo round($progress); ?>%</span>
                        </div>
                        <div class="prog-track">
                            <div class="prog-fill" style="width:<?php echo $progress; ?>%"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Actions -->
                <div class="action-row">
                    <a href="results.php?election_id=<?php echo $el['id']; ?>" class="abtn abtn-teal">
                        <i class="bi bi-bar-chart"></i> Results
                    </a>
                    <a href="manage_positions.php?election_id=<?php echo $el['id']; ?>" class="abtn abtn-blue">
                        <i class="bi bi-list-task"></i> Positions
                    </a>
                    <a href="add_candidates.php?election_id=<?php echo $el['id']; ?>" class="abtn abtn-green">
                        <i class="bi bi-people"></i> Candidates
                    </a>

                    <!-- Edit -->
                    <button class="abtn abtn-slate"
                            data-bs-toggle="modal"
                            data-bs-target="#editModal<?php echo $el['id']; ?>">
                        <i class="bi bi-pencil"></i> Edit
                    </button>

                    <!-- Activate / Deactivate -->
                    <?php if($el['is_active']): ?>
                    <a href="?deactivate=<?php echo $el['id']; ?>" class="abtn abtn-amber"
                       onclick="return confirm('Deactivate this election?')">
                        <i class="bi bi-stop-circle"></i> Deactivate
                    </a>

                    <!-- Pause / Resume (only for active elections) -->
                    <?php if($el['is_paused']): ?>
                    <a href="?resume=<?php echo $el['id']; ?>" class="abtn abtn-green"
                       onclick="return confirm('Resume voting for this election?')">
                        <i class="bi bi-play-circle"></i> Resume
                    </a>
                    <?php else: ?>
                    <a href="?pause=<?php echo $el['id']; ?>" class="abtn abtn-amber"
                       onclick="return confirm('Pause voting? Students will not be able to cast votes while paused. Applications are NOT affected.')">
                        <i class="bi bi-pause-circle"></i> Pause
                    </a>
                    <?php endif; ?>

                    <?php else: ?>
                    <a href="?activate=<?php echo $el['id']; ?>" class="abtn abtn-green"
                       onclick="return confirm('Activate this election? Any currently active election will be deactivated.')">
                        <i class="bi bi-play-circle"></i> Activate
                    </a>
                    <?php endif; ?>

                    <!-- Delete -->
                    <button class="abtn abtn-red"
                            data-bs-toggle="modal"
                            data-bs-target="#deleteModal<?php echo $el['id']; ?>">
                        <i class="bi bi-trash3"></i> Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ── EDIT MODAL ── -->
    <div class="modal fade modal-glass" id="editModal<?php echo $el['id']; ?>" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-head-custom">
                    <span style="font-size:1.2rem;">✏️</span>
                    <h5>Edit Election</h5>
                    <button class="modal-close" data-bs-dismiss="modal">✕</button>
                </div>
                <form method="POST">
                    <div class="modal-body-custom">
                        <input type="hidden" name="election_id" value="<?php echo $el['id']; ?>">

                        <div class="mb-3">
                            <label class="field-label">Election Title</label>
                            <input type="text" name="title" class="input-glass"
                                   value="<?php echo htmlspecialchars($el['title']); ?>" required>
                        </div>

                        <div class="date-row">
                            <div>
                                <label class="field-label">
                                    <i class="bi bi-calendar-event" style="color:#6366f1;margin-right:3px;"></i>
                                    Start Date &amp; Time
                                </label>
                                <input type="datetime-local" name="start_date" class="input-glass"
                                       value="<?php echo date('Y-m-d\TH:i', strtotime($el['start_date'])); ?>" required>
                            </div>
                            <div>
                                <label class="field-label">
                                    <i class="bi bi-calendar-x" style="color:#f472b6;margin-right:3px;"></i>
                                    End Date &amp; Time
                                </label>
                                <input type="datetime-local" name="end_date" class="input-glass"
                                       value="<?php echo date('Y-m-d\TH:i', strtotime($el['end_date'])); ?>" required>
                            </div>
                        </div>

                        <?php if(!empty($el['application_start'])): ?>
                        <div class="mt-3">
                            <label class="field-label">
                                <i class="bi bi-person-plus" style="color:#10b981;margin-right:3px;"></i>
                                Applications Open Date &amp; Time
                            </label>
                            <input type="datetime-local" name="app_start" class="input-glass"
                                   value="<?php echo date('Y-m-d\TH:i', strtotime($el['application_start'])); ?>">
                            <div style="font-size:.75rem;color:#94a3b8;margin-top:.3rem;">
                                Application window closes automatically when voting starts. Leave unchanged to keep current.
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if($el['is_active']): ?>
                        <div class="alert-glass warning mt-3" style="margin-bottom:0;">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <span>This election is currently <strong>ACTIVE</strong>. Changes apply immediately.</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-foot-custom">
                        <button type="button" class="btn-cancel" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Cancel
                        </button>
                        <button type="submit" name="edit_election" class="btn-save">
                            <i class="bi bi-save2"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ── DELETE MODAL ── -->
    <div class="modal fade modal-glass" id="deleteModal<?php echo $el['id']; ?>" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-head-custom" style="background:rgba(254,226,226,.6);">
                    <span style="font-size:1.2rem;">🗑️</span>
                    <h5 style="color:#991b1b;">Delete Election</h5>
                    <button class="modal-close" data-bs-dismiss="modal">✕</button>
                </div>
                <div class="modal-body-custom">
                    <div style="text-align:center;margin-bottom:1.25rem;">
                        <div style="width:56px;height:56px;border-radius:50%;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);display:inline-flex;align-items:center;justify-content:center;font-size:1.75rem;">🗑️</div>
                        <div style="font-size:1rem;font-weight:800;color:#0f172a;margin-top:.75rem;">
                            "<?php echo htmlspecialchars($el['title']); ?>"
                        </div>
                    </div>

                    <div class="alert-glass danger" style="margin-bottom:1rem;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span>This action <strong>cannot be undone</strong>. Everything below will be permanently removed.</span>
                    </div>

                    <ul class="del-list">
                        <li><i class="bi bi-x-circle-fill"></i><strong><?php echo $el['position_count']; ?></strong>&nbsp;positions</li>
                        <li><i class="bi bi-x-circle-fill"></i><strong><?php echo $el['candidate_count']; ?></strong>&nbsp;candidates (incl. photos)</li>
                        <li><i class="bi bi-x-circle-fill"></i><strong><?php echo $el['voter_count']; ?></strong>&nbsp;votes cast</li>
                    </ul>

                    <?php if($el['is_active']): ?>
                    <div class="alert-glass warning mt-3" style="margin-bottom:0;">
                        <i class="bi bi-lightning-charge-fill"></i>
                        <span>This election is currently <strong>ACTIVE</strong>!</span>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-foot-custom">
                    <button type="button" class="btn-cancel" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancel
                    </button>
                    <a href="?delete_election=<?php echo $el['id']; ?>" class="btn-del">
                        <i class="bi bi-trash3-fill"></i> Yes, Delete
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php endforeach; ?>

<?php else: ?>
    <div class="glass empty-state">
        <div class="ei">🗳️</div>
        <p style="font-weight:700;color:#475569;font-size:1rem;">No elections found</p>
        <p>Create your first election to get started.</p>
        <a href="create_election.php" class="btn-primary-glass" style="margin-top:.75rem;">
            <i class="bi bi-plus-circle"></i> Create Election
        </a>
    </div>
<?php endif; ?>

</div><!-- /page-wrap -->

<?php include '../includes/footer.php'; ?>