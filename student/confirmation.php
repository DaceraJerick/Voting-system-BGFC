<?php
// filename: student/confirmation.php
// location: student/confirmation.php

require_once '../config/database.php';
require_once '../includes/auth_check.php';

if($_SESSION['user_type'] != 'student') {
    header("Location: ../index.php");
    exit();
}

// Check active election FIRST
$election = $pdo->query("
    SELECT * FROM elections 
    WHERE is_active = 1 
    AND NOW() BETWEEN start_date AND end_date 
    LIMIT 1
")->fetch();

if(!$election) {
    header("Location: dashboard.php?msg=no_active_election");
    exit();
}

// IMPORTANT FIX: Check if student has already voted in THIS SPECIFIC election
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM votes 
    WHERE election_id = ? AND student_id = ?
");
$stmt->execute([$election['id'], $_SESSION['student_id']]);
$has_voted_in_this_election = $stmt->fetchColumn() > 0;

if($has_voted_in_this_election) {
    header("Location: dashboard.php?msg=already_voted_in_this_election");
    exit();
}

$success = false;
$error = '';
$vote_summary = [];

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['votes'])) {
    try {
        $pdo->beginTransaction();
        
        // DOUBLE CHECK: Verify again that student hasn't voted in this election
        $check = $pdo->prepare("
            SELECT COUNT(*) FROM votes 
            WHERE election_id = ? AND student_id = ?
        ");
        $check->execute([$election['id'], $_SESSION['student_id']]);
        if($check->fetchColumn() > 0) {
            throw new Exception("You have already voted in this election!");
        }
        
        // Validate and record each vote
        foreach($_POST['votes'] as $position_id => $candidate_id) {
            // Verify candidate belongs to position and election
            $verify = $pdo->prepare("
                SELECT c.id, c.full_name, p.position_name 
                FROM candidates c
                JOIN positions p ON c.position_id = p.id
                WHERE c.id = ? AND p.id = ? AND p.election_id = ?
            ");
            $verify->execute([$candidate_id, $position_id, $election['id']]);
            $candidate = $verify->fetch();
            
            if(!$candidate) {
                throw new Exception("Invalid vote selection!");
            }
            
            // Record vote
            $stmt = $pdo->prepare("
                INSERT INTO votes (election_id, student_id, candidate_id, position_id) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$election['id'], $_SESSION['student_id'], $candidate_id, $position_id]);
            
            // Store for summary
            $vote_summary[] = [
                'position' => $candidate['position_name'],
                'candidate' => $candidate['full_name']
            ];
        }
        
        // Update student's has_voted flag (for historical purposes)
        $stmt = $pdo->prepare("UPDATE students SET has_voted = 1 WHERE student_id = ?");
        $stmt->execute([$_SESSION['student_id']]);
        
        $pdo->commit();
        $success = true;
        
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = "An error occurred while recording your vote: " . $e->getMessage();
    }
}


include '../includes/header.php';
?>


<style>
/* ── Soft Glass Confirmation Interface ── */
.confirmation-container { max-width: 800px; margin: 0 auto; padding-bottom: 5rem; }
.confirmation-card { background: rgba(255, 255, 255, 0.4); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.4); border-radius: 35px; overflow: hidden; box-shadow: 0 12px 40px rgba(0,0,0,0.03); }
.confirmation-header { background: #6366f1; color: white; padding: 4rem 2rem; text-align: center; position: relative; overflow: hidden; }
.confirmation-header::after { content: ''; position: absolute; width: 200px; height: 200px; background: rgba(255,255,255,0.1); border-radius: 50%; top: -50px; right: -50px; }
.confirmation-icon { font-size: 5rem; margin-bottom: 1.5rem; display: block; }
.confirmation-title { font-size: 2.25rem; font-weight: 800; margin-bottom: 0.75rem; }
.confirmation-subtitle { font-size: 1.1rem; font-weight: 600; opacity: 0.9; }
.vote-timestamp { background: rgba(255,255,255,0.15); padding: 0.6rem 1.5rem; border-radius: 99px; display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; font-weight: 700; margin-top: 1.5rem; }
.confirmation-body { padding: 3rem 2.5rem; }
.summary-table { background: rgba(255,255,255,0.5); border: 1px solid #f1f5f9; border-radius: 20px; padding: 1.5rem; margin-bottom: 2rem; }
.summary-item { display: flex; align-items: center; padding: 1rem 0; border-bottom: 1px solid #f1f5f9; }
.summary-item:last-child { border-bottom: none; }
.position-badge { background: #eef2ff; color: #6366f1; padding: 0.35rem 0.85rem; border-radius: 99px; font-size: 0.75rem; font-weight: 800; margin-right: 1rem; min-width: 130px; text-align: center; }
.btn-return { background: #6366f1; color: white; border: none; padding: 1.15rem 2.5rem; border-radius: 99px; font-size: 1.1rem; font-weight: 800; transition: all 0.3s; box-shadow: 0 8px 25px rgba(99, 102, 241, 0.3); text-decoration: none; display: inline-block; }
.btn-return:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(99, 102, 241, 0.4); color: white; }
.review-card { background: transparent; }
.review-item { display: flex; justify-content: space-between; align-items: center; padding: 1.25rem; background: rgba(255,255,255,0.6); border-radius: 16px; margin-bottom: 0.75rem; border: 1px solid rgba(255,255,255,0.5); }
.election-id-badge { font-family: monospace; background: #e9ecef; padding: 3px 10px; border-radius: 5px; font-size: 0.8rem; }
</style>

<div class="confirmation-container">
    <?php if($success): ?>
        <!-- Success View - VOTE CONFIRMED -->
        <div class="confirmation-card">
            <div class="confirmation-header">
                <div class="success-animation">
                    <i class="bi bi-check-circle-fill confirmation-icon"></i>
                </div>
                <h1 class="confirmation-title">Vote Confirmed!</h1>
                <h2 class="confirmation-subtitle">Vote Submitted Successfully!</h2>
                <p class="lead mb-3">Thank you for participating in the election.</p>
                <p class="mb-4">Your vote has been recorded and counted.</p>
                
                <div class="vote-timestamp">
                    <i class="bi bi-clock"></i>
                    Voted on: <?php echo date('F d, Y h:i A'); ?>
                </div>
            </div>
            
            <div class="confirmation-body">
                <div class="text-center mb-4">
                    <span class="voter-badge">
                        <i class="bi bi-person-check"></i>
                        Voter: <?php echo htmlspecialchars($_SESSION['student_id']); ?>
                    </span>
                    <span class="election-id-badge ms-2">
                        <i class="bi bi-calendar-check"></i>
                        <?php echo htmlspecialchars($election['title']); ?>
                    </span>
                </div>
                
                <h4 class="fw-bold mb-4">
                    <i class="bi bi-check2-square text-success"></i>
                    Your Vote Summary
                </h4>
                
                <div class="summary-table">
                    <?php foreach($vote_summary as $vote): ?>
                        <div class="summary-item">
                            <span class="position-badge">
                                <?php echo htmlspecialchars($vote['position']); ?>
                            </span>
                            <span class="flex-grow-1 fw-bold">
                                <?php echo htmlspecialchars($vote['candidate']); ?>
                            </span>
                            <span class="text-success">
                                <i class="bi bi-check-circle-fill"></i>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="text-center mt-4">
                    <p class="text-muted mb-4">
                        <i class="bi bi-shield-check text-success fs-4"></i><br>
                        Your participation helps make our student council stronger.<br>
                        <small>A confirmation has been recorded in the system.</small>
                    </p>
                    
                    <a href="dashboard.php?msg=vote_success" class="btn-return">
                        <i class="bi bi-house-door"></i> RETURN TO DASHBOARD
                    </a>
                </div>
                
                <!-- Share/Print Options -->
                <div class="mt-5 text-center">
                    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-printer"></i> Print Confirmation
                    </button>
                </div>
            </div>
        </div>
        
    <?php elseif($error): ?>
        <!-- Error View -->
        <div class="confirmation-card">
            <div class="confirmation-header" style="background: linear-gradient(135deg, #dc3545, #c82333);">
                <i class="bi bi-exclamation-triangle-fill confirmation-icon"></i>
                <h1 class="confirmation-title">Vote Error</h1>
                <p class="lead mb-0">Your vote could not be processed.</p>
            </div>
            <div class="confirmation-body text-center">
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                
                <p class="mb-4">Please try again or contact the election administrator.</p>
                
                <div class="d-flex justify-content-center gap-3">
                    <a href="vote.php" class="btn btn-primary">
                        <i class="bi bi-arrow-repeat"></i> Try Again
                    </a>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="bi bi-house-door"></i> Dashboard
                    </a>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <!-- Review Vote View -->
        <div class="confirmation-card">
            <div class="confirmation-header" style="background: linear-gradient(135deg, #0d6efd, #0b5ed7);">
                <i class="bi bi-check2-square confirmation-icon"></i>
                <h1 class="confirmation-title">Review Your Vote</h1>
                <p class="lead mb-0">Please review your selections before confirming.</p>
            </div>
            
            <div class="confirmation-body">
                <div class="review-card">
                    <h5 class="fw-bold mb-4">
                        <i class="bi bi-eye"></i>
                        Your Selections
                    </h5>
                    
                    <form method="POST" action="">
                        <?php
                        foreach($_POST['vote'] as $position_id => $candidate_id):
                            $stmt = $pdo->prepare("
                                SELECT p.position_name, c.full_name
                                FROM positions p
                                JOIN candidates c ON c.id = ?
                                WHERE p.id = ?
                            ");
                            $stmt->execute([$candidate_id, $position_id]);
                            $selection = $stmt->fetch();
                        ?>
                            <div class="review-item">
                                <div>
                                    <span class="position-badge">
                                        <?php echo htmlspecialchars($selection['position_name']); ?>
                                    </span>
                                    <span class="fw-bold ms-2">
                                        <?php echo htmlspecialchars($selection['full_name']); ?>
                                    </span>
                                </div>
                                <span class="text-success">
                                    <i class="bi bi-check-circle-fill"></i>
                                </span>
                            </div>
                            <input type="hidden" name="votes[<?php echo $position_id; ?>]" value="<?php echo $candidate_id; ?>">
                        <?php endforeach; ?>
                        
                        <div class="alert alert-warning mt-4">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <strong>Important:</strong> By confirming, you certify that these are your choices.
                            <span class="d-block mt-1">You CANNOT change your vote after submission.</span>
                        </div>
                        
                        <div class="d-flex justify-content-center gap-3 mt-4">
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="bi bi-check-circle"></i> CONFIRM VOTE
                            </button>
                            <a href="vote.php" class="btn btn-outline-secondary btn-lg px-5">
                                <i class="bi bi-arrow-left"></i> CHANGE
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>