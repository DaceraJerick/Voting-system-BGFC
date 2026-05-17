<?php
// filename: student/login.php
// location: student/login.php

require_once '../config/database.php';

if(isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'student') {
    header("Location: dashboard.php");
    exit();
}

$error = '';
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = trim($_POST['student_id']);
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    if($student && password_verify($password, $student['password'])) {
        $_SESSION['user_id'] = $student['id'];
        $_SESSION['student_id'] = $student['student_id'];
        $_SESSION['full_name'] = $student['full_name'];
        $_SESSION['user_type'] = 'student';
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Invalid Student ID or Password!";
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
    max-width: 400px;
    border-radius: 28px;
    padding: 2.5rem 2rem;
    box-shadow: 0 10px 40px rgba(0,0,0,0.03);
    border: 1px solid #f1f5f9;
}

.auth-logo {
    width: 64px; height: 64px;
    background: #1e1b4b;
    border-radius: 18px;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1.5rem;
    color: white; font-size: 1.8rem;
    box-shadow: 0 8px 16px rgba(30,27,75,0.2);
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
    border-radius: 14px; padding: 0.85rem 1rem;
    font-size: 0.95rem; font-weight: 600;
    color: #1e1b4b; margin-bottom: 1.25rem;
    transition: border-color 0.2s, background 0.2s;
}
.auth-input:focus { border-color: #6366f1; outline: none; background: #fcfdff; }

.btn-auth {
    width: 100%; background: #1e1b4b; color: white;
    border: none; border-radius: 16px; padding: 1rem;
    font-weight: 800; font-size: 0.95rem;
    box-shadow: 0 8px 24px rgba(30,27,75,0.2);
    margin-top: 0.5rem;
}

.auth-footer {
    text-align: center; margin-top: 2rem;
    font-size: 0.85rem; color: #64748b;
    font-weight: 600;
}
.auth-link { color: #6366f1; text-decoration: none; font-weight: 800; }

.error-msg {
    background: #fef2f2; border: 1px solid #fee2e2;
    padding: 0.75rem 1rem; border-radius: 12px;
    color: #ef4444; font-size: 0.8rem; font-weight: 700;
    margin-bottom: 1.5rem; text-align: center;
}

.demo-pill {
    background: #f8fafc; border: 1px solid #f1f5f9;
    padding: 1rem; border-radius: 16px; margin-top: 2rem;
}
.demo-pill span { display: block; font-size: 0.65rem; color: #94a3b8; font-weight: 600; }
.demo-pill code { color: #475569; font-weight: 800; font-size: 0.75rem; }

</style>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-logo"><i class="bi bi-person-fill"></i></div>
        <h1 class="auth-title">Student Login</h1>
        <p class="auth-sub">Enter your details to cast your vote</p>

        <?php if($error): ?>
            <div class="error-msg"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <label class="form-label">Student ID</label>
            <input type="text" name="student_id" class="auth-input" placeholder="e.g. 2021-001" required>

            <label class="form-label">Password</label>
            <input type="password" name="password" class="auth-input" placeholder="••••••••" required>

            <button type="submit" class="btn-auth">Sign In</button>
        </form>

        <div class="auth-footer">
            Don't have an account? <a href="register.php" class="auth-link">Register</a>
        </div>

        <div class="demo-pill">
            <span>DEMO ACCOUNT</span>
            <code>ID: 2021-001 / Pass: student123</code>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>