<?php
// filename: student/vote_enhanced.php
// ENHANCED VOTING PAGE WITH 5000+ USER OPTIMIZATION
// This is an example of how to integrate the new systems

require_once '../config/database.php';
require_once '../config/cache.php';
require_once '../config/logger.php';
require_once '../includes/auth_check.php';

// Log page access
$logger->logAccess('student/vote.php', $_SESSION['student_id'], 'student');

if($_SESSION['user_type'] != 'student') {
    header("Location: ../index.php");
    exit();
}

// START: OPTIMIZED QUERIES WITH CACHING & ERROR HANDLING

try {
    // Check active election (with caching)
    $election = $cache->remember('active_election', function() use ($pdo) {
        $stmt = $pdo->prepare("
            SELECT * FROM elections 
            WHERE is_active = 1 
            AND NOW() BETWEEN start_date AND end_date 
            LIMIT 1
        ");
        $stmt->execute();
        return $stmt->fetch();
    }, 60); // 1 minute cache
    
    if(!$election) {
        $logger->logError('No active election found', 'INFO', __FILE__, __LINE__);
        header("Location: dashboard.php?msg=no_active_election");
        exit();
    }
    
    // Check if student already voted (DO NOT cache this - critical data)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM votes 
        WHERE election_id = ? AND student_id = ?
    ");
    $stmt->execute([$election['id'], $_SESSION['student_id']]);
    $has_voted = $stmt->fetchColumn() > 0;
    
    if($has_voted) {
        $logger->logError('Student already voted', 'WARNING', $_SESSION['student_id'], __FILE__, __LINE__);
        header("Location: dashboard.php?msg=already_voted_in_this_election");
        exit();
    }
    
    // Get positions and candidates (with caching)
    $positions_grouped = $cache->remember('election_' . $election['id'] . '_positions', function() use ($pdo, $election) {
        $positions = $pdo->prepare("
            SELECT 
                p.id as position_id,
                p.position_name,
                p.display_order,
                c.id as candidate_id,
                c.full_name as candidate_name,
                c.student_id,
                c.platform,
                c.photo
            FROM positions p
            LEFT JOIN candidates c ON c.position_id = p.id
            WHERE p.election_id = ?
            ORDER BY p.display_order, c.full_name
        ");
        $positions->execute([$election['id']]);
        $results = $positions->fetchAll();
        
        $grouped = [];
        foreach($results as $row) {
            if(!isset($grouped[$row['position_name']])) {
                $grouped[$row['position_name']] = [
                    'position_id' => $row['position_id'],
                    'display_order' => $row['display_order'],
                    'candidates' => []
                ];
            }
            if($row['candidate_id']) {
                $grouped[$row['position_name']]['candidates'][] = $row;
            }
        }
        return $grouped;
    }, 300); // 5 minutes cache
    
    if(empty($positions_grouped)) {
        $logger->logError('No candidates found for election', 'WARNING', __FILE__, __LINE__);
        header("Location: dashboard.php?msg=no_candidates");
        exit();
    }
    
    // Get voting statistics
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT student_id) FROM votes WHERE election_id = ?");
    $stmt->execute([$election['id']]);
    $total_votes_cast = $stmt->fetchColumn();
    
} catch (Exception $e) {
    // Log error and redirect
    $logger->logError($e->getMessage(), 'EXCEPTION', __FILE__, __LINE__);
    $_SESSION['error_msg'] = 'An error occurred. Please try again.';
    header("Location: dashboard.php?msg=error");
    exit();
}

include '../includes/header.php';
?>

<style>
.vote-header {
    background: linear-gradient(135deg, #0f7f63 0%, #1a9c72 100%);
    color: white;
    padding: 40px;
    border-radius: 20px;
    margin-bottom: 30px;
    box-shadow: 0 18px 60px rgba(0,0,0,0.25);
}

.vote-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.stat-card {
    background: rgba(255,255,255,0.08);
    padding: 20px;
    border-radius: 16px;
    text-align: center;
    border: 1px solid rgba(255,255,255,0.08);
}

.stat-number {
    font-size: 28px;
    font-weight: bold;
    margin-bottom: 5px;
    color: #f4fffb;
}

.stat-label {
    font-size: 12px;
    opacity: 0.8;
    text-transform: uppercase;
    color: #d7efd6;
}

.card {
    background: rgba(8,18,32,0.95) !important;
    border: 1px solid rgba(255,255,255,0.08) !important;
    color: #e6f7e7;
}

.card-header {
    background: rgba(15,127,99,0.12) !important;
    color: #e6f7e7 !important;
    border-bottom: 1px solid rgba(255,255,255,0.08) !important;
}

.card-body {
    color: #d7efda;
}

.vote-card {
    background: rgba(10,20,34,0.96);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 22px;
}

.vote-card .card-img-top {
    border-top-left-radius: 22px;
    border-top-right-radius: 22px;
}

.vote-card .card-body {
    background: transparent;
}

.vote-card .form-check-label,
.vote-card .card-title,
.vote-card .card-text {
    color: #d7f0d6;
}

.vote-card .form-check-input:checked {
    background-color: #0f7f63;
    border-color: #0f7f63;
}

.vote-card .form-check-input {
    box-shadow: none;
}

.vote-card img,
.vote-card .placeholder-box {
    border-radius: 22px 22px 0 0;
}

.placeholder-box {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 250px;
    background: rgba(255,255,255,0.05);
    color: #a7c9a7;
}
</style>

<div class="container mt-5">
    <div class="vote-header">
        <h1>Cast Your Vote</h1>
        <p class="mb-0"><?php echo htmlspecialchars($election['election_name']); ?></p>
        
        <div class="vote-stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_votes_cast; ?></div>
                <div class="stat-label">Votes Cast</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($positions_grouped); ?></div>
                <div class="stat-label">Positions</div>
            </div>
        </div>
    </div>
    
    <form id="votingForm" method="POST" action="submit_vote.php" class="no-loader">
        <input type="hidden" name="election_id" value="<?php echo htmlspecialchars($election['id']); ?>">
        
        <?php foreach($positions_grouped as $position_name => $position_data): ?>
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><?php echo htmlspecialchars($position_name); ?></h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach($position_data['candidates'] as $candidate): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card vote-card h-100">
                            <?php if($candidate['photo']): ?>
                            <img src="../assets/uploads/candidates/<?php echo htmlspecialchars($candidate['photo']); ?>" 
                                 class="card-img-top" alt="<?php echo htmlspecialchars($candidate['candidate_name']); ?>" 
                                 style="height: 250px; object-fit: cover;">
                            <?php else: ?>
                            <div style="height: 250px; background: rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: center;">
                                <span style="color: #9bb7a3;">No Photo</span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="card-body">
                                <h6 class="card-title"><?php echo htmlspecialchars($candidate['candidate_name']); ?></h6>
                                <p class="card-text small"><?php echo htmlspecialchars(substr($candidate['platform'], 0, 100)); ?>...</p>
                                
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" 
                                           name="position_<?php echo htmlspecialchars($position_data['position_id']); ?>" 
                                           value="<?php echo htmlspecialchars($candidate['candidate_id']); ?>" 
                                           id="candidate_<?php echo htmlspecialchars($candidate['candidate_id']); ?>">
                                    <label class="form-check-label" for="candidate_<?php echo htmlspecialchars($candidate['candidate_id']); ?>">
                                        Vote for this candidate
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <div class="mt-5 mb-5">
            <button type="submit" class="btn btn-success btn-lg w-100">Submit Your Vote</button>
        </div>
    </form>
</div>

<script>
// Enhanced form submission with modern UI
document.getElementById('votingForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Validate all positions have selection
    const positionCount = document.querySelectorAll('[name^="position_"]').length;
    const checkedCount = document.querySelectorAll('[name^="position_"]:checked').length;
    
    if (checkedCount === 0) {
        toast.warning('Please select at least one candidate');
        return;
    }
    
    if (checkedCount < positionCount) {
        confirmDialog.show({
            title: 'Incomplete Voting',
            message: 'You have not voted for all positions. Continue anyway?',
            type: 'warning',
            confirmText: 'Continue',
            cancelText: 'Cancel',
            onConfirm: () => {
                submitVote();
            }
        });
    } else {
        confirmDialog.show({
            title: 'Confirm Your Vote',
            message: 'Please review your selections. You cannot change your vote after submission.',
            type: 'info',
            confirmText: 'Submit Vote',
            cancelText: 'Review Again',
            onConfirm: () => {
                submitVote();
            }
        });
    }
});

function submitVote() {
    loader.show(1000);
    document.getElementById('votingForm').submit();
}
</script>

<?php include '../includes/footer.php'; ?>
