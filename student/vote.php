<?php
// filename: student/vote.php
// location: student/vote.php

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

if($election['is_paused'] == 1) {
    header("Location: dashboard.php?msg=election_paused");
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

// Get all positions and candidates for this election
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

// Group candidates by position
$positions_grouped = [];
$has_any_candidate = false;

foreach($results as $row) {
    if(!isset($positions_grouped[$row['position_name']])) {
        $positions_grouped[$row['position_name']] = [
            'position_id' => $row['position_id'],
            'display_order' => $row['display_order'],
            'candidates' => []
        ];
    }
    if($row['candidate_id']) {
        $positions_grouped[$row['position_name']]['candidates'][] = $row;
        $has_any_candidate = true;
    }
}

if(!$has_any_candidate) {
    header("Location: dashboard.php?msg=no_candidates");
    exit();
}

// Get total statistics for this election
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT student_id) FROM votes WHERE election_id = ?");
$stmt->execute([$election['id']]);
$total_votes_cast = $stmt->fetchColumn();

$total_eligible = $pdo->query("SELECT COUNT(*) FROM eligible_ids")->fetchColumn();
$total_registered = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();

include '../includes/header.php';
?>

<style>
/* ── Dark Glass Voting Interface ── */
.vote-header { background: rgba(13,15,27,0.9); backdrop-filter: blur(20px); border: 1px solid rgba(99,102,241,0.16); padding: 2.5rem; border-radius: 30px; margin-bottom: 2rem; color: #e2e8f0; box-shadow: 0 18px 45px rgba(0,0,0,0.28); }
.election-timer { background: #10b981; color: white; padding: 0.75rem 1.5rem; border-radius: 99px; display: inline-flex; align-items: center; gap: 0.75rem; box-shadow: 0 6px 22px rgba(16,185,129,0.24); }
.position-card { background: transparent; border: none; margin-bottom: 3rem; }
.position-header { margin-bottom: 1.5rem; padding-left: 0.5rem; }
.position-title { font-size: 1.35rem; font-weight: 800; color: #e2e8f0; margin: 0; display: flex; align-items: center; gap: 0.75rem; }
.candidate-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem; }
.candidate-card { background: rgba(15,17,23,0.86); backdrop-filter: blur(10px); border: 1px solid rgba(99,102,241,0.16); border-radius: 28px; padding: 2rem; text-align: center; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); cursor: pointer; position: relative; box-shadow: 0 10px 35px rgba(0,0,0,0.24); }
.candidate-card:hover { transform: translateY(-8px); background: rgba(99,102,241,0.16); box-shadow: 0 14px 45px rgba(0,0,0,0.32); border-color: #6366f1; }
.candidate-card.selected { border: 2px solid #10b981; background: rgba(16,185,129,0.16); box-shadow: 0 14px 40px rgba(16,185,129,0.18); }
.candidate-photo { width: 110px; height: 110px; border-radius: 50%; object-fit: cover; border: 4px solid rgba(99,102,241,0.18); box-shadow: 0 6px 20px rgba(0,0,0,0.25); margin: 0 auto 1.25rem; }
.candidate-name { font-size: 1.15rem; font-weight: 800; color: #e2e8f0; margin-bottom: 0.25rem; }
.candidate-id { font-size: 0.75rem; font-weight: 700; color: #94a3b8; background: rgba(15,17,23,0.8); padding: 0.35rem 0.75rem; border-radius: 99px; display: inline-block; margin-bottom: 1.25rem; }
.candidate-platform { background: rgba(15,17,23,0.85); padding: 1rem; border-radius: 16px; font-size: 0.85rem; color: #cbd5e1; line-height: 1.6; height: 100px; overflow-y: auto; text-align: left; border: 1px solid rgba(99,102,241,0.12); }
.select-indicator { display: inline-flex; width: 28px; height: 28px; border: 2px solid rgba(99,102,241,0.18); border-radius: 50%; align-items: center; justify-content: center; transition: all 0.2s; background: rgba(15,17,23,0.7); color: #94a3b8; }
.candidate-card.selected .select-indicator { background: #10b981; border-color: #10b981; color: white; }
.btn-submit-vote { background: #10b981; color: white; border: none; padding: 1.15rem 3.5rem; border-radius: 99px; font-size: 1.1rem; font-weight: 800; transition: all 0.3s; box-shadow: 0 10px 30px rgba(16,185,129,0.32); }
.btn-submit-vote:hover { transform: translateY(-3px) scale(1.02); background: #0f766e; box-shadow: 0 12px 30px rgba(16,185,129,0.4); color: white; }
.vote-instructions { background: rgba(99,102,241,0.12); border-left: 5px solid #10b981; padding: 1.5rem 2rem; border-radius: 20px; margin-bottom: 2.5rem; color: #cbd5e1; }

@media (max-width: 991px) {
    .vote-header { padding: 1.5rem; }
    .display-5 { font-size: 2rem; }
}

/* =========================================
   LIGHT MODE OVERRIDES
   ========================================= */
[data-theme="light"] .vote-header {
    background: rgba(255, 255, 255, 0.95);
    border-color: rgba(0, 0, 0, 0.08);
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}
[data-theme="light"] .vote-header h1,
[data-theme="light"] .vote-header h2,
[data-theme="light"] .vote-header p,
[data-theme="light"] .vote-header .lead {
    color: #1e293b !important;
}
[data-theme="light"] .vote-header i:not(.bi-clock) {
    color: #1e293b;
}

[data-theme="light"] .vote-instructions {
    background: rgba(99, 102, 241, 0.08);
    color: #475569;
}
[data-theme="light"] .vote-instructions h5 {
    color: #1e293b;
}
[data-theme="light"] .vote-instructions ul li {
    color: #475569;
}

[data-theme="light"] .position-title {
    color: #1e293b;
}

[data-theme="light"] .candidate-card {
    background: rgba(255, 255, 255, 0.95);
    border-color: rgba(0, 0, 0, 0.08);
    box-shadow: 0 8px 25px rgba(0,0,0,0.06);
}
[data-theme="light"] .candidate-card:hover {
    background: #ffffff;
    border-color: rgba(99, 102, 241, 0.3);
    box-shadow: 0 12px 30px rgba(0,0,0,0.1);
}
[data-theme="light"] .candidate-card.selected {
    background: rgba(16, 185, 129, 0.08);
    border-color: #10b981;
}

[data-theme="light"] .candidate-name {
    color: #1e293b;
}
[data-theme="light"] .candidate-id {
    background: rgba(0, 0, 0, 0.05);
    color: #475569;
}
[data-theme="light"] .candidate-platform {
    background: #f8fafc;
    border-color: rgba(0, 0, 0, 0.06);
    color: #475569;
}
[data-theme="light"] .select-indicator {
    background: #f1f5f9;
    border-color: #cbd5e1;
    color: #94a3b8;
}
[data-theme="light"] .candidate-card.selected .select-indicator {
    background: #10b981;
    border-color: #10b981;
    color: white;
}
[data-theme="light"] .candidate-card .fw-bold {
    color: #475569;
}
[data-theme="light"] .candidate-card.selected .fw-bold {
    color: #10b981;
}
</style>

<!-- Vote Header -->
<div class="vote-header">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h1 class="display-5 fw-bold mb-3">
                <i class="bi bi-check2-circle"></i> Cast Your Vote
            </h1>
            <h2 class="h3 mb-3"><?php echo htmlspecialchars($election['title']); ?></h2>
            <p class="lead mb-0">Select ONE candidate for each position. Your vote is confidential and secure.</p>
        </div>
        <div class="col-md-4 text-md-end">
            <div class="election-timer">
                <i class="bi bi-clock fs-4"></i>
                <?php
                // Use helper function for consistent Asia/Manila timezone countdown
                $remaining_secs = getRemainingSecs($election['end_date']);
                $initial_text = formatRemainingTime($remaining_secs);
                $iso_end = date('c', strtotime($election['end_date']));
                ?>
                <span id="vote-countdown-<?php echo $election['id']; ?>" data-end="<?php echo $iso_end; ?>" data-remaining="<?php echo $remaining_secs; ?>" class="fs-5 fw-bold"><?php echo $initial_text; ?></span>
                <br>
                <small>remaining</small>
            </div>
        </div>
    </div>
</div>


<!-- Instructions -->
<div class="vote-instructions">
    <div class="d-flex">
        <div class="me-3">
            <i class="bi bi-info-circle-fill text-success fs-2"></i>
        </div>
        <div>
            <h5 class="fw-bold mb-2">How to Vote:</h5>
            <ul class="mb-0">
                <li>Click on a candidate card to select them for that position</li>
                <li>You must select one candidate for EACH position</li>
                <li>Review your selections before submitting</li>
                <li>You CANNOT change your vote after submission</li>
            </ul>
        </div>
    </div>
</div>

<form id="votingForm" method="POST" action="confirmation.php">
    <?php foreach($positions_grouped as $position_name => $data): ?>
        <div class="position-card">
            <div class="position-header d-flex justify-content-between align-items-center">
                <h3 class="position-title">
                    <i class="bi bi-person-badge"></i> <?php echo htmlspecialchars($position_name); ?>
                    <span class="required-badge">REQUIRED</span>
                </h3>
                <span class="badge bg-secondary">
                    <?php echo count($data['candidates']); ?> candidate(s)
                </span>
            </div>
            
            <div class="candidate-grid">
                <?php foreach($data['candidates'] as $candidate): ?>
                    <div class="candidate-card" onclick="selectCandidate('<?php echo $data['position_id']; ?>', '<?php echo $candidate['candidate_id']; ?>', this)">
                        <!-- Candidate Photo -->
                        <?php if(!empty($candidate['photo']) && file_exists('../' . $candidate['photo'])): ?>
                            <img src="../<?php echo $candidate['photo']; ?>" 
                                 class="candidate-photo" 
                                 alt="<?php echo htmlspecialchars($candidate['candidate_name']); ?>">
                        <?php else: ?>
                            <div class="candidate-photo d-flex align-items-center justify-content-center bg-light mx-auto">
                                <i class="bi bi-person-circle" style="font-size: 60px; color: #adb5bd;"></i>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Candidate Info -->
                        <div class="candidate-name">
                            <?php echo htmlspecialchars($candidate['candidate_name']); ?>
                        </div>
                        <div class="candidate-id">
                            <i class="bi bi-person-badge"></i> <?php echo htmlspecialchars($candidate['student_id']); ?>
                        </div>
                        
                        <!-- Platform -->
                        <?php if(!empty($candidate['platform'])): ?>
                            <div class="candidate-platform">
                                <i class="bi bi-quote"></i>
                                <?php echo nl2br(htmlspecialchars($candidate['platform'])); ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Radio Button -->
                        <div class="mt-3">
                            <span class="select-indicator"></span>
                            <span class="fw-bold">Select Candidate</span>
                            <input type="radio" 
                                   class="select-radio" 
                                   name="vote[<?php echo $data['position_id']; ?>]" 
                                   value="<?php echo $candidate['candidate_id']; ?>"
                                   id="candidate_<?php echo $candidate['candidate_id']; ?>"
                                   required>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
    
    <!-- Submit Section -->
    <div class="text-center mt-5 mb-5">
        <div class="card border-0 bg-light p-4">
            <div class="row align-items-center">
                <div class="col-md-8 text-md-start">
                    <h5 class="fw-bold mb-1">Ready to submit your vote?</h5>
                    <p class="text-muted mb-md-0">Please review your selections before confirming.</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <button type="submit" class="btn-submit-vote">
                        <i class="bi bi-check2-circle"></i> SUBMIT VOTE
                    </button>
                    <a href="dashboard.php" class="btn btn-outline-secondary mt-2 mt-md-0 ms-md-2">
                        <i class="bi bi-x-circle"></i> CANCEL
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
function selectCandidate(positionId, candidateId, element) {
    // Remove selected class from all cards in this position
    document.querySelectorAll(`input[name="vote[${positionId}]"]`).forEach(radio => {
        const card = radio.closest('.candidate-card');
        if(card) card.classList.remove('selected');
    });
    
    // Add selected class to current card
    element.classList.add('selected');
    
    // Check the radio button
    const radio = document.getElementById(`candidate_${candidateId}`);

    if(radio) radio.checked = true;
}

// Countdown using ISO timestamp to avoid browser parsing differences
<?php if(isset($election['end_date'])): ?>
function updateVoteCountdown<?php echo $election['id']; ?>() {
    const el = document.getElementById('vote-countdown-<?php echo $election['id']; ?>');
    if(!el) return;

    // Prefer server-provided remaining seconds to avoid timezone/parsing issues
    if(el.dataset && typeof el.dataset.remaining !== 'undefined') {
        let remaining = parseInt(el.dataset.remaining, 10);

        const tick = function() {
                    if(remaining <= 0) {
                        // Election ended on server-side; redirect student to dashboard/results
                        window.location.href = 'dashboard.php?msg=election_ended';
                        return;
                    }

            const days = Math.floor(remaining / 86400);
            const hours = Math.floor((remaining % 86400) / 3600);
            const minutes = Math.floor((remaining % 3600) / 60);
            const seconds = remaining % 60;

            let parts = [];
            if(days > 0) parts.push(days + 'd');
            if(hours > 0) parts.push(hours + 'h');
            if(minutes > 0) parts.push(minutes + 'm');
            parts.push(seconds + 's');

            el.innerText = parts.join(' ');
            remaining -= 1;
        };

        // run immediately and then every second
        tick();
        setInterval(tick, 1000);
        return;
    }

    // Fallback: parse ISO timestamp from data-end
    let endStr = el.dataset && el.dataset.end ? el.dataset.end : "<?php echo date('c', strtotime($election['end_date'])); ?>";
    const end = new Date(endStr).getTime();
    
    function tickFallback() {
        const now = new Date().getTime();
        const distance = end - now;

        if(distance <= 0) {
            // Fallback: election ended according to parsed end time — redirect
            window.location.href = 'dashboard.php?msg=election_ended';
            return;
        }

        const days = Math.floor(distance / (1000 * 60 * 60 * 24));
        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);

        let parts = [];
        if(days > 0) parts.push(days + 'd');
        if(hours > 0) parts.push(hours + 'h');
        if(minutes > 0) parts.push(minutes + 'm');
        parts.push(seconds + 's');

        el.innerText = parts.join(' ');
    }
    
    tickFallback();
    setInterval(tickFallback, 1000);
}

updateVoteCountdown<?php echo $election['id']; ?>();
<?php endif; ?>

// Validate that all positions have a selection before submit
document.getElementById('votingForm').addEventListener('submit', function(e) {
    // Prevent submitting if election already ended
    const countdownEl = document.getElementById('vote-countdown-<?php echo $election['id']; ?>');
    if(countdownEl) {
        if(countdownEl.dataset && typeof countdownEl.dataset.remaining !== 'undefined') {
            if(parseInt(countdownEl.dataset.remaining, 10) <= 0) {
                e.preventDefault();
                alert('This election has ended. You cannot submit a vote.');
                return;
            }
        } else if(countdownEl.dataset && countdownEl.dataset.end) {
            const endMs = new Date(countdownEl.dataset.end).getTime();
            if(endMs - Date.now() <= 0) {
                e.preventDefault();
                alert('This election has ended. You cannot submit a vote.');
                return;
            }
        }
    }

    const positions = <?php echo json_encode(array_keys($positions_grouped)); ?>;
    let missingSelections = [];
    
    <?php foreach($positions_grouped as $position_name => $data): ?>
        const selected_<?php echo $data['position_id']; ?> = document.querySelector(`input[name="vote[<?php echo $data['position_id']; ?>]"]:checked`);
        if(!selected_<?php echo $data['position_id']; ?>) {
            missingSelections.push('<?php echo addslashes($position_name); ?>');
        }
    <?php endforeach; ?>
    
    if(missingSelections.length > 0) {
        e.preventDefault();
        alert('Please select a candidate for: ' + missingSelections.join(', '));
    } else {
        if(!confirm('Are you sure you want to submit your vote? This action cannot be undone!')) {
            e.preventDefault();
        }
    }
});

// Keep selected state on page load
document.querySelectorAll('input[type="radio"]:checked').forEach(radio => {
    const card = radio.closest('.candidate-card');
    if(card) card.classList.add('selected');
});
</script>

<?php include '../includes/footer.php'; ?>