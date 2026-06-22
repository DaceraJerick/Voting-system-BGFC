<?php
// filename: student/my_application.php

require_once '../config/database.php';
require_once '../includes/auth_check.php';

if($_SESSION['user_type'] != 'student') {
    header("Location: ../index.php");
    exit();
}

$student_id = $_SESSION['student_id'];
$message = isset($_GET['msg']) ? $_GET['msg'] : '';

// Get the latest application for this student
$stmt = $pdo->prepare("
    SELECT a.*, e.title as election_title 
    FROM candidate_applications a 
    JOIN elections e ON a.election_id = e.id 
    WHERE a.student_id = ? 
    ORDER BY a.submitted_at DESC 
    LIMIT 1
");
$stmt->execute([$student_id]);
$application = $stmt->fetch();

include '../includes/header.php';
?>
<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@600;700&display=swap');
body { background: #f8fafc; color: #0f172a; font-family: 'Plus Jakarta Sans', sans-serif; }
[data-theme="dark"] body { background: #0f172a !important; color: #e2e8f0 !important; }

.page-wrap { max-width: 800px; margin: 2rem auto; padding: 0 1.5rem 4rem; }

.status-panel {
    background: white; border-radius: 24px; padding: 3rem; text-align: center;
    box-shadow: 0 12px 32px rgba(0,0,0,0.04); margin-bottom: 2rem;
}
[data-theme="dark"] .status-panel { background: rgba(30,41,59,0.8); border: 1px solid rgba(255,255,255,0.05); }

.status-icon {
    width: 100px; height: 100px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-size: 3rem; margin: 0 auto 2rem;
}
.icon-pending { background: rgba(251,191,36,0.1); color: #d97706; }
.icon-approved { background: rgba(34,197,94,0.1); color: #16a34a; }
.icon-rejected { background: rgba(239,68,68,0.1); color: #dc2626; }

.status-title { font-family: 'Sora', sans-serif; font-size: 2rem; font-weight: 800; margin-bottom: 1rem; }
.status-desc { color: #64748b; font-size: 1.1rem; max-width: 500px; margin: 0 auto; }
[data-theme="dark"] .status-desc { color: #94a3b8; }

.details-box {
    background: rgba(248,250,252,1); border-radius: 16px; padding: 2rem; text-align: left; margin-top: 2rem;
}
[data-theme="dark"] .details-box { background: rgba(15,23,42,0.6); }

.detail-row { display: flex; justify-content: space-between; padding: 1rem 0; border-bottom: 1px solid rgba(0,0,0,0.05); }
.detail-row:last-child { border-bottom: none; padding-bottom: 0; }
[data-theme="dark"] .detail-row { border-bottom-color: rgba(255,255,255,0.05); }

.detail-label { font-weight: 700; color: #64748b; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.05em; }
.detail-value { font-weight: 800; color: #1e293b; font-size: 1rem; text-align: right; }
[data-theme="dark"] .detail-label { color: #94a3b8; }
[data-theme="dark"] .detail-value { color: #e2e8f0; }

.rejection-box {
    background: rgba(239,68,68,0.05); border: 1px solid rgba(239,68,68,0.2);
    padding: 1.5rem; border-radius: 16px; margin-top: 1.5rem; color: #991b1b; text-align: left;
}
[data-theme="dark"] .rejection-box { color: #fca5a5; }

.btn-back {
    display: inline-block; background: white; border: 1.5px solid rgba(0,0,0,0.1); color: #475569;
    padding: 0.85rem 2rem; border-radius: 99px; font-weight: 700; text-decoration: none; transition: all 0.2s;
    margin-top: 2rem;
}
.btn-back:hover { background: #f8fafc; color: #1e293b; border-color: rgba(0,0,0,0.2); }
[data-theme="dark"] .btn-back { background: rgba(30,41,59,0.8); border-color: rgba(255,255,255,0.1); color: #cbd5e1; }
[data-theme="dark"] .btn-back:hover { background: rgba(51,65,85,0.8); color: white; }
</style>

<div class="page-wrap">
    <?php if($message): ?>
        <div class="alert alert-success fw-bold rounded-4 border-0 mb-4 shadow-sm text-center">
            <i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if($application): ?>
        <div class="status-panel">
            
            <?php if($application['status'] == 'pending'): ?>
                <div class="status-icon icon-pending"><i class="bi bi-hourglass-split"></i></div>
                <h1 class="status-title">Application Pending</h1>
                <p class="status-desc">Your application is currently being reviewed by the administration. You will be notified once a decision has been made.</p>
            <?php elseif($application['status'] == 'approved'): ?>
                <div class="status-icon icon-approved"><i class="bi bi-check-circle-fill"></i></div>
                <h1 class="status-title">Application Approved!</h1>
                <p class="status-desc">Congratulations! You have been approved to run as a candidate. You will automatically be added to the election ballot.</p>
            <?php elseif($application['status'] == 'rejected'): ?>
                <div class="status-icon icon-rejected"><i class="bi bi-x-circle-fill"></i></div>
                <h1 class="status-title">Application Rejected</h1>
                <p class="status-desc">Unfortunately, your application to run as a candidate has not been approved.</p>
            <?php endif; ?>

            <?php if($application['status'] == 'rejected' && !empty($application['rejection_reason'])): ?>
                <div class="rejection-box">
                    <div style="font-size: 0.8rem; font-weight: 800; text-transform: uppercase; margin-bottom: 0.5rem; opacity: 0.8;">Reason for Rejection</div>
                    <div><?php echo nl2br(htmlspecialchars($application['rejection_reason'])); ?></div>
                </div>
            <?php endif; ?>

            <div class="details-box">
                <div class="detail-row">
                    <div class="detail-label">Election</div>
                    <div class="detail-value"><?php echo htmlspecialchars($application['election_title']); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Position</div>
                    <div class="detail-value" style="color: #6366f1;"><?php echo htmlspecialchars($application['position_applied']); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Date Submitted</div>
                    <div class="detail-value"><?php echo date('F j, Y', strtotime($application['submitted_at'])); ?></div>
                </div>
                <?php if($application['reviewed_at']): ?>
                <div class="detail-row">
                    <div class="detail-label">Date Reviewed</div>
                    <div class="detail-value"><?php echo date('F j, Y', strtotime($application['reviewed_at'])); ?></div>
                </div>
                <?php endif; ?>
            </div>

            <a href="dashboard.php" class="btn-back"><i class="bi bi-arrow-left"></i> Return to Dashboard</a>
        </div>
    <?php else: ?>
        <div class="status-panel">
            <div class="status-icon" style="background: rgba(0,0,0,0.05); color: #94a3b8;"><i class="bi bi-file-earmark-x"></i></div>
            <h1 class="status-title">No Application Found</h1>
            <p class="status-desc">You have not submitted any candidate applications recently.</p>
            <a href="dashboard.php" class="btn-back"><i class="bi bi-arrow-left"></i> Return to Dashboard</a>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
