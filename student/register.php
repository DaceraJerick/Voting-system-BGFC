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
  background: #f5f7fb;
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
    background: white;
    width: 100%;
    max-width: 450px;
    border-radius: 28px;
    padding: 2.5rem 2rem;
    box-shadow: 0 10px 40px rgba(0,0,0,0.03);
    border: 1px solid #f1f5f9;
}

.auth-logo {
    width: 64px; height: 64px;
    background: #6366f1;
    border-radius: 18px;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1.5rem;
    color: white; font-size: 1.8rem;
    box-shadow: 0 8px 16px rgba(99,102,241,0.2);
}

.auth-title {
    font-size: 1.5rem; font-weight: 800;
    color: #1e1b4b; text-align: center;
    margin-bottom: 0.5rem;
}
.auth-sub {
    font-size: 0.82rem; color: #64748b;
    text-align: center; margin-bottom: 2rem;
    font-weight: 600;
}

.form-label {
    font-size: 0.72rem; font-weight: 800;
    color: #64748b; text-transform: uppercase;
    letter-spacing: 0.05em; margin-bottom: 0.5rem;
}

.auth-input {
    width: 100%; border: 1.5px solid #f1f5f9;
    border-radius: 12px; padding: 0.75rem 1rem;
    font-size: 0.9rem; font-weight: 600;
    color: #1e1b4b; margin-bottom: 1rem;
    transition: border-color 0.2s;
}
.auth-input:focus { border-color: #6366f1; outline: none; }

.btn-auth {
    width: 100%; background: #6366f1; color: white;
    border: none; border-radius: 16px; padding: 1rem;
    font-weight: 800; font-size: 0.95rem;
    box-shadow: 0 8px 24px rgba(99,102,241,0.2);
    margin-top: 1rem;
}

.auth-footer {
    text-align: center; margin-top: 1.5rem;
    font-size: 0.82rem; color: #64748b;
    font-weight: 600;
}
.auth-link { color: #6366f1; text-decoration: none; font-weight: 800; }

.error-msg {
    background: #fef2f2; border: 1px solid #fee2e2;
    padding: 0.75rem; border-radius: 12px;
    color: #ef4444; font-size: 0.78rem; font-weight: 700;
    margin-bottom: 1.5rem; text-align: center;
}

.success-state {
    text-align: center; padding: 1rem 0;
}
.success-icon {
    font-size: 3rem; color: #22c55e; margin-bottom: 1rem;
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