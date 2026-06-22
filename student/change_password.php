<?php
// filename: student/change_password.php
// location: student/change_password.php

require_once '../config/database.php';
require_once '../includes/auth_check.php';

if($_SESSION['user_type'] != 'student') {
    header("Location: ../index.php");
    exit();
}

$student_id = $_SESSION['student_id'];
$error = '';
$success = '';

// Get current student data
$stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    if(password_verify($current_password, $student['password'])) {
        if($new_password === $confirm_password) {
            if(strlen($new_password) >= 6) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("UPDATE students SET password = ? WHERE student_id = ?");
                if($stmt->execute([$hashed_password, $student_id])) {
                    $success = "Your password has been changed successfully!";
                } else {
                    $error = "Error changing password. Please try again.";
                }
            } else {
                $error = "Password must be at least 6 characters long!";
            }
        } else {
            $error = "New passwords do not match!";
        }
    } else {
        $error = "Current password is incorrect!";
    }
}

include '../includes/header.php';
?>

<style>
.password-container {
    max-width: 600px;
    margin: 40px auto;
    padding: 0 1rem;
}

.password-card {
    background: rgba(8,20,34,0.96);
    border-radius: 30px;
    padding: 40px;
    box-shadow: 0 30px 80px rgba(0,0,0,0.45);
    border: 1px solid rgba(75,180,127,0.15);
    color: #e7f5e5;
}

.password-header {
    text-align: center;
    margin-bottom: 30px;
}

.password-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #0f7f63, #22c55e);
    border-radius: 25px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 40px;
    color: white;
    margin: 0 auto 20px;
}

.strength-meter {
    height: 6px;
    background: rgba(255,255,255,0.08);
    border-radius: 3px;
    margin-top: 10px;
    overflow: hidden;
}

.strength-bar {
    height: 100%;
    width: 0;
    transition: width 0.3s, background 0.3s;
}

.form-control,
.form-control-lg {
    background: rgba(255,255,255,0.04);
    color: #e8f9e8;
    border: 1px solid rgba(255,255,255,0.08);
}

.form-control:focus,
.form-control-lg:focus {
    background: rgba(15,127,99,0.12);
    border-color: #0f7f63;
    box-shadow: 0 0 0 4px rgba(15,127,99,0.1);
    color: #e8f9e8;
}

.text-muted {
    color: #97bda9 !important;
}

.btn-outline-secondary {
    color: #c7dcc9;
    border-color: rgba(255,255,255,0.16);
}

.btn-outline-secondary:hover {
    background: rgba(255,255,255,0.08);
}
</style>

<div class="password-container">
    <div class="password-card">
        <div class="password-header">
            <div class="password-icon">
                <i class="bi bi-key"></i>
            </div>
            <h2 class="fw-bold">Change Password</h2>
            <p class="text-muted">Secure your account with a strong password</p>
        </div>
        
        <?php if($success): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill"></i>
                <?php echo $success; ?>
            </div>
            <div class="text-center mt-4">
                <a href="profile.php" class="btn btn-primary">
                    <i class="bi bi-arrow-left"></i> Back to Profile
                </a>
            </div>
        <?php else: ?>
            <?php if($error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-4">
                    <label class="form-label fw-bold">
                        <i class="bi bi-lock"></i> Current Password
                    </label>
                    <input type="password" name="current_password" class="form-control form-control-lg" 
                           placeholder="Enter current password" required>
                </div>
                
                <div class="mb-4">
                    <label class="form-label fw-bold">
                        <i class="bi bi-key"></i> New Password
                    </label>
                    <input type="password" name="new_password" id="new_password" class="form-control form-control-lg" 
                           placeholder="Enter new password" required>
                    <div class="strength-meter mt-2">
                        <div class="strength-bar" id="strengthBar"></div>
                    </div>
                    <small class="text-muted" id="strengthText">Minimum 6 characters</small>
                </div>
                
                <div class="mb-4">
                    <label class="form-label fw-bold">
                        <i class="bi bi-key-fill"></i> Confirm New Password
                    </label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control form-control-lg" 
                           placeholder="Confirm new password" required>
                    <small class="text-muted" id="matchText"></small>
                </div>
                
                <div class="d-grid gap-3">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-check-circle"></i> Change Password
                    </button>
                    <a href="profile.php" class="btn btn-outline-secondary btn-lg">
                        <i class="bi bi-x-circle"></i> Cancel
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
// Password strength checker
document.getElementById('new_password')?.addEventListener('input', function() {
    const password = this.value;
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');
    
    let strength = 0;
    if(password.length >= 6) strength += 25;
    if(password.length >= 8) strength += 15;
    if(password.match(/[a-z]+/)) strength += 20;
    if(password.match(/[A-Z]+/)) strength += 20;
    if(password.match(/[0-9]+/)) strength += 20;
    if(password.match(/[$@#&!]+/)) strength += 20;
    
    strength = Math.min(strength, 100);
    strengthBar.style.width = strength + '%';
    
    if(strength < 30) {
        strengthBar.style.background = '#fc8181';
        strengthText.textContent = 'Weak password';
    } else if(strength < 60) {
        strengthBar.style.background = '#fbd38d';
        strengthText.textContent = 'Medium password';
    } else if(strength < 80) {
        strengthBar.style.background = '#9ae6b4';
        strengthText.textContent = 'Strong password';
    } else {
        strengthBar.style.background = '#48bb78';
        strengthText.textContent = 'Very strong password';
    }
});

// Password match checker
document.getElementById('confirm_password')?.addEventListener('input', function() {
    const password = document.getElementById('new_password').value;
    const confirm = this.value;
    const matchText = document.getElementById('matchText');
    
    if(password === confirm) {
        matchText.innerHTML = '<span style="color: #48bb78;"><i class="bi bi-check-circle"></i> Passwords match</span>';
    } else {
        matchText.innerHTML = '<span style="color: #fc8181;"><i class="bi bi-exclamation-circle"></i> Passwords do not match</span>';
    }
});
</script>

<?php include '../includes/footer.php'; ?>