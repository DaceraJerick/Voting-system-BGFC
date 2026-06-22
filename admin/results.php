<?php
// filename: admin/results.php
// location: admin/results.php

require_once '../config/database.php';
require_once '../includes/auth_check.php';

if($_SESSION['user_type'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

$elections = $pdo->query("
    SELECT *,
    CASE
        WHEN is_active=1 AND NOW() BETWEEN start_date AND end_date THEN 'active'
        WHEN NOW() > end_date THEN 'closed'
        WHEN NOW() < start_date THEN 'upcoming'
        ELSE COALESCE(status,'pending')
    END as display_status
    FROM elections ORDER BY created_at DESC
")->fetchAll();

$selected_election = null;
$election_details  = null;
$grouped_results   = [];
$eligible_count = $registered_count = $voted_count = 0;

if(isset($_GET['election_id'])) {
    $selected_election = $_GET['election_id'];
    $stmt = $pdo->prepare("SELECT * FROM elections WHERE id = ?");
    $stmt->execute([$selected_election]);
    $election_details = $stmt->fetch();

    if($election_details) {
        $eligible_count   = $pdo->query("SELECT COUNT(*) FROM eligible_ids")->fetchColumn();
        $registered_count = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT student_id) FROM votes WHERE election_id=?");
        $stmt->execute([$selected_election]);
        $voted_count = $stmt->fetchColumn();

        $checkImg = $pdo->query("SHOW COLUMNS FROM candidates LIKE 'image'");
        $imageField = $checkImg->rowCount() > 0 ? 'c.image' : 'c.photo';

        $stmt = $pdo->prepare("
            SELECT p.id as position_id, p.position_name, p.display_order,
                   c.id as candidate_id, c.full_name as candidate_name, c.student_id,
                   {$imageField} as candidate_image, c.photo as candidate_photo,
                   COUNT(v.id) as vote_count
            FROM positions p
            LEFT JOIN candidates c ON c.position_id=p.id
            LEFT JOIN votes v ON v.candidate_id=c.id AND v.election_id=p.election_id
            WHERE p.election_id=?
            GROUP BY p.id, c.id
            ORDER BY p.display_order, vote_count DESC
        ");
        $stmt->execute([$selected_election]);

        foreach($stmt->fetchAll() as $row) {
            $pn = $row['position_name'];
            if(!isset($grouped_results[$pn]))
                $grouped_results[$pn] = ['position_id'=>$row['position_id'],'candidates'=>[],'total_votes'=>0];
            $grouped_results[$pn]['candidates'][] = $row;
            $grouped_results[$pn]['total_votes']  += $row['vote_count'];
        }

        foreach($grouped_results as &$pos) {
            $max = 0; $winners = [];
            foreach($pos['candidates'] as $c) {
                if($c['vote_count'] > $max)       { $max=$c['vote_count']; $winners=[$c]; }
                elseif($c['vote_count']==$max && $max>0) { $winners[]=$c; }
            }
            $pos['winners']   = $winners;
            $pos['max_votes'] = $max;
            $pos['is_tie']    = count($winners) > 1;
        }
    }
}

$turnout = $registered_count > 0 ? round(($voted_count/$registered_count)*100,1) : 0;

include '../includes/header.php';
?>

<style>
.results-container {
    max-width: 1400px;
    margin: 0 auto;
}

/* Header Section */
.results-header {
    background: linear-gradient(135deg, #1e3c72, #2a5298);
    color: white;
    padding: 50px;
    border-radius: 30px;
    margin-bottom: 30px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
}

.results-header::before {
    content: '📊';
    position: absolute;
    top: -50px;
    right: -50px;
    font-size: 300px;
    opacity: 0.1;
    transform: rotate(15deg);
}

/* Selector Panel */
.selector-panel {
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

.election-select {
    padding: 15px 25px;
    border-radius: 12px;
    border: 2px solid #e9ecef;
    font-size: 1rem;
    font-weight: 500;
    color: #2c3e50;
    width: 100%;
    transition: all 0.3s;
}

.election-select:focus {
    border-color: #3498db;
    box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
    outline: none;
}

/* Stats Cards */
.stats-strip {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 25px;
    border-radius: 20px;
    box-shadow: 0 10px 20px rgba(0,0,0,0.03);
    border: 1px solid #e9ecef;
    text-align: center;
}

.stat-value {
    font-size: 2rem;
    font-weight: 800;
    color: #2c3e50;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 0.85rem;
    color: #7f8c8d;
    text-transform: uppercase;
    font-weight: 600;
}

/* Results Content */
.results-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 25px;
}

@media (max-width: 991px) {
    .results-grid {
        grid-template-columns: 1fr;
    }
}

.position-card {
    background: white;
    border-radius: 25px;
    padding: 30px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.05);
    border: 1px solid #e9ecef;
}

.position-title {
    font-size: 1.4rem;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.candidate-row {
    margin-bottom: 20px;
}

.candidate-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.candidate-name {
    font-weight: 600;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 8px;
}

.winner-badge {
    background: #f1c40f;
    color: #2c3e50;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 700;
}

.vote-count {
    font-weight: 700;
    color: #3498db;
}

.progress {
    height: 12px;
    background-color: #f8f9fa;
    border-radius: 50px;
    overflow: hidden;
}

.progress-bar {
    border-radius: 50px;
}

/* Export Bar */
.export-bar {
    background: white;
    border-radius: 20px;
    padding: 20px;
    margin-top: 40px;
    display: flex;
    justify-content: center;
    gap: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}

.btn-export {
    padding: 12px 30px;
    border-radius: 50px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
    text-decoration: none;
}

.btn-print {
    background: #2c3e50;
    color: white;
    border: none;
}

.btn-print:hover {
    background: #1a252f;
    transform: translateY(-2px);
    color: white;
}

.btn-csv {
    background: #27ae60;
    color: white;
    border: none;
}

.btn-csv:hover {
    background: #219150;
    transform: translateY(-2px);
    color: white;
}

/* No Election */
.no-election {
    text-align: center;
    padding: 80px 20px;
    background: white;
    border-radius: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}

.no-election i {
    font-size: 80px;
    color: #dee2e6;
    margin-bottom: 20px;
}

/* =========================================
   DARK MODE OVERRIDES
   ========================================= */
[data-theme="dark"] .selector-panel { background: rgba(20,22,35,0.85); border-color: rgba(99,102,241,0.15); box-shadow: 0 10px 30px rgba(0,0,0,0.25); }
[data-theme="dark"] .selector-title { color: #e2e8f0; }
[data-theme="dark"] .election-select { background: rgba(15,17,27,0.9); border-color: rgba(99,102,241,0.2); color: #e2e8f0; }
[data-theme="dark"] .election-select option { background: #1a1c2e; color: #e2e8f0; }
[data-theme="dark"] .stat-card { background: rgba(20,22,35,0.85); border-color: rgba(99,102,241,0.15); box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
[data-theme="dark"] .stat-value { color: #e2e8f0; }
[data-theme="dark"] .stat-label { color: #94a3b8; }
[data-theme="dark"] .position-card { background: rgba(20,22,35,0.85); border-color: rgba(99,102,241,0.15); box-shadow: 0 15px 35px rgba(0,0,0,0.2); }
[data-theme="dark"] .position-title { color: #e2e8f0; }
[data-theme="dark"] .candidate-name { color: #e2e8f0; }
[data-theme="dark"] .progress { background-color: rgba(255,255,255,0.05); }
[data-theme="dark"] .export-bar { background: rgba(20,22,35,0.85); box-shadow: 0 10px 30px rgba(0,0,0,0.25); }
[data-theme="dark"] .no-election { background: rgba(20,22,35,0.85); }
[data-theme="dark"] .no-election h3 { color: #e2e8f0; }
[data-theme="dark"] .no-election p { color: #94a3b8; }
[data-theme="dark"] .no-election i { color: #334155; }
</style>

<div class="results-container">
    <div class="results-header">
        <div class="row align-items-center">
            <div class="col-md-9">
                <h1 class="display-4 fw-bold mb-3">Live Election Results</h1>
                <p class="lead mb-0">Real-time voting statistics and position standings for historical and active elections.</p>
            </div>
            <div class="col-md-3 text-md-end">
                <span class="badge bg-white text-dark p-3 fs-6">
                    <i class="bi bi-clock-history"></i> Live Updates
                </span>
            </div>
        </div>
    </div>

    <div class="selector-panel">
        <div class="selector-title">
            <i class="bi bi-search"></i> Select Election Period
        </div>
        <form method="GET" action="" id="electionSelectForm">
            <select name="election_id" class="form-select election-select" id="electionSelect">
                <option value="">-- Choose an Election --</option>
                <?php foreach($elections as $el): ?>
                    <option value="<?php echo $el['id']; ?>" <?php echo ($selected_election == $el['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($el['title']); ?> (<?php echo ucfirst($el['display_status']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if($selected_election && $election_details): ?>
        <!-- Statistics Strip -->
        <div class="stats-strip">
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($eligible_count); ?></div>
                <div class="stat-label">Eligible Voters</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($registered_count); ?></div>
                <div class="stat-label">Registered Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($voted_count); ?></div>
                <div class="stat-label">Total Votes Cast</div>
            </div>
            <div class="stat-card">
                <div class="stat-value text-success"><?php echo $turnout; ?>%</div>
                <div class="stat-label">Turnout Rate</div>
            </div>
        </div>

        <!-- Standings -->
        <?php if(!empty($grouped_results)): ?>
            <div class="results-grid">
                <?php foreach($grouped_results as $pos_name => $data): ?>
                    <div class="position-card">
                        <div class="position-title">
                            <?php echo htmlspecialchars($pos_name); ?>
                            <span class="badge bg-light text-dark fs-6"><?php echo $data['total_votes']; ?> votes</span>
                        </div>
                        
                        <?php foreach($data['candidates'] as $candidate): ?>
                            <?php 
                            $percentage = $data['total_votes'] > 0 ? round(($candidate['vote_count'] / $data['total_votes']) * 100, 1) : 0;
                            $is_winner = $data['max_votes'] > 0 && $candidate['vote_count'] == $data['max_votes'];
                            ?>
                            <div class="candidate-row">
                                <div class="candidate-info">
                                    <div class="candidate-name">
                                        <?php echo htmlspecialchars($candidate['candidate_name']); ?>
                                        <?php if($is_winner): ?>
                                            <span class="winner-badge"><i class="bi bi-trophy-fill"></i> LEADING</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <div class="vote-count"><?php echo $candidate['vote_count']; ?></div>
                                        <?php if($candidate['candidate_id']): ?>
                                        <a href="candidate_voters.php?candidate_id=<?php echo $candidate['candidate_id']; ?>&election_id=<?php echo $selected_election; ?>" 
                                           style="font-size:0.72rem;font-weight:700;color:#6366f1;text-decoration:none;background:rgba(99,102,241,0.08);border:1px solid rgba(99,102,241,0.2);border-radius:99px;padding:2px 10px;white-space:nowrap;transition:all .2s;"
                                           onmouseover="this.style.background='rgba(99,102,241,0.15)'"
                                           onmouseout="this.style.background='rgba(99,102,241,0.08)'">
                                            <i class="bi bi-people-fill"></i> View Voters
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar <?php echo $is_winner ? 'bg-success' : 'bg-primary'; ?>" 
                                         role="progressbar" 
                                         style="width: <?php echo $percentage; ?>%" 
                                         aria-valuenow="<?php echo $percentage; ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100"></div>
                                </div>
                                <div class="text-end small text-muted mt-1"><?php echo $percentage; ?>%</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="export-bar no-print">
                <button onclick="window.print()" class="btn-export btn-print">
                    <i class="bi bi-printer"></i> Print Results
                </button>
                <a href="export_results.php?election_id=<?php echo $selected_election; ?>" class="btn-export btn-csv">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
                </a>
                <a href="dashboard.php" class="btn btn-outline-secondary rounded-pill px-4 fw-bold">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        <?php else: ?>
            <div class="alert alert-info py-4 text-center">
                <i class="bi bi-info-circle fs-2 d-block mb-2"></i>
                No voting results available for this election yet.
            </div>
        <?php endif; ?>
    <?php elseif($selected_election): ?>
        <div class="alert alert-danger">Election details not found.</div>
    <?php else: ?>
        <div class="no-election">
            <i class="bi bi-bar-chart-line"></i>
            <h3>No Election Selected</h3>
            <p class="text-muted">Please choose an election period from the dropdown above to view results.</p>
        </div>
    <?php endif; ?>
</div>

<script>
// Auto-submit on select change
document.getElementById('electionSelect')?.addEventListener('change', function() {
    if(this.value) document.getElementById('electionSelectForm').submit();
});
</script>

<?php include '../includes/footer.php'; ?>