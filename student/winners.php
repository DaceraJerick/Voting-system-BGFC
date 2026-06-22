<?php
// filename: student/winners.php
// location: student/winners.php

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

$show_live_fallback = false;
$live_results = [];

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
} else if (!empty($past_elections)) {
    // Default to latest past election
    header("Location: winners.php?election_id=" . $past_elections[0]['id']);
    exit();
} else if ($active_election) {
    // Show live fallback
    $show_live_fallback = true;
    
    $stmt = $pdo->prepare("
        SELECT 
            p.id as position_id, p.position_name, p.display_order,
            c.id as candidate_id, c.full_name as candidate_name, c.student_id, c.photo,
            COUNT(v.id) as vote_count
        FROM positions p
        LEFT JOIN candidates c ON c.position_id = p.id
        LEFT JOIN votes v ON v.candidate_id = c.id AND v.election_id = p.election_id
        WHERE p.election_id = ?
        GROUP BY p.id, c.id
        ORDER BY p.display_order, vote_count DESC
    ");
    $stmt->execute([$active_election['id']]);
    $results = $stmt->fetchAll();
    
    foreach($results as $row) {
        if(!isset($live_results[$row['position_name']])) {
            $live_results[$row['position_name']] = [];
        }
        $live_results[$row['position_name']][] = $row;
    }
}

include '../includes/header.php';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Sora:wght@700;800&display=swap');

/* ── Adaptive Winners Styles ── */
.winners-banner {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 60%, #a855f7 100%);
    color: white;
    padding: 3rem 2rem 7rem;
    border-radius: 30px;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 24px 60px rgba(99,102,241,0.35);
}
.winners-banner::before {
    content: ''; position: absolute; top: -100px; right: -100px;
    width: 350px; height: 350px; border-radius: 50%;
    background: rgba(255,255,255,0.08); pointer-events: none;
}
.winners-banner::after {
    content: ''; position: absolute; bottom: -80px; left: 30%;
    width: 220px; height: 220px; border-radius: 50%;
    background: rgba(255,255,255,0.05); pointer-events: none;
}
.winners-content { margin: -5rem 1.5rem 0; position: relative; z-index: 10; }
.winners-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem; }

/* ── Adaptive Selector ── */
.election-selector-glass {
    background: var(--panel-bg, rgba(255,255,255,0.95));
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid var(--border-soft, rgba(99,102,241,0.14));
    border-radius: 20px;
    padding: 1.25rem 2rem;
    margin-bottom: 2.5rem;
    display: flex; align-items: center; justify-content: space-between;
    box-shadow: 0 12px 40px rgba(0,0,0,0.08);
}
[data-theme="dark"] .election-selector-glass {
    background: rgba(13,15,27,0.85);
    border-color: rgba(99,102,241,0.16);
    box-shadow: 0 14px 40px rgba(0,0,0,0.24);
}
.election-selector-glass h3 { color: var(--text-high, #0f172a); font-family: 'Sora', sans-serif; font-size: 1.1rem; font-weight: 800; margin: 0; }
[data-theme="dark"] .election-selector-glass h3 { color: #e2e8f0; }
.election-selector-glass p { color: var(--text-muted, #64748b); font-size: 0.75rem; font-weight: 700; margin: 0; text-transform: uppercase; }

/* ── Adaptive Winner Card ── */
.winner-card-premium {
    background: var(--panel-bg, rgba(255,255,255,0.95));
    backdrop-filter: blur(10px);
    border: 1px solid var(--border-soft, rgba(99,102,241,0.14));
    border-radius: 28px;
    padding: 2.5rem 2rem;
    text-align: center; position: relative;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 10px 35px rgba(0,0,0,0.08);
}
[data-theme="dark"] .winner-card-premium {
    background: rgba(13,15,27,0.86);
    border-color: rgba(99,102,241,0.16);
    box-shadow: 0 10px 35px rgba(0,0,0,0.24);
}
.winner-card-premium:hover { transform: translateY(-10px); border-color: #6366f1; box-shadow: 0 16px 40px rgba(99,102,241,0.15); }
[data-theme="dark"] .winner-card-premium:hover { background: rgba(99,102,241,0.16); border-color: #6366f1; }

.pos-pill-premium {
    background: rgba(16,185,129,0.12); color: #059669;
    padding: 0.35rem 1rem; border-radius: 99px;
    font-size: 0.7rem; font-weight: 800; display: inline-block;
    margin-bottom: 1.5rem; border: 1px solid rgba(16,185,129,0.25);
}
[data-theme="dark"] .pos-pill-premium { background: rgba(34,197,94,0.18); color: #a7f3d0; border-color: rgba(34,197,94,0.25); }

.winner-frame {
    width: 110px; height: 110px; border-radius: 50%; margin: 0 auto 1.25rem;
    position: relative; padding: 5px;
    background: linear-gradient(135deg, #34d399, #0f766e);
}
.winner-img-premium { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 4px solid rgba(255,255,255,0.8); }
[data-theme="dark"] .winner-img-premium { border-color: rgba(255,255,255,0.12); }
.winner-crown { position: absolute; top: -12px; right: -5px; font-size: 1.5rem; transform: rotate(15deg); filter: drop-shadow(0 4px 6px rgba(0,0,0,0.16)); }

.winner-name-premium { font-family: 'Sora', sans-serif; font-size: 1.15rem; font-weight: 800; color: var(--text-high, #0f172a); display: block; margin-bottom: 0.25rem; }
[data-theme="dark"] .winner-name-premium { color: #e2e8f0; }
.winner-meta-premium { font-size: 0.8rem; color: var(--text-muted, #64748b); font-weight: 600; display: block; margin-bottom: 1.5rem; }

.winner-stats {
    margin: 0 -1rem;
    background: rgba(0,0,0,0.03);
    border-radius: 0 0 24px 24px;
    display: flex; justify-content: space-around; padding: 1rem 0;
}
[data-theme="dark"] .winner-stats { background: rgba(255,255,255,0.04); }
.stat-item { display: flex; flex-direction: column; }
.stat-val { font-family: 'Sora', sans-serif; font-size: 1.1rem; font-weight: 800; color: var(--text-high, #0f172a); }
[data-theme="dark"] .stat-val { color: #e2e8f0; }
.stat-lbl { font-size: 0.65rem; color: var(--text-muted, #64748b); font-weight: 700; text-transform: uppercase; }

.btn-change-glass { background: #10b981; color: white; border: none; border-radius: 12px; padding: 0.6rem 1.25rem; font-size: 0.8rem; font-weight: 800; transition: all 0.2s; box-shadow: 0 6px 22px rgba(16,185,129,0.22); }
.btn-change-glass:hover { transform: translateY(-2px); background: #0f766e; box-shadow: 0 8px 24px rgba(16,185,129,0.28); }

/* Fallback Card Styles */
.live-fallback-card {
    background: var(--panel-bg, rgba(255,255,255,0.95));
    border: 1px solid var(--border-soft, rgba(99,102,241,0.14));
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 12px 40px rgba(0,0,0,0.08);
}
[data-theme="dark"] .live-fallback-card { background: rgba(13,15,27,0.86); border-color: rgba(99,102,241,0.16); box-shadow: 0 14px 40px rgba(0,0,0,0.24); }
.live-candidate-row { border-bottom: 1px solid var(--border-soft, rgba(0,0,0,0.05)); }
[data-theme="dark"] .live-candidate-row { border-color: rgba(255,255,255,0.05) !important; }
.live-candidate-row:last-child { border-bottom: none !important; }

@media (max-width: 991px) {
    .winners-grid { grid-template-columns: 1fr; }
    .winners-banner { padding: 3rem 1.5rem 6rem; border-radius: 25px; text-align: center; }
    .winners-content { margin: -4rem 0.5rem 80px; }
    .election-selector-glass { padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; flex-direction: column; gap: 1rem; text-align: center; }
}
@media (min-width: 992px) {
    .winners-banner { padding: 4rem 3rem 8rem; }
    .winners-content { margin: -6rem 3rem 0; }
}
</style>

<div class="winners-banner">
    <div class="container-fluid">
        <h2 class="fw-800 mb-2" style="font-family: 'Sora', sans-serif; font-size: 2.2rem; text-shadow: 0 2px 12px rgba(0,0,0,0.15);">Hall of Winners</h2>
        <p class="small mb-0" style="color: rgba(255,255,255,0.8); font-weight: 600;">The elected leaders of our community through the years.</p>
    </div>
</div>

<div class="winners-content">

    <?php if ($selected_election): ?>
    <div class="election-selector-glass">
        <div class="election-info">
            <p>ELECTION PERIOD</p>
            <h3><?php echo htmlspecialchars($selected_election['title']); ?></h3>
        </div>
        <button class="btn-change-glass" data-bs-toggle="modal" data-bs-target="#electionModal">
            <i class="bi bi-calendar-event me-2"></i> CHANGE PERIOD
        </button>
    </div>

    <!-- Modal for Election Selector -->
    <div class="modal fade" id="electionModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content overflow-hidden border-0" style="background: var(--panel-strong, #ffffff); border-radius: 28px;">
                <div class="modal-header border-0 pb-0 p-4">
                    <h5 class="modal-title fw-800 text-success">Past Elections</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="list-group list-group-flush">
                        <?php foreach($past_elections as $election): ?>
                        <a href="winners.php?election_id=<?php echo $election['id']; ?>" class="list-group-item list-group-item-action border-0 py-3 px-3 d-flex justify-content-between align-items-center mb-2 <?php echo ($selected_election['id'] == $election['id']) ? 'bg-success-subtle fw-800 text-success' : ''; ?>" style="border-radius: 16px; background: transparent;">
                            <div>
                                <div class="mb-0 fw-bold" style="color: var(--text-high);"><?php echo htmlspecialchars($election['title']); ?></div>
                                <small style="color: var(--text-muted); font-size: 0.7rem;">Ended <?php echo date('M d, Y', strtotime($election['end_date'])); ?></small>
                            </div>
                            <?php if($selected_election['id'] == $election['id']): ?>
                                <i class="bi bi-check-circle-fill text-success"></i>
                            <?php else: ?>
                                <i class="bi bi-chevron-right small opacity-50 text-secondary"></i>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($winners_only)): ?>
        <div class="winners-grid">
            <?php foreach($winners_only as $position_name => $data): ?>
                <?php foreach($data['winners'] as $winner): ?>
                <div class="winner-card-premium">
                    <div class="winner-crown">👑</div>
                    <span class="pos-pill-premium"><?php echo htmlspecialchars($position_name); ?></span>
                    
                    <div class="winner-frame">
                        <?php if(!empty($winner['photo']) && file_exists('../' . $winner['photo'])): ?>
                            <img src="../<?php echo $winner['photo']; ?>" class="winner-img-premium" alt="Winner">
                        <?php else: ?>
                            <div class="winner-img-premium d-flex align-items-center justify-content-center" style="background: rgba(16,185,129,0.12);">
                                <i class="bi bi-person-fill" style="color: #10b981; font-size: 3rem;"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <span class="winner-name-premium"><?php echo htmlspecialchars($winner['candidate_name']); ?></span>
                    <span class="winner-meta-premium">Student ID: <?php echo htmlspecialchars($winner['student_id']); ?></span>
                    
                    <div class="winner-stats">
                        <div class="stat-item">
                            <span class="stat-val"><?php echo number_format($winner['vote_count']); ?></span>
                            <span class="stat-lbl">Votes Received</span>
                        </div>
                        <div class="stat-item">
                            <?php $perc = $data['total_votes'] > 0 ? round(($winner['vote_count'] / $data['total_votes']) * 100, 1) : 0; ?>
                            <span class="stat-val"><?php echo $perc; ?>%</span>
                            <span class="stat-lbl">Final Margin</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state text-center py-5">
            <div class="empty-icon text-warning mb-3" style="font-size: 3rem; opacity: 0.5;"><i class="bi bi-trophy"></i></div>
            <div class="empty-text fw-bold" style="color: var(--text-muted);">No winners declared for this election.</div>
        </div>
    <?php endif; ?>

    <?php elseif($show_live_fallback): ?>
        <!-- LIVE FALLBACK: No past elections, but there is an active election -->
        <div class="text-center mb-5 mt-4">
            <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-3" style="background: rgba(99,102,241,0.15); color: #6366f1; border: 1px solid rgba(99,102,241,0.3); font-weight: 800; font-size: 0.8rem;">
                <i class="bi bi-record-circle-fill text-primary" style="animation: pulse 2s infinite;"></i> LIVE STANDINGS
            </div>
            <h3 class="fw-800" style="font-family: 'Sora', sans-serif; color: var(--text-high);">Currently Leading</h3>
            <p style="color: var(--text-muted); max-width: 500px; margin: 0 auto;">There are no past winners in the Hall of Fame yet. Below are the live standings of the current election.</p>
        </div>

        <div class="standings-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr)); gap: 1.5rem;">
            <?php foreach($live_results as $position_name => $candidates): ?>
                <div class="position-card">
                    <span class="d-block mb-3 fw-bold" style="font-family: 'Sora', sans-serif; font-size: 1.1rem; color: var(--text-high);"><?php echo htmlspecialchars($position_name); ?></span>
                    <div class="live-fallback-card">
                        <?php foreach($candidates as $candidate): ?>
                            <div class="d-flex align-items-center gap-3 py-3 live-candidate-row">
                                <?php if(!empty($candidate['photo']) && file_exists('../' . $candidate['photo'])): ?>
                                    <img src="../<?php echo $candidate['photo']; ?>" class="rounded-circle border" style="width:45px;height:45px;object-fit:cover; border-color: rgba(99,102,241,0.4) !important;">
                                <?php else: ?>
                                    <div class="rounded-circle d-flex align-items-center justify-content-center border" style="width:45px;height:45px; border-color: rgba(99,102,241,0.4) !important; background: rgba(99,102,241,0.1);">
                                        <i class="bi bi-person text-primary"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="flex-grow-1">
                                    <span class="d-block fw-bold" style="color: var(--text-high);"><?php echo htmlspecialchars($candidate['candidate_name']); ?></span>
                                    <small style="color: var(--text-muted); font-size: 0.75rem;">ID: <?php echo htmlspecialchars($candidate['student_id']); ?></small>
                                </div>
                                <div class="badge rounded-pill" style="background: rgba(99,102,241,0.15); color: #6366f1; padding: 6px 12px; font-weight: 700; border: 1px solid rgba(99,102,241,0.2);">
                                    <?php echo number_format($candidate['vote_count']); ?> votes
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
    <?php else: ?>
    <!-- No past elections and no active elections -->
    <div class="empty-state text-center py-5 mt-4">
        <div class="empty-icon mb-3" style="font-size: 3.5rem; opacity: 0.3; color: var(--text-muted);"><i class="bi bi-calendar-x"></i></div>
        <h4 class="fw-bold" style="color: var(--text-high);">No Elections Found</h4>
        <div class="empty-text" style="color: var(--text-muted);">The Hall of Winners is currently empty. Check back after an election has concluded!</div>
    </div>
    <?php endif; ?>

</div>

<?php include '../includes/footer.php'; ?>