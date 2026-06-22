<?php
// filename: student/dashboard.php
require_once '../config/database.php';
require_once '../includes/auth_check.php';

if($_SESSION['user_type'] != 'student') {
    header("Location: ../index.php");
    exit();
}

$student_id = $_SESSION['student_id'];

// Get student information
$stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

// Check if student is registered (eligible)
$check_eligible = $pdo->prepare("SELECT is_registered FROM eligible_ids WHERE student_id = ?");
$check_eligible->execute([$student_id]);
$eligible = $check_eligible->fetch();

if($student && (!$eligible || !$eligible['is_registered'])) {
    $update_eligible = $pdo->prepare("UPDATE eligible_ids SET is_registered = 1 WHERE student_id = ?");
    $update_eligible->execute([$student_id]);
}

// Include the election scheduler
require_once '../cron/election_scheduler.php';

// Get active election
$active_election = $pdo->query("
    SELECT * FROM elections 
    WHERE is_active = 1 
    AND NOW() BETWEEN start_date AND end_date 
    LIMIT 1
")->fetch();

if(!$active_election) {
    $active_election = $pdo->query("
        SELECT * FROM elections 
        WHERE NOW() BETWEEN start_date AND end_date 
        AND is_active = 0
        ORDER BY start_date ASC
        LIMIT 1
    ")->fetch();
}

// Check for open application windows
$open_application_election = $pdo->query("
    SELECT id, title, is_paused FROM elections 
    WHERE application_start IS NOT NULL
    AND application_end IS NOT NULL
    AND NOW() BETWEEN application_start AND application_end
    ORDER BY application_end ASC
    LIMIT 1
")->fetch();

$student_application_status = null;
if($open_application_election) {
    $stmt = $pdo->prepare("SELECT status FROM candidate_applications WHERE student_id = ? AND election_id = ?");
    $stmt->execute([$student_id, $open_application_election['id']]);
    $student_application_status = $stmt->fetchColumn();
}

// Determine voting status in the current active election
$has_voted_in_active = false;
$user_votes = [];
if($active_election) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE election_id = ? AND student_id = ?");
    $stmt->execute([$active_election['id'], $student_id]);
    $has_voted_in_active = $stmt->fetchColumn() > 0;

    // Get positions for this election
    $stmt = $pdo->prepare("SELECT * FROM positions WHERE election_id = ? ORDER BY id ASC");
    $stmt->execute([$active_election['id']]);
    $positions = $stmt->fetchAll();
} else {
    $positions = [];
}

// Determine Dashboard State
// A: Idle (no election)
// B: Live (election exists, not voted)
// C: Submitted (election exists, voted)
$state = 'A';
$total_votes_overall = 0;
$candidates_count = 0;
$election_year = date('Y');

if($active_election) {
    if ($active_election['is_active'] == 1) {
        if ($active_election['is_paused'] == 1 && !$has_voted_in_active) {
            $state = 'P';
        } else {
            $state = $has_voted_in_active ? 'C' : 'B';
        }
    } else {
        $state = 'D'; // Deactivated / Upcoming
    }
    $election_year = date('Y', strtotime($active_election['start_date']));
    
    // Total votes cast in this election
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT student_id) FROM votes WHERE election_id = ?");
    $stmt->execute([$active_election['id']]);
    $total_votes_overall = $stmt->fetchColumn();
    
    // Total candidates in this election
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM candidates c JOIN positions p ON c.position_id = p.id WHERE p.election_id = ?");
    $stmt->execute([$active_election['id']]);
    $candidates_count = $stmt->fetchColumn();
}

include '../includes/header.php';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Sora:wght@700;800&display=swap');

/* ── Dashboard Banner ── */
.dashboard-banner {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 60%, #a855f7 100%);
    color: white;
    padding: 3rem 2rem 10rem;
    border-radius: 30px;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 24px 60px rgba(99,102,241,0.35);
}
.dashboard-banner::before {
    content: '';
    position: absolute; top: -100px; right: -100px;
    width: 350px; height: 350px; border-radius: 50%;
    background: rgba(255,255,255,0.08);
    pointer-events: none;
}
.dashboard-banner::after {
    content: '';
    position: absolute; bottom: -80px; left: 30%;
    width: 220px; height: 220px; border-radius: 50%;
    background: rgba(255,255,255,0.05);
    pointer-events: none;
}
.db-title {
    font-family: 'Sora', sans-serif;
    font-size: 2.2rem; font-weight: 800;
    color: white; margin-bottom: 0.4rem;
    text-shadow: 0 2px 12px rgba(0,0,0,0.15);
    position: relative; z-index: 1;
}
.db-sub {
    font-size: 0.95rem; color: rgba(255,255,255,0.78);
    font-weight: 600; position: relative; z-index: 1;
}

/* ── Overlap ── */
.dashboard-overlap {
    margin: -8rem 1.5rem 0;
    position: relative; z-index: 10;
}

/* ── Stats Grid ── */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.25rem; margin-bottom: 2.5rem;
}

/* ── Adaptive Glass Panel ── */
.stat-card-glass {
    background: var(--panel-bg, rgba(255,255,255,0.95));
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid var(--border-soft, rgba(99,102,241,0.14));
    border-radius: 22px; padding: 1.5rem;
    box-shadow: 0 12px 40px rgba(0,0,0,0.08);
    display: flex; flex-direction: column; gap: 0.75rem;
    transition: transform 0.2s, box-shadow 0.2s;
}
.stat-card-glass:hover {
    transform: translateY(-3px);
    box-shadow: 0 18px 50px rgba(0,0,0,0.12);
}
[data-theme="dark"] .stat-card-glass {
    background: rgba(13,15,27,0.92);
    border-color: rgba(99,102,241,0.16);
    box-shadow: 0 18px 45px rgba(0,0,0,0.28);
}

.stat-icon-glass {
    width: 44px; height: 44px; border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem;
}

.stat-val {
    font-family: 'Sora', sans-serif;
    font-size: 1.5rem; font-weight: 800;
    color: var(--text-high, #0f172a);
    line-height: 1;
}
[data-theme="dark"] .stat-val { color: #f1f5f9; }

.stat-lbl {
    font-size: 0.78rem; font-weight: 700;
    color: var(--text-muted, #64748b);
    text-transform: uppercase; letter-spacing: 0.04em;
}
[data-theme="dark"] .stat-lbl { color: #94a3b8; }

/* ── Section Label ── */
.section-label {
    font-size: 0.72rem; font-weight: 800;
    text-transform: uppercase; letter-spacing: 0.06em;
    color: var(--text-muted, #64748b);
    margin-bottom: 0.75rem; display: block;
}
[data-theme="dark"] .section-label { color: #94a3b8; }

/* ── Main Flex ── */
.main-flex { display: flex; gap: 2rem; }
.main-left { flex: 1.2; }
.main-right { flex: 0.8; }

/* ── Status Panel ── */
.status-glass-card {
    background: var(--panel-bg, rgba(255,255,255,0.95));
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid var(--border-soft, rgba(99,102,241,0.14));
    border-radius: 28px; padding: 2rem;
    height: 100%; display: flex; flex-direction: column;
    box-shadow: 0 12px 40px rgba(0,0,0,0.07);
}
[data-theme="dark"] .status-glass-card {
    background: rgba(13,15,27,0.86);
    border-color: rgba(99,102,241,0.16);
    box-shadow: 0 20px 55px rgba(0,0,0,0.3);
}

/* ── Hero Box (status inner card) ── */
.hero-box-glass {
    background: linear-gradient(145deg, #6366f1, #7c3aed);
    border-radius: 22px;
    padding: 2.5rem; color: white; text-align: center;
    margin-bottom: 1.5rem; position: relative; overflow: hidden;
    box-shadow: 0 16px 40px rgba(99,102,241,0.3);
}
.hero-box-glass::before {
    content: '';
    position: absolute; top: -60px; right: -60px;
    width: 160px; height: 160px; border-radius: 50%;
    background: rgba(255,255,255,0.08);
}
.hero-box-glass h3 {
    font-family: 'Sora', sans-serif;
    font-size: 1.4rem; font-weight: 800; color: white;
    margin-bottom: 0.4rem;
}
.hero-box-glass p { color: rgba(255,255,255,0.8); }

.hero-avatar-glass {
    width: 72px; height: 72px; border-radius: 50%;
    background: rgba(255,255,255,0.18); margin: 0 auto 1.25rem;
    display: flex; align-items: center; justify-content: center;
    font-family: 'Sora', sans-serif;
    font-size: 1.6rem; font-weight: 800; color: white;
    border: 3px solid rgba(255,255,255,0.3);
    box-shadow: 0 6px 20px rgba(0,0,0,0.2);
}

/* ── Status footer row ── */
.status-footer {
    display: flex; align-items: center; justify-content: space-between;
    margin-top: auto; padding-top: 1rem;
}
.status-footer .election-name {
    font-size: 0.85rem; font-weight: 700;
    color: var(--text-muted, #64748b);
}
[data-theme="dark"] .status-footer .election-name { color: #94a3b8; }

/* ── Tool Tiles ── */
.tools-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;
}
.tool-tile-modern {
    background: var(--panel-bg, rgba(255,255,255,0.95));
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1.5px solid var(--border-soft, rgba(99,102,241,0.12));
    border-radius: 22px; padding: 1.75rem 1.25rem;
    text-decoration: none;
    color: var(--text-high, #0f172a);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex; flex-direction: column; gap: 0.35rem;
    text-align: center;
    box-shadow: 0 8px 24px rgba(0,0,0,0.06);
}
.tool-tile-modern:hover {
    transform: translateY(-6px);
    border-color: #6366f1;
    box-shadow: 0 16px 40px rgba(99,102,241,0.15);
    background: rgba(99,102,241,0.06);
    color: #4338ca;
}
[data-theme="dark"] .tool-tile-modern {
    background: rgba(13,15,27,0.9);
    border-color: rgba(99,102,241,0.16);
    color: #e2e8f0;
    box-shadow: 0 12px 36px rgba(0,0,0,0.28);
}
[data-theme="dark"] .tool-tile-modern:hover {
    background: rgba(99,102,241,0.12);
    border-color: #6366f1;
    color: #c7d2fe;
}
.tool-tile-modern i {
    font-size: 2rem; margin-bottom: 0.5rem;
    transition: transform 0.2s;
}
.tool-tile-modern:hover i { transform: scale(1.15); }
.tool-tile-modern span {
    font-size: 0.88rem; font-weight: 800;
    color: inherit;
}

/* ── Icon colors ── */
.icon-green { background: rgba(16,185,129,0.12); color: #059669; }
.icon-gold  { background: rgba(245,158,11,0.12);  color: #d97706; }
.icon-blue  { background: rgba(59,130,246,0.12);  color: #2563eb; }
.icon-purple{ background: rgba(139,92,246,0.12);  color: #7c3aed; }

[data-theme="dark"] .icon-green { background: rgba(16,185,129,0.14); color: #34d399; }
[data-theme="dark"] .icon-gold  { background: rgba(245,158,11,0.14);  color: #fcd34d; }
[data-theme="dark"] .icon-blue  { background: rgba(99,102,241,0.14);  color: #818cf8; }
[data-theme="dark"] .icon-purple{ background: rgba(139,92,246,0.14);  color: #c4b5fd; }

/* ── Responsive ── */
@media (max-width: 991px) {
    .dashboard-banner { padding: 3rem 1.5rem 8rem; border-radius: 25px; text-align: center; }
    .dashboard-overlap { margin: -6rem 0.5rem 80px; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .main-flex { flex-direction: column; }
    .db-title { font-size: 1.75rem; }
}
@media (max-width: 575px) {
    .dashboard-banner { padding: 2.5rem 1rem 7.5rem; border-radius: 20px; }
    .dashboard-overlap { margin: -5.5rem 0.25rem 80px; }
    .stats-grid { grid-template-columns: 1fr 1fr; gap: 0.75rem; }
    .stat-card-glass { padding: 1rem; }
    .stat-val { font-size: 1.25rem; }
    .main-flex { gap: 1rem; }
    .tools-grid { gap: 0.75rem; }
    .tool-tile-modern { padding: 1.25rem 0.75rem; }
    .tool-tile-modern i { font-size: 1.5rem; }
    .db-title { font-size: 1.5rem; }
}
</style>

<div class="dashboard-banner">
    <div class="container-fluid">
        <div class="db-title">Voter Dashboard</div>
        <div class="db-sub">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>. Your voice matters.</div>
    </div>
</div>

<div class="dashboard-overlap">
    <!-- STATS GRID -->
    <div class="stats-grid">
        <div class="stat-card-glass">
            <div class="stat-icon-glass icon-green"><i class="bi bi-shield-check"></i></div>
            <div>
                <div class="stat-val">
                    <?php 
                    if($state == 'C') echo 'Voted';
                    elseif($state == 'B') echo 'Pending';
                    elseif($state == 'P') echo 'Paused';
                    else echo 'N/A';
                    ?>
                </div>
                <div class="stat-lbl">My Status</div>
            </div>
        </div>
        <div class="stat-card-glass">
            <div class="stat-icon-glass icon-blue"><i class="bi bi-graph-up-arrow"></i></div>
            <div>
                <div class="stat-val"><?php echo $total_votes_overall; ?></div>
                <div class="stat-lbl">Voter Turnout</div>
            </div>
        </div>
        <div class="stat-card-glass">
            <div class="stat-icon-glass icon-gold"><i class="bi bi-calendar-event"></i></div>
            <div>
                <div class="stat-val"><?php echo $election_year; ?></div>
                <div class="stat-lbl">Active Year</div>
            </div>
        </div>
        <div class="stat-card-glass">
            <div class="stat-icon-glass icon-purple"><i class="bi bi-people"></i></div>
            <div>
                <div class="stat-val"><?php echo $candidates_count; ?></div>
                <div class="stat-lbl">Candidates</div>
            </div>
        </div>
    </div>

    <!-- MAIN FLEX -->
    <div class="main-flex">
        <div class="main-left">
            <span class="section-label">Voting Status</span>
            <div class="status-glass-card">
                <div class="hero-box-glass">
                    <div class="hero-avatar-glass">
                        <?php 
                        $initials = '';
                        $parts = explode(' ', $_SESSION['full_name']);
                        foreach($parts as $p) { if(!empty($p)) $initials .= strtoupper(substr($p, 0, 1)); }
                        echo substr($initials, 0, 2);
                        ?>
                    </div>
                    <h3>
                        <?php 
                        if ($state == 'C') echo 'Ballot Confirmed';
                        elseif ($state == 'P') echo 'Voting Paused';
                        elseif ($state == 'D') echo 'Election Closed';
                        elseif ($state == 'A') echo 'No Active Election';
                        else echo 'Voting Participation'; 
                        ?>
                    </h3>
                    <p class="small mb-4">
                        <?php 
                        if ($state == 'C') echo 'Your ballot has been securely cast and recorded.';
                        elseif ($state == 'P') echo 'This election is temporarily paused by the administrator. Voting is suspended.';
                        elseif ($state == 'D') echo 'This election is currently deactivated or upcoming.';
                        elseif ($state == 'A') echo 'There are no active elections at this time.';
                        else echo "Your vote hasn't been cast yet. Participate now!"; 
                        ?>
                    </p>
                    <div class="badge rounded-pill px-4 py-2 fw-800 shadow-sm" style="font-size: 0.75rem; background: rgba(255,255,255,0.18); color: white; border: 1px solid rgba(255,255,255,0.28);">
                        <i class="bi bi-patch-check-fill me-1"></i> 
                        <?php 
                        if ($state == 'C') echo 'BALLOT CONFIRMED';
                        elseif ($state == 'P') echo 'VOTING PAUSED';
                        elseif ($state == 'D' || $state == 'A') echo 'VOTING CLOSED';
                        else echo 'VOTE REQUIRED'; 
                        ?>
                    </div>
                </div>
                <div class="status-footer">
                    <span class="election-name"><?php echo $active_election ? htmlspecialchars($active_election['title']) : 'Election Period'; ?></span>
                    <?php if($state == 'B'): ?>
                        <a href="vote.php" class="btn btn-primary rounded-pill px-4 fw-800" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);border:none;box-shadow:0 6px 18px rgba(99,102,241,0.3);">CAST VOTE NOW</a>
                    <?php elseif($state == 'P'): ?>
                        <button class="btn btn-warning rounded-pill px-4 fw-800" disabled style="opacity:0.8; color:white; border:none; background:linear-gradient(135deg,#f59e0b,#d97706);">PAUSED</button>
                    <?php else: ?>
                        <button class="btn btn-light rounded-pill px-4 fw-800" disabled style="opacity:0.6;">LOCKED</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="main-right">
            <span class="section-label">Voter Tools</span>
            <div class="tools-grid">
                <?php if($state == 'B'): ?>
                <a href="vote.php" class="tool-tile-modern">
                    <i class="bi bi-check2-circle" style="color:#059669;"></i>
                    <span>Vote Now</span>
                </a>
                <?php elseif($state == 'P'): ?>
                <a href="javascript:void(0)" class="tool-tile-modern" style="opacity: 0.6; cursor: not-allowed;" onclick="alert('Voting is temporarily paused.')">
                    <i class="bi bi-pause-circle" style="color:#f59e0b;"></i>
                    <span>Vote Paused</span>
                </a>
                <?php else: ?>
                <a href="javascript:void(0)" class="tool-tile-modern" style="opacity: 0.6; cursor: not-allowed;">
                    <i class="bi bi-check2-circle" style="color:#64748b;"></i>
                    <span>Vote Now</span>
                </a>
                <?php endif; ?>
                <a href="results.php" class="tool-tile-modern">
                    <i class="bi bi-bar-chart" style="color:#6366f1;"></i>
                    <span>Results</span>
                </a>
                <a href="winners.php" class="tool-tile-modern">
                    <i class="bi bi-trophy" style="color:#d97706;"></i>
                    <span>Hall of Fame</span>
                </a>
                <a href="profile.php" class="tool-tile-modern">
                    <i class="bi bi-person" style="color:#2563eb;"></i>
                    <span>My Profile</span>
                </a>
                <?php if($open_application_election): ?>
                    <?php if(!$student_application_status): ?>
                        <?php if($open_application_election['is_paused'] == 1): ?>
                        <a href="javascript:void(0)" class="tool-tile-modern" style="grid-column: 1 / -1; opacity: 0.6; cursor: not-allowed;" onclick="alert('Candidate application is temporarily paused.')">
                            <i class="bi bi-megaphone" style="color: #64748b;"></i>
                            <span>Applications Paused</span>
                        </a>
                        <?php else: ?>
                        <a href="apply_candidate.php" class="tool-tile-modern" style="grid-column: 1 / -1;">
                            <i class="bi bi-megaphone" style="color: #7c3aed;"></i>
                            <span>Run for Office</span>
                        </a>
                        <?php endif; ?>
                    <?php else: ?>
                    <a href="my_application.php" class="tool-tile-modern" style="grid-column: 1 / -1;">
                        <i class="bi bi-file-earmark-person" style="color: #7c3aed;"></i>
                        <span>My Application</span>
                    </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>




<?php include '../includes/footer.php'; ?>