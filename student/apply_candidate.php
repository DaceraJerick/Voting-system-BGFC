<?php
// filename: student/apply_candidate.php

require_once '../config/database.php';
require_once '../includes/auth_check.php';

if($_SESSION['user_type'] != 'student') {
    header("Location: ../index.php");
    exit();
}

$student_id = $_SESSION['student_id'];

// Check for open application windows
$open_election = $pdo->query("
    SELECT * FROM elections 
    WHERE application_start IS NOT NULL
    AND application_end IS NOT NULL
    AND NOW() BETWEEN application_start AND application_end
    ORDER BY application_end ASC
    LIMIT 1
")->fetch();

if(!$open_election) {
    header("Location: dashboard.php?err=" . urlencode("There are no open candidate application windows at this time."));
    exit();
}

if($open_election['is_paused'] == 1) {
    header("Location: dashboard.php?err=" . urlencode("Candidate application is temporarily suspended because the election is paused."));
    exit();
}

// Check if student already applied for this election
$stmt = $pdo->prepare("SELECT id FROM candidate_applications WHERE student_id = ? AND election_id = ?");
$stmt->execute([$student_id, $open_election['id']]);
if($stmt->fetch()) {
    header("Location: my_application.php");
    exit();
}

$message = '';
$error = '';

// Get student details
$stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

// Get positions for this election
$stmt = $pdo->prepare("SELECT * FROM positions WHERE election_id = ? ORDER BY display_order");
$stmt->execute([$open_election['id']]);
$positions = $stmt->fetchAll();

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_application'])) {
    $position_id = $_POST['position_id'] ?? '';
    
    // Convert position ID to position name just for display/storage if needed,
    // but our schema stores `position_applied` as varchar. Let's find the name.
    $position_name = '';
    foreach($positions as $pos) {
        if($pos['id'] == $position_id) {
            $position_name = $pos['position_name'];
            break;
        }
    }
    
    $platform = '';
    $biography = '';
    $achievements = trim($_POST['achievements'] ?? '');
    
    if(empty($position_name)) {
        $error = "Please select a valid position.";
    } else {
        $photo_path = null;
        
        // Handle photo upload
        if(isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['profile_photo']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $filesize = $_FILES['profile_photo']['size'];
            
            if(in_array($ext, $allowed) && $filesize <= 5 * 1024 * 1024) {
                $upload_dir = '../assets/uploads/candidates/';
                if(!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
                
                $new_filename = uniqid() . '_app_' . preg_replace('/[^a-zA-Z0-9.]/', '', $filename);
                if(move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_dir . $new_filename)) {
                    $photo_path = 'assets/uploads/candidates/' . $new_filename;
                }
            } else {
                $error = "Invalid photo format or size (max 5MB).";
            }
        }
        
        if(empty($error)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO candidate_applications 
                    (election_id, student_id, position_applied, platform_statement, biography, achievements, profile_photo, status, submitted_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([
                    $open_election['id'],
                    $student_id,
                    $position_name,
                    $platform,
                    $biography,
                    $achievements,
                    $photo_path
                ]);
                header("Location: my_application.php?msg=" . urlencode("Your application has been submitted and is pending review."));
                exit();
            } catch(Exception $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}

include '../includes/header.php';
?>
<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@600;700&display=swap');
body { background: #f8fafc; color: #0f172a; font-family: 'Plus Jakarta Sans', sans-serif; }
[data-theme="dark"] body { background: #0f172a !important; color: #e2e8f0 !important; }

.page-wrap { max-width: 900px; margin: 2rem auto; padding: 0 1.5rem 4rem; }

.form-panel {
    background: white; border-radius: 24px; padding: 3rem;
    box-shadow: 0 12px 32px rgba(0,0,0,0.04);
}
[data-theme="dark"] .form-panel { background: rgba(30,41,59,0.8); border: 1px solid rgba(255,255,255,0.05); }

.page-title { font-family: 'Sora', sans-serif; font-size: 2rem; font-weight: 800; margin-bottom: 0.5rem; }
.page-desc { color: #64748b; font-size: 1rem; margin-bottom: 2rem; }
[data-theme="dark"] .page-desc { color: #94a3b8; }

.form-label { font-weight: 700; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.03em; color: #475569; }
[data-theme="dark"] .form-label { color: #cbd5e1; }
.form-control, .form-select {
    padding: 0.85rem 1.15rem; border-radius: 12px; background: rgba(248,250,252,1); border: 2px solid transparent; transition: all 0.2s;
}
.form-control:focus, .form-select:focus { background: white; border-color: #6366f1; box-shadow: 0 0 0 4px rgba(99,102,241,0.1); }
[data-theme="dark"] .form-control, [data-theme="dark"] .form-select { background: rgba(15,23,42,0.8); border-color: rgba(255,255,255,0.1); color: white; }
[data-theme="dark"] .form-control:focus, [data-theme="dark"] .form-select:focus { border-color: #818cf8; }

.form-control[readonly] { background: rgba(0,0,0,0.02); opacity: 0.7; }
[data-theme="dark"] .form-control[readonly] { background: rgba(255,255,255,0.02); }

.section-divider { height: 1px; background: rgba(0,0,0,0.06); margin: 2.5rem 0; }
[data-theme="dark"] .section-divider { background: rgba(255,255,255,0.06); }

.btn-submit {
    background: linear-gradient(135deg, #6366f1, #4f46e5); color: white; font-weight: 800; border-radius: 99px;
    padding: 1rem 2.5rem; border: none; font-size: 1.1rem; box-shadow: 0 8px 24px rgba(99,102,241,0.3); transition: all 0.2s;
}
.btn-submit:hover { transform: translateY(-3px); box-shadow: 0 12px 32px rgba(99,102,241,0.4); color: white; }
</style>

<div class="page-wrap">
    <a href="dashboard.php" class="text-decoration-none fw-bold mb-4 d-inline-block" style="color: #64748b;">
        <i class="bi bi-arrow-left"></i> Back to Dashboard
    </a>

    <?php if($error): ?>
        <div class="alert alert-danger fw-bold rounded-4 border-0 mb-4 shadow-sm">
            <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="form-panel">
        <h1 class="page-title">Run for Office</h1>
        <p class="page-desc">Complete this application to run as a candidate in the <strong><?php echo htmlspecialchars($open_election['title']); ?></strong>.</p>
        
        <form method="POST" enctype="multipart/form-data">
            <!-- Basic Info (Read Only) -->
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($student['full_name']); ?>" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Student ID</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($student['student_id']); ?>" readonly>
                </div>
            </div>

            <div class="section-divider"></div>

            <div class="mb-4">
                <label class="form-label">Position Applying For <span class="text-danger">*</span></label>
                <select name="position_id" class="form-select" required>
                    <option value="">-- Select Position --</option>
                    <?php foreach($positions as $pos): ?>
                        <option value="<?php echo $pos['id']; ?>"><?php echo htmlspecialchars($pos['position_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>


            
            <div class="mb-4">
                <label class="form-label">Achievements & Credentials</label>
                <textarea name="achievements" class="form-control" rows="3" placeholder="List your relevant achievements, leadership roles, etc."></textarea>
            </div>

            <div class="mb-5">
                <label class="form-label">Profile Photo (Optional)</label>
                <input type="file" name="profile_photo" class="form-control" accept="image/*">
                <div class="form-text mt-2 opacity-75">If left empty, the admin will use your current student profile photo (if available) or assign a placeholder. Max 5MB.</div>
            </div>

            <div class="text-end mt-4">
                <button type="submit" name="submit_application" class="btn-submit">
                    Submit Application <i class="bi bi-arrow-right ms-2"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
