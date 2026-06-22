<?php
// filename: admin/manage_admins.php
// location: admin/manage_admins.php

require_once '../config/database.php';
require_once '../includes/auth_check.php';

if($_SESSION['user_type'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

$message = '';
$error = '';

// Handle add admin
if(isset($_POST['add_admin'])) {
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if(empty($username) || empty($password)) {
        $error = "Username and Password are required!";
    } elseif($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } else {
        // Check if username exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        if($stmt->fetchColumn() > 0) {
            $error = "Username already exists!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO admins (username, password, full_name, email) VALUES (?, ?, ?, ?)");
            if($stmt->execute([$username, $hashed_password, $full_name, $email])) {
                $message = "Admin account created successfully!";
            } else {
                $error = "Failed to create admin account.";
            }
        }
    }
}

// Handle delete admin
if(isset($_GET['delete'])) {
    $admin_id = $_GET['delete'];
    
    // Prevent deleting yourself
    if($admin_id == $_SESSION['user_id']) {
        $error = "You cannot delete your own account!";
    } else {
        $stmt = $pdo->prepare("DELETE FROM admins WHERE id = ?");
        if($stmt->execute([$admin_id])) {
            $message = "Admin account deleted successfully!";
        } else {
            $error = "Failed to delete admin account.";
        }
    }
}

// Get all admins
$admins = $pdo->query("SELECT id, username, full_name, email, created_at FROM admins ORDER BY created_at DESC")->fetchAll();

include '../includes/header.php';
?>

<style>
/* =========================================
   LIGHT MODE (default)
   ========================================= */
.card { background: #fff; border: 1px solid #e9ecef !important; }
.card-header.bg-white { background: #fff !important; }
.text-dark { color: #1e293b !important; }

/* =========================================
   DARK MODE OVERRIDES
   ========================================= */
[data-theme="dark"] h2 { color: #e2e8f0 !important; }
[data-theme="dark"] .text-muted { color: #94a3b8 !important; }
[data-theme="dark"] .card { background: rgba(20,22,35,0.85) !important; border-color: rgba(99,102,241,0.15) !important; box-shadow: 0 4px 24px rgba(0,0,0,0.25) !important; }
[data-theme="dark"] .card-header.bg-white { background: rgba(15,17,27,0.9) !important; border-bottom-color: rgba(99,102,241,0.1) !important; }
[data-theme="dark"] .card-header h5 { color: #e2e8f0 !important; }
[data-theme="dark"] .table { color: #e2e8f0 !important; }
[data-theme="dark"] .table thead.bg-light th { background: rgba(15,17,27,0.8) !important; color: #94a3b8 !important; border-color: rgba(99,102,241,0.1) !important; }
[data-theme="dark"] .table tbody tr { border-color: rgba(99,102,241,0.08) !important; }
[data-theme="dark"] .table tbody tr:hover td { background: rgba(99,102,241,0.04) !important; }
[data-theme="dark"] .table td { border-color: rgba(99,102,241,0.08) !important; color: #e2e8f0 !important; }
[data-theme="dark"] .text-dark { color: #e2e8f0 !important; }
[data-theme="dark"] .text-body { color: #94a3b8 !important; }

/* =========================================
   LIGHT MODE OVERRIDES
   ========================================= */
[data-theme="light"] h2 { color: #1e293b !important; }
[data-theme="light"] .text-muted { color: #64748b !important; }
[data-theme="light"] .card { background: #ffffff !important; border-color: rgba(0,0,0,0.08) !important; box-shadow: 0 4px 20px rgba(0,0,0,0.06) !important; }
[data-theme="light"] .card-header.bg-white { background: #f8fafc !important; border-bottom-color: rgba(0,0,0,0.08) !important; }
[data-theme="light"] .card-header h5 { color: #1e293b !important; }
[data-theme="light"] .table { color: #1e293b !important; }
[data-theme="light"] .table thead.bg-light th { background: #f1f5f9 !important; color: #475569 !important; border-color: rgba(0,0,0,0.08) !important; }
[data-theme="light"] .table tbody tr { border-color: rgba(0,0,0,0.05) !important; background: transparent !important; }
[data-theme="light"] .table tbody tr:hover td { background: rgba(241,245,249,0.8) !important; }
[data-theme="light"] .table td { border-color: rgba(0,0,0,0.05) !important; color: #1e293b !important; background: transparent !important; }
[data-theme="light"] .text-dark { color: #0f172a !important; }
[data-theme="light"] .text-body { color: #334155 !important; }
</style>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h2><i class="bi bi-shield-lock-fill text-primary"></i> Manage Administrators</h2>
                <p class="text-muted mb-0">View and manage administrator accounts.</p>
            </div>
            <div class="mt-2 mt-sm-0">
                <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                    <i class="bi bi-person-plus-fill"></i> Add New Admin
                </button>
                <a href="dashboard.php" class="btn btn-outline-secondary ms-2 shadow-sm">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<?php if($message): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm">
        <i class="bi bi-check-circle-fill"></i> <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if($error): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-primary">
                    <i class="bi bi-people-fill"></i> Administrator Accounts
                </h5>
                <span class="badge bg-primary rounded-pill"><?php echo count($admins); ?> Admins</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">ID</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Registered</th>
                                <th class="text-center pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($admins as $admin): ?>
                            <tr>
                                <td class="ps-4">
                                    <span class="badge bg-secondary rounded-pill"><?php echo $admin['id']; ?></span>
                                </td>
                                <td>
                                    <strong class="text-dark"><?php echo htmlspecialchars($admin['username']); ?></strong>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex justify-content-center align-items-center me-3" style="width: 36px; height: 36px;">
                                            <i class="bi bi-person-fill"></i>
                                        </div>
                                        <?php echo htmlspecialchars($admin['full_name'] ?? 'N/A'); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if(!empty($admin['email'])): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($admin['email']); ?>" class="text-decoration-none text-body">
                                            <i class="bi bi-envelope text-muted me-1"></i> <?php echo htmlspecialchars($admin['email']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <i class="bi bi-calendar3 text-muted me-1"></i> <?php echo date('M d, Y', strtotime($admin['created_at'])); ?>
                                </td>
                                <td class="text-center pe-4">
                                    <?php if($admin['id'] != $_SESSION['user_id']): ?>
                                        <a href="?delete=<?php echo $admin['id']; ?>" 
                                           class="btn btn-sm btn-outline-danger"
                                           onclick="return confirm('Are you sure you want to delete this admin account?');"
                                           title="Delete Admin">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-success px-3 py-2 rounded-pill"><i class="bi bi-star-fill text-warning"></i> Current User</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Admin Modal -->
<div class="modal fade" id="addAdminModal" tabindex="-1" aria-labelledby="addAdminModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addAdminModalLabel">Add New Administrator</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="full_name" name="full_name">
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email">
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_admin" class="btn btn-primary">Add Admin</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>