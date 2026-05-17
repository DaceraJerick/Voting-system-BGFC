<?php
// filename: admin/register.php
// location: admin/register.php

require_once '../config/database.php';

// Check if there's already an admin (optional - you can remove this if you want multiple admins)
$stmt = $pdo->query("SELECT COUNT(*) FROM admins");
$admin_count = $stmt->fetchColumn();

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    
    // Validation
    if(empty($username) || empty($password) || empty($confirm_password) || empty($full_name)) {
        $error = "All fields are required!";
    } elseif($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif(strlen($password) < 6) {
        $error = "Password must be at least 6 characters long!";
    } else {
        // Check if username already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        if($stmt->fetchColumn() > 0) {
            $error = "Username already exists!";
        } else {
            // Hash password and insert admin
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("INSERT INTO admins (username, password, full_name, email) VALUES (?, ?, ?, ?)");
            if($stmt->execute([$username, $hashed_password, $full_name, $email])) {
                $success = "Admin registration successful! You can now login.";
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">Admin Registration</h4>
            </div>
            <div class="card-body">
                <?php if($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if($admin_count > 0): ?>
                <div class="alert alert-warning">
                    <strong>Note:</strong> Admin accounts already exist. You can still register additional admin accounts.
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Full Name</label>
                            <input type="text" name="full_name" class="form-control" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label>Email Address</label>
                            <input type="email" name="email" class="form-control">
                            <small class="text-muted">Optional</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control" required>
                        <small class="text-muted">Choose a unique username</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Password</label>
                            <input type="password" name="password" class="form-control" required>
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label>Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Register Admin</button>
                    <a href="login.php" class="btn btn-link">Already have an account? Login</a>
                    <a href="../index.php" class="btn btn-secondary">Back</a>
                </form>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">Admin Registration Guidelines</h5>
            </div>
            <div class="card-body">
                <ul class="mb-0">
                    <li>Only authorized personnel should register as administrators</li>
                    <li>Use a strong password with a mix of letters, numbers, and special characters</li>
                    <li>Keep your login credentials secure</li>
                    <li>Admin accounts have full control over the voting system</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>