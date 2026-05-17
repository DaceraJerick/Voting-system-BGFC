<?php
// filename: student/profile.php
// location: student/profile.php

require_once '../config/database.php';
require_once '../includes/auth_check.php';

if($_SESSION['user_type'] != 'student') {
    header("Location: ../index.php");
    exit();
}

$student_id = $_SESSION['student_id'];
$success = '';
$error = '';

// Get current student data
$stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

// Handle profile update
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Unified Profile Update
    if(isset($_POST['update_profile'])) {
        $new_name = trim($_POST['full_name']);
        $new_contact = trim($_POST['contact_number']);
        $changes = 0;
        
        if(!empty($new_name)) {
            try {
                $pdo->beginTransaction();
                
                // Update Name if changed
                if($new_name != $student['full_name']) {
                    $log = $pdo->prepare("INSERT INTO student_profile_history (student_id, field_name, old_value, new_value) VALUES (?, ?, ?, ?)");
                    $log->execute([$student_id, 'full_name', $student['full_name'], $new_name]);
                    
                    $stmt = $pdo->prepare("UPDATE students SET full_name = ? WHERE student_id = ?");
                    $stmt->execute([$new_name, $student_id]);
                    
                    $_SESSION['full_name'] = $new_name;
                    $changes++;
                }
                
                // Update Contact if changed
                if($new_contact != ($student['contact_number'] ?? '')) {
                    $log = $pdo->prepare("INSERT INTO student_profile_history (student_id, field_name, old_value, new_value) VALUES (?, ?, ?, ?)");
                    $log->execute([$student_id, 'contact_number', $student['contact_number'] ?? '', $new_contact]);
                    
                    $stmt = $pdo->prepare("UPDATE students SET contact_number = ? WHERE student_id = ?");
                    $stmt->execute([$new_contact, $student_id]);
                    $changes++;
                }
                
                $pdo->commit();
                
                if($changes > 0) {
                    $success = "Your profile has been updated successfully!";
                    // Refresh data
                    $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ?");
                    $stmt->execute([$student_id]);
                    $student = $stmt->fetch();
                } else {
                    $error = "No changes were detected.";
                }
                
            } catch(PDOException $e) {
                if($pdo->inTransaction()) $pdo->rollBack();
                $error = "Error updating profile: " . $e->getMessage();
            }
        } else {
            $error = "Please enter a valid full name.";
        }
    }
    
    // Update Profile Photo
    elseif(isset($_POST['update_photo'])) {
        if(isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['profile_photo']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $filesize = $_FILES['profile_photo']['size'];
            
            if(!in_array($ext, $allowed)) {
                $error = "Only JPG, JPEG, PNG, and GIF files are allowed!";
            } elseif($filesize > 5 * 1024 * 1024) {
                $error = "File size must be less than 5MB!";
            } else {
                // Create directory if not exists
                $upload_dir = '../assets/uploads/students/';
                if(!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Delete old photo if exists
                if(!empty($student['profile_photo']) && file_exists('../' . $student['profile_photo'])) {
                    unlink('../' . $student['profile_photo']);
                }
                
                // Generate unique filename
                $new_filename = 'student_' . $student_id . '_' . uniqid() . '.' . $ext;
                $upload_path = $upload_dir . $new_filename;
                
                if(move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_path)) {
                    $photo_path = 'assets/uploads/students/' . $new_filename;
                    
                    try {
                        // Log old value
                        $log = $pdo->prepare("INSERT INTO student_profile_history (student_id, field_name, old_value, new_value) VALUES (?, ?, ?, ?)");
                        $log->execute([$student_id, 'profile_photo', $student['profile_photo'] ?? '', $photo_path]);
                        
                        // Update photo
                        $stmt = $pdo->prepare("UPDATE students SET profile_photo = ? WHERE student_id = ?");
                        $stmt->execute([$photo_path, $student_id]);
                        
                        $success = "Your profile photo has been updated successfully!";
                        
                        // Refresh student data
                        $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ?");
                        $stmt->execute([$student_id]);
                        $student = $stmt->fetch();
                        
                    } catch(PDOException $e) {
                        $error = "Error updating profile photo: " . $e->getMessage();
                    }
                } else {
                    $error = "Failed to upload image!";
                }
            }
        } else {
            $error = "Please select an image to upload.";
        }
    }
    
    // Remove Profile Photo
    elseif(isset($_POST['remove_photo'])) {
        if(!empty($student['profile_photo']) && file_exists('../' . $student['profile_photo'])) {
            unlink('../' . $student['profile_photo']);
        }
        
        try {
            // Log old value
            $log = $pdo->prepare("INSERT INTO student_profile_history (student_id, field_name, old_value, new_value) VALUES (?, ?, ?, ?)");
            $log->execute([$student_id, 'profile_photo', $student['profile_photo'] ?? '', '']);
            
            // Remove photo
            $stmt = $pdo->prepare("UPDATE students SET profile_photo = NULL WHERE student_id = ?");
            $stmt->execute([$student_id]);
            
            $success = "Your profile photo has been removed.";
            
            // Refresh student data
            $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ?");
            $stmt->execute([$student_id]);
            $student = $stmt->fetch();
            
        } catch(PDOException $e) {
            $error = "Error removing profile photo: " . $e->getMessage();
        }
    }
}

// Get profile history
$history = $pdo->prepare("
    SELECT * FROM student_profile_history 
    WHERE student_id = ? 
    ORDER BY changed_at DESC 
    LIMIT 10
");
$history->execute([$student_id]);
$history = $history->fetchAll();

include '../includes/header.php';
?>

<style>
/* ── Soft Glass Profile Styles ── */
.profile-banner { background: #6366f1; color: white; padding: 3rem 2rem 7rem; border-radius: 30px; margin-bottom: 2rem; position: relative; overflow: hidden; }
.profile-banner::after { content: ''; position: absolute; width: 300px; height: 300px; background: rgba(255,255,255,0.05); border-radius: 50%; top: -100px; right: -100px; }
.profile-content { margin: -5rem 1.5rem 0; position: relative; z-index: 10; }
.badge-v { background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); padding: 0.35rem 1rem; border-radius: 99px; font-size: 0.8rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; }
.profile-grid-desktop { display: grid; grid-template-columns: 1fr 400px; gap: 2rem; }
.avatar-wrap { width: 130px; height: 130px; border-radius: 40px; position: relative; margin-bottom: 2rem; background: white; padding: 6px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
.profile-img { width: 100%; height: 100%; border-radius: 34px; object-fit: cover; }
.avatar-placeholder { width: 100%; height: 100%; border-radius: 34px; background: #eef2ff; color: #6366f1; display: flex; align-items: center; justify-content: center; font-size: 3rem; }
.btn-cam { position: absolute; bottom: -5px; right: -5px; background: #6366f1; color: white; width: 36px; height: 36px; border-radius: 12px; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 3px solid white; transition: all 0.2s; }
.btn-cam:hover { transform: scale(1.1); background: #4f46e5; }
.app-input { width: 100%; background: rgba(255, 255, 255, 0.6); border: 1px solid #e2e8f0; padding: 0.8rem 1.25rem; border-radius: 16px; margin-bottom: 1.25rem; font-weight: 600; color: #1e293b; transition: all 0.2s; }
.app-input:focus { background: white; border-color: #6366f1; outline: none; box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1); }
.app-input[readonly] { background: #f8f9fa; color: #94a3b8; cursor: not-allowed; }
.setting-label { font-size: 0.75rem; font-weight: 800; color: #1e293b; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 0.5rem; margin-left: 0.25rem; }
.btn-save { background: #6366f1; color: white; border: none; width: 100%; padding: 1rem; border-radius: 16px; font-weight: 800; transition: all 0.2s; box-shadow: 0 4px 15px rgba(99, 102, 241, 0.2); }
.btn-save:hover { background: #4f46e5; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(99, 102, 241, 0.3); }
.tile-btn { background: rgba(255, 255, 255, 0.5); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.4); border-radius: 20px; padding: 1.5rem; text-decoration: none; color: inherit; flex: 1; display: flex; flex-direction: column; align-items: center; gap: 0.25rem; transition: all 0.2s; }
.tile-btn:hover { background: rgba(255, 255, 255, 0.8); border-color: #6366f1; transform: translateY(-3px); }
.tile-icon { width: 44px; height: 44px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; margin-bottom: 0.5rem; }
.tile-lbl { font-size: 0.85rem; font-weight: 800; color: #1e293b; }
.tile-sub { font-size: 0.65rem; color: #64748b; font-weight: 600; }
.tiles-row { display: flex; gap: 1rem; }
.bg-gold { background: #fefce8; color: #ca8a04; }
.bg-blue { background: #eff6ff; color: #2563eb; }

@media (max-width: 991px) {
    .profile-grid-desktop { grid-template-columns: 1fr; }
    .profile-banner { padding: 3rem 1.5rem 6rem; border-radius: 25px; text-align: center; }
    .profile-content { margin: -4rem 0.5rem 80px; }
    .avatar-wrap { margin: 0 auto 2rem; }
    .badge-v { margin: 0 auto; }
}
@media (min-width: 992px) {
    .profile-banner { padding: 4rem 3rem 8rem; }
    .profile-content { margin: -6rem 3rem 0; }
}
</style>

<div class="profile-banner">
    <div class="container-fluid">
        <h2 class="fw-800 mb-2" style="font-size: 2.2rem;"><?php echo htmlspecialchars($student['full_name']); ?></h2>
        <div class="badge-v"><i class="bi bi-patch-check-fill"></i> Verified Student</div>
    </div>
</div>

<div class="profile-content">
    <div class="profile-grid-desktop">
        <!-- Left Col -->
        <div class="profile-main-col">
            <div class="avatar-wrap">
                <?php if(!empty($student['profile_photo']) && file_exists('../' . $student['profile_photo'])): ?>
                    <img src="../<?php echo $student['profile_photo']; ?>" class="profile-img" alt="Avatar">
                <?php else: ?>
                    <div class="avatar-placeholder">
                        <i class="bi bi-person-fill"></i>
                    </div>
                <?php endif; ?>
                <label for="profile_photo" class="btn-cam">
                    <i class="bi bi-camera-fill"></i>
                </label>
            </div>

            <!-- Hidden upload form -->
            <form method="POST" action="" enctype="multipart/form-data" id="photoForm">
                <input type="file" name="profile_photo" id="profile_photo" accept="image/*" style="display:none;" onchange="this.form.submit()">
                <input type="hidden" name="update_photo" value="1">
            </form>

            <?php if($success): ?>
                <div class="app-card" style="border-color:#22c55e; background:#f0fdf4; padding:0.85rem 1rem;">
                    <p class="small fw-bold text-success mb-0"><i class="bi bi-check-circle-fill me-2"></i> <?php echo $success; ?></p>
                </div>
            <?php endif; ?>
            <?php if($error): ?>
                <div class="app-card" style="border-color:#ef4444; background:#fef2f2; padding:0.85rem 1rem;">
                    <p class="small fw-bold text-danger mb-0"><i class="bi bi-exclamation-circle-fill me-2"></i> <?php echo $error; ?></p>
                </div>
            <?php endif; ?>

            <div class="section-label">Account settings</div>
            <div class="app-card">
                <form method="POST" action="">
                    <span class="setting-label">Full Name</span>
                    <input type="text" name="full_name" class="app-input" value="<?php echo htmlspecialchars($student['full_name']); ?>" required>
                    
                    <span class="setting-label">Contact Number</span>
                    <input type="tel" name="contact_number" class="app-input" value="<?php echo htmlspecialchars($student['contact_number'] ?? ''); ?>" placeholder="09xxxxxxxxx">
                    
                    <span class="setting-label">Student ID</span>
                    <input type="text" class="app-input" value="<?php echo htmlspecialchars($student['student_id']); ?>" readonly>

                    <button type="submit" name="update_profile" class="btn-save mt-2">Save profile changes</button>
                </form>
            </div>
        </div>

        <!-- Right Col -->
        <div class="profile-side-col">
            <div class="section-label">Quick actions</div>
            <div class="tiles-row mb-4">
                <a href="change_password.php" class="tile-btn">
                    <div class="tile-icon bg-gold"><i class="bi bi-shield-lock-fill"></i></div>
                    <span class="tile-lbl">Security</span>
                    <span class="tile-sub">Password</span>
                </a>
                <a href="#history" class="tile-btn">
                    <div class="tile-icon bg-blue"><i class="bi bi-clock-history"></i></div>
                    <span class="tile-lbl">History</span>
                    <span class="tile-sub">Logs</span>
                </a>
            </div>

            <?php if(count($history) > 0): ?>
            <div id="history">
                <div class="section-label">Recent Activity</div>
                <div class="app-card p-0 overflow-hidden">
                    <ul class="list-unstyled mb-0">
                        <?php foreach($history as $item): ?>
                        <li class="p-4 border-bottom border-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="small fw-bold text-indigo-900 d-block"><?php echo ucfirst(str_replace('_', ' ', $item['field_name'])); ?> Updated</span>
                                    <span class="text-muted" style="font-size:0.75rem;">Successfully updated on your account.</span>
                                </div>
                                <span class="small text-muted" style="font-size:0.65rem;"><?php echo date('M d', strtotime($item['changed_at'])); ?></span>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>



<?php include '../includes/footer.php'; ?>