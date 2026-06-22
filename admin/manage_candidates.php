<?php
// filename: admin/manage_candidates.php
// location: admin/manage_candidates.php

require_once '../config/database.php';
require_once '../includes/auth_check.php';

if($_SESSION['user_type'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

$message = '';
$error = '';

$election_id = isset($_GET['election_id']) ? $_GET['election_id'] : null;

if(!$election_id) {
    $el = $pdo->query("SELECT id FROM elections ORDER BY created_at DESC LIMIT 1")->fetch();
    if($el) $election_id = $el['id'];
}

$election = null;
if($election_id) {
    $stmt = $pdo->prepare("SELECT * FROM elections WHERE id = ?");
    $stmt->execute([$election_id]);
    $election = $stmt->fetch();
}

if(isset($_GET['delete_candidate'])) {
    $cid = $_GET['delete_candidate'];
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT full_name, photo FROM candidates WHERE id = ?");
        $stmt->execute([$cid]);
        $c = $stmt->fetch();
        if($c) {
            if(!empty($c['photo']) && file_exists('../'.$c['photo'])) unlink('../'.$c['photo']);
            $pdo->prepare("DELETE FROM candidates WHERE id = ?")->execute([$cid]);
            $pdo->commit();
            $message = "Candidate <strong>{$c['full_name']}</strong> deleted successfully!";
        } else { $error = "Candidate not found!"; }
    } catch(PDOException $e) { $pdo->rollBack(); $error = "Error: ".$e->getMessage(); }
}

if(isset($_POST['edit_candidate'])) {
    $cid = $_POST['candidate_id'];
    try {
        $pdo->beginTransaction();
        $image_path = null;
        if(isset($_FILES['candidate_image']) && $_FILES['candidate_image']['error']==0) {
            $ext = strtolower(pathinfo($_FILES['candidate_image']['name'], PATHINFO_EXTENSION));
            if(in_array($ext,['jpg','jpeg','png','gif']) && $_FILES['candidate_image']['size'] <= 5*1024*1024) {
                $stmt = $pdo->prepare("SELECT photo FROM candidates WHERE id = ?");
                $stmt->execute([$cid]);
                $old = $stmt->fetchColumn();
                if($old && file_exists('../'.$old)) unlink('../'.$old);
                $dir = '../assets/uploads/candidates/';
                if(!file_exists($dir)) mkdir($dir,0777,true);
                $fname = uniqid().'_'.preg_replace('/[^a-zA-Z0-9.]/','',$_FILES['candidate_image']['name']);
                if(move_uploaded_file($_FILES['candidate_image']['tmp_name'],$dir.$fname))
                    $image_path = 'assets/uploads/candidates/'.$fname;
            }
        }
        $target_pos = $_POST['position_id'];
        
        // Get current candidate position
        $stmt = $pdo->prepare("SELECT position_id FROM candidates WHERE id = ?");
        $stmt->execute([$cid]);
        $current_pos = $stmt->fetchColumn();
        
        $can_update = true;
        if($target_pos != $current_pos) {
            $stmt = $pdo->prepare("SELECT max_candidates, (SELECT COUNT(*) FROM candidates WHERE position_id = ?) as current_count FROM positions WHERE id = ?");
            $stmt->execute([$target_pos, $target_pos]);
            $pos_info = $stmt->fetch();
            
            if($pos_info && $pos_info['current_count'] >= $pos_info['max_candidates']) {
                $can_update = false;
                $error = "The selected position has already reached its maximum candidate limit!";
            }
        }

        if($can_update) {
            if($image_path)
                $pdo->prepare("UPDATE candidates SET position_id=?, platform=?, photo=? WHERE id=?")->execute([$target_pos,$_POST['platform'],$image_path,$cid]);
            else
                $pdo->prepare("UPDATE candidates SET position_id=?, platform=? WHERE id=?")->execute([$target_pos,$_POST['platform'],$cid]);
            $pdo->commit();
            $message = "Candidate updated successfully!";
        } else {
            $pdo->rollBack();
        }
    } catch(PDOException $e) { $pdo->rollBack(); $error = "Error: ".$e->getMessage(); }
}

$candidates = [];
if($election_id) {
    $stmt = $pdo->prepare("
        SELECT c.*, p.position_name, p.id as pos_id, p.display_order,
               e.title as election_title, e.is_active,
               s.profile_photo as student_photo,
               (SELECT COUNT(*) FROM votes v WHERE v.candidate_id=c.id) as vote_count
        FROM candidates c
        JOIN positions p ON c.position_id=p.id
        JOIN elections e ON p.election_id=e.id
        LEFT JOIN students s ON c.student_id=s.student_id
        WHERE p.election_id=?
        ORDER BY p.display_order, c.full_name
    ");
    $stmt->execute([$election_id]);
    $candidates = $stmt->fetchAll();
}

$positions = [];
if($election_id) {
    $stmt = $pdo->prepare("SELECT *, (SELECT COUNT(*) FROM candidates c WHERE c.position_id = p.id) as current_candidates FROM positions p WHERE election_id=? ORDER BY display_order");
    $stmt->execute([$election_id]);
    $positions = $stmt->fetchAll();
}

$all_elections = $pdo->query("SELECT id, title, is_active FROM elections ORDER BY created_at DESC")->fetchAll();

include '../includes/header.php';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@600;700&display=swap');

* { box-sizing: border-box; }

body {
  font-family: 'Plus Jakarta Sans', sans-serif;
  min-height: 100vh;
  background:
    radial-gradient(ellipse at 10% 20%, rgba(99,179,237,0.25) 0%, transparent 50%),
    radial-gradient(ellipse at 85% 10%, rgba(167,139,250,0.2) 0%, transparent 45%),
    radial-gradient(ellipse at 60% 80%, rgba(110,231,183,0.2) 0%, transparent 45%),
    radial-gradient(ellipse at 20% 85%, rgba(251,191,36,0.15) 0%, transparent 40%),
    #eef2ff;
  background-attachment: fixed;
  color: #1e293b;
}

/* floating blobs */
body::before, body::after {
  content: '';
  position: fixed;
  border-radius: 50%;
  filter: blur(80px);
  pointer-events: none;
  z-index: 0;
}
body::before {
  width: 500px; height: 500px;
  top: -100px; left: -100px;
  background: rgba(99,179,237,0.2);
  animation: drift 12s ease-in-out infinite alternate;
}
body::after {
  width: 400px; height: 400px;
  bottom: -80px; right: -80px;
  background: rgba(167,139,250,0.2);
  animation: drift 15s ease-in-out infinite alternate-reverse;
}
@keyframes drift {
  from { transform: translate(0,0) scale(1); }
  to   { transform: translate(40px,30px) scale(1.08); }
}

.page-wrap { position:relative;z-index:1; max-width: 1400px; margin: 0 auto; padding: 1.5rem 1.75rem 4rem; }

.glass {
    background:rgba(255,255,255,0.55);
    backdrop-filter:blur(20px) saturate(160%);
    -webkit-backdrop-filter:blur(20px) saturate(160%);
    border:1px solid rgba(255,255,255,0.8);
    border-radius:20px;
    box-shadow:0 4px 24px rgba(100,120,200,0.08);
}

/* header */
.page-hdr { display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:2rem; padding: 0 0.5rem; }
.page-hdr h2 { font-family:'Sora',sans-serif; font-size:clamp(1.4rem,2.5vw,2rem);font-weight:800;color:#1e293b;letter-spacing:-.025em;margin:0; }
.page-hdr p  { color:#64748b;font-size:.9rem;margin:.3rem 0 0; }

/* alerts */
.alert-glass {
    display:flex;align-items:flex-start;gap:.75rem;padding:1.1rem 1.4rem;border-radius:18px;margin-bottom:1.5rem;font-size:.9rem;animation:popIn .4s ease both;
    backdrop-filter: blur(12px);
}
.alert-glass.success{background:rgba(220,252,231,.7);border:1px solid rgba(34,197,94,.2);color:#166534;}
.alert-glass.danger {background:rgba(254,226,226,.7);border:1px solid rgba(239,68,68,.2);color:#991b1b;}
.alert-glass i{font-size:1.1rem;flex-shrink:0;margin-top:2px;}
.xbtn{margin-left:auto;background:none;border:none;cursor:pointer;color:inherit;opacity:.55;font-size:1rem;line-height:1;padding:0;transition:opacity .2s;}
.xbtn:hover{opacity:1;}

/* filter bar */
.filter-bar {
    padding:1.2rem 1.6rem;margin-bottom:2rem;
    display:flex;align-items:center;justify-content:space-between;
    flex-wrap:wrap;gap:1rem;
}
.filter-left { display:flex;align-items:center;gap:1.2rem;flex-wrap:wrap;flex:1; }
.filter-label { font-size:.8rem;font-weight:800;color:#475569;text-transform:uppercase;letter-spacing:.04em; }

.select-glass {
    padding:.7rem 1.2rem;
    background:rgba(255,255,255,.7)!important;
    border:1.5px solid rgba(0,0,0,0.05)!important;
    border-radius:14px!important;font-size:.875rem!important;
    color:#1e293b!important;cursor:pointer;
    transition:all .2s;outline:none;
    min-width:240px; font-family:inherit;
}
.select-glass:focus{border-color:#6366f1!important;box-shadow:0 0 0 4px rgba(99,102,241,.1)!important;}

.count-pill {
    display:inline-flex;align-items:center;gap:.5rem;
    background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.15);
    border-radius:99px;padding:.35rem 1rem;
    font-size:.8rem;font-weight:700;color:#6366f1;white-space:nowrap;
}

.btn-primary-glass {
    display:inline-flex;align-items:center;gap:.6rem;
    background:linear-gradient(135deg,#6366f1,#8b5cf6);
    color:#fff!important;border:none;border-radius:99px;
    padding:.7rem 1.5rem;font-size:.875rem;font-weight:700;
    cursor:pointer;text-decoration:none;white-space:nowrap;
    box-shadow:0 4px 14px rgba(99,102,241,.3);transition:all .2s;
}
.btn-primary-glass:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(99,102,241,.4);color:#fff;}

/* election info strip */
.el-strip {
    display:flex;align-items:center;gap:.8rem;flex-wrap:wrap;
    padding:.8rem 1.4rem;margin-bottom:2rem;
    background:rgba(255,255,255,0.4);
    border:1.5px solid rgba(99,102,241,0.1);
    border-radius:16px;font-size:.875rem;color:#4338ca;
    backdrop-filter: blur(10px);
}
.el-strip strong { font-weight: 800; color: #1e1b4b; }
.live-tag{display:inline-flex;align-items:center;gap:.35rem;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.2);border-radius:99px;padding:.2rem .8rem;font-size:.75rem;font-weight:700;color:#16a34a;margin-left:auto;}
.live-dot{width:6px;height:6px;border-radius:50%;background:#22c55e;animation:pulse 2s infinite;display:inline-block;}

/* candidate grid */
.cand-grid {
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(280px,1fr));
    gap:1.5rem;
}

/* candidate card */
.cand-card {
    background: rgba(255,255,255,0.5);
    border: 1.5px solid rgba(255,255,255,0.8);
    border-radius: 24px;
    overflow:hidden;position:relative;
    display:flex;flex-direction:column;
    animation:popIn .5s cubic-bezier(.34,1.56,.64,1) both;
    transition:all .3s cubic-bezier(.34,1.56,.64,1);
    box-shadow: 0 4px 20px rgba(100,120,200,0.05);
}
.cand-card:hover{transform:translateY(-8px);border-color:#6366f1;box-shadow:0 15px 40px rgba(99,102,241,0.12);background:rgba(255,255,255,0.8);}
.cand-card.live-border{border-top:4px solid #10b981;}

/* avatar */
.cand-avatar-wrap { padding:2rem 1.4rem .8rem;display:flex;justify-content:center; }
.cand-avatar {
    width:96px;height:96px;border-radius:50%;object-fit:cover;
    border:4px solid white;
    box-shadow:0 6px 18px rgba(0,0,0,0.06);
}
.cand-avatar-placeholder {
    width:96px;height:96px;border-radius:50%;
    background:linear-gradient(135deg, #e0e7ff, #c7d2fe);
    border:4px solid white;
    box-shadow:0 6px 18px rgba(0,0,0,0.06);
    display:flex;align-items:center;justify-content:center;
    font-size:2.8rem;color:#4338ca;
}

/* card body */
.cand-body { padding:0 1.5rem 1.5rem;flex:1;display:flex;flex-direction:column;align-items:center;text-align:center; }
.cand-name { font-family:'Sora',sans-serif; font-size:1.05rem;font-weight:800;color:#1e293b;margin-bottom:.4rem;letter-spacing:-.01em; }
.cand-pos  {
    display:inline-flex;align-items:center;gap:.4rem;
    font-size:.75rem;font-weight:800;color:#6366f1;
    background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.15);
    border-radius:99px;padding:.25rem .8rem;margin-bottom:.6rem;
    text-transform: uppercase; letter-spacing: 0.03em;
}
.cand-sid  {
    font-size:.75rem;font-weight:600;color:#94a3b8;
    background:rgba(0,0,0,.02);border-radius:99px;padding:.2rem .8rem;margin-bottom:1rem;
}

/* platform */
.cand-platform {
    background:rgba(255,255,255,.6);border:1.5px solid rgba(0,0,0,0.03);
    border-radius:16px;padding:.9rem 1.1rem;
    font-size:.8rem;color:#475569;line-height:1.6;
    text-align:left;width:100%;margin-bottom:1.2rem;
    max-height:85px;overflow:hidden;position:relative;
}
.cand-platform::after{content:'';position:absolute;bottom:0;left:0;right:0;height:25px;background:linear-gradient(transparent,rgba(255,255,255,.8));}

/* vote badge */
.vote-badge {
    display:inline-flex;align-items:center;gap:.4rem;
    background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.15);
    border-radius:99px;padding:.3rem .9rem;
    font-size:.78rem;font-weight:800;color:#065f46;margin-bottom:1.2rem;
}

/* card actions */
.cand-actions { display:grid; grid-template-columns: 1fr 1fr; gap:.8rem; width:100%; }
.btn-edit {
    display:flex;align-items:center;justify-content:center;gap:.4rem;
    background:rgba(255,255,255,.8);border:1.5px solid rgba(0,0,0,0.04);
    color:#475569;border-radius:14px;padding:.6rem;
    font-size:.85rem;font-weight:700;cursor:pointer;
    text-decoration:none;transition:all .2s;
}
.btn-edit:hover{background:#f1f5f9;border-color:#6366f1;color:#6366f1;}
.btn-del-sm {
    display:flex;align-items:center;justify-content:center;gap:.4rem;
    background:rgba(255,255,255,.8);border:1.5px solid rgba(0,0,0,0.04);
    color:#ef4444;border-radius:14px;padding:.6rem;
    font-size:.85rem;font-weight:700;cursor:pointer;
    transition:all .2s;
}
.btn-del-sm:hover{background:rgba(239,68,68,.05);border-color:#ef4444;}

/* empty state */
.empty-state{text-align:center;padding:5rem 2rem; background: rgba(255,255,255,0.4); border-radius: 24px;}
.empty-state .ei{font-size:4.5rem;margin-bottom:1.5rem; filter: grayscale(0.5) opacity(0.5);}
.empty-state p{font-size:1rem;color:#64748b; font-weight: 500;}

/* ── MODALS ── */
.modal-glass .modal-content {
    background:rgba(255,255,255,.96);
    backdrop-filter:blur(24px) saturate(160%);
    -webkit-backdrop-filter:blur(24px) saturate(160%);
    border:1px solid white;
    border-radius:28px;
    box-shadow:0 25px 70px rgba(30,40,100,.18);
    overflow:hidden;
}
.modal-head {
    padding:1.4rem 1.8rem;
    display:flex;align-items:center;gap:.8rem;
    border-bottom:1px solid rgba(0,0,0,.04);
}
.modal-head h5{font-family:'Sora',sans-serif;font-size:1.1rem;font-weight:800;color:#1e293b;margin:0;flex:1;}
.modal-head.danger-head{background:rgba(254,226,226,.3); border-bottom-color: rgba(239,68,68,0.1);}
.modal-close{background:none;border:none;cursor:pointer;color:#94a3b8;font-size:1.4rem;line-height:1;padding:0;transition:all .2s;}
.modal-close:hover{color:#1e293b; transform: rotate(90deg);}
.mbody{padding:1.8rem;}
.mfoot{padding:1.4rem 1.8rem;border-top:1px solid rgba(0,0,0,.04);display:flex;gap:.8rem;justify-content:flex-end; background: rgba(0,0,0,0.012);}

.field-label{display:block;font-size:.78rem;font-weight:800;color:#64748b;margin-bottom:.5rem; text-transform:uppercase; letter-spacing:0.04em;}
.input-glass{width:100%;padding:.75rem 1.1rem;background:rgba(255,255,255,.8)!important;border:1.5px solid rgba(0,0,0,.06)!important;border-radius:14px!important;font-size:.9rem!important;color:#1e293b!important;font-family:inherit;transition:all .2s;outline:none;}
.input-glass:focus{border-color:#6366f1!important;box-shadow:0 0 0 4px rgba(99,102,241,.12)!important; background: white !important;}

/* photo preview in modal */
.modal-avatar{width:90px;height:90px;border-radius:50%;object-fit:cover;border:4px solid white;box-shadow:0 6px 16px rgba(0,0,0,0.08);}
.modal-avatar-placeholder{width:90px;height:90px;border-radius:50%;background:linear-gradient(135deg, #e0e7ff, #c7d2fe);border:4px solid white;display:flex;align-items:center;justify-content:center;font-size:2.5rem;color:#4338ca;box-shadow:0 6px 16px rgba(0,0,0,0.08);}

.upload-hint{font-size:.75rem;color:#94a3b8;margin-top:.4rem; font-weight: 500;}

.btn-save{display:inline-flex;align-items:center;gap:.6rem;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff!important;border:none;border-radius:99px;padding:.75rem 1.8rem;font-size:.875rem;font-weight:700;cursor:pointer;box-shadow:0 4px 14px rgba(99,102,241,.3);transition:all .2s;}
.btn-save:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(99,102,241,.4); opacity: 0.95;}
.btn-cancel{display:inline-flex;align-items:center;gap:.6rem;background:white;border:1.5px solid rgba(0,0,0,0.08);color:#64748b;border-radius:99px;padding:.75rem 1.8rem;font-size:.875rem;font-weight:700;cursor:pointer;transition:all .2s;}
.btn-cancel:hover{background:#f8fafc; border-color:#cbd5e1; color:#1e293b;}
.btn-del-confirm{display:inline-flex;align-items:center;gap:.6rem;background:linear-gradient(135deg,#ef4444,#f87171);color:#fff!important;border:none;border-radius:99px;padding:.75rem 1.8rem;font-size:.875rem;font-weight:700;cursor:pointer;text-decoration:none;box-shadow:0 4px 14px rgba(239,68,68,.3);transition:all .2s;}
.btn-del-confirm:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(239,68,68,.4); color:#fff; text-decoration:none;}

/* delete modal center content */
.del-center{text-align:center;padding:.5rem 0 1.25rem;}
.del-icon-wrap{width:72px;height:72px;border-radius:50%;background:rgba(239,68,68,.1);border:1.5px solid rgba(239,68,68,.15);display:inline-flex;align-items:center;justify-content:center;font-size:2rem;margin-bottom:1rem; color:#ef4444;}
.del-name{font-family:'Sora',sans-serif;font-size:1.15rem;font-weight:800;color:#1e293b;margin-bottom:.3rem;}
.del-meta{font-size:.85rem;color:#94a3b8; font-weight: 500;}

.warn-inline{display:flex;align-items:flex-start;gap:.8rem;padding:.9rem 1.1rem;border-radius:14px;font-size:.85rem;margin-top:1rem; font-weight: 500;}
.warn-inline.orange{background:rgba(251,191,36,.1);border:1px solid rgba(251,191,36,.25);color:#92400e;}
.warn-inline.red   {background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.15);color:#991b1b;}
.warn-inline i{flex-shrink:0;margin-top:2px; font-size: 1.1rem;}

@keyframes popIn{from{opacity:0;transform:translateY(18px) scale(.96)}to{opacity:1;transform:translateY(0) scale(1)}}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}

/* =========================================
   DARK MODE OVERRIDES
   ========================================= */
[data-theme="dark"] body { background: radial-gradient(at 0% 0%, rgba(99,102,241,0.08) 0px, transparent 50%), radial-gradient(at 100% 100%, rgba(139,92,246,0.06) 0px, transparent 50%), #0d0f1a !important; color: #e2e8f0 !important; }
[data-theme="dark"] .page-hdr h2 { color: #e2e8f0 !important; }
[data-theme="dark"] .glass { background: rgba(20,22,35,0.85) !important; border-color: rgba(255,255,255,0.08) !important; }
[data-theme="dark"] .filter-bar { background: rgba(20,22,35,0.85) !important; }
[data-theme="dark"] .filter-label { color: #94a3b8 !important; }
[data-theme="dark"] .select-glass { background: rgba(15,17,27,0.9) !important; border-color: rgba(99,102,241,0.15) !important; color: #e2e8f0 !important; }
[data-theme="dark"] .el-strip { background: rgba(20,22,35,0.7) !important; border-color: rgba(99,102,241,0.15) !important; color: #94a3b8 !important; }
[data-theme="dark"] .el-strip strong { color: #e2e8f0 !important; }
[data-theme="dark"] .cand-card { background: rgba(20,22,35,0.8) !important; border-color: rgba(255,255,255,0.08) !important; }
[data-theme="dark"] .cand-card:hover { background: rgba(30,32,48,0.95) !important; border-color: #6366f1 !important; }
[data-theme="dark"] .cand-name { color: #e2e8f0 !important; }
[data-theme="dark"] .cand-sid { color: #94a3b8 !important; background: rgba(255,255,255,0.04) !important; }
[data-theme="dark"] .cand-platform { background: rgba(15,17,27,0.6) !important; color: #94a3b8 !important; }
[data-theme="dark"] .cand-platform::after { background: linear-gradient(transparent, rgba(15,17,27,0.6)) !important; }
[data-theme="dark"] .btn-edit { background: rgba(20,22,35,0.8) !important; border-color: rgba(99,102,241,0.12) !important; color: #94a3b8 !important; }
[data-theme="dark"] .btn-del-sm { background: rgba(20,22,35,0.8) !important; border-color: rgba(239,68,68,0.12) !important; }
[data-theme="dark"] .modal-content { background: rgba(15,17,23,0.97) !important; border-color: rgba(99,102,241,0.16) !important; color: #e2e8f0 !important; }
[data-theme="dark"] .modal-head { border-bottom-color: rgba(99,102,241,0.12) !important; }
[data-theme="dark"] .modal-head h5 { color: #e2e8f0 !important; }
[data-theme="dark"] .mfoot { background: rgba(15,17,23,0.5) !important; border-top-color: rgba(99,102,241,0.1) !important; }
[data-theme="dark"] .field-label { color: #94a3b8 !important; }
[data-theme="dark"] .input-glass { background: rgba(20,22,35,0.9) !important; border-color: rgba(99,102,241,0.15) !important; color: #e2e8f0 !important; }
[data-theme="dark"] .empty-state { background: rgba(20,22,35,0.7) !important; }
</style>

<div class="page-wrap">

    <!-- Page Header -->
    <div class="page-hdr">
        <div>
            <h2>🧑‍💼 Manage Candidates</h2>
            <p>Edit, update, or delete candidates from any election.</p>
        </div>
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

    <!-- Filter Bar -->
    <div class="filter-bar glass">
        <div class="filter-left">
            <span class="filter-label">Election:</span>
            <form method="GET" style="display:contents;">
                <select name="election_id" class="select-glass" onchange="this.form.submit()">
                    <option value="">— Select Election —</option>
                    <?php foreach($all_elections as $e): ?>
                    <option value="<?php echo $e['id']; ?>" <?php echo $election_id==$e['id']?'selected':''; ?>>
                        <?php echo htmlspecialchars($e['title']); ?>
                        <?php echo $e['is_active']?' ● Active':''; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php if(count($candidates)>0): ?>
            <span class="count-pill">
                <i class="bi bi-people-fill"></i> <?php echo count($candidates); ?> candidates
            </span>
            <?php endif; ?>
        </div>
        <?php if($election_id): ?>
        <a href="add_candidates.php?election_id=<?php echo $election_id; ?>" class="btn-primary-glass">
            <i class="bi bi-person-plus-fill"></i> Add Candidate
        </a>
        <?php endif; ?>
    </div>

    <!-- Election Info Strip -->
    <?php if($election): ?>
    <div class="el-strip">
        <i class="bi bi-calendar3"></i>
        <span>Managing: <strong><?php echo htmlspecialchars($election['title']); ?></strong></span>
        <?php if($election['is_active']): ?>
        <span class="live-tag"><span class="live-dot"></span>Active</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Candidates Grid -->
    <?php if(count($candidates) > 0): ?>
    <div class="cand-grid">
        <?php foreach($candidates as $i => $c): ?>
        <div class="cand-card glass <?php echo $c['is_active']?'live-border':''; ?>"
             style="animation-delay:<?php echo $i*0.05; ?>s">

            <!-- Avatar -->
            <div class="cand-avatar-wrap">
                <?php 
                $avatar_src = '';
                if(!empty($c['photo']) && file_exists('../'.$c['photo'])) {
                    $avatar_src = '../'.$c['photo'];
                } elseif(!empty($c['student_photo']) && file_exists('../'.$c['student_photo'])) {
                    $avatar_src = '../'.$c['student_photo'];
                }
                ?>
                <?php if($avatar_src): ?>
                <img src="<?php echo $avatar_src; ?>" class="cand-avatar"
                     alt="<?php echo htmlspecialchars($c['full_name']); ?>">
                <?php else: ?>
                <div class="cand-avatar-placeholder">
                    <i class="bi bi-person-fill"></i>
                </div>
                <?php endif; ?>
            </div>

            <!-- Body -->
            <div class="cand-body">
                <div class="cand-name"><?php echo htmlspecialchars($c['full_name']); ?></div>
                <div class="cand-pos">
                    <i class="bi bi-tag-fill"></i>
                    <?php echo htmlspecialchars($c['position_name']); ?>
                </div>
                <div class="cand-sid">
                    <i class="bi bi-person-badge"></i>
                    <?php echo htmlspecialchars($c['student_id']); ?>
                </div>

                <?php if(!empty($c['platform'])): ?>
                <div class="cand-platform">
                    <?php echo nl2br(htmlspecialchars(substr($c['platform'],0,120))); ?>
                    <?php if(strlen($c['platform'])>120): ?>…<?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="vote-badge">
                    <i class="bi bi-check2-circle"></i>
                    <?php echo $c['vote_count']; ?> Vote<?php echo $c['vote_count']!=1?'s':''; ?>
                </div>

                <div class="cand-actions">
                    <button class="btn-edit" data-bs-toggle="modal"
                            data-bs-target="#editModal<?php echo $c['id']; ?>">
                        <i class="bi bi-pencil"></i> Edit
                    </button>
                    <button class="btn-del-sm" data-bs-toggle="modal"
                            data-bs-target="#deleteModal<?php echo $c['id']; ?>">
                        <i class="bi bi-trash3"></i> Delete
                    </button>
                </div>
            </div>
        </div>

        <!-- ── EDIT MODAL ── -->
        <div class="modal fade modal-glass" id="editModal<?php echo $c['id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-head">
                        <span style="font-size:1.15rem;">✏️</span>
                        <h5>Edit Candidate</h5>
                        <button class="modal-close" data-bs-dismiss="modal">✕</button>
                    </div>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mbody">
                            <input type="hidden" name="candidate_id" value="<?php echo $c['id']; ?>">

                            <!-- Current photo -->
                            <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem;">
                                <?php 
                                $modal_avatar = '';
                                if(!empty($c['photo']) && file_exists('../'.$c['photo'])) {
                                    $modal_avatar = '../'.$c['photo'];
                                } elseif(!empty($c['student_photo']) && file_exists('../'.$c['student_photo'])) {
                                    $modal_avatar = '../'.$c['student_photo'];
                                }
                                ?>
                                <?php if($modal_avatar): ?>
                                <img src="<?php echo $modal_avatar; ?>" class="modal-avatar">
                                <?php else: ?>
                                <div class="modal-avatar-placeholder"><i class="bi bi-person-fill"></i></div>
                                <?php endif; ?>
                                <div>
                                    <div style="font-size:.9rem;font-weight:700;color:#1e293b;"><?php echo htmlspecialchars($c['full_name']); ?></div>
                                    <div style="font-size:.76rem;color:#94a3b8;"><?php echo htmlspecialchars($c['student_id']); ?></div>
                                </div>
                            </div>

                            <!-- Position -->
                            <div class="mb-3">
                                <label class="field-label">Position</label>
                                <select name="position_id" class="input-glass" required>
                                    <?php foreach($positions as $pos): 
                                        $is_full = ($pos['current_candidates'] >= $pos['max_candidates']);
                                        $is_current = ($pos['id'] == $c['pos_id']);
                                        $cap_text = "(" . $pos['current_candidates'] . "/" . $pos['max_candidates'] . ")";
                                    ?>
                                    <option value="<?php echo $pos['id']; ?>"
                                        <?php echo ($is_full && !$is_current) ? 'disabled style="color:#94a3b8;"' : ''; ?>
                                        <?php echo $is_current ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($pos['position_name']); ?>
                                        <?php echo $cap_text; ?>
                                        <?php echo ($is_full && !$is_current) ? ' — FULL' : ''; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Platform -->
                            <div class="mb-3">
                                <label class="field-label">Platform / Manifesto</label>
                                <textarea name="platform" class="input-glass" rows="4"
                                          style="resize:vertical;"><?php echo htmlspecialchars($c['platform']); ?></textarea>
                            </div>

                            <!-- New photo -->
                            <div class="mb-3">
                                <label class="field-label">Update Photo <span style="font-weight:400;color:#94a3b8;">(optional)</span></label>
                                <input type="file" name="candidate_image" class="input-glass" accept="image/*">
                                <div class="upload-hint">Max 5MB · JPG, PNG, GIF · Leave empty to keep current</div>
                            </div>

                            <?php if($c['is_active']): ?>
                            <div class="warn-inline orange" style="margin-top:0;">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <span>This election is <strong>Active</strong>. Changes apply immediately.</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="mfoot">
                            <button type="button" class="btn-cancel" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle"></i> Cancel
                            </button>
                            <button type="submit" name="edit_candidate" class="btn-save">
                                <i class="bi bi-save2"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ── DELETE MODAL ── -->
        <div class="modal fade modal-glass" id="deleteModal<?php echo $c['id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-head danger-head">
                        <span style="font-size:1.15rem;">🗑️</span>
                        <h5 style="color:#991b1b;">Delete Candidate</h5>
                        <button class="modal-close" data-bs-dismiss="modal">✕</button>
                    </div>
                    <div class="mbody">
                        <div class="del-center">
                            <div class="del-icon-wrap">
                                <?php 
                                $del_avatar = '';
                                if(!empty($c['photo']) && file_exists('../'.$c['photo'])) {
                                    $del_avatar = '../'.$c['photo'];
                                } elseif(!empty($c['student_photo']) && file_exists('../'.$c['student_photo'])) {
                                    $del_avatar = '../'.$c['student_photo'];
                                }
                                ?>
                                <?php if($del_avatar): ?>
                                <img src="<?php echo $del_avatar; ?>"
                                     style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
                                <?php else: ?>
                                🧑
                                <?php endif; ?>
                            </div>
                            <div class="del-name"><?php echo htmlspecialchars($c['full_name']); ?></div>
                            <div class="del-meta">
                                <?php echo htmlspecialchars($c['position_name']); ?>
                                &nbsp;·&nbsp;
                                <?php echo htmlspecialchars($c['student_id']); ?>
                            </div>
                        </div>

                        <div class="alert-glass danger" style="margin-bottom:.75rem;">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <span>This action <strong>cannot be undone</strong>.</span>
                        </div>

                        <?php if($c['vote_count']>0): ?>
                        <div class="warn-inline orange">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <span>This candidate has <strong><?php echo $c['vote_count']; ?> vote<?php echo $c['vote_count']!=1?'s':''; ?></strong>. All votes will be permanently removed.</span>
                        </div>
                        <?php endif; ?>

                        <?php if($c['is_active']): ?>
                        <div class="warn-inline red">
                            <i class="bi bi-lightning-charge-fill"></i>
                            <span>This election is currently <strong>ACTIVE</strong>!</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="mfoot">
                        <button type="button" class="btn-cancel" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Cancel
                        </button>
                        <a href="?delete_candidate=<?php echo $c['id']; ?>&election_id=<?php echo $election_id; ?>"
                           class="btn-del-confirm">
                            <i class="bi bi-trash3-fill"></i> Yes, Delete
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php endforeach; ?>
    </div>

    <?php else: ?>
    <div class="glass empty-state">
        <div class="ei">🧑‍💼</div>
        <p style="font-weight:700;color:#475569;font-size:1rem;">No candidates found</p>
        <p>
            <?php if($election_id): ?>
                This election has no candidates yet.
            <?php else: ?>
                Select an election above to view its candidates.
            <?php endif; ?>
        </p>
        <?php if($election_id): ?>
        <a href="add_candidates.php?election_id=<?php echo $election_id; ?>" class="btn-primary-glass"
           style="margin-top:.75rem;display:inline-flex;">
            <i class="bi bi-person-plus-fill"></i> Add First Candidate
        </a>
        <?php else: ?>
        <a href="create_election.php" class="btn-primary-glass"
           style="margin-top:.75rem;display:inline-flex;">
            <i class="bi bi-plus-circle"></i> Create Election
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div><!-- /page-wrap -->

<?php include '../includes/footer.php'; ?>