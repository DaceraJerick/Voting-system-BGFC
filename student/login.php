<?php
// filename: student/login.php
// location: student/login.php

require_once '../config/database.php';

if(isset($_SESSION['user_type'])) {
    if($_SESSION['user_type'] == 'student') {
        header("Location: dashboard.php");
        exit();
    } elseif($_SESSION['user_type'] == 'admin') {
        header("Location: ../admin/dashboard.php");
        exit();
    }
}

$error = '';
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $identifier = trim($_POST['student_id']); // This can now be username OR student_id
    $password = $_POST['password'];
    
    // First, check if it's an admin
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$identifier]);
    $admin = $stmt->fetch();
    
    if($admin && password_verify($password, $admin['password'])) {
        $_SESSION['user_id'] = $admin['id'];
        $_SESSION['username'] = $admin['username'];
        $_SESSION['full_name'] = $admin['full_name'] ?? $admin['username'];
        $_SESSION['user_type'] = 'admin';
        header("Location: ../admin/dashboard.php");
        exit();
    }
    
    // If not admin, check if student
    $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ?");
    $stmt->execute([$identifier]);
    $student = $stmt->fetch();
    
    if($student && password_verify($password, $student['password'])) {
        $_SESSION['user_id'] = $student['id'];
        $_SESSION['student_id'] = $student['student_id'];
        $_SESSION['full_name'] = $student['full_name'];
        $_SESSION['user_type'] = 'student';
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Invalid Credentials!";
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
    max-width: 420px;
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
    font-size: 1.55rem; font-weight: 800;
    color: #e6f7e8; text-align: center;
    margin-bottom: 0.5rem;
}
.auth-sub {
    font-size: 0.87rem; color: #9ac9ae;
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
    border-radius: 16px; padding: 0.95rem 1rem;
    font-size: 0.95rem; font-weight: 600;
    color: #e8f9e9; margin-bottom: 1.25rem;
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
    margin-top: 0.5rem;
}

.auth-footer {
    text-align: center; margin-top: 2rem;
    font-size: 0.85rem; color: #a4c9b3;
    font-weight: 600;
}
.auth-link { color: #93e0b4; text-decoration: none; font-weight: 800; }

.error-msg {
    background: rgba(248,113,113,0.16); border: 1px solid rgba(248,113,113,0.28);
    padding: 0.75rem 1rem; border-radius: 14px;
    color: #ffc7c7; font-size: 0.8rem; font-weight: 700;
    margin-bottom: 1.5rem; text-align: center;
}

.demo-pill {
    background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);
    padding: 1rem; border-radius: 16px; margin-top: 2rem;
}
.demo-pill span { display: block; font-size: 0.65rem; color: #9ac9ae; font-weight: 600; }
.demo-pill code { color: #d1f8d9; font-weight: 800; font-size: 0.75rem; }

</style>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-logo"><i class="bi bi-person-fill"></i></div>
        <h1 class="auth-title">System Login</h1>
        <p class="auth-sub">Enter your details to access the system</p>

        <?php if($error): ?>
            <div class="error-msg"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <label class="form-label">Username / Student ID</label>
            <input type="text" name="student_id" class="auth-input" placeholder="e.g. 2021-001 or admin" required>

            <label class="form-label">Password</label>
            <div style="position: relative; margin-bottom: 1.25rem;">
                <input type="password" name="password" id="passwordField" class="auth-input" placeholder="••••••••" required style="padding-right: 2.5rem; margin-bottom: 0;">
                <i class="bi bi-eye-slash" id="togglePassword" style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); cursor: pointer; color: #8db89e; font-size: 1.1rem; transition: color 0.2s;"></i>
            </div>

            <button type="submit" class="btn-auth">Sign In</button>
        </form>

        <div class="auth-footer">
            <div style="margin-bottom: 1rem;">
                Don't have an account? <a href="register.php" class="auth-link">Register as Student</a>
            </div>
            <a href="../index.php" class="auth-link" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1rem; background: rgba(255,255,255,0.05); border-radius: 12px; transition: background 0.2s;">
                <i class="bi bi-arrow-left"></i> Back to Home
            </a>
        </div>
    </div>
</div>

<script>
document.getElementById('togglePassword').addEventListener('click', function() {
    const passwordField = document.getElementById('passwordField');
    const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
    passwordField.setAttribute('type', type);
    
    // Toggle the eye icon
    this.classList.toggle('bi-eye');
    this.classList.toggle('bi-eye-slash');
    
    // Highlight the icon when active
    if(type === 'text') {
        this.style.color = '#e8f9e9';
    } else {
        this.style.color = '#8db89e';
    }
});
</script>

<?php include '../includes/footer.php'; ?>