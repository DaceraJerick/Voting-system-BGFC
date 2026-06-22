<?php
// filename: admin/winners.php
// location: admin/winners.php

require_once '../config/database.php';
require_once '../includes/auth_check.php';

if($_SESSION['user_type'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

// Get all elections for the dropdown
$all_elections = $pdo->query("
    SELECT id, title, start_date, end_date, is_active 
    FROM elections 
    ORDER BY created_at DESC
")->fetchAll();

$upcoming_elections = [];
$active_elections = [];
$ended_elections = [];

foreach($all_elections as $el) {
    $now = time();
    $start = strtotime($el['start_date']);
    $end   = strtotime($el['end_date']);
    if($now < $start) {
        // Not started yet
        $upcoming_elections[] = $el;
    } elseif($now >= $start && $now <= $end) {
        // Currently running
        $active_elections[] = $el;
    } else {
        // Past end date
        $ended_elections[] = $el;
    }
}

$selected_election = null;
$winners_only = [];
$total_stats = ['voters' => 0, 'positions' => 0, 'candidates' => 0];

if(isset($_GET['election_id'])) {
    $election_id = $_GET['election_id'];
    $stmt = $pdo->prepare("SELECT * FROM elections WHERE id = ?");
    $stmt->execute([$election_id]);
    $selected_election = $stmt->fetch();

    // Only calculate winners if the election has actually ended
    $election_has_ended = $selected_election && strtotime($selected_election['end_date']) < time();
    $election_not_started = $selected_election && strtotime($selected_election['start_date']) > time();

    if($selected_election && $election_has_ended) {
        $election_results = [];
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students");
        $stmt->execute();
        $total_stats['voters'] = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM positions WHERE election_id = ?");
        $stmt->execute([$election_id]);
        $total_stats['positions'] = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM candidates c 
            JOIN positions p ON c.position_id = p.id 
            WHERE p.election_id = ?
        ");
        $stmt->execute([$election_id]);
        $total_stats['candidates'] = $stmt->fetchColumn();
        
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
                COUNT(v.id) as vote_count
            FROM positions p
            LEFT JOIN candidates c ON c.position_id = p.id
            LEFT JOIN votes v ON v.candidate_id = c.id AND v.election_id = p.election_id
            WHERE p.election_id = ?
            GROUP BY p.id, c.id
            ORDER BY p.display_order, vote_count DESC
        ");
        $stmt->execute([$election_id]);
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
            $second_highest = 0;
            
            if(count($position['candidates']) > 0) {
                $max_votes = $position['candidates'][0]['vote_count'];
                foreach($position['candidates'] as $candidate) {
                    if($candidate['vote_count'] == $max_votes && $max_votes > 0) {
                        $winners[] = $candidate;
                    } elseif ($candidate['vote_count'] < $max_votes) {
                        if ($second_highest == 0) {
                            $second_highest = $candidate['vote_count'];
                        }
                    }
                }
            }
            
            $margin = count($winners) > 1 ? 0 : ($max_votes - $second_highest);
            
            $winners_only[$position_name] = [
                'winners' => $winners,
                'total_votes' => $position['total_votes'],
                'max_votes' => $max_votes,
                'margin' => $margin,
                'is_tie' => count($winners) > 1
            ];
        }
    }
}

include '../includes/header.php';
?>

<style>
.winners-container {
    max-width: 1400px;
    margin: 0 auto;
}

/* Header Section */
.winners-header {
    background: linear-gradient(135deg, #2c3e50, #3498db);
    color: white;
    padding: 50px;
    border-radius: 30px;
    margin-bottom: 30px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
}

.winners-header::before {
    content: '🏆';
    position: absolute;
    top: -50px;
    right: -50px;
    font-size: 300px;
    opacity: 0.1;
    transform: rotate(15deg);
}

/* Election Selector */
.election-selector {
    background: white;
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    border: 1px solid #e9ecef;
}

.selector-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Election Cards */
.election-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.election-card {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border-radius: 16px;
    padding: 20px;
    transition: all 0.3s;
    cursor: pointer;
    border: 2px solid transparent;
    position: relative;
    overflow: hidden;
}

.election-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(52, 152, 219, 0.2);
}

.election-card.selected {
    border: 2px solid #3498db;
    background: linear-gradient(135deg, #f0f7ff, #e3f2fd);
}

.election-card.active {
    border-left: 5px solid #f1c40f;
}

.election-card.ended {
    border-left: 5px solid #2ecc71;
}

.election-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 10px;
}

.election-date {
    font-size: 0.85rem;
    color: #7f8c8d;
    display: flex;
    align-items: center;
    gap: 5px;
    margin-bottom: 15px;
}

.election-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    padding: 5px 12px;
    border-radius: 50px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
}

.badge-ended {
    background: #2ecc71;
    color: white;
}

.badge-active {
    background: #f1c40f;
    color: #2c3e50;
}

/* Winners Display */
.winners-showcase {
    background: white;
    border-radius: 30px;
    padding: 40px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.08);
    margin-top: 30px;
}

.election-info-bar {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.election-name {
    font-size: 2rem;
    font-weight: 800;
    background: linear-gradient(135deg, #2c3e50, #3498db);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 5px;
}

.election-meta {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.meta-item {
    background: white;
    padding: 8px 20px;
    border-radius: 50px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.03);
}

/* Winner Cards */
.position-winner-section {
    margin-bottom: 40px;
    padding: 30px;
    background: linear-gradient(135deg, #ffffff, #f8f9fa);
    border-radius: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.03);
    border: 1px solid #e9ecef;
}

.position-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 30px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e9ecef;
}

.position-icon {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #3498db, #2980b9);
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
}

.position-name {
    font-size: 1.5rem;
    font-weight: 700;
    color: #2c3e50;
    flex: 1;
}

.winner-badge-large {
    background: linear-gradient(135deg, #f1c40f, #f39c12);
    color: #2c3e50;
    padding: 10px 25px;
    border-radius: 50px;
    font-weight: 800;
    font-size: 1rem;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 5px 15px rgba(241, 196, 15, 0.3);
}

.tie-badge-large {
    background: linear-gradient(135deg, #95a5a6, #7f8c8d);
    color: white;
    padding: 10px 25px;
    border-radius: 50px;
    font-weight: 800;
    font-size: 1rem;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.winner-profile {
    display: flex;
    align-items: center;
    gap: 40px;
    flex-wrap: wrap;
}

.winner-photo {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 5px solid #f1c40f;
    box-shadow: 0 15px 30px rgba(241, 196, 15, 0.2);
}

.winner-photo-placeholder {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: linear-gradient(135deg, #e9ecef, #dee2e6);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 60px;
    color: #adb5bd;
    border: 5px solid #f1c40f;
}

.winner-info {
    flex: 1;
}

.winner-name {
    font-size: 2rem;
    font-weight: 800;
    color: #2c3e50;
    margin-bottom: 10px;
}

.winner-id {
    font-size: 1rem;
    color: #7f8c8d;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
    background: #f8f9fa;
    padding: 8px 20px;
    border-radius: 50px;
    display: inline-flex;
}

.winner-stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-top: 20px;
}

.stat-box {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 12px;
    text-align: center;
}

.stat-box .number {
    font-size: 1.5rem;
    font-weight: 800;
    color: #3498db;
}

.stat-box .label {
    font-size: 0.8rem;
    color: #7f8c8d;
    text-transform: uppercase;
}

.tie-winners-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 25px;
    margin-top: 20px;
}

.tie-candidate {
    background: #f8f9fa;
    border-radius: 16px;
    padding: 25px;
    text-align: center;
    border: 1px solid #e9ecef;
}

.tie-candidate .winner-photo {
    width: 100px;
    height: 100px;
    margin: 0 auto 15px;
}

.tie-candidate .winner-name {
    font-size: 1.3rem;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 15px;
    margin-top: 30px;
    flex-wrap: wrap;
    justify-content: center;
}

.btn-print {
    background: linear-gradient(135deg, #2c3e50, #34495e);
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 50px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
}

.btn-print:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(44, 62, 80, 0.3);
    color: white;
}

.btn-export {
    background: linear-gradient(135deg, #27ae60, #2ecc71);
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 50px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
}

.btn-export:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(46, 204, 113, 0.3);
    color: white;
}

/* No Results */
.no-results {
    text-align: center;
    padding: 60px 20px;
    background: #f8f9fa;
    border-radius: 20px;
}

.no-results i {
    font-size: 80px;
    color: #dee2e6;
    margin-bottom: 20px;
}

/* Responsive */
@media (max-width: 768px) {
    .winners-header {
        padding: 30px 20px;
    }
    
    .winners-showcase {
        padding: 20px;
    }
    
    .winner-profile {
        flex-direction: column;
        text-align: center;
    }
    
    .winner-stats-grid {
        grid-template-columns: 1fr;
    }
    
    .position-header {
        flex-direction: column;
        text-align: center;
    }
    
    .election-info-bar {
        flex-direction: column;
        text-align: center;
    }
}

/* =========================================
   DARK MODE OVERRIDES
   ========================================= */
[data-theme="dark"] .election-selector { background: rgba(20,22,35,0.85); border-color: rgba(99,102,241,0.15); box-shadow: 0 10px 30px rgba(0,0,0,0.25); }
[data-theme="dark"] .selector-title { color: #e2e8f0; }
[data-theme="dark"] .election-card { background: rgba(30,32,48,0.8); border-color: transparent; }
[data-theme="dark"] .election-card:hover { box-shadow: 0 15px 30px rgba(0,0,0,0.4); }
[data-theme="dark"] .election-title { color: #e2e8f0; }
[data-theme="dark"] .election-card.selected { border-color: #3498db; background: rgba(52,152,219,0.1); }
[data-theme="dark"] .winners-showcase { background: rgba(20,22,35,0.85); box-shadow: 0 20px 40px rgba(0,0,0,0.25); }
[data-theme="dark"] .election-info-bar { background: rgba(30,32,48,0.8); }
[data-theme="dark"] .meta-item { background: rgba(20,22,35,0.9); color: #e2e8f0; border: 1px solid rgba(255,255,255,0.05); }
[data-theme="dark"] .position-winner-section { background: rgba(30,32,48,0.8); border-color: rgba(99,102,241,0.15); }
[data-theme="dark"] .position-header { border-bottom-color: rgba(255,255,255,0.1); }
[data-theme="dark"] .position-name { color: #e2e8f0; }
[data-theme="dark"] .winner-name { color: #e2e8f0; }
[data-theme="dark"] .winner-id { background: rgba(20,22,35,0.9); color: #94a3b8; }
[data-theme="dark"] .stat-box { background: rgba(20,22,35,0.9); }
[data-theme="dark"] .stat-box .label { color: #94a3b8; }
[data-theme="dark"] .tie-candidate { background: rgba(20,22,35,0.9); border-color: rgba(255,255,255,0.05); }
[data-theme="dark"] .no-results { background: rgba(30,32,48,0.8); color: #94a3b8; }
[data-theme="dark"] .no-results i { color: #475569; }
</style>

<div class="winners-container">
    <!-- Header -->
    <div class="winners-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-4 fw-bold mb-3">
                    <i class="bi bi-trophy-fill"></i> Election Winners
                </h1>
                <p class="lead mb-0">
                    View and manage winners from completed elections. 
                    Winners are determined by the highest number of votes.
                </p>
            </div>
            <div class="col-md-4 text-md-end">
                <span class="badge bg-white text-dark p-3">
                    <i class="bi bi-calendar-check"></i> 
                    <?php echo count($ended_elections); ?> Completed Elections
                </span>
            </div>
        </div>
    </div>

    <!-- Election Selector -->
    <div class="election-selector">
        <div class="selector-title">
            <i class="bi bi-calendar3"></i>
            Select an Election
        </div>

        <!-- Upcoming Elections (not started yet) -->
        <?php if(count($upcoming_elections) > 0): ?>
            <div class="mb-4">
                <h6 class="fw-bold mb-3" style="color: #6366f1;">
                    <i class="bi bi-calendar-plus-fill"></i> Upcoming Elections
                </h6>
                <div class="election-grid">
                    <?php foreach($upcoming_elections as $election): ?>
                        <div class="election-card" style="border-left: 5px solid #6366f1; cursor: default; opacity: 0.7;">
                            <span class="election-badge" style="background:#6366f1; color:white;">UPCOMING</span>
                            <div class="election-title"><?php echo htmlspecialchars($election['title']); ?></div>
                            <div class="election-date">
                                <i class="bi bi-calendar-event"></i>
                                Starts: <?php echo date('M d, Y h:i A', strtotime($election['start_date'])); ?>
                            </div>
                            <div class="text-muted small">
                                <i class="bi bi-clock"></i> Not started yet
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Active Elections (if any) -->
        <?php if(count($active_elections) > 0): ?>
            <div class="mb-4">
                <h6 class="fw-bold mb-3" style="color: #f39c12;">
                    <i class="bi bi-lightning-charge-fill"></i> Active Elections
                </h6>
                <div class="election-grid">
                    <?php foreach($active_elections as $election): ?>
                        <div class="election-card active" onclick="window.location.href='?election_id=<?php echo $election['id']; ?>'">
                            <span class="election-badge badge-active">ACTIVE</span>
                            <div class="election-title"><?php echo htmlspecialchars($election['title']); ?></div>
                            <div class="election-date">
                                <i class="bi bi-calendar"></i>
                                Ends: <?php echo date('M d, Y h:i A', strtotime($election['end_date'])); ?>
                            </div>
                            <div class="text-muted small">
                                <i class="bi bi-hourglass-split"></i> In Progress
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Ended Elections -->
        <h6 class="fw-bold mb-3" style="color: #27ae60;">
            <i class="bi bi-check-circle-fill"></i> Completed Elections
        </h6>
        
        <?php if(count($ended_elections) > 0): ?>
            <div class="election-grid">
                <?php foreach($ended_elections as $election): ?>
                    <div class="election-card ended <?php echo ($selected_election && $selected_election['id'] == $election['id']) ? 'selected' : ''; ?>" 
                         onclick="window.location.href='?election_id=<?php echo $election['id']; ?>'">
                        <span class="election-badge badge-ended">ENDED</span>
                        <div class="election-title"><?php echo htmlspecialchars($election['title']); ?></div>
                        <div class="election-date">
                            <i class="bi bi-calendar-check"></i>
                            Ended: <?php echo date('M d, Y', strtotime($election['end_date'])); ?>
                        </div>
                        <div class="mt-2">
                            <span class="badge bg-success me-1">
                                <i class="bi bi-trophy"></i> Winners Available
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> No completed elections yet.
            </div>
        <?php endif; ?>
    </div>

    <!-- Winners Display -->
    <?php if($selected_election): ?>
        <div class="winners-showcase">

        <?php if($election_not_started): ?>
            <!-- Election hasn't started yet -->
            <div class="no-results">
                <i class="bi bi-clock" style="font-size: 80px; color: #6366f1; opacity: 0.5;"></i>
                <h4 class="mt-4 fw-bold">Election Has Not Started Yet</h4>
                <p class="text-muted">Winners will be available once the election ends on <strong><?php echo date('F d, Y h:i A', strtotime($selected_election['end_date'])); ?></strong>.</p>
            </div>
        <?php elseif(!$election_has_ended): ?>
            <!-- Election is currently active -->
            <div class="no-results">
                <i class="bi bi-hourglass-split" style="font-size: 80px; color: #f39c12; opacity: 0.6;"></i>
                <h4 class="mt-4 fw-bold">Election Is Currently In Progress</h4>
                <p class="text-muted">Winners will be declared after the election ends on <strong><?php echo date('F d, Y h:i A', strtotime($selected_election['end_date'])); ?></strong>.</p>
            </div>
        <?php else: ?>
            <!-- Election Info Bar -->
            <div class="election-info-bar">
                <div>
                    <div class="election-name">
                        <?php echo htmlspecialchars($selected_election['title']); ?>
                    </div>
                    <div class="election-meta">
                        <span class="meta-item">
                            <i class="bi bi-calendar-check text-primary"></i>
                            Ended: <?php echo date('F d, Y', strtotime($selected_election['end_date'])); ?>
                        </span>
                        <span class="meta-item">
                            <i class="bi bi-people text-success"></i>
                            <?php echo $total_stats['voters'] ?? 0; ?> Voters
                        </span>
                        <span class="meta-item">
                            <i class="bi bi-tag text-info"></i>
                            <?php echo $total_stats['positions'] ?? 0; ?> Positions
                        </span>
                    </div>
                </div>
                <div>
                    <span class="badge bg-warning text-dark p-3 fs-6">
                        <i class="bi bi-trophy-fill"></i> 
                        <?php echo count($winners_only); ?> Winners Declared
                    </span>
                </div>
            </div>

            <!-- Winners by Position -->
            <?php if(!empty($winners_only)): ?>
                <?php foreach($winners_only as $position_name => $data): ?>
                    <div class="position-winner-section">
                        <div class="position-header">
                            <div class="position-icon">
                                <i class="bi bi-person-badge"></i>
                            </div>
                            <div class="position-name">
                                <?php echo htmlspecialchars($position_name); ?>
                            </div>
                            <?php if($data['is_tie']): ?>
                                <span class="tie-badge-large">
                                    <i class="bi bi-people-fill"></i> TIE
                                </span>
                            <?php else: ?>
                                <span class="winner-badge-large">
                                    <i class="bi bi-crown-fill"></i> WINNER
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if($data['is_tie']): ?>
                            <!-- Multiple Winners (Tie) -->
                            <div class="tie-winners-grid">
                                <?php foreach($data['winners'] as $winner): ?>
                                    <div class="tie-candidate">
                                        <?php if(!empty($winner['photo']) && file_exists('../' . $winner['photo'])): ?>
                                            <img src="../<?php echo $winner['photo']; ?>" 
                                                 class="winner-photo" 
                                                 alt="<?php echo htmlspecialchars($winner['candidate_name']); ?>">
                                        <?php else: ?>
                                            <div class="winner-photo-placeholder mx-auto">
                                                <i class="bi bi-person-circle"></i>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <h4 class="winner-name mt-3">
                                            <?php echo htmlspecialchars($winner['candidate_name']); ?>
                                        </h4>
                                        <div class="winner-id justify-content-center">
                                            <i class="bi bi-person-badge"></i>
                                            <?php echo htmlspecialchars($winner['student_id']); ?>
                                        </div>
                                        
                                        <div class="winner-stats-grid mt-3">
                                            <div class="stat-box">
                                                <div class="number"><?php echo $winner['vote_count']; ?></div>
                                                <div class="label">Votes</div>
                                            </div>
                                            <div class="stat-box">
                                                <?php $percentage = $data['total_votes'] > 0 ? round(($winner['vote_count'] / $data['total_votes']) * 100, 1) : 0; ?>
                                                <div class="number"><?php echo $percentage; ?>%</div>
                                                <div class="label">Share</div>
                                            </div>
                                            <div class="stat-box">
                                                <div class="number">#<?php echo $data['total_votes']; ?></div>
                                                <div class="label">Total</div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <!-- Single Winner -->
                            <?php $winner = !empty($data['winners']) ? $data['winners'][0] : null; ?>
                            <?php if($winner): ?>
                            <div class="winner-profile">
                                <?php if(!empty($winner['photo']) && file_exists('../' . $winner['photo'])): ?>
                                    <img src="../<?php echo $winner['photo']; ?>" 
                                         class="winner-photo" 
                                         alt="<?php echo htmlspecialchars($winner['candidate_name']); ?>">
                                <?php else: ?>
                                    <div class="winner-photo-placeholder">
                                        <i class="bi bi-person-circle"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="winner-info">
                                    <div class="winner-name">
                                        <?php echo htmlspecialchars($winner['candidate_name'] ?? ''); ?>
                                    </div>
                                    <div class="winner-id">
                                        <i class="bi bi-person-badge"></i>
                                        <?php echo htmlspecialchars($winner['student_id'] ?? ''); ?>
                                    </div>
                                    
                                    <div class="winner-stats-grid">
                                        <div class="stat-box">
                                            <div class="number"><?php echo $winner['vote_count'] ?? 0; ?></div>
                                            <div class="label">Votes Received</div>
                                        </div>
                                        <div class="stat-box">
                                            <?php $percentage = $data['total_votes'] > 0 ? round((($winner['vote_count'] ?? 0) / $data['total_votes']) * 100, 1) : 0; ?>
                                            <div class="number"><?php echo $percentage; ?>%</div>
                                            <div class="label">Vote Share</div>
                                        </div>
                                        <div class="stat-box">
                                            <div class="number"><?php echo $data['margin']; ?></div>
                                            <div class="label">Margin</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="no-results">
                                <i class="bi bi-person-x"></i>
                                <p class="mt-3 text-muted">No votes were cast for this position.</p>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <!-- Position Summary -->
                        <div class="mt-4 pt-3 border-top">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <small class="text-muted d-block">Total Votes Cast</small>
                                    <strong><?php echo $data['total_votes']; ?> votes</strong>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted d-block">Winning Votes</small>
                                    <strong class="text-success"><?php echo $data['max_votes']; ?> votes</strong>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted d-block">Voter Turnout</small>
                                    <strong>
                                        <?php 
                                        $position_turnout = $total_stats['voters'] > 0 ? round(($data['total_votes'] / $total_stats['voters']) * 100, 1) : 0;
                                        echo $position_turnout; ?>%
                                    </strong>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <button onclick="window.print()" class="btn-print">
                        <i class="bi bi-printer"></i> Print Winners
                    </button>
                    <a href="export_winners.php?election_id=<?php echo $selected_election['id']; ?>" class="btn-export">
                        <i class="bi bi-download"></i> Export as CSV
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; // end else (election has ended) ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Auto-refresh winners display (optional)
<?php if($selected_election && strtotime($selected_election['end_date']) < time()): ?>
    // Election is ended, no auto-refresh needed
<?php elseif($selected_election): ?>
    // For active elections, refresh every 30 seconds
    setTimeout(function() {
        location.reload();
    }, 30000);
<?php endif; ?>

// Print optimization
window.onbeforeprint = function() {
    const style = document.createElement('style');
    style.innerHTML = `
        .navbar, .status-bar, .election-selector, .action-buttons, footer {
            display: none !important;
        }
        .winners-header {
            background: #2c3e50 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .winner-badge-large, .tie-badge-large {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    `;
    document.head.appendChild(style);
};
</script>

<?php include '../includes/footer.php'; ?>