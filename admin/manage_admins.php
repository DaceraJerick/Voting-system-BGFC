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

<div class="row mb-4">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h2><i class="bi bi-shield-lock-fill text-primary"></i> Manage Administrators</h2>
                <p class="text-muted mb-0">View and manage administrator accounts.</p>
            </div>
            <div class="mt-2 mt-sm-0">
                <a href="register.php" class="btn btn-primary shadow-sm">
                    <i class="bi bi-person-plus-fill"></i> Add New Admin
                </a>
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

<?php include '../includes/footer.php'; ?>