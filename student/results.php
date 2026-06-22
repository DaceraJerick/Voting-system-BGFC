<?php
// filename: student/results.php
// location: student/results.php

require_once '../config/database.php';
require_once '../includes/auth_check.php';

if($_SESSION['user_type'] != 'student') {
    header("Location: ../index.php");
    exit();
}

// Get all past elections (completed/ended)
$past_elections = $pdo->query("
    SELECT * FROM elections 
    WHERE end_date < NOW() 
    ORDER BY end_date DESC
")->fetchAll();

// Get current/active election
$active_election = $pdo->query("
    SELECT * FROM elections 
    WHERE is_active = 1 
    AND NOW() BETWEEN start_date AND end_date 
    LIMIT 1
")->fetch();

// Get selected election results
$selected_election = null;
$election_results = [];
$winners_only = [];

if(isset($_GET['election_id'])) {
    $election_id = $_GET['election_id'];
    
    // Get election details
    $stmt = $pdo->prepare("SELECT * FROM elections WHERE id = ?");
    $stmt->execute([$election_id]);
    $selected_election = $stmt->fetch();
    
    if($selected_election) {
        // Get results per position
        $stmt = $pdo->prepare("
            SELECT 
                p.id as position_id,
                p.position_name,
                p.display_order,
                c.id as candidate_id,
                c.full_name as candidate_name,
                c.student_id,
                c.photo,
                COUNT(v.id) as vote_count,
                (
                    SELECT COUNT(DISTINCT student_id) 
                    FROM votes 
                    WHERE election_id = ?
                ) as total_voters
            FROM positions p
            LEFT JOIN candidates c ON c.position_id = p.id
            LEFT JOIN votes v ON v.candidate_id = c.id AND v.election_id = p.election_id
            WHERE p.election_id = ?
            GROUP BY p.id, c.id
            ORDER BY p.display_order, vote_count DESC
        ");
        $stmt->execute([$election_id, $election_id]);
        $results = $stmt->fetchAll();
        
        // Group by position and find winners
        foreach($results as $row) {
            if(!isset($election_results[$row['position_name']])) {
                $election_results[$row['position_name']] = [
                    'position_id' => $row['position_id'],
                    'candidates' => [],
                    'total_votes' => 0
                ];
            }
            $election_results[$row['position_name']]['candidates'][] = $row;
            $election_results[$row['position_name']]['total_votes'] += $row['vote_count'];
        }
        
        // Extract winners only
        foreach($election_results as $position_name => $position) {
            $max_votes = 0;
            $winners = [];
            
            foreach($position['candidates'] as $candidate) {
                if($candidate['vote_count'] > $max_votes) {
                    $max_votes = $candidate['vote_count'];
                    $winners = [$candidate];
                } elseif($candidate['vote_count'] == $max_votes && $max_votes > 0) {
                    $winners[] = $candidate;
                }
            }
            
            $winners_only[$position_name] = [
                'winners' => $winners,
                'total_votes' => $position['total_votes'],
                'is_tie' => count($winners) > 1
            ];
        }
    }
}

include '../includes/header.php';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Sora:wght@700;800&display=swap');

/* ── Results Banner ── */
.results-banner {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 60%, #a855f7 100%);
    color: white;
    padding: 3rem 2rem 7rem;
    border-radius: 30px;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 24px 60px rgba(99,102,241,0.35);
}
.results-banner::before {
    content: '';
    position: absolute; top: -100px; right: -100px;
    width: 350px; height: 350px; border-radius: 50%;
    background: rgba(255,255,255,0.08);
    pointer-events: none;
}
.results-banner::after {
    content: '';
    position: absolute; bottom: -80px; left: 30%;
    width: 220px; height: 220px; border-radius: 50%;
    background: rgba(255,255,255,0.05);
    pointer-events: none;
}
.results-content { margin: -5rem 1.5rem 0; position: relative; z-index: 10; }
.standings-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr)); gap: 1.5rem; }

/* ── Adaptive Search Bar ── */
.search-bar-glass {
    background: var(--panel-bg, rgba(255,255,255,0.95));
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid var(--border-soft, rgba(99,102,241,0.14));
    border-radius: 20px;
    padding: 1.25rem 2rem;
    margin-bottom: 2.5rem;
    display: flex; align-items: center; gap: 1rem;
    box-shadow: 0 12px 40px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
}
[data-theme="dark"] .search-bar-glass {
    background: rgba(13,15,27,0.85);
    border-color: rgba(99,102,241,0.16);
    box-shadow: 0 14px 40px rgba(0,0,0,0.24);
}
.search-bar-glass:focus-within {
    border-color: #6366f1;
    box-shadow: 0 0 0 4px rgba(99,102,241,0.1);
}
.search-bar-glass i { color: #10b981; font-size: 1.25rem; }
.search-bar-glass .app-select {
    color: var(--text-high, #0f172a);
    font-weight: 700; border: none; background: transparent; width: 100%; outline: none; padding: 0;
}
[data-theme="dark"] .search-bar-glass .app-select { color: #e2e8f0; }

.search-bar-glass .app-select option {
    background: var(--panel-bg, #ffffff);
    color: var(--text-high, #0f172a);
}

/* ── Position Label ── */
.pos-label {
    font-family: 'Sora', sans-serif;
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--text-high, #0f172a);
    display: block;
    margin-bottom: 1rem;
}
[data-theme="dark"] .pos-label { color: #f1f5f9; }

/* ── Adaptive App Card ── */
.result-card {
    background: var(--panel-bg, rgba(255,255,255,0.95));
    backdrop-filter: blur(22px);
    -webkit-backdrop-filter: blur(22px);
    border: 1px solid var(--border-soft, rgba(99,102,241,0.14));
    border-radius: 24px;
    padding: 1.25rem;
    box-shadow: 0 12px 40px rgba(0,0,0,0.08);
    margin-bottom: 1.5rem;
    color: var(--text-high, #0f172a);
}
[data-theme="dark"] .result-card {
    background: rgba(12,14,24,0.92);
    border-color: rgba(99,102,241,0.16);
    box-shadow: 0 18px 60px rgba(0,0,0,0.35);
    color: #f8fafc;
}

.winner-name {
    font-family: 'Sora', sans-serif;
    font-weight: 800;
    font-size: 1.1rem;
}

.winner-meta {
    font-size: 0.8rem;
    color: var(--text-muted, #64748b);
}

.empty-state .empty-text {
    color: var(--text-muted, #64748b) !important;
}
.empty-icon {
    color: var(--text-muted, #64748b);
}

@media (max-width: 991px) {
    .standings-grid { grid-template-columns: 1fr; }
    .results-banner { padding: 3rem 1.5rem 6rem; border-radius: 25px; text-align: center; }
    .results-content { margin: -4rem 0.5rem 80px; }
    .search-bar-glass { padding: 1rem 1.5rem; margin-bottom: 1.5rem; }
}
@media (min-width: 992px) {
    .results-banner { padding: 4rem 3rem 8rem; }
    .results-content { margin: -6rem 3rem 0; }
}
</style>

<div class="results-banner">
    <div class="container-fluid">
        <h2 class="fw-800 mb-2" style="font-family: 'Sora', sans-serif; font-size: 2.2rem; text-shadow: 0 2px 12px rgba(0,0,0,0.15);">Election result standings</h2>
        <p class="small mb-0" style="color: rgba(255,255,255,0.8); font-weight: 600;">Live results and official tallies from the current election period.</p>
    </div>
</div>

<div class="results-content">
    
    <div class="search-bar-glass">
        <i class="bi bi-search"></i>
        <form method="GET" action="" id="electionForm" class="flex-grow-1">
            <select name="election_id" class="app-select" onchange="this.form.submit()">
                <option value="">Search or select an election period...</option>
                <?php foreach($past_elections as $election): ?>
                    <option value="<?php echo $election['id']; ?>" <?php echo (isset($_GET['election_id']) && $_GET['election_id'] == $election['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($election['title']); ?> (Ended <?php echo date('M d', strtotime($election['end_date'])); ?>)
                    </option>
                <?php endforeach; ?>
                <?php if($active_election): ?>
                    <option value="<?php echo $active_election['id']; ?>" <?php echo (isset($_GET['election_id']) && $_GET['election_id'] == $active_election['id']) ? 'selected' : ''; ?>>
                        [LIVE] <?php echo htmlspecialchars($active_election['title']); ?>
                    </option>
                <?php endif; ?>
            </select>
        </form>
    </div>

    <?php if($selected_election && !empty($winners_only)): ?>
        <div class="standings-grid">
            <?php foreach($winners_only as $position_name => $data): ?>
                <div class="position-card">
                    <span class="pos-label"><?php echo htmlspecialchars($position_name); ?></span>
                    <div class="result-card">
                        <?php foreach($data['winners'] as $winner): ?>
                            <div class="d-flex align-items-center gap-3 py-3 <?php echo count($data['winners']) > 1 ? 'border-bottom border-light' : ''; ?>" style="border-color: var(--border-soft) !important;">
                                <?php if(!empty($winner['photo']) && file_exists('../' . $winner['photo'])): ?>
                                    <img src="../<?php echo $winner['photo']; ?>" class="winner-avatar rounded-circle border" alt="Winner" style="width:50px; height:50px; object-fit: cover; border-color:#0f7f63 !important;">
                                <?php else: ?>
                                    <div class="winner-placeholder rounded-circle border d-flex align-items-center justify-content-center" style="width:50px; height:50px; border-color:#0f7f63 !important; font-size:1.2rem; background: rgba(15,127,99,0.12); color: #9ee3c7;"><i class="bi bi-person-fill"></i></div>
                                <?php endif; ?>
                                <div>
                                    <span class="winner-name d-block"><?php echo htmlspecialchars($winner['candidate_name']); ?></span>
                                    <span class="winner-meta">ID: <?php echo htmlspecialchars($winner['student_id']); ?></span>
                                </div>
                                <div class="vote-badge ms-auto" style="background: rgba(15,127,99,0.14); color: #10b981; font-weight: 700; border: 1px solid rgba(15,127,99,0.18); padding: 4px 10px; border-radius: 99px; font-size: 0.8rem;">
                                    <?php echo number_format($winner['vote_count']); ?> votes
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="mt-2 text-center">
                            <small class="fw-bold" style="color: var(--text-muted, #64748b);">Total Position Votes: <?php echo number_format($data['total_votes']); ?></small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php elseif(isset($_GET['election_id'])): ?>
        <div class="empty-state text-center py-5">
            <div class="empty-icon mb-3" style="font-size: 3rem; opacity: 0.3;"><i class="bi bi-bar-chart"></i></div>
            <div class="empty-text fw-bold">No winners found for this selection yet.</div>
        </div>
    <?php else: ?>
        <div class="empty-state text-center py-5">
            <div class="empty-icon mb-3" style="font-size: 3rem; opacity: 0.3;"><i class="bi bi-search"></i></div>
            <div class="empty-text fw-bold">Use the search bar above to view standings or past results.</div>
        </div>
    <?php endif; ?>

</div>



<?php include '../includes/footer.php'; ?>