<?php
// filename: admin/view_application.php

require_once '../config/database.php';
require_once '../includes/auth_check.php';

if($_SESSION['user_type'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if(!$id) {
    header("Location: candidate_applications.php");
    exit();
}

$stmt = $pdo->prepare("
    SELECT a.*, e.title as election_title, s.full_name, s.student_id as s_id, s.profile_photo as s_photo
    FROM candidate_applications a
    JOIN elections e ON a.election_id = e.id
    JOIN students s ON a.student_id = s.student_id
    WHERE a.id = ?
");
$stmt->execute([$id]);
$app = $stmt->fetch();

if(!$app) {
    header("Location: candidate_applications.php?err=" . urlencode("Application not found"));
    exit();
}

$message = isset($_GET['msg']) ? $_GET['msg'] : '';
$error = isset($_GET['err']) ? $_GET['err'] : '';

include '../includes/header.php';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@600;700&display=swap');

body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f0f4f8; color: #1e293b; }
[data-theme="dark"] body { background: #0d0f1a !important; color: #e2e8f0 !important; }

.page-wrap { max-width: 1000px; margin: 0 auto; padding: 1.5rem 1.75rem 4rem; }

.glass-panel {
    background:rgba(255,255,255,0.7);
    backdrop-filter:blur(20px);
    border:1px solid rgba(0,0,0,0.05);
    border-radius:24px;
    padding: 2.5rem;
    box-shadow:0 12px 32px rgba(0,0,0,0.04);
}
[data-theme="dark"] .glass-panel {
    background: rgba(20,22,35,0.85) !important;
    border-color: rgba(255,255,255,0.08) !important;
}

.profile-header {
    display: flex; align-items: center; gap: 2rem; margin-bottom: 2.5rem;
    padding-bottom: 2rem; border-bottom: 1px solid rgba(0,0,0,0.06);
}
[data-theme="dark"] .profile-header { border-bottom-color: rgba(255,255,255,0.06); }

.avatar {
    width: 140px; height: 140px; border-radius: 50%; object-fit: cover;
    border: 6px solid white; box-shadow: 0 8px 24px rgba(0,0,0,0.08);
}
[data-theme="dark"] .avatar { border-color: #1e293b; }

.avatar-placeholder {
    width: 140px; height: 140px; border-radius: 50%;
    background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
    display: flex; align-items: center; justify-content: center;
    font-size: 4rem; color: #4338ca; border: 6px solid white;
}
[data-theme="dark"] .avatar-placeholder { border-color: #1e293b; }

.name { font-family: 'Sora', sans-serif; font-size: 2rem; font-weight: 800; margin-bottom: 0.25rem; }
.meta-info { font-size: 0.95rem; color: #64748b; font-weight: 500; margin-bottom: 0.75rem; display:flex; gap: 1rem; align-items: center;}
[data-theme="dark"] .meta-info { color: #94a3b8; }

.status-badge {
    padding: 0.4rem 1rem; border-radius: 99px; font-size: 0.8rem; font-weight: 800; text-transform: uppercase;
}
.status-pending { background: rgba(251,191,36,0.15); color: #b45309; border: 1px solid rgba(251,191,36,0.3); }
.status-approved { background: rgba(34,197,94,0.15); color: #15803d; border: 1px solid rgba(34,197,94,0.3); }
.status-rejected { background: rgba(239,68,68,0.15); color: #b91c1c; border: 1px solid rgba(239,68,68,0.3); }

[data-theme="dark"] .status-pending { color: #fcd34d; }
[data-theme="dark"] .status-approved { color: #86efac; }
[data-theme="dark"] .status-rejected { color: #fca5a5; }

.section-title { font-size: 0.85rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem; }
.content-box {
    background: rgba(255,255,255,0.6); border: 1px solid rgba(0,0,0,0.04);
    padding: 1.5rem; border-radius: 16px; margin-bottom: 1.5rem;
    font-size: 0.95rem; line-height: 1.7; color: #334155;
}
[data-theme="dark"] .content-box { background: rgba(15,17,27,0.5); border-color: rgba(255,255,255,0.04); color: #cbd5e1; }

.action-bar {
    display: flex; gap: 1rem; margin-top: 3rem; padding-top: 2rem; border-top: 1px solid rgba(0,0,0,0.06);
}
[data-theme="dark"] .action-bar { border-top-color: rgba(255,255,255,0.06); }

.btn-approve {
    background: linear-gradient(135deg, #10b981, #059669); color: white; border: none;
    padding: 0.85rem 2rem; border-radius: 99px; font-weight: 700; cursor: pointer;
    box-shadow: 0 4px 14px rgba(16,185,129,0.3); transition: all 0.2s; text-decoration: none;
}
.btn-approve:hover { transform: translateY(-2px); color: white; box-shadow: 0 8px 24px rgba(16,185,129,0.4); }

.btn-reject {
    background: white; color: #ef4444; border: 1.5px solid rgba(239,68,68,0.2);
    padding: 0.85rem 2rem; border-radius: 99px; font-weight: 700; cursor: pointer;
    transition: all 0.2s;
}
.btn-reject:hover { background: rgba(239,68,68,0.05); border-color: #ef4444; }
[data-theme="dark"] .btn-reject { background: rgba(20,22,35,0.8); }

.btn-back {
    color: #64748b; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; margin-bottom: 1.5rem; transition: color 0.2s;
}
.btn-back:hover { color: #1e293b; }
[data-theme="dark"] .btn-back { color: #94a3b8; }
[data-theme="dark"] .btn-back:hover { color: #e2e8f0; }

.reject-reason-box {
    background: rgba(239,68,68,0.05); border: 1px solid rgba(239,68,68,0.2);
    padding: 1.5rem; border-radius: 16px; margin-bottom: 1.5rem; color: #991b1b;
}
[data-theme="dark"] .reject-reason-box { color: #fca5a5; }

/* Modal */
.modal-content { border-radius: 24px; overflow: hidden; border: none; }
[data-theme="dark"] .modal-content { background: rgba(20,22,35,0.95); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.1); }
.modal-header { border-bottom: 1px solid rgba(0,0,0,0.05); padding: 1.5rem; }
[data-theme="dark"] .modal-header { border-bottom-color: rgba(255,255,255,0.05); }
.modal-title { font-family: 'Sora', sans-serif; font-weight: 800; font-size: 1.2rem; color: #1e293b; }
[data-theme="dark"] .modal-title { color: #e2e8f0; }
.modal-body { padding: 1.5rem; }
.modal-footer { border-top: 1px solid rgba(0,0,0,0.05); padding: 1.5rem; }
[data-theme="dark"] .modal-footer { border-top-color: rgba(255,255,255,0.05); }
.form-control { border-radius: 12px; padding: 0.8rem; background: rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.1); }
.form-control:focus { box-shadow: none; border-color: #6366f1; background: white; }
[data-theme="dark"] .form-control { background: rgba(0,0,0,0.2); border-color: rgba(255,255,255,0.1); color: white; }
[data-theme="dark"] .form-control:focus { background: rgba(0,0,0,0.4); border-color: #818cf8; color: white; }

</style>

<div class="page-wrap">
    <a href="candidate_applications.php" class="btn-back"><i class="bi bi-arrow-left"></i> Back to Applications</a>

    <?php if($message): ?>
    <div class="alert alert-success d-flex align-items-center gap-2 mb-4 rounded-4 border-0 shadow-sm">
        <i class="bi bi-check-circle-fill"></i>
        <span><?php echo htmlspecialchars($message); ?></span>
    </div>
    <?php endif; ?>
    <?php if($error): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-4 rounded-4 border-0 shadow-sm">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <span><?php echo htmlspecialchars($error); ?></span>
    </div>
    <?php endif; ?>

    <div class="glass-panel">
        <div class="profile-header">
            <?php 
            $photo_path = '';
            if(!empty($app['profile_photo']) && file_exists('../'.$app['profile_photo'])) {
                $photo_path = '../'.$app['profile_photo'];
            } elseif(!empty($app['s_photo']) && file_exists('../'.$app['s_photo'])) {
                $photo_path = '../'.$app['s_photo'];
            }
            ?>
            <?php if($photo_path): ?>
                <img src="<?php echo $photo_path; ?>" class="avatar" alt="Photo">
            <?php else: ?>
                <div class="avatar-placeholder"><i class="bi bi-person-fill"></i></div>
            <?php endif; ?>
            
            <div>
                <div class="name"><?php echo htmlspecialchars($app['full_name']); ?></div>
                <div class="meta-info">
                    <span><i class="bi bi-person-badge"></i> <?php echo htmlspecialchars($app['s_id']); ?></span>
                    <span><i class="bi bi-tag-fill text-indigo-500"></i> <?php echo htmlspecialchars($app['position_applied']); ?></span>
                    <span><i class="bi bi-calendar3"></i> <?php echo htmlspecialchars($app['election_title']); ?></span>
                </div>
                <div>
                    <span class="status-badge status-<?php echo $app['status']; ?>"><?php echo ucfirst($app['status']); ?></span>
                    <span style="font-size: 0.8rem; color: #94a3b8; margin-left: 0.75rem;">Submitted: <?php echo date('M d, Y', strtotime($app['submitted_at'])); ?></span>
                </div>
            </div>
        </div>

        <?php if($app['status'] == 'rejected' && !empty($app['rejection_reason'])): ?>
            <div class="section-title">Rejection Reason</div>
            <div class="reject-reason-box">
                <?php echo nl2br(htmlspecialchars($app['rejection_reason'])); ?>
            </div>
        <?php endif; ?>



        <div class="section-title">Achievements & Credentials</div>
        <div class="content-box">
            <?php echo !empty($app['achievements']) ? nl2br(htmlspecialchars($app['achievements'])) : '<em style="opacity:0.5;">No achievements provided</em>'; ?>
        </div>

        <div class="action-bar">
            <?php if($app['status'] == 'pending'): ?>
                <a href="approve_application.php?action=approve&id=<?php echo $app['id']; ?>" class="btn-approve" onclick="return confirm('Are you sure you want to approve this candidate?');">
                    <i class="bi bi-check2-circle"></i> Approve Application
                </a>
                <button type="button" class="btn-reject" data-bs-toggle="modal" data-bs-target="#rejectModal">
                    <i class="bi bi-x-circle"></i> Reject Application
                </button>
            <?php elseif($app['status'] == 'approved'): ?>
                <button type="button" class="btn-reject" data-bs-toggle="modal" data-bs-target="#rejectModal">
                    <i class="bi bi-x-circle"></i> Reject Application
                </button>
            <?php elseif($app['status'] == 'rejected'): ?>
                <a href="approve_application.php?action=reopen&id=<?php echo $app['id']; ?>" class="btn-approve" style="background: linear-gradient(135deg, #f59e0b, #d97706); box-shadow: 0 4px 14px rgba(245,158,11,0.3);" onclick="return confirm('Reopen this application and set it back to pending?');">
                    <i class="bi bi-arrow-counterclockwise"></i> Reopen Application
                </a>
            <?php endif; ?>
            <a href="approve_application.php?action=delete&id=<?php echo $app['id']; ?>" class="btn-reject" style="margin-left: auto; border-color: #dc3545; color: #dc3545;" onclick="return confirm('Are you sure you want to permanently delete this application?');">
                <i class="bi bi-trash"></i> Delete Application
            </a>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form action="approve_application.php" method="POST">
        <div class="modal-header">
          <h5 class="modal-title">Reject Application</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" <?php echo isset($_COOKIE['cv_theme']) && $_COOKIE['cv_theme'] == 'dark' ? 'style="filter:invert(1);"' : ''; ?>></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" value="reject">
          <input type="hidden" name="id" value="<?php echo $app['id']; ?>">
          
          <div class="mb-3">
            <label class="form-label" style="font-weight: 700; font-size: 0.9rem;">Reason for Rejection (Optional)</label>
            <textarea class="form-control" name="rejection_reason" rows="4" placeholder="Briefly explain why this application is being rejected..."></textarea>
            <div class="form-text" style="font-size: 0.75rem; margin-top: 0.5rem; opacity: 0.7;">This reason will be visible to the student.</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold">Submit Rejection</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
