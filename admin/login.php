<?php
// filename: admin/login.php
// location: admin/login.php

require_once '../config/database.php';

$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    
    if($admin && password_verify($password, $admin['password'])) {
        $_SESSION['user_id'] = $admin['id'];
        $_SESSION['username'] = $admin['username'];
        $_SESSION['full_name'] = $admin['full_name'] ?? $admin['username'];
        $_SESSION['user_type'] = 'admin';
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Invalid username or password!";
    }
}

include '../includes/header.php';
?>

<style>
/* Modern 4D Design System */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

:root {
    --primary: #4361ee;
    --primary-dark: #3a56d4;
    --primary-light: #6c8cff;
    --secondary: #7209b7;
    --accent: #f72585;
    --dark: #0b1e33;
    --light: #f8fafc;
    --gray: #64748b;
    --success: #10b981;
    --warning: #fbbf24;
    --danger: #ef4444;
    --gradient-1: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --gradient-2: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --gradient-3: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    --gradient-4: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    --shadow-sm: 0 2px 4px rgba(0,0,0,0.02);
    --shadow-md: 0 4px 6px rgba(0,0,0,0.03);
    --shadow-lg: 0 10px 25px rgba(0,0,0,0.05);
    --shadow-xl: 0 20px 40px rgba(0,0,0,0.08);
    --shadow-2xl: 0 30px 60px rgba(0,0,0,0.12);
}

body {
    background: linear-gradient(135deg, #f5f7fa 0%, #e9edf5 100%);
    font-family: 'Inter', sans-serif;
    min-height: 100vh;
    position: relative;
    overflow-x: hidden;
}

/* Animated Background Elements */
.bg-element {
    position: fixed;
    border-radius: 50%;
    filter: blur(80px);
    z-index: 0;
    opacity: 0.3;
    animation: float 20s infinite ease-in-out;
}

.bg-element-1 {
    width: 500px;
    height: 500px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    top: -200px;
    right: -200px;
    animation-delay: 0s;
}

.bg-element-2 {
    width: 400px;
    height: 400px;
    background: linear-gradient(135deg, #f093fb, #f5576c);
    bottom: -150px;
    left: -150px;
    animation-delay: -5s;
}

.bg-element-3 {
    width: 300px;
    height: 300px;
    background: linear-gradient(135deg, #4facfe, #00f2fe);
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    animation-delay: -10s;
}

@keyframes float {
    0%, 100% {
        transform: translate(0, 0) scale(1);
    }
    33% {
        transform: translate(30px, -30px) scale(1.1);
    }
    66% {
        transform: translate(-20px, 20px) scale(0.9);
    }
}

/* Particles */
.particles {
    position: fixed;
    width: 100%;
    height: 100%;
    top: 0;
    left: 0;
    z-index: 1;
    pointer-events: none;
}

.particle {
    position: absolute;
    width: 4px;
    height: 4px;
    background: rgba(67, 97, 238, 0.2);
    border-radius: 50%;
    animation: particleFloat 15s infinite linear;
}

@keyframes particleFloat {
    0% {
        transform: translateY(0) translateX(0);
        opacity: 0;
    }
    10% {
        opacity: 0.5;
    }
    90% {
        opacity: 0.5;
    }
    100% {
        transform: translateY(-100vh) translateX(100px);
        opacity: 0;
    }
}

/* Login Container */
.login-container {
    min-height: calc(100vh - 100px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    position: relative;
    z-index: 10;
}

/* 4D Card */
.login-card {
    width: 100%;
    max-width: 480px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-radius: 40px;
    padding: 50px 40px;
    box-shadow: 
        0 50px 100px -20px rgba(0, 0, 0, 0.25),
        0 30px 60px -30px rgba(67, 97, 238, 0.3),
        inset 0 0 0 1px rgba(255, 255, 255, 0.4);
    position: relative;
    overflow: hidden;
    transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.login-card:hover {
    transform: translateY(-10px) scale(1.02);
    box-shadow: 
        0 60px 120px -20px rgba(0, 0, 0, 0.3),
        0 40px 80px -30px rgba(67, 97, 238, 0.4),
        inset 0 0 0 2px rgba(255, 255, 255, 0.5);
}

/* Card Shine Effect */
.login-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.3),
        transparent
    );
    transition: left 0.7s ease;
}

.login-card:hover::before {
    left: 100%;
}

/* Card Header */
.login-header {
    text-align: center;
    margin-bottom: 40px;
    position: relative;
}

/* Logo/Icon */
.logo-wrapper {
    width: 100px;
    height: 100px;
    margin: 0 auto 25px;
    position: relative;
}

.logo-ring {
    position: absolute;
    width: 100%;
    height: 100%;
    border: 3px solid transparent;
    border-top-color: var(--primary);
    border-right-color: var(--secondary);
    border-bottom-color: var(--accent);
    border-left-color: var(--primary-light);
    border-radius: 50%;
    animation: rotate 4s linear infinite;
}

@keyframes rotate {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.logo-icon {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 70px;
    height: 70px;
    background: var(--gradient-1);
    border-radius: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 35px;
    box-shadow: 0 15px 30px rgba(67, 97, 238, 0.3);
}

.login-header h2 {
    font-size: 2.2rem;
    font-weight: 800;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 10px;
    letter-spacing: -0.5px;
}

.login-header p {
    color: var(--gray);
    font-size: 0.95rem;
    font-weight: 500;
}

/* Form */
.login-form {
    position: relative;
}

/* Input Groups */
.input-group {
    margin-bottom: 25px;
    position: relative;
}

.input-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--dark);
    margin-bottom: 8px;
}

.input-label i {
    color: var(--primary);
    font-size: 1rem;
}

.input-wrapper {
    position: relative;
}

.input-icon {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray);
    font-size: 1.2rem;
    transition: all 0.3s;
    z-index: 10;
}

.input-field {
    width: 100%;
    padding: 16px 20px 16px 50px;
    border: 2px solid #e2e8f0;
    border-radius: 20px;
    font-size: 1rem;
    transition: all 0.3s;
    background: white;
    color: var(--dark);
    font-weight: 500;
}

.input-field:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.15);
    outline: none;
}

.input-field:focus + .input-icon {
    color: var(--primary);
    transform: translateY(-50%) scale(1.1);
}

/* Floating Label Effect */
.input-field::placeholder {
    color: #a0aec0;
    font-weight: 400;
    transition: all 0.3s;
}

.input-field:focus::placeholder {
    opacity: 0;
    transform: translateX(10px);
}

/* Password Strength Indicator (optional) */
.password-strength {
    margin-top: 8px;
    height: 4px;
    background: #e2e8f0;
    border-radius: 2px;
    overflow: hidden;
    position: relative;
}

.strength-bar {
    height: 100%;
    width: 0;
    transition: width 0.3s, background 0.3s;
    border-radius: 2px;
}

/* Remember Me & Forgot Password */
.form-options {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.remember-me {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.remember-me input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: var(--primary);
    cursor: pointer;
}

.remember-me span {
    color: var(--gray);
    font-size: 0.9rem;
    font-weight: 500;
}

.forgot-link {
    color: var(--primary);
    font-size: 0.9rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s;
    position: relative;
}

.forgot-link::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 0;
    height: 2px;
    background: var(--primary);
    transition: width 0.3s;
}

.forgot-link:hover {
    color: var(--primary-dark);
}

.forgot-link:hover::after {
    width: 100%;
}

/* Login Button */
.btn-login {
    width: 100%;
    padding: 18px;
    background: var(--gradient-1);
    color: white;
    border: none;
    border-radius: 20px;
    font-size: 1.1rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    transition: all 0.3s;
    box-shadow: 0 10px 20px rgba(67, 97, 238, 0.2);
    margin-bottom: 20px;
}

.btn-login::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.btn-login:hover {
    transform: translateY(-2px);
    box-shadow: 0 15px 30px rgba(67, 97, 238, 0.3);
}

.btn-login:hover::before {
    width: 300px;
    height: 300px;
}

.btn-login i {
    margin-right: 8px;
    transition: transform 0.3s;
}

.btn-login:hover i {
    transform: translateX(5px);
}

/* Back Link */
.back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--gray);
    text-decoration: none;
    font-weight: 500;
    font-size: 0.95rem;
    transition: all 0.3s;
    padding: 8px 16px;
    border-radius: 50px;
    background: rgba(255, 255, 255, 0.5);
    backdrop-filter: blur(10px);
}

.back-link:hover {
    color: var(--primary);
    transform: translateX(-5px);
    background: rgba(255, 255, 255, 0.8);
}

/* Demo Credentials Card */
.demo-card {
    margin-top: 30px;
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(10px);
    border-radius: 25px;
    padding: 25px;
    border: 1px solid rgba(255, 255, 255, 0.5);
    position: relative;
    overflow: hidden;
    transition: all 0.3s;
}

.demo-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
}

.demo-card::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(67, 97, 238, 0.03) 0%, transparent 70%);
    animation: rotate 20s linear infinite;
}

.demo-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 20px;
    font-size: 1.1rem;
}

.demo-title i {
    color: var(--primary);
    font-size: 1.3rem;
}

.credential-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.credential-item {
    background: white;
    border-radius: 16px;
    padding: 15px;
    text-align: center;
    border: 1px solid #e2e8f0;
    transition: all 0.3s;
}

.credential-item:hover {
    border-color: var(--primary);
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(67, 97, 238, 0.1);
}

.credential-label {
    font-size: 0.75rem;
    color: var(--gray);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 8px;
}

.credential-value {
    font-family: 'Inter', monospace;
    font-weight: 700;
    color: var(--dark);
    background: var(--light);
    padding: 8px 12px;
    border-radius: 12px;
    display: inline-block;
    font-size: 0.9rem;
}

.credential-value.admin {
    color: var(--primary);
}

.credential-value.password {
    color: var(--accent);
}

/* Alert Messages */
.alert {
    padding: 16px 20px;
    border-radius: 20px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 12px;
    animation: slideIn 0.5s ease-out;
    background: rgba(254, 202, 202, 0.9);
    backdrop-filter: blur(10px);
    border: 1px solid #fecaca;
    color: #991b1b;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.alert i {
    font-size: 1.3rem;
}

/* Loading Skeleton */
@keyframes shimmer {
    0% {
        background-position: -1000px 0;
    }
    100% {
        background-position: 1000px 0;
    }
}

.skeleton {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 1000px 100%;
    animation: shimmer 2s infinite;
}

/* Responsive Design */
@media (max-width: 576px) {
    .login-card {
        padding: 40px 25px;
    }
    
    .login-header h2 {
        font-size: 1.8rem;
    }
    
    .credential-grid {
        grid-template-columns: 1fr;
    }
    
    .form-options {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }
}

/* Dark Mode Support (optional) */
@media (prefers-color-scheme: dark) {
    .login-card {
        background: rgba(30, 41, 59, 0.95);
    }
    
    .input-field {
        background: #1e293b;
        border-color: #334155;
        color: white;
    }
    
    .input-field::placeholder {
        color: #64748b;
    }
    
    .credential-item {
        background: #1e293b;
    }
}
</style>

<!-- Animated Background Elements -->
<div class="bg-element bg-element-1"></div>
<div class="bg-element bg-element-2"></div>
<div class="bg-element bg-element-3"></div>

<!-- Particles -->
<div class="particles" id="particles"></div>

<div class="login-container">
    <div class="login-card">
        <!-- Card Header -->
        <div class="login-header">
            <div class="logo-wrapper">
                <div class="logo-ring"></div>
                <div class="logo-icon">
                    <i class="bi bi-shield-lock-fill"></i>
                </div>
            </div>
            <h2>Welcome Back</h2>
            <p>Enter your credentials to access admin dashboard</p>
        </div>

        <!-- Error Message -->
        <?php if($error): ?>
            <div class="alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="POST" action="" class="login-form">
            <!-- Username Field -->
            <div class="input-group">
                <label class="input-label">
                    <i class="bi bi-person-circle"></i>
                    Username
                </label>
                <div class="input-wrapper">
                    <span class="input-icon">
                        <i class="bi bi-person-badge"></i>
                    </span>
                    <input type="text" 
                           name="username" 
                           class="input-field" 
                           placeholder="Enter your username"
                           autocomplete="off"
                           required>
                </div>
            </div>

            <!-- Password Field -->
            <div class="input-group">
                <label class="input-label">
                    <i class="bi bi-lock"></i>
                    Password
                </label>
                <div class="input-wrapper">
                    <span class="input-icon">
                        <i class="bi bi-key"></i>
                    </span>
                    <input type="password" 
                           name="password" 
                           class="input-field" 
                           id="password"
                           placeholder="Enter your password"
                           required>
                </div>
                <!-- Optional password strength indicator -->
                <div class="password-strength">
                    <div class="strength-bar" id="strengthBar"></div>
                </div>
            </div>

            <!-- Form Options -->
            <div class="form-options">
                <label class="remember-me">
                    <input type="checkbox" name="remember">
                    <span>Remember me</span>
                </label>
                <a href="forgot-password.php" class="forgot-link">
                    Forgot Password?
                </a>
            </div>

            <!-- Login Button -->
            <button type="submit" class="btn-login">
                <i class="bi bi-box-arrow-in-right"></i>
                Sign In to Dashboard
            </button>

            <!-- Back to Home -->
            <div class="text-center">
                <a href="../index.php" class="back-link">
                    <i class="bi bi-arrow-left"></i>
                    Back to Home
                </a>
            </div>
        </form>
</div>

<script>
// Generate floating particles
function createParticles() {
    const particlesContainer = document.getElementById('particles');
    const particleCount = 50;
    
    for (let i = 0; i < particleCount; i++) {
        const particle = document.createElement('div');
        particle.className = 'particle';
        
        // Random position
        particle.style.left = Math.random() * 100 + '%';
        particle.style.top = Math.random() * 100 + '%';
        
        // Random size
        const size = Math.random() * 6 + 2;
        particle.style.width = size + 'px';
        particle.style.height = size + 'px';
        
        // Random animation delay
        particle.style.animationDelay = Math.random() * 10 + 's';
        
        // Random opacity
        particle.style.background = `rgba(67, 97, 238, ${Math.random() * 0.3})`;
        
        particlesContainer.appendChild(particle);
    }
}

// Password strength indicator (optional enhancement)
document.getElementById('password')?.addEventListener('input', function() {
    const password = this.value;
    const strengthBar = document.getElementById('strengthBar');
    
    if (password.length === 0) {
        strengthBar.style.width = '0';
        return;
    }
    
    let strength = 0;
    if (password.length >= 6) strength += 25;
    if (password.length >= 8) strength += 15;
    if (password.match(/[a-z]+/)) strength += 20;
    if (password.match(/[A-Z]+/)) strength += 20;
    if (password.match(/[0-9]+/)) strength += 20;
    if (password.match(/[$@#&!]+/)) strength += 20;
    
    strength = Math.min(strength, 100);
    strengthBar.style.width = strength + '%';
    
    if (strength < 30) {
        strengthBar.style.background = '#ef4444';
    } else if (strength < 60) {
        strengthBar.style.background = '#fbbf24';
    } else if (strength < 80) {
        strengthBar.style.background = '#10b981';
    } else {
        strengthBar.style.background = '#10b981';
    }
});

// Initialize particles
document.addEventListener('DOMContentLoaded', function() {
    createParticles();
    
    // Add smooth scroll behavior
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            document.querySelector(this.getAttribute('href')).scrollIntoView({
                behavior: 'smooth'
            });
        });
    });
});

// Remove register link since we're hiding admin registration
document.addEventListener('DOMContentLoaded', function() {
    // If you want to completely remove the register link from the DOM
    const registerLinks = document.querySelectorAll('a[href="register.php"]');
    registerLinks.forEach(link => link.remove());
});
</script>

<?php include '../includes/footer.php'; ?>