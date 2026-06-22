<?php
// filename: admin/activate_election.php
// location: admin/activate_election.php

require_once '../config/database.php';
require_once '../includes/auth_check.php';

if($_SESSION['user_type'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

$message = '';
$error = '';

if(isset($_GET['activate'])) {
    $election_id = $_GET['activate'];
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT id, title FROM elections WHERE id = ?");
        $stmt->execute([$election_id]);
        $el = $stmt->fetch();
        if(!$el) throw new Exception("Election not found!");

        $pos_count = $pdo->prepare("SELECT COUNT(*) FROM positions WHERE election_id = ?");
        $pos_count->execute([$election_id]);
        if($pos_count->fetchColumn() == 0) throw new Exception("Cannot activate: no positions added yet.");

        $can_count = $pdo->prepare("SELECT COUNT(*) FROM candidates c JOIN positions p ON c.position_id=p.id WHERE p.election_id=?");
        $can_count->execute([$election_id]);
        if($can_count->fetchColumn() == 0) throw new Exception("Cannot activate: no candidates added yet.");

        $filled = $pdo->prepare("SELECT COUNT(DISTINCT p.id) FROM positions p LEFT JOIN candidates c ON c.position_id=p.id WHERE p.election_id=? AND c.id IS NOT NULL");
        $filled->execute([$election_id]);
        if($filled->fetchColumn() == 0) throw new Exception("Cannot activate: no candidates assigned to any position.");

        $pdo->query("UPDATE elections SET is_active=0, status='completed' WHERE is_active=1");
        $pdo->prepare("UPDATE elections SET is_active=1, status='active' WHERE id=?")->execute([$election_id]);
        $pdo->commit();
        $message = "Election <strong>{$el['title']}</strong> activated successfully!";
        error_log("Election {$election_id} activated by {$_SESSION['username']} at ".date('Y-m-d H:i:s'));
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

if(isset($_GET['deactivate'])) {
    $election_id = $_GET['deactivate'];
    try {
        $stmt = $pdo->prepare("SELECT title FROM elections WHERE id=?");
        $stmt->execute([$election_id]);
        $el = $stmt->fetch();
        if($el) {
            $pdo->prepare("UPDATE elections SET is_active=0, status='pending' WHERE id=?")->execute([$election_id]);
            $message = "Election <strong>{$el['title']}</strong> deactivated.";
        }
    } catch(PDOException $e) { $error = "Error: ".$e->getMessage(); }
}

$elections = $pdo->query("
    SELECT e.*,
        CASE
            WHEN e.is_active=1 AND NOW() BETWEEN e.start_date AND e.end_date THEN 'active'
            WHEN NOW() > e.end_date THEN 'ended'
            WHEN NOW() < e.start_date THEN 'upcoming'
            ELSE COALESCE(e.status,'pending')
        END as current_status,
        (SELECT COUNT(*) FROM positions p WHERE p.election_id=e.id) as position_count,
        (SELECT COUNT(*) FROM candidates c JOIN positions p ON c.position_id=p.id WHERE p.election_id=e.id) as candidate_count,
        (SELECT COUNT(DISTINCT p.id) FROM positions p LEFT JOIN candidates c ON c.position_id=p.id WHERE p.election_id=e.id AND c.id IS NOT NULL) as filled_positions,
        (SELECT COUNT(DISTINCT student_id) FROM votes v WHERE v.election_id=e.id) as voter_count
    FROM elections e
    ORDER BY CASE WHEN e.is_active=1 THEN 0 WHEN NOW()<e.start_date THEN 1 WHEN NOW() BETWEEN e.start_date AND e.end_date THEN 2 ELSE 3 END, e.created_at DESC
")->fetchAll();

// System stats
$active_count   = array_sum(array_column($elections,'is_active'));
$upcoming_count = count(array_filter($elections, fn($e)=>$e['current_status']=='upcoming'));
$total_positions  = array_sum(array_column($elections,'position_count'));
$total_candidates = array_sum(array_column($elections,'candidate_count'));

include '../includes/header.php';
?>

<style>
/* ── Activate Election Page ── */
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
.page-hdr { display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.75rem; }
.page-hdr h2 { font-size:clamp(1.4rem,2.5vw,1.9rem);font-weight:800;color:#0f172a;letter-spacing:-.025em;margin:0; }
.page-hdr p  { color:#64748b;font-size:.9rem;margin:.3rem 0 0; }

.btn-primary-glass {
    display:inline-flex;align-items:center;gap:.5rem;
    background:linear-gradient(135deg,#6366f1,#818cf8);
    color:#fff;border:none;border-radius:99px;
    padding:.65rem 1.5rem;font-size:.875rem;font-weight:700;
    cursor:pointer;text-decoration:none;white-space:nowrap;
    box-shadow:0 4px 14px rgba(99,102,241,.3);transition:all .2s ease;
}
.btn-primary-glass:hover{transform:scale(1.03);opacity:.9;color:#fff;text-decoration:none;}

/* alerts */
.alert-glass{display:flex;align-items:flex-start;gap:.75rem;padding:1rem 1.1rem;border-radius:14px;margin-bottom:1.25rem;font-size:.875rem;animation:popIn .4s ease both;}
.alert-glass.success{background:rgba(220,252,231,.8);border:1px solid rgba(134,239,172,.5);color:#166534;}
.alert-glass.danger {background:rgba(254,226,226,.8);border:1px solid rgba(252,165,165,.5);color:#991b1b;}
.alert-glass.warning{background:rgba(254,249,195,.8);border:1px solid rgba(253,224,71,.5);color:#854d0e;}
.alert-glass.info   {background:rgba(219,234,254,.8);border:1px solid rgba(147,197,253,.5);color:#1e40af;}
.alert-glass.slate  {background:rgba(241,245,249,.8);border:1px solid rgba(203,213,225,.5);color:#475569;}
.alert-glass i{font-size:1rem;flex-shrink:0;margin-top:2px;}
.xbtn{margin-left:auto;background:none;border:none;cursor:pointer;color:inherit;opacity:.55;font-size:1rem;line-height:1;padding:0;}
.xbtn:hover{opacity:1;}

/* info banner */
.info-banner {
    display:flex;align-items:center;gap:1rem;
    padding:1rem 1.4rem;margin-bottom:1.75rem;
    background:rgba(219,234,254,.7);
    border:1px solid rgba(147,197,253,.45);
    border-left:4px solid #6366f1;
    border-radius:16px;backdrop-filter:blur(12px);
}
.info-banner .bi{font-size:1.5rem;color:#6366f1;flex-shrink:0;}
.info-banner h6{font-size:.875rem;font-weight:700;color:#1e40af;margin:0 0 .25rem;}
.info-banner p {font-size:.8rem;color:#1d4ed8;margin:0;}

/* system stats strip */
.sys-strip { display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.75rem; }
@media(max-width:700px){ .sys-strip{grid-template-columns:repeat(2,1fr);} }
.ss {
    padding:1.1rem 1rem;text-align:center;position:relative;overflow:hidden;
    animation:popIn .5s cubic-bezier(.34,1.56,.64,1) both;
    transition:transform .25s ease,box-shadow .25s ease;
}
.ss:hover{transform:translateY(-3px);box-shadow:0 10px 28px rgba(100,120,200,.12);}
.ss:nth-child(1){animation-delay:.04s} .ss:nth-child(2){animation-delay:.09s}
.ss:nth-child(3){animation-delay:.14s} .ss:nth-child(4){animation-delay:.19s}
.ss-blob{position:absolute;width:70px;height:70px;border-radius:50%;right:-14px;top:-14px;filter:blur(24px);opacity:.25;pointer-events:none;}
.ss.c1 .ss-blob{background:#818cf8} .ss.c2 .ss-blob{background:#34d399}
.ss.c3 .ss-blob{background:#f472b6} .ss.c4 .ss-blob{background:#fb923c}
.ss-num{font-size:1.7rem;font-weight:800;color:#0f172a;line-height:1;letter-spacing:-.03em;}
.ss-lbl{font-size:.7rem;color:#64748b;text-transform:uppercase;letter-spacing:.06em;margin-top:.25rem;}

/* election card */
.el-card {
    overflow:hidden;margin-bottom:1.1rem;
    animation:popIn .5s cubic-bezier(.34,1.56,.64,1) both;
    transition:transform .25s ease,box-shadow .25s ease;
}
.el-card:hover{transform:translateY(-3px);box-shadow:0 14px 36px rgba(100,120,200,.13);}
.el-card.st-active  {border-left:4px solid #22c55e;}
.el-card.st-upcoming{border-left:4px solid #fbbf24;}
.el-card.st-ended   {border-left:4px solid #94a3b8;}
.el-card.st-pending {border-left:4px solid #818cf8;}

/* card head */
.el-head{display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:.75rem;padding:1.1rem 1.4rem;border-bottom:1px solid rgba(200,210,240,.3);}
.el-title{font-size:1rem;font-weight:800;color:#0f172a;margin:0 0 .3rem;letter-spacing:-.02em;}
.el-date{font-size:.75rem;color:#94a3b8;display:flex;align-items:center;gap:.35rem;flex-wrap:wrap;}

/* status pill */
.sp{display:inline-flex;align-items:center;gap:.3rem;font-size:.68rem;font-weight:700;padding:.22rem .75rem;border-radius:99px;letter-spacing:.04em;text-transform:uppercase;white-space:nowrap;}
.sp-active  {background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.25);color:#16a34a;}
.sp-ended   {background:rgba(148,163,184,.1);border:1px solid rgba(148,163,184,.2);color:#64748b;}
.sp-upcoming{background:rgba(251,191,36,.12);border:1px solid rgba(251,191,36,.3);color:#92400e;}
.sp-pending {background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.2);color:#6366f1;}
.live-dot{width:7px;height:7px;border-radius:50%;background:#22c55e;box-shadow:0 0 0 3px rgba(34,197,94,.2);animation:pulse 2s infinite;display:inline-block;}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}

/* body */
.el-body{padding:1.2rem 1.4rem;}
.el-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;}
@media(max-width:800px){.el-grid{grid-template-columns:1fr;}}

/* stat tiles */
.stat-tiles{display:grid;grid-template-columns:repeat(2,1fr);gap:.6rem;}
.stile{
    background:rgba(255,255,255,.65);border:1px solid rgba(255,255,255,.9);
    border-radius:14px;padding:.75rem .85rem;text-align:center;
    backdrop-filter:blur(10px);
}
.stile-num{font-size:1.35rem;font-weight:800;color:#0f172a;line-height:1;}
.stile-lbl{font-size:.68rem;color:#94a3b8;font-weight:500;margin:.15rem 0 .3rem;white-space:nowrap;}
.stile-hint{font-size:.68rem;font-weight:600;display:flex;align-items:center;justify-content:center;gap:.25rem;}
.hint-ok {color:#16a34a;} .hint-warn{color:#92400e;} .hint-err{color:#dc2626;}

/* progress */
.prog-wrap{margin-top:.85rem;}
.prog-meta{display:flex;justify-content:space-between;font-size:.72rem;color:#94a3b8;margin-bottom:.35rem;}
.prog-track{height:7px;background:rgba(0,0,0,.07);border-radius:99px;overflow:hidden;}
.prog-fill{height:100%;border-radius:99px;background:linear-gradient(90deg,#6366f1,#a78bfa);transition:width 1.2s ease;}

/* upcoming countdown */
.countdown-box{
    display:flex;align-items:center;gap:.6rem;
    background:rgba(255,255,255,.6);border:1px solid rgba(255,255,255,.9);
    border-radius:12px;padding:.65rem .9rem;margin-top:.75rem;
    font-size:.8rem;color:#64748b;
}
.countdown-box i{color:#fbbf24;font-size:1rem;}

/* checklist */
.checklist-title{font-size:.8rem;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.65rem;}
.cl-list{list-style:none;padding:0;margin:0 0 1rem;}
.cl-item{
    display:flex;align-items:center;gap:.65rem;
    padding:.6rem .85rem;
    border-radius:12px;font-size:.835rem;
    margin-bottom:.4rem;
    border:1px solid;
}
.cl-item.ok  {background:rgba(220,252,231,.5);border-color:rgba(134,239,172,.4);color:#166534;}
.cl-item.err {background:rgba(254,226,226,.5);border-color:rgba(252,165,165,.4);color:#991b1b;}
.cl-item i{font-size:.95rem;flex-shrink:0;}
.cl-item strong{color:inherit;}
.cl-sub{font-size:.72rem;opacity:.8;margin-left:.2rem;}

/* action buttons */
.act-row{display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;}

.btn-activate{
    display:inline-flex;align-items:center;gap:.5rem;
    background:linear-gradient(135deg,#22c55e,#16a34a);
    color:#fff;border:none;border-radius:99px;
    padding:.65rem 1.4rem;font-size:.875rem;font-weight:700;
    text-decoration:none;cursor:pointer;
    box-shadow:0 4px 14px rgba(34,197,94,.32);
    transition:all .2s ease;
}
.btn-activate:hover{transform:scale(1.04);opacity:.9;color:#fff;text-decoration:none;}

.btn-deactivate{
    display:inline-flex;align-items:center;gap:.5rem;
    background:rgba(251,191,36,.15);border:1px solid rgba(251,191,36,.4);
    color:#92400e;border-radius:99px;
    padding:.65rem 1.4rem;font-size:.875rem;font-weight:700;
    text-decoration:none;cursor:pointer;
    transition:all .2s ease;
}
.btn-deactivate:hover{background:rgba(251,191,36,.28);color:#92400e;text-decoration:none;}

.btn-disabled{
    display:inline-flex;align-items:center;gap:.5rem;
    background:rgba(148,163,184,.1);border:1px solid rgba(148,163,184,.2);
    color:#94a3b8;border-radius:99px;
    padding:.65rem 1.4rem;font-size:.875rem;font-weight:700;
    cursor:not-allowed;pointer-events:none;
}

.abtn{display:inline-flex;align-items:center;gap:.35rem;padding:.38rem .85rem;border-radius:99px;font-size:.75rem;font-weight:600;text-decoration:none;cursor:pointer;border:1px solid;transition:all .18s ease;white-space:nowrap;background:none;}
.abtn:hover{transform:translateY(-1px);text-decoration:none;}
.abtn-blue{border-color:rgba(99,102,241,.3);color:#6366f1;}
.abtn-blue:hover{background:rgba(99,102,241,.08);border-color:rgba(99,102,241,.5);color:#6366f1;}
.abtn-green{border-color:rgba(34,197,94,.3);color:#16a34a;}
.abtn-green:hover{background:rgba(34,197,94,.08);border-color:rgba(34,197,94,.5);color:#16a34a;}
.abtn-teal{border-color:rgba(20,184,166,.3);color:#0f766e;}
.abtn-teal:hover{background:rgba(20,184,166,.08);border-color:rgba(20,184,166,.5);color:#0f766e;}

/* status info boxes */
.info-box{
    padding:.85rem 1rem;border-radius:14px;font-size:.82rem;margin-top:.85rem;
    display:flex;align-items:flex-start;gap:.65rem;
}
.info-box.live{background:rgba(220,252,231,.7);border:1px solid rgba(134,239,172,.45);color:#166534;}
.info-box.ended{background:rgba(241,245,249,.75);border:1px solid rgba(203,213,225,.5);color:#475569;}
.info-box.blocked{background:rgba(254,249,195,.75);border:1px solid rgba(253,224,71,.45);color:#854d0e;}
.info-box i{flex-shrink:0;font-size:.95rem;margin-top:2px;}
.info-box a{color:inherit;font-weight:600;text-underline-offset:2px;}

/* empty */
.empty-state{text-align:center;padding:4rem 1rem;}
.empty-state .ei{font-size:4rem;opacity:.2;margin-bottom:1rem;}
.empty-state p{font-size:.9rem;color:#94a3b8;}

@keyframes popIn{from{opacity:0;transform:translateY(14px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}

/* =========================================
   DARK MODE OVERRIDES
   ========================================= */
[data-theme="dark"] .page-hdr h2 { color: #e2e8f0 !important; }
[data-theme="dark"] .page-hdr p { color: #94a3b8 !important; }
[data-theme="dark"] .glass { background: rgba(20,22,35,0.85) !important; border-color: rgba(99,102,241,0.15) !important; box-shadow: 0 4px 24px rgba(0,0,0,0.3) !important; }
[data-theme="dark"] .info-banner { background: rgba(30,32,60,0.8) !important; border-color: rgba(99,102,241,0.2) !important; }
[data-theme="dark"] .info-banner h6 { color: #c7d2fe !important; }
[data-theme="dark"] .info-banner p { color: #a5b4fc !important; }
[data-theme="dark"] .ss-num { color: #e2e8f0 !important; }
[data-theme="dark"] .ss-lbl { color: #94a3b8 !important; }
[data-theme="dark"] .el-head { border-bottom-color: rgba(99,102,241,0.1) !important; }
[data-theme="dark"] .el-title { color: #e2e8f0 !important; }
[data-theme="dark"] .el-date { color: #94a3b8 !important; }
[data-theme="dark"] .stile { background: rgba(20,22,35,0.8) !important; border-color: rgba(99,102,241,0.1) !important; }
[data-theme="dark"] .stile-num { color: #e2e8f0 !important; }
[data-theme="dark"] .stile-lbl { color: #94a3b8 !important; }
[data-theme="dark"] .checklist-title { color: #94a3b8 !important; }
[data-theme="dark"] .countdown-box { background: rgba(20,22,35,0.8) !important; border-color: rgba(99,102,241,0.1) !important; color: #94a3b8 !important; }
[data-theme="dark"] .prog-meta { color: #94a3b8 !important; }
[data-theme="dark"] .prog-track { background: rgba(255,255,255,0.06) !important; }
</style>

<div class="page-wrap">

    <!-- Page Header -->
    <div class="page-hdr">
        <div>
            <h2>⚡ Activate Election</h2>
            <p>Activate or deactivate elections. Only one election can be active at a time.</p>
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

    <!-- Info Banner -->
    <div class="info-banner">
        <i class="bi bi-info-circle-fill"></i>
        <div>
            <h6>Before Activating an Election</h6>
            <p>Ensure at least <strong>1 position</strong> and <strong>1 candidate per position</strong> are set up.
               Activating a new election will automatically deactivate any currently active one.</p>
        </div>
    </div>

    <!-- System Stats Strip -->
    <div class="sys-strip">
        <div class="ss glass c1">
            <div class="ss-blob"></div>
            <div class="ss-num"><?php echo $active_count; ?></div>
            <div class="ss-lbl">Active Elections</div>
        </div>
        <div class="ss glass c2">
            <div class="ss-blob"></div>
            <div class="ss-num"><?php echo $upcoming_count; ?></div>
            <div class="ss-lbl">Upcoming</div>
        </div>
        <div class="ss glass c3">
            <div class="ss-blob"></div>
            <div class="ss-num"><?php echo $total_positions; ?></div>
            <div class="ss-lbl">Total Positions</div>
        </div>
        <div class="ss glass c4">
            <div class="ss-blob"></div>
            <div class="ss-num"><?php echo $total_candidates; ?></div>
            <div class="ss-lbl">Total Candidates</div>
        </div>
    </div>

    <!-- Election Cards -->
    <?php if(count($elections) > 0):
        foreach($elections as $i => $el):
            $st = $el['current_status'];
            $pill_class = 'sp-pending'; $pill_label = 'Pending'; $card_class = 'st-pending';
            if($st=='active')   { $pill_class='sp-active';   $pill_label='Active';   $card_class='st-active'; }
            elseif($st=='ended')    { $pill_class='sp-ended';    $pill_label='Ended';    $card_class='st-ended'; }
            elseif($st=='upcoming') { $pill_class='sp-upcoming'; $pill_label='Upcoming'; $card_class='st-upcoming'; }

            $has_pos      = $el['position_count'] > 0;
            $has_can      = $el['candidate_count'] > 0;
            $has_filled   = $el['filled_positions'] > 0;
            $is_ready     = $has_pos && $has_can && $has_filled;

            $now_ts = time();
            $start_ts = strtotime($el['start_date']);
            $end_ts   = strtotime($el['end_date']);
            $progress = $st=='active' ? min(100, max(0,(($now_ts-$start_ts)/($end_ts-$start_ts))*100)) : 0;
            $remaining = max(0, $end_ts - $now_ts);
            $rem_d = floor($remaining/86400); $rem_h = floor(($remaining%86400)/3600);

            $until_start = max(0, $start_ts - $now_ts);
            $uts_d = floor($until_start/86400); $uts_h = floor(($until_start%86400)/3600);
    ?>
    <div class="el-card glass <?php echo $card_class; ?>" style="animation-delay:<?php echo $i*0.07; ?>s">

        <!-- Head -->
        <div class="el-head">
            <div>
                <div class="el-title">
                    <?php echo htmlspecialchars($el['title']); ?>
                    <?php if($el['is_active']): ?>
                        <span class="sp sp-active ms-2" style="vertical-align:middle;font-size:.65rem;">
                            <span class="live-dot"></span> LIVE
                        </span>
                    <?php endif; ?>
                </div>
                <div class="el-date">
                    <i class="bi bi-calendar3"></i>
                    <?php echo date('M d, Y h:i A', $start_ts); ?>
                    <span style="color:#cbd5e1;">→</span>
                    <?php echo date('M d, Y h:i A', $end_ts); ?>
                </div>
            </div>
            <span class="sp <?php echo $pill_class; ?>"><?php echo $pill_label; ?></span>
        </div>

        <!-- Body -->
        <div class="el-body">
            <div class="el-grid">

                <!-- LEFT: Stats + Progress -->
                <div>
                    <div class="stat-tiles">

                        <div class="stile">
                            <div class="stile-num"><?php echo $el['position_count']; ?></div>
                            <div class="stile-lbl">Positions</div>
                            <div class="stile-hint <?php echo $has_pos?'hint-ok':'hint-err'; ?>">
                                <i class="bi bi-<?php echo $has_pos?'check-circle-fill':'x-circle-fill'; ?>"></i>
                                <?php echo $has_pos?'Ready':'Required'; ?>
                            </div>
                        </div>

                        <div class="stile">
                            <div class="stile-num"><?php echo $el['candidate_count']; ?></div>
                            <div class="stile-lbl">Candidates</div>
                            <div class="stile-hint <?php echo $has_can?'hint-ok':'hint-err'; ?>">
                                <i class="bi bi-<?php echo $has_can?'check-circle-fill':'x-circle-fill'; ?>"></i>
                                <?php echo $has_can?'Sufficient':'Required'; ?>
                            </div>
                        </div>

                        <div class="stile">
                            <div class="stile-num"><?php echo $el['filled_positions']; ?>/<?php echo $el['position_count']; ?></div>
                            <div class="stile-lbl">Filled Positions</div>
                            <div class="stile-hint <?php echo $has_filled?'hint-ok':'hint-err'; ?>">
                                <i class="bi bi-<?php echo $has_filled?'check-circle-fill':'x-circle-fill'; ?>"></i>
                                <?php echo ($el['filled_positions']==$el['position_count']&&$el['position_count']>0)?'Complete':'Incomplete'; ?>
                            </div>
                        </div>

                        <div class="stile">
                            <div class="stile-num"><?php echo $el['voter_count']; ?></div>
                            <div class="stile-lbl">Votes Cast</div>
                            <div class="stile-hint <?php echo $el['is_active']?'hint-ok':''; ?>" style="color:#94a3b8;">
                                <i class="bi bi-<?php echo $el['is_active']?'people-fill':'people'; ?>"></i>
                                <?php echo $el['is_active']?'Voting now':'Total'; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Active Progress -->
                    <?php if($st=='active'): ?>
                    <div class="prog-wrap">
                        <div class="prog-meta">
                            <span>Election progress</span>
                            <span><?php echo round($progress,1); ?>%
                                — <?php echo $rem_d; ?>d <?php echo $rem_h; ?>h remaining
                            </span>
                        </div>
                        <div class="prog-track">
                            <div class="prog-fill" style="width:<?php echo $progress; ?>%"></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Upcoming countdown -->
                    <?php if($st=='upcoming'): ?>
                    <div class="countdown-box">
                        <i class="bi bi-hourglass-split"></i>
                        <span>Starts in <strong><?php echo $uts_d>0?"$uts_d days, $uts_h hrs":"$uts_h hrs"; ?></strong>
                            &nbsp;·&nbsp; <?php echo date('M d, Y h:i A', $start_ts); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- RIGHT: Checklist + Actions -->
                <div>
                    <div class="checklist-title">Activation Checklist</div>
                    <ul class="cl-list">
                        <li class="cl-item <?php echo $has_pos?'ok':'err'; ?>">
                            <i class="bi bi-<?php echo $has_pos?'check-circle-fill':'x-circle-fill'; ?>"></i>
                            <span>
                                Positions: <strong><?php echo $el['position_count']; ?></strong>
                                <?php if(!$has_pos): ?><span class="cl-sub">(At least 1 required)</span><?php endif; ?>
                            </span>
                        </li>
                        <li class="cl-item <?php echo $has_can?'ok':'err'; ?>">
                            <i class="bi bi-<?php echo $has_can?'check-circle-fill':'x-circle-fill'; ?>"></i>
                            <span>
                                Candidates: <strong><?php echo $el['candidate_count']; ?></strong>
                                <?php if(!$has_can): ?><span class="cl-sub">(At least 1 required)</span><?php endif; ?>
                            </span>
                        </li>
                        <li class="cl-item <?php echo $has_filled?'ok':'err'; ?>">
                            <i class="bi bi-<?php echo $has_filled?'check-circle-fill':'x-circle-fill'; ?>"></i>
                            <span>
                                Filled Positions: <strong><?php echo $el['filled_positions']; ?>/<?php echo $el['position_count']; ?></strong>
                                <?php if(!$has_filled): ?><span class="cl-sub">(Each position needs a candidate)</span><?php endif; ?>
                            </span>
                        </li>
                    </ul>

                    <!-- Primary action -->
                    <div class="act-row">
                        <?php if($el['is_active']): ?>
                            <a href="?deactivate=<?php echo $el['id']; ?>" class="btn-deactivate"
                               onclick="return confirm('Deactivate this election? Students will not be able to vote.')">
                                <i class="bi bi-pause-circle"></i> Deactivate Election
                            </a>
                        <?php elseif($is_ready): ?>
                            <a href="?activate=<?php echo $el['id']; ?>" class="btn-activate"
                               onclick="return confirm('Activate this election? Any currently active election will be deactivated.')">
                                <i class="bi bi-play-circle-fill"></i> Activate Election
                            </a>
                        <?php else: ?>
                            <span class="btn-disabled">
                                <i class="bi bi-slash-circle"></i> Not Ready
                            </span>
                        <?php endif; ?>

                        <a href="manage_positions.php?election_id=<?php echo $el['id']; ?>" class="abtn abtn-blue">
                            <i class="bi bi-list-task"></i> Positions
                        </a>
                        <a href="add_candidates.php?election_id=<?php echo $el['id']; ?>" class="abtn abtn-green">
                            <i class="bi bi-person-plus"></i> Candidates
                        </a>
                        <a href="results.php?election_id=<?php echo $el['id']; ?>" class="abtn abtn-teal">
                            <i class="bi bi-bar-chart"></i> Results
                        </a>
                    </div>

                    <!-- Status info boxes -->
                    <?php if($el['is_active'] && $st=='active'): ?>
                    <div class="info-box live">
                        <i class="bi bi-check-circle-fill"></i>
                        <div>
                            <strong>Election is LIVE!</strong><br>
                            Students can vote now. <strong><?php echo $el['voter_count']; ?></strong> have already voted.
                        </div>
                    </div>

                    <?php elseif(!$is_ready && !$el['is_active']): ?>
                    <div class="info-box blocked">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <div>
                            <strong>Cannot activate yet.</strong>&nbsp;
                            <?php
                            $issues = [];
                            if(!$has_pos) $issues[] = 'add positions';
                            if(!$has_can) $issues[] = 'add candidates';
                            if(!$has_filled) $issues[] = 'assign candidates to every position';
                            echo 'Please: '.implode(', ', $issues).'.';
                            ?>
                            &nbsp;<a href="manage_positions.php?election_id=<?php echo $el['id']; ?>">Setup →</a>
                        </div>
                    </div>

                    <?php elseif($st=='ended'): ?>
                    <div class="info-box ended">
                        <i class="bi bi-clock-history"></i>
                        <div>
                            Ended on <strong><?php echo date('M d, Y h:i A', $end_ts); ?></strong>.
                            <a href="results.php?election_id=<?php echo $el['id']; ?>">View final results →</a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div><!-- /el-grid -->
        </div><!-- /el-body -->
    </div><!-- /el-card -->
    <?php endforeach; ?>

<?php else: ?>
    <div class="glass empty-state">
        <div class="ei">⚡</div>
        <p style="font-weight:700;color:#475569;font-size:1rem;">No elections found</p>
        <p>Create your first election before activating.</p>
        <a href="create_election.php" class="btn-primary-glass" style="margin-top:.75rem;display:inline-flex;">
            <i class="bi bi-plus-circle"></i> Create Election
        </a>
    </div>
<?php endif; ?>

</div><!-- /page-wrap -->

<?php include '../includes/footer.php'; ?>