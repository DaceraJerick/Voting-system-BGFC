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
}

include '../includes/header.php';
?>

<style>
/* ── Soft Glass Winners Styles ── */
.winners-banner { background: #6366f1; color: white; padding: 3rem 2rem 7rem; border-radius: 30px; margin-bottom: 2rem; position: relative; overflow: hidden; }
.winners-banner::after { content: ''; position: absolute; width: 300px; height: 300px; background: rgba(255,255,255,0.05); border-radius: 50%; top: -100px; right: -100px; }
.winners-content { margin: -5rem 1.5rem 0; position: relative; z-index: 10; }
.winners-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem; }
.election-selector-glass { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.5); border-radius: 20px; padding: 1.25rem 2rem; margin-bottom: 2.5rem; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 10px 30px rgba(0,0,0,0.03); }
.election-selector-glass h3 { color: #1e293b; font-size: 1.1rem; font-weight: 800; margin: 0; }
.election-selector-glass p { color: #64748b; font-size: 0.75rem; font-weight: 700; margin: 0; text-transform: uppercase; }
.btn-change-glass { background: #6366f1; color: white; border: none; border-radius: 12px; padding: 0.6rem 1.25rem; font-size: 0.8rem; font-weight: 800; transition: all 0.2s; box-shadow: 0 4px 15px rgba(99, 102, 241, 0.2); }
.btn-change-glass:hover { transform: translateY(-2px); background: #4f46e5; box-shadow: 0 6px 20px rgba(99, 102, 241, 0.3); }
.winner-card-premium { background: rgba(255, 255, 255, 0.5); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.4); border-radius: 28px; padding: 2.5rem 2rem; text-align: center; position: relative; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 4px 25px rgba(0,0,0,0.02); }
.winner-card-premium:hover { transform: translateY(-10px); background: rgba(255, 255, 255, 0.8); border-color: #6366f1; }
.pos-pill-premium { background: #fdf6b2; color: #723b13; padding: 0.35rem 1rem; border-radius: 99px; font-size: 0.7rem; font-weight: 800; display: inline-block; margin-bottom: 1.5rem; border: 1px solid #fce96a; }
.winner-frame { width: 110px; height: 110px; border-radius: 50%; margin: 0 auto 1.25rem; position: relative; padding: 5px; background: linear-gradient(135deg, #fbbf24, #f59e0b); }
.winner-img-premium { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 4px solid white; }
.winner-crown { position: absolute; top: -12px; right: -5px; font-size: 1.5rem; transform: rotate(15deg); filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1)); }
.winner-name-premium { font-size: 1.15rem; font-weight: 800; color: #1e1b4b; display: block; margin-bottom: 0.25rem; }
.winner-meta-premium { font-size: 0.8rem; color: #94a3b8; font-weight: 600; display: block; margin-bottom: 1.5rem; }

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
        <h2 class="fw-800 mb-2" style="font-size: 2.2rem;">Hall of Winners</h2>
        <p class="small opacity-75 mb-0">The elected leaders of our community through the years.</p>
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
            <div class="modal-content overflow-hidden border-0" style="border-radius: 28px;">
                <div class="modal-header border-0 pb-0 p-4">
                    <h5 class="modal-title fw-800 text-indigo-900">Past Elections</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="list-group list-group-flush">
                        <?php foreach($past_elections as $election): ?>
                        <a href="winners.php?election_id=<?php echo $election['id']; ?>" class="list-group-item list-group-item-action border-0 py-3 px-3 d-flex justify-content-between align-items-center mb-2 <?php echo ($selected_election['id'] == $election['id']) ? 'bg-indigo-50 fw-800 text-primary' : ''; ?>" style="border-radius: 16px;">
                            <div>
                                <div class="mb-0"><?php echo htmlspecialchars($election['title']); ?></div>
                                <small class="text-muted" style="font-size: 0.7rem;">Ended <?php echo date('M d, Y', strtotime($election['end_date'])); ?></small>
                            </div>
                            <?php if($selected_election['id'] == $election['id']): ?>
                                <i class="bi bi-check-circle-fill text-primary"></i>
                            <?php else: ?>
                                <i class="bi bi-chevron-right small opacity-50"></i>
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
                            <div class="winner-img-premium d-flex align-items-center justify-content-center bg-indigo-50">
                                <i class="bi bi-person-fill text-indigo-300" style="font-size: 3rem;"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <span class="winner-name-premium"><?php echo htmlspecialchars($winner['candidate_name']); ?></span>
                    <span class="winner-meta-premium">Student ID: <?php echo htmlspecialchars($winner['student_id']); ?></span>
                    
                    <div class="winner-stats" style="margin: 0 -1rem;">
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
        <div class="empty-state">
            <div class="empty-icon text-gold-400"><i class="bi bi-trophy"></i></div>
            <div class="empty-text">No winners declared for this election.</div>
        </div>
    <?php endif; ?>

    <?php else: ?>
    <!-- No past elections at all -->
    <div class="empty-state">
        <div class="empty-icon"><i class="bi bi-calendar-x"></i></div>
        <div class="empty-text">No past elections found in the system.</div>
    </div>
    <?php endif; ?>

</div>



<?php include '../includes/footer.php'; ?>