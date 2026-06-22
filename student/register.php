<?php
// filename: student/register.php
// location: student/register.php

require_once '../config/database.php';

if(isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'student') {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = trim($_POST['student_id']);
    $full_name = trim($_POST['full_name']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if(empty($student_id) || empty($full_name) || empty($password)) {
        $error = "All fields are required!";
    } elseif($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif(strlen($password) < 6) {
        $error = "Password must be at least 6 characters!";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM eligible_ids WHERE student_id = ? AND is_registered = 0");
        $stmt->execute([$student_id]);
        $eligible = $stmt->fetch();
        
        if($eligible) {
            $check = $pdo->prepare("SELECT COUNT(*) FROM students WHERE student_id = ?");
            $check->execute([$student_id]);
            if($check->fetchColumn() > 0) {
                $error = "This ID is already registered!";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                try {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("INSERT INTO students (student_id, full_name, password) VALUES (?, ?, ?)");
                    $stmt->execute([$student_id, $full_name, $hashed_password]);
                    $stmt = $pdo->prepare("UPDATE eligible_ids SET is_registered = 1 WHERE student_id = ?");
                    $stmt->execute([$student_id]);
                    $pdo->commit();
                    $success = "Registration successful!";
                } catch(PDOException $e) {
                    $pdo->rollBack();
                    $error = "Error: " . $e->getMessage();
                }
            }
        } else {
            $error = "Invalid Student ID or already registered!";
        }
    }
}

include '../includes/header.php';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

body {
  font-family: 'Plus Jakarta Sans', sans-serif;
  background: radial-gradient(circle at top left, rgba(15,127,99,0.24), transparent 25%),
              linear-gradient(135deg, #07121f 0%, #02060b 100%);
  color: #e4f6df;
  margin: 0;
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

.auth-container {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem 1.25rem;
}

.auth-card {
    background: rgba(8,20,34,0.96);
    width: 100%;
    max-width: 480px;
    border-radius: 30px;
    padding: 2.5rem 2rem;
    box-shadow: 0 30px 80px rgba(0,0,0,0.5);
    border: 1px solid rgba(75,180,127,0.14);
}

.auth-logo {
    width: 64px; height: 64px;
    background: #0f7f63;
    border-radius: 18px;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1.5rem;
    color: white; font-size: 1.8rem;
    box-shadow: 0 14px 40px rgba(15,127,99,0.28);
}

.auth-title {
    font-size: 1.5rem; font-weight: 800;
    color: #e6f7e8; text-align: center;
    margin-bottom: 0.5rem;
}
.auth-sub {
    font-size: 0.82rem; color: #9ac9ae;
    text-align: center; margin-bottom: 2rem;
    font-weight: 600;
}

.form-label {
    font-size: 0.72rem; font-weight: 800;
    color: #8db89e; text-transform: uppercase;
    letter-spacing: 0.05em; margin-bottom: 0.5rem;
}

.auth-input {
    width: 100%; border: 1.5px solid rgba(255,255,255,0.08);
    border-radius: 16px; padding: 0.75rem 1rem;
    font-size: 0.9rem; font-weight: 600;
    color: #e8f9e8; margin-bottom: 1rem;
    background: rgba(255,255,255,0.04);
    transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
}
.auth-input:focus { border-color: #0f7f63; outline: none; background: rgba(15,127,99,0.12); box-shadow: 0 0 0 4px rgba(15,127,99,0.12); }

.btn-auth {
    width: 100%; background: linear-gradient(135deg, #0f7f63 0%, #22c55e 100%);
    color: white;
    border: none; border-radius: 18px; padding: 1rem;
    font-weight: 800; font-size: 0.95rem;
    box-shadow: 0 16px 40px rgba(15,127,99,0.3);
    margin-top: 1rem;
}

.auth-footer {
    text-align: center; margin-top: 1.5rem;
    font-size: 0.82rem; color: #a4c9b3;
    font-weight: 600;
}
.auth-link { color: #93e0b4; text-decoration: none; font-weight: 800; }

.error-msg {
    background: rgba(248,113,113,0.16); border: 1px solid rgba(248,113,113,0.28);
    padding: 0.75rem; border-radius: 12px;
    color: #ffc7c7; font-size: 0.78rem; font-weight: 700;
    margin-bottom: 1.5rem; text-align: center;
}

.success-state {
    text-align: center; padding: 1rem 0;
}
.success-icon {
    font-size: 3rem; color: #7ee8a5; margin-bottom: 1rem;
}

</style>

<div class="auth-container">
    <div class="auth-card">
        <?php if($success): ?>
            <div class="success-state">
                <div class="success-icon"><i class="bi bi-check-circle-fill"></i></div>
                <h2 class="auth-title">Welcome Aboard!</h2>
                <p class="auth-sub">Your account has been created successfully.</p>
                <a href="login.php" class="btn-auth d-block text-decoration-none">Continue to Login</a>
            </div>
        <?php else: ?>
            <div class="auth-logo"><i class="bi bi-person-plus-fill"></i></div>
            <h1 class="auth-title">Voter Registration</h1>
            <p class="auth-sub">Create your account to participate</p>

            <?php if($error): ?>
                <div class="error-msg"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <label class="form-label">Student ID</label>
                <input type="text" name="student_id" class="auth-input" placeholder="e.g. 2021-001" required>

                <label class="form-label">Full Name</label>
                <input type="text" name="full_name" class="auth-input" placeholder="Enter your full name" required>

                <label class="form-label">Password</label>
                <input type="password" name="password" class="auth-input" placeholder="Create password" required>

                <label class="form-label">Confirm Password</label>
                <input type="password" name="confirm_password" class="auth-input" placeholder="Repeat password" required>

                <button type="submit" class="btn-auth">Create Account</button>
            </form>

            <div class="auth-footer">
                Already have an account? <a href="login.php" class="auth-link">Login</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>