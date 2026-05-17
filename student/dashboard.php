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
    $state = $has_voted_in_active ? 'C' : 'B';
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
/* ── Soft Glass Dashboard Styles ── */
.dashboard-banner {
    background: #6366f1;
    color: white;
    padding: 3rem 2rem 10rem;
    border-radius: 30px;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
}
.dashboard-banner::after {
    content: ''; position: absolute; width: 400px; height: 400px;
    background: rgba(255,255,255,0.05); border-radius: 50%;
    top: -150px; right: -150px;
}

.dashboard-overlap {
    margin: -8rem 1.5rem 0;
    position: relative;
    z-index: 10;
}

.stats-grid {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.25rem; margin-bottom: 2.5rem;
}

.stat-card-glass {
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.5);
    border-radius: 24px; padding: 1.5rem;
    box-shadow: 0 10px 30px rgba(0,0,0,0.03);
    display: flex; flex-direction: column; gap: 0.75rem;
}

.stat-icon-glass {
    width: 40px; height: 40px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.15rem;
}

.main-flex { display: flex; gap: 2rem; }
.main-left { flex: 1.2; }
.main-right { flex: 0.8; }

.status-glass-card {
    background: rgba(255, 255, 255, 0.5);
    backdrop-filter: blur(15px);
    border: 1px solid rgba(255, 255, 255, 0.4);
    border-radius: 28px; padding: 2rem;
    height: 100%; display: flex; flex-direction: column;
}

.hero-box-glass {
    background: #6366f1; border-radius: 24px;
    padding: 2.5rem; color: white; text-align: center;
    margin-bottom: 2rem; position: relative; overflow: hidden;
    box-shadow: 0 12px 30px rgba(99, 102, 241, 0.2);
}

.hero-avatar-glass {
    width: 70px; height: 70px; border-radius: 50%;
    background: rgba(255,255,255,0.15); margin: 0 auto 1.5rem;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.6rem; font-weight: 800; border: 3px solid rgba(255,255,255,0.1);
}

.tools-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;
}
.tool-tile-modern {
    background: rgba(255, 255, 255, 0.5);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.4);
    border-radius: 24px; padding: 2rem 1.5rem;
    text-decoration: none; color: inherit;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex; flex-direction: column; gap: 0.25rem;
    text-align: center;
}
.tool-tile-modern:hover { transform: translateY(-8px); background: rgba(255, 255, 255, 0.8); border-color: #6366f1; }
.tool-tile-modern i { font-size: 2rem; margin-bottom: 0.75rem; }

@media (max-width: 991px) {
    .dashboard-banner { padding: 3rem 1.5rem 8rem; border-radius: 25px; text-align: center; }
    .dashboard-overlap { margin: -6rem 0.5rem 80px; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .main-flex { flex-direction: column; }
}
</style>

<div class="dashboard-banner">
    <div class="container-fluid">
        <h2 class="fw-800 mb-2" style="font-size: 2.2rem;">Voter Dashboard</h2>
        <p class="small opacity-75 mb-0">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>. Your voice matters.</p>
    </div>
</div>

<div class="dashboard-overlap">
    <!-- STATS GRID -->
    <div class="stats-grid">
        <div class="stat-card-glass">
            <div class="stat-icon-glass icon-purple"><i class="bi bi-shield-check"></i></div>
            <div class="stat-info">
                <h3 class="mb-0 fw-800" style="font-size: 1.5rem; color:#1e293b;"><?php echo ($state == 'C' ? 'Voted' : 'Pending'); ?></h3>
                <span class="small fw-700 text-muted">My Status</span>
            </div>
        </div>
        <div class="stat-card-glass">
            <div class="stat-icon-glass icon-green"><i class="bi bi-graph-up-arrow"></i></div>
            <div class="stat-info">
                <h3 class="mb-0 fw-800" style="font-size: 1.5rem; color:#1e293b;"><?php echo $total_votes_overall; ?></h3>
                <span class="small fw-700 text-muted">Voter Turnout</span>
            </div>
        </div>
        <div class="stat-card-glass">
            <div class="stat-icon-glass icon-gold"><i class="bi bi-calendar-event"></i></div>
            <div class="stat-info">
                <h3 class="mb-0 fw-800" style="font-size: 1.5rem; color:#1e293b;"><?php echo $election_year; ?></h3>
                <span class="small fw-700 text-muted">Active Year</span>
            </div>
        </div>
        <div class="stat-card-glass">
            <div class="stat-icon-glass icon-blue"><i class="bi bi-people"></i></div>
            <div class="stat-info">
                <h3 class="mb-0 fw-800" style="font-size: 1.5rem; color:#1e293b;"><?php echo $candidates_count; ?></h3>
                <span class="small fw-700 text-muted">Candidates</span>
            </div>
        </div>
    </div>

    <!-- MAIN FLEX -->
    <div class="main-flex">
        <div class="main-left">
            <div class="section-label">Voting Status</div>
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
                    <h3 class="fw-800 mb-2"><?php echo ($state == 'C' ? 'Voted Successfully' : 'Voting Participation'); ?></h3>
                    <p class="small opacity-75 mb-4"><?php echo ($state == 'C' ? 'Your ballot was securely recorded.' : 'Your vote hasn\'t been cast yet. Participate now!'); ?></p>
                    <div class="badge bg-white text-indigo-700 rounded-pill px-4 py-2 fw-800 shadow-sm" style="font-size: 0.75rem;">
                        <i class="bi bi-patch-check-fill me-1"></i> <?php echo ($state == 'C' ? 'BALLOT CONFIRMED' : 'VOTE REQUIRED'); ?>
                    </div>
                </div>
                <div class="mt-auto d-flex justify-content-between align-items-center">
                    <span class="small fw-700 text-muted"><?php echo $active_election ? htmlspecialchars($active_election['title']) : 'Election Period'; ?></span>
                    <?php if($state == 'B'): ?>
                        <a href="vote.php" class="btn btn-primary rounded-xl px-4 fw-800">CAST VOTE NOW</a>
                    <?php else: ?>
                        <button class="btn btn-light rounded-xl px-4 fw-800" disabled>LOCKED</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="main-right">
            <div class="section-label">Voter Tools</div>
            <div class="tools-grid">
                <a href="vote.php" class="tool-tile-modern">
                    <i class="bi bi-check2-circle icon-purple"></i>
                    <span class="fw-800" style="font-size: 0.9rem;">Vote Now</span>
                </a>
                <a href="results.php" class="tool-tile-modern">
                    <i class="bi bi-bar-chart icon-green"></i>
                    <span class="fw-800" style="font-size: 0.9rem;">Results</span>
                </a>
                <a href="winners.php" class="tool-tile-modern">
                    <i class="bi bi-trophy icon-gold"></i>
                    <span class="fw-800" style="font-size: 0.9rem;">Hall of Fame</span>
                </a>
                <a href="profile.php" class="tool-tile-modern">
                    <i class="bi bi-person icon-blue"></i>
                    <span class="fw-800" style="font-size: 0.9rem;">My Profile</span>
                </a>
            </div>
        </div>
    </div>
</div>



<?php include '../includes/footer.php'; ?>