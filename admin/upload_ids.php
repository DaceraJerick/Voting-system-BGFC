<?php
// filename: admin/upload_ids.php
// location: admin/upload_ids.php

require_once '../config/database.php';
require_once '../includes/auth_check.php';

if($_SESSION['user_type'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

$message = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {

    if(isset($_POST['add_single_id']) && !empty($_POST['single_student_id'])) {
        $student_id = trim($_POST['single_student_id']);
        try {
            $stmt = $pdo->prepare("INSERT INTO eligible_ids (student_id) VALUES (?)");
            $stmt->execute([$student_id]);
            $message = "ID <strong>$student_id</strong> added successfully!";
        } catch(PDOException $e) {
            $error = $e->errorInfo[1] == 1062 ? "ID <strong>$student_id</strong> already exists!" : "Error: " . $e->getMessage();
        }
    }

    elseif(isset($_POST['add_bulk_text']) && !empty($_POST['bulk_ids'])) {
        $lines = explode("\n", trim($_POST['bulk_ids']));
        $added = $duplicates = $invalid = 0;
        foreach($lines as $line) {
            $student_id = trim($line);
            if(!empty($student_id)) {
                try {
                    $pdo->prepare("INSERT INTO eligible_ids (student_id) VALUES (?)")->execute([$student_id]);
                    $added++;
                } catch(PDOException $e) {
                    $e->errorInfo[1] == 1062 ? $duplicates++ : $invalid++;
                }
            }
        }
        $message = "Imported: <strong>$added</strong> added &nbsp;·&nbsp; <strong>$duplicates</strong> duplicates &nbsp;·&nbsp; <strong>$invalid</strong> invalid";
    }

    elseif(isset($_FILES['id_file']) && $_FILES['id_file']['error'] == 0) {
        $file = $_FILES['id_file'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $added = $duplicates = $invalid = 0;

        if(in_array($file_ext, ['csv','txt','xls','xlsx'])) {
            if(($handle = fopen($file['tmp_name'], "r")) !== FALSE) {
                while(($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    foreach($data as $field) {
                        $student_id = trim($field);
                        if(!empty($student_id)) {
                            try {
                                $pdo->prepare("INSERT INTO eligible_ids (student_id) VALUES (?)")->execute([$student_id]);
                                $added++;
                            } catch(PDOException $e) {
                                $e->errorInfo[1] == 1062 ? $duplicates++ : $invalid++;
                            }
                        }
                    }
                }
                fclose($handle);
                $message = "File processed: <strong>$added</strong> added &nbsp;·&nbsp; <strong>$duplicates</strong> duplicates &nbsp;·&nbsp; <strong>$invalid</strong> invalid";
            }
        } else {
            $error = "Unsupported file format. Please upload CSV, TXT, XLS, or XLSX.";
        }
    }
}

if(isset($_GET['delete'])) {
    try {
        $pdo->prepare("DELETE FROM eligible_ids WHERE id = ? AND is_registered = 0")->execute([$_GET['delete']]);
        $message = "ID deleted successfully!";
    } catch(PDOException $e) { $error = "Error: " . $e->getMessage(); }
}

if(isset($_POST['bulk_delete']) && isset($_POST['selected_ids'])) {
    $ids = $_POST['selected_ids'];
    if(!empty($ids)) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("DELETE FROM eligible_ids WHERE id IN ($ph) AND is_registered = 0");
        $stmt->execute($ids);
        $message = $stmt->rowCount() . " IDs deleted successfully!";
    }
}

if(isset($_POST['delete_all_unregistered'])) {
    $stmt = $pdo->query("DELETE FROM eligible_ids WHERE is_registered = 0");
    $message = $stmt->rowCount() . " unregistered IDs deleted.";
}

$total_eligible   = $pdo->query("SELECT COUNT(*) FROM eligible_ids")->fetchColumn();
$registered_count = $pdo->query("SELECT COUNT(*) FROM eligible_ids WHERE is_registered = 1")->fetchColumn();
$unregistered_count = $pdo->query("SELECT COUNT(*) FROM eligible_ids WHERE is_registered = 0")->fetchColumn();

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;
$view   = $_GET['view'] ?? 'all';

$where_clause = "";
if ($view === 'registered') {
    $where_clause = "WHERE e.is_registered = 1";
} elseif ($view === 'unregistered') {
    $where_clause = "WHERE e.is_registered = 0";
}

$stmt = $pdo->prepare("
    SELECT e.*, s.full_name 
    FROM eligible_ids e 
    LEFT JOIN students s ON e.student_id = s.student_id 
    $where_clause 
    ORDER BY e.uploaded_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->bindParam(1, $limit,  PDO::PARAM_INT);
$stmt->bindParam(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$eligible_ids = $stmt->fetchAll();

$total_ids_view = $pdo->query("SELECT COUNT(*) FROM eligible_ids e $where_clause")->fetchColumn();
$total_pages = ceil($total_ids_view / $limit);

include '../includes/header.php';
?>

<style>
/* ── Upload IDs Page (Dark Mode Friendly) ── */
.page-wrap { position: relative; z-index: 1; padding-bottom: 4rem; }

.glass {
    background: rgba(15,17,23,0.78);
    backdrop-filter: blur(18px) saturate(150%);
    -webkit-backdrop-filter: blur(18px) saturate(150%);
    border: 1px solid rgba(99,102,241,0.16);
    border-radius: 20px;
    box-shadow: 0 18px 50px rgba(0,0,0,0.5);
}

/* page header */
.page-header { margin-bottom: 1.75rem; }
.page-header h2 {
    font-size: clamp(1.4rem,2.5vw,1.9rem); font-weight: 800;
    color: #e2e8f0; letter-spacing: -0.025em; margin: 0;
}
.page-header p { color: #94a3b8; font-size: 0.9rem; margin: 0.3rem 0 0; }

/* stat cards */
.stats-row { display: grid; grid-template-columns: repeat(3,1fr); gap: 1rem; margin-bottom: 1.75rem; }
@media(max-width:700px){ .stats-row{ grid-template-columns:1fr; } }

.sc {
    padding: 1.3rem 1.4rem; position: relative; overflow: hidden;
    animation: popIn .5s cubic-bezier(.34,1.56,.64,1) both;
    transition: transform .25s ease, box-shadow .25s ease;
}
.sc:hover { transform: translateY(-3px); box-shadow: 0 18px 44px rgba(0,0,0,0.45); }
.sc:nth-child(1){animation-delay:.04s} .sc:nth-child(2){animation-delay:.1s} .sc:nth-child(3){animation-delay:.16s}

.sc-blob {
    position:absolute;width:80px;height:80px;border-radius:50%;
    right:-16px;top:-16px;filter:blur(28px);opacity:.18;pointer-events:none;
}
.sc.c1 .sc-blob{background:#5b67f0} .sc.c2 .sc-blob{background:#34d399} .sc.c3 .sc-blob{background:#fbbf24}

.sc-icon {
    width:40px;height:40px;border-radius:11px;
    display:flex;align-items:center;justify-content:center;
    font-size:1.1rem;margin-bottom:.85rem;color:#e2e8f0;
}
.sc.c1 .sc-icon{background:rgba(99,102,241,0.12)} .sc.c2 .sc-icon{background:rgba(52,211,153,0.12)}
.sc.c3 .sc-icon{background:rgba(251,191,36,0.12)}
.sc-num  { font-size:1.9rem;font-weight:800;color:#f8fafc;letter-spacing:-.03em;line-height:1; }
.sc-lbl  { font-size:0.75rem;color:#94a3b8;font-weight:500;margin-top:.25rem; }

/* main grid */
.main-grid { display:grid;grid-template-columns:1fr 1fr;gap:1.25rem; }
@media(max-width:1000px){ .main-grid{grid-template-columns:1fr;} }

/* panel */
.panel { overflow:hidden; animation: popIn .5s .2s both; }
.panel-head {
    padding:1.1rem 1.4rem;
    border-bottom:1px solid rgba(99,102,241,0.08);
    display:flex;align-items:center;gap:.65rem;
}
.panel-head h5 { font-size:.92rem;font-weight:700;color:#e2e8f0;flex:1;margin:0; }
.panel-body { padding:1.4rem; }

/* tabs */
.import-tabs {
    display:flex;gap:.4rem;margin-bottom:1.25rem;
    background:rgba(20,22,35,0.6);
    border:1px solid rgba(99,102,241,0.12);
    border-radius:12px;padding:.35rem;
}
.tab-btn {
    flex:1;display:flex;align-items:center;justify-content:center;gap:.45rem;
    padding:.55rem .5rem;border-radius:9px;border:none;background:transparent;
    font-size:.8rem;font-weight:600;color:#94a3b8;cursor:pointer;
    transition:all .2s ease;
    text-decoration:none;
}
.tab-btn.active { background:rgba(99,102,241,0.08);color:#e2e8f0;box-shadow:0 6px 18px rgba(0,0,0,0.45); text-decoration:none; }
.tab-btn:hover:not(.active) { background:rgba(99,102,241,0.06);color:#c7d2fe; text-decoration:none; }
.tab-btn i { font-size:.9rem; }
.tab-pane-custom { display:none; } .tab-pane-custom.active { display:block; }

/* upload drop zone */
.drop-zone {
    border:2px dashed rgba(99,102,241,0.18);
    border-radius:16px;padding:2rem 1.5rem;
    text-align:center;cursor:pointer;
    background:rgba(15,17,23,0.6);
    transition:all .25s ease;
}
.drop-zone:hover, .drop-zone.drag-over {
    border-color:rgba(99,102,241,0.36);
    background:rgba(99,102,241,0.04);
}
.drop-icon { font-size:2.5rem;color:#60a5fa;margin-bottom:.75rem; }
.drop-title { font-size:.9rem;font-weight:700;color:#e2e8f0;margin-bottom:.3rem; }
.drop-sub   { font-size:.75rem;color:#94a3b8; }
.file-chip {
    display:inline-flex;align-items:center;gap:.5rem;
    background:rgba(99,102,241,0.06);border:1px solid rgba(99,102,241,.12);
    border-radius:99px;padding:.25rem .85rem;
    font-size:.78rem;font-weight:600;color:#c7d2fe;margin-top:.75rem;
}

/* inputs */
.form-control-glass {
    background:rgba(20,22,35,0.75)!important;
    border:1px solid rgba(99,102,241,0.12)!important;
    border-radius:12px!important;
    padding:.65rem 1rem!important;
    font-size:.875rem!important;color:#e2e8f0!important;
    transition:border-color .2s,box-shadow .2s;
}
.form-control-glass:focus {
    border-color:rgba(99,102,241,.36)!important;
    box-shadow:0 0 0 4px rgba(99,102,241,.08)!important;
    outline:none!important;
}
.form-label-sm { font-size:.8rem;font-weight:600;color:#94a3b8;margin-bottom:.4rem; }

/* buttons */
.btn-primary-glass {
    display:inline-flex;align-items:center;gap:.5rem;
    background:linear-gradient(135deg,#22c55e,#16a34a);
    color:#fff;border:none;border-radius:99px;
    padding:.6rem 1.4rem;font-size:.85rem;font-weight:700;
    cursor:pointer;text-decoration:none;
    box-shadow:0 8px 26px rgba(16,185,129,.18);
    transition:all .2s ease;
}
.btn-primary-glass:hover { transform:scale(1.03);opacity:.95;color:#fff;text-decoration:none; }

.btn-outline-glass {
    display:inline-flex;align-items:center;gap:.5rem;
    background:rgba(255,255,255,0.02);border:1px solid rgba(99,102,241,.08);
    color:#cbd5e1;border-radius:99px;
    padding:.6rem 1.4rem;font-size:.85rem;font-weight:600;
    cursor:pointer;text-decoration:none;
    transition:all .2s ease;
}
.btn-outline-glass:hover { background:rgba(99,102,241,.08);border-color:rgba(99,102,241,.18);color:#fff;text-decoration:none; }

.btn-danger-glass {
    display:inline-flex;align-items:center;gap:.5rem;
    background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.12);
    color:#fca5a5;border-radius:99px;
    padding:.5rem 1.1rem;font-size:.82rem;font-weight:700;
    cursor:pointer;text-decoration:none;
    transition:all .2s ease;
}
.btn-danger-glass:hover { background:rgba(239,68,68,.14);border-color:rgba(239,68,68,.2); }

.btn-warning-glass {
    display:inline-flex;align-items:center;gap:.5rem;
    background:rgba(251,191,36,.06);border:1px solid rgba(251,191,36,.12);
    color:#fbbf24;border-radius:99px;
    padding:.6rem 1.4rem;font-size:.85rem;font-weight:600;
    cursor:pointer;text-decoration:none;width:100%;justify-content:center;
    transition:all .2s ease;
}
.btn-warning-glass:hover { background:rgba(251,191,36,.12);border-color:rgba(251,191,36,.2); }

.btn-success-glass {
    display:inline-flex;align-items:center;gap:.5rem;
    background:rgba(34,197,94,.06);border:1px solid rgba(34,197,94,.12);
    color:#86efac;border-radius:99px;
    padding:.6rem 1.4rem;font-size:.85rem;font-weight:600;
    cursor:pointer;text-decoration:none;width:100%;justify-content:center;
    transition:all .2s ease;
}
.btn-success-glass:hover { background:rgba(34,197,94,.12);border-color:rgba(34,197,94,.18); }

/* alerts */
.alert-glass {
    display:flex;align-items:flex-start;gap:.75rem;
    padding:1rem 1.1rem;border-radius:14px;margin-bottom:1.25rem;
    font-size:.875rem;
    animation: popIn .4s ease both;
}
.alert-glass.success { background:rgba(20,83,45,0.14);border:1px solid rgba(34,197,94,0.12);color:#86efac; }
.alert-glass.danger  { background:rgba(127,29,29,0.12);border:1px solid rgba(239,68,68,0.12);color:#fca5a5; }
.alert-glass i { font-size:1rem;flex-shrink:0;margin-top:2px; }
.alert-glass .close-btn { margin-left:auto;background:none;border:none;cursor:pointer;color:inherit;opacity:.6;padding:0;font-size:1rem;line-height:1; }
.alert-glass .close-btn:hover { opacity:1; }

/* ID list */
.ids-list { max-height:480px;overflow-y:auto; }
.id-row {
    display:flex;align-items:center;gap:.75rem;
    padding:.7rem 1.25rem;
    border-bottom:1px solid rgba(99,102,241,0.06);
    transition:background .15s ease;
}
.id-row:last-child { border-bottom:none; }
.id-row:hover { background:rgba(99,102,241,0.02); }
.id-row.is-registered { background:rgba(34,197,94,0.06); }
.id-row.is-registered:hover { background:rgba(34,197,94,0.08); }

.id-cb input { accent-color:#6366f1; width:16px;height:16px;cursor:pointer; }

.id-name { font-size:.875rem;font-weight:700;color:#e2e8f0; }
.id-date { font-size:.72rem;color:#94a3b8; }

.badge-reg  { display:inline-flex;align-items:center;gap:.25rem;font-size:.68rem;font-weight:700;padding:.18rem .6rem;border-radius:99px;background:rgba(34,197,94,.08);color:#86efac;border:1px solid rgba(34,197,94,.12); }
.badge-unreg{ display:inline-flex;align-items:center;gap:.25rem;font-size:.68rem;font-weight:700;padding:.18rem .6rem;border-radius:99px;background:rgba(251,191,36,.08);color:#fbbf24;border:1px solid rgba(251,191,36,.12); }

.id-fullname { color:#60a5fa; font-weight:700; margin-left: .25rem; }

.del-btn {
    width:28px;height:28px;border-radius:8px;
    background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.08);
    display:flex;align-items:center;justify-content:center;
    color:#fca5a5;font-size:.85rem;
    text-decoration:none;transition:all .2s ease;flex-shrink:0;
}
.del-btn:hover { background:rgba(239,68,68,.14);border-color:rgba(239,68,68,.18);color:#fff; }

/* bulk bar */
.bulk-bar {
    display:flex;align-items:center;justify-content:space-between;
    padding:.75rem 1.25rem;
    background:rgba(15,17,23,0.82);
    border-bottom:1px solid rgba(99,102,241,.06);
    gap:.75rem;flex-wrap:wrap;
}
.bulk-bar label { font-size:.8rem;font-weight:600;color:#cbd5e1;cursor:pointer; }
.bulk-bar input[type=checkbox] { accent-color:#6366f1;width:15px;height:15px; }

/* head badges */
.head-badges { display:flex;gap:.4rem;flex-wrap:wrap; }
.hb {
    font-size:.68rem;font-weight:700;padding:.2rem .65rem;border-radius:99px;
}
.hb.blue   { background:rgba(99,102,241,.08);color:#c7d2fe; }
.hb.green  { background:rgba(34,197,94,.06);color:#86efac; }
.hb.amber  { background:rgba(251,191,36,.06);color:#fbbf24; }

/* empty state */
.empty-state { text-align:center;padding:3rem 1rem; }
.empty-state .empty-icon { font-size:3rem;opacity:.18;margin-bottom:.75rem;color:#94a3b8 }
.empty-state p { font-size:0.875rem;color:#94a3b8; }

/* notes list */
.notes-list { list-style:none;padding:0;margin:0; }
.notes-list li { display:flex;align-items:flex-start;gap:.6rem;padding:.55rem 0;font-size:.83rem;color:#94a3b8;border-bottom:1px solid rgba(99,102,241,.06); }
.notes-list li:last-child { border-bottom:none; }
.notes-list li i { color:#86efac;font-size:.9rem;flex-shrink:0;margin-top:2px; }

/* pagination */
.pager { display:flex;gap:.35rem;padding:1rem 1.25rem;justify-content:center; }
.pg-btn {
    min-width:34px;height:34px;border-radius:9px;
    display:flex;align-items:center;justify-content:center;
    font-size:.8rem;font-weight:600;cursor:pointer;
    text-decoration:none;border:1px solid rgba(99,102,241,.06);
    background:rgba(15,17,23,0.82);color:#cbd5e1;
    transition:all .2s ease;
}
.pg-btn:hover { background:rgba(99,102,241,.06);border-color:rgba(99,102,241,.12);color:#fff;text-decoration:none; }
.pg-btn.active { background:linear-gradient(135deg,#6366f1,#818cf8);color:#fff;border-color:transparent;box-shadow:0 2px 8px rgba(99,102,241,.2); }
.pg-btn.disabled { opacity:.4;pointer-events:none; }

@keyframes popIn {
    from{opacity:0;transform:translateY(14px) scale(.97)}
    to{opacity:1;transform:translateY(0) scale(1)}
}

/* =========================================
   LIGHT MODE OVERRIDES
   ========================================= */
[data-theme="light"] .glass { background: rgba(255,255,255,0.95) !important; border-color: rgba(0,0,0,0.08) !important; box-shadow: 0 8px 30px rgba(0,0,0,0.08) !important; }
[data-theme="light"] .page-header h2 { color: #1e293b !important; }
[data-theme="light"] .page-header p { color: #64748b !important; }
[data-theme="light"] .sc.glass { background: rgba(255,255,255,0.98) !important; }
[data-theme="light"] .sc-icon { color: #1e293b !important; }
[data-theme="light"] .sc-num { color: #1e293b !important; }
[data-theme="light"] .sc-lbl { color: #64748b !important; }
[data-theme="light"] .panel-head { background: #f8fafc !important; border-bottom-color: rgba(0,0,0,0.06) !important; }
[data-theme="light"] .panel-head h5 { color: #1e293b !important; }
[data-theme="light"] .panel-body { background: #fff !important; }
[data-theme="light"] .import-tabs { background: #f1f5f9 !important; border-color: rgba(0,0,0,0.06) !important; }
[data-theme="light"] .tab-btn { color: #64748b !important; background: transparent !important; }
[data-theme="light"] .tab-btn.active { background: #fff !important; color: #1e293b !important; box-shadow: 0 2px 8px rgba(0,0,0,0.08) !important; }
[data-theme="light"] .tab-btn:hover:not(.active) { background: rgba(255,255,255,0.6) !important; color: #1e293b !important; }
[data-theme="light"] .drop-zone { background: #f8fafc !important; border-color: rgba(99,102,241,0.25) !important; }
[data-theme="light"] .drop-zone:hover, [data-theme="light"] .drop-zone.drag-over { background: rgba(99,102,241,0.04) !important; }
[data-theme="light"] .drop-title { color: #1e293b !important; }
[data-theme="light"] .drop-sub { color: #64748b !important; }
[data-theme="light"] .form-control-glass { background: #fff !important; border-color: rgba(0,0,0,0.12) !important; color: #1e293b !important; }
[data-theme="light"] .form-label-sm { color: #475569 !important; }
[data-theme="light"] .bulk-bar { background: #f8fafc !important; border-bottom-color: rgba(0,0,0,0.05) !important; }
[data-theme="light"] .bulk-bar label { color: #1e293b !important; }
[data-theme="light"] .btn-warning-glass { background: rgba(251,191,36,0.1) !important; border-color: rgba(251,191,36,0.25) !important; color: #b45309 !important; }
[data-theme="light"] .btn-success-glass { background: rgba(34,197,94,0.1) !important; border-color: rgba(34,197,94,0.25) !important; color: #15803d !important; }
[data-theme="light"] .btn-outline-glass { background: #f1f5f9 !important; border-color: rgba(0,0,0,0.1) !important; color: #475569 !important; }
[data-theme="light"] .btn-danger-glass { background: rgba(239,68,68,0.08) !important; border-color: rgba(239,68,68,0.2) !important; color: #dc2626 !important; }
[data-theme="light"] .id-row { border-bottom-color: rgba(0,0,0,0.05) !important; background: #fff !important; }
[data-theme="light"] .id-row:hover { background: #f8fafc !important; }
[data-theme="light"] .id-row.is-registered { background: rgba(34,197,94,0.04) !important; }
[data-theme="light"] .id-name { color: #1e293b !important; }
[data-theme="light"] .id-fullname { color: #3b82f6 !important; }
[data-theme="light"] .id-date { color: #64748b !important; }
[data-theme="light"] .notes-list li { color: #475569 !important; border-bottom-color: rgba(0,0,0,0.05) !important; background: transparent !important; }
[data-theme="light"] .notes-list li strong { color: #1e293b !important; }
[data-theme="light"] .pg-btn { background: #fff !important; color: #475569 !important; border-color: rgba(0,0,0,0.1) !important; }
[data-theme="light"] .pg-btn:hover { background: #f1f5f9 !important; color: #1e293b !important; }
[data-theme="light"] .empty-state p { color: #475569 !important; }
[data-theme="light"] .ids-list { background: #fff !important; }
</style>

<div class="page-wrap">

    <!-- Page Header -->
    <div class="page-header">
        <h2>📤 Upload Eligible IDs</h2>
        <p>Import student IDs via file upload, bulk text, or manual entry.</p>
    </div>

    <!-- Stat Cards -->
    <div class="stats-row">
        <div class="sc glass c1">
            <div class="sc-blob"></div>
            <div class="sc-icon">🪪</div>
            <div class="sc-num"><?php echo number_format($total_eligible); ?></div>
            <div class="sc-lbl">Total Eligible IDs</div>
        </div>
        <div class="sc glass c2">
            <div class="sc-blob"></div>
            <div class="sc-icon">✅</div>
            <div class="sc-num"><?php echo number_format($registered_count); ?></div>
            <div class="sc-lbl">Registered</div>
        </div>
        <div class="sc glass c3">
            <div class="sc-blob"></div>
            <div class="sc-icon">⏳</div>
            <div class="sc-num"><?php echo number_format($unregistered_count); ?></div>
            <div class="sc-lbl">Unregistered</div>
        </div>
    </div>

    <!-- Main Grid -->
    <div class="main-grid">

        <!-- LEFT: Upload Forms -->
        <div style="display:flex;flex-direction:column;gap:1.25rem;">

            <!-- Import Panel -->
            <div class="panel glass" style="animation-delay:.1s">
                <div class="panel-head">
                    <h5>⬆️ Import Methods</h5>
                </div>
                <div class="panel-body">

                    <!-- Alerts -->
                    <?php if($message): ?>
                    <div class="alert-glass success" id="alertMsg">
                        <i class="bi bi-check-circle-fill"></i>
                        <span><?php echo $message; ?></span>
                        <button class="close-btn" onclick="this.closest('.alert-glass').remove()">✕</button>
                    </div>
                    <?php endif; ?>
                    <?php if($error): ?>
                    <div class="alert-glass danger" id="alertErr">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span><?php echo $error; ?></span>
                        <button class="close-btn" onclick="this.closest('.alert-glass').remove()">✕</button>
                    </div>
                    <?php endif; ?>

                    <!-- Tab Buttons -->
                    <div class="import-tabs">
                        <button class="tab-btn active" onclick="switchTab('file',this)">
                            <i class="bi bi-file-earmark-arrow-up"></i> File Upload
                        </button>
                        <button class="tab-btn" onclick="switchTab('bulk',this)">
                            <i class="bi bi-textarea-t"></i> Bulk Text
                        </button>
                        <button class="tab-btn" onclick="switchTab('single',this)">
                            <i class="bi bi-person-plus"></i> Single ID
                        </button>
                    </div>

                    <!-- Tab: File Upload -->
                    <div class="tab-pane-custom active" id="tab-file">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="drop-zone" id="dropZone" onclick="document.getElementById('id_file').click()">
                                <div class="drop-icon">☁️</div>
                                <div class="drop-title">Click to upload or drag & drop</div>
                                <div class="drop-sub">Excel (.xls, .xlsx), CSV, or TXT &nbsp;·&nbsp; Max 5MB</div>
                                <div id="fileChip" style="display:none;" class="file-chip">
                                    <i class="bi bi-file-earmark-check"></i><span id="fileName"></span>
                                </div>
                                <input type="file" name="id_file" id="id_file" accept=".csv,.txt,.xls,.xlsx" style="display:none;">
                            </div>
                            <div class="d-flex gap-2 mt-3 flex-wrap">
                                <button type="submit" class="btn-primary-glass" id="uploadBtn">
                                    <i class="bi bi-cloud-upload"></i> Upload File
                                </button>
                                <a href="download_template.php" class="btn-outline-glass">
                                    <i class="bi bi-download"></i> Template
                                </a>
                            </div>
                        </form>
                    </div>

                    <!-- Tab: Bulk Text -->
                    <div class="tab-pane-custom" id="tab-bulk">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label-sm">Paste IDs — one per line</label>
                                <textarea name="bulk_ids" class="form-control-glass form-control" rows="8"
                                    placeholder="2021-001&#10;2021-002&#10;2021-003" required></textarea>
                                <div style="font-size:.73rem;color:#94a3b8;margin-top:.4rem;">Each line becomes one eligible ID</div>
                            </div>
                            <button type="submit" name="add_bulk_text" class="btn-primary-glass">
                                <i class="bi bi-check-all"></i> Import IDs
                            </button>
                        </form>
                    </div>

                    <!-- Tab: Single ID -->
                    <div class="tab-pane-custom" id="tab-single">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label-sm">Student ID</label>
                                <div class="input-group" style="gap:.5rem;">
                                    <input type="text" name="single_student_id"
                                           class="form-control-glass form-control"
                                           placeholder="e.g., 2021-001" required>
                                    <button type="submit" name="add_single_id" class="btn-primary-glass" style="border-radius:99px!important;white-space:nowrap;">
                                        <i class="bi bi-plus-lg"></i> Add
                                    </button>
                                </div>
                                <div style="font-size:.73rem;color:#94a3b8;margin-top:.4rem;">Add a single student ID manually</div>
                            </div>
                        </form>
                    </div>

                </div>
            </div>

            <!-- Bulk Actions Panel -->
            <div class="panel glass" style="animation-delay:.18s">
                <div class="panel-head">
                    <h5>⚡ Bulk Actions</h5>
                </div>
                <div class="panel-body" style="display:flex;flex-direction:column;gap:.75rem;">
                    <form method="POST" onsubmit="return confirm('Delete all unregistered IDs? This cannot be undone.')">
                        <button type="submit" name="delete_all_unregistered" class="btn-warning-glass">
                            <i class="bi bi-trash3"></i> Delete All Unregistered IDs
                        </button>
                    </form>
                    <a href="export_ids.php" class="btn-success-glass">
                        <i class="bi bi-download"></i> Export Eligible IDs (CSV)
                    </a>
                </div>
            </div>

            <!-- Notes Panel -->
            <div class="panel glass" style="animation-delay:.26s">
                <div class="panel-head">
                    <h5>ℹ️ Important Notes</h5>
                </div>
                <div class="panel-body" style="padding-top:1rem;padding-bottom:1rem;">
                    <ul class="notes-list">
                        <li><i class="bi bi-check-circle-fill"></i><span><strong>Duplicate Prevention:</strong> System automatically blocks duplicate IDs</span></li>
                        <li><i class="bi bi-check-circle-fill"></i><span><strong>Registration Lock:</strong> Once a student registers, their ID is locked</span></li>
                        <li><i class="bi bi-check-circle-fill"></i><span><strong>Delete Safety:</strong> Only unregistered IDs can be deleted</span></li>
                        <li><i class="bi bi-check-circle-fill"></i><span><strong>Excel Format:</strong> Place IDs in column A, no headers needed</span></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- RIGHT: Eligible IDs List -->
        <div class="panel glass" style="animation-delay:.14s;align-self:start;">
            <div class="panel-head" style="flex-direction: column; align-items: stretch; gap: 1rem;">
                <div style="display:flex; align-items:center; justify-content:space-between;">
                    <h5>📋 Eligible IDs List</h5>
                    <div class="head-badges">
                        <span class="hb blue"><?php echo $total_eligible; ?> Total</span>
                        <span class="hb green"><?php echo $registered_count; ?> Reg.</span>
                        <span class="hb amber"><?php echo $unregistered_count; ?> Unreg.</span>
                    </div>
                </div>
                <!-- View Tabs -->
                <div class="import-tabs" style="margin-bottom: 0;">
                    <a href="?view=all" class="tab-btn <?php echo $view === 'all' ? 'active' : ''; ?>">
                        <i class="bi bi-list"></i> All
                    </a>
                    <a href="?view=registered" class="tab-btn <?php echo $view === 'registered' ? 'active' : ''; ?>">
                        <i class="bi bi-check-circle-fill"></i> Registered
                    </a>
                    <a href="?view=unregistered" class="tab-btn <?php echo $view === 'unregistered' ? 'active' : ''; ?>">
                        <i class="bi bi-clock-history"></i> Unregistered
                    </a>
                </div>
            </div>

            <?php if(count($eligible_ids) > 0): ?>
            <form method="POST" id="bulkDeleteForm">

                <!-- Bulk Bar -->
                <div class="bulk-bar">
                    <label style="display:flex;align-items:center;gap:.5rem;">
                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                        Select all unregistered
                    </label>
                    <button type="submit" name="bulk_delete" class="btn-danger-glass"
                            onclick="return confirm('Delete selected IDs?')">
                        <i class="bi bi-trash3"></i> Delete Selected
                    </button>
                </div>

                <!-- List -->
                <div class="ids-list">
                    <?php foreach($eligible_ids as $id): ?>
                    <div class="id-row <?php echo $id['is_registered'] ? 'is-registered' : ''; ?>">
                        <div class="id-cb">
                            <input type="checkbox" name="selected_ids[]"
                                   value="<?php echo $id['id']; ?>"
                                   <?php echo $id['is_registered'] ? 'disabled' : ''; ?>>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
                                <span class="id-name">
                                    <?php echo htmlspecialchars($id['student_id']); ?>
                                    <?php if (!empty($id['full_name'])): ?>
                                        <span style="color: #94a3b8; margin: 0 0.1rem;">&mdash;</span> <span class="id-fullname"><?php echo htmlspecialchars($id['full_name']); ?></span>
                                    <?php endif; ?>
                                </span>
                                <?php if($id['is_registered']): ?>
                                    <span class="badge-reg"><i class="bi bi-check-circle-fill"></i>Registered</span>
                                <?php else: ?>
                                    <span class="badge-unreg"><i class="bi bi-clock"></i>Unregistered</span>
                                <?php endif; ?>
                            </div>
                            <div class="id-date"><?php echo date('M d, Y', strtotime($id['uploaded_at'])); ?></div>
                        </div>
                        <?php if(!$id['is_registered']): ?>
                        <a href="?delete=<?php echo $id['id']; ?>" class="del-btn"
                           onclick="return confirm('Delete this ID?')" title="Delete">
                            <i class="bi bi-x-lg"></i>
                        </a>
                        <?php else: ?>
                        <div style="width:28px;"></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </form>

            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
            <div class="pager">
                <a class="pg-btn <?php echo $page<=1?'disabled':''; ?>" href="?view=<?php echo $view; ?>&page=<?php echo $page-1; ?>">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <?php
                $start = max(1,$page-2); $end = min($total_pages,$page+2);
                for($i=$start;$i<=$end;$i++):
                ?>
                <a class="pg-btn <?php echo $i==$page?'active':''; ?>" href="?view=<?php echo $view; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <a class="pg-btn <?php echo $page>=$total_pages?'disabled':''; ?>" href="?view=<?php echo $view; ?>&page=<?php echo $page+1; ?>">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">📭</div>
                <p style="font-weight:600;color:#475569;">No eligible IDs yet</p>
                <p>Use the import methods on the left to add student IDs.</p>
            </div>
            <?php endif; ?>
        </div>

    </div><!-- /main-grid -->
</div><!-- /page-wrap -->

<script>
// Tab switching
function switchTab(name, btn) {
    document.querySelectorAll('.tab-pane-custom').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
}

// File input display
const fileInput = document.getElementById('id_file');
if(fileInput) {
    fileInput.addEventListener('change', function() {
        const name = this.files[0]?.name;
        if(name) {
            document.getElementById('fileName').textContent = name;
            document.getElementById('fileChip').style.display = 'inline-flex';
        }
    });
}

// Drag and drop
const dz = document.getElementById('dropZone');
if(dz) {
    ['dragenter','dragover'].forEach(e => dz.addEventListener(e, ev => { ev.preventDefault(); dz.classList.add('drag-over'); }));
    ['dragleave','drop'].forEach(e => dz.addEventListener(e, ev => { ev.preventDefault(); dz.classList.remove('drag-over'); }));
    dz.addEventListener('drop', ev => {
        const file = ev.dataTransfer.files[0];
        if(file) {
            const dt = new DataTransfer();
            dt.items.add(file);
            fileInput.files = dt.files;
            document.getElementById('fileName').textContent = file.name;
            document.getElementById('fileChip').style.display = 'inline-flex';
        }
    });
}

// Select all unregistered
function toggleSelectAll(src) {
    document.querySelectorAll('input[name="selected_ids[]"]:not([disabled])').forEach(cb => cb.checked = src.checked);
}

// Prevent empty upload
document.getElementById('uploadBtn')?.addEventListener('click', function(e) {
    if(fileInput && fileInput.files.length === 0) {
        e.preventDefault();
        alert('Please select a file first!');
    }
});
</script>

<?php include '../includes/footer.php'; ?>