<?php
// filename: admin/fix_positions.php
// Admin utility to fix position ordering

require_once '../config/database.php';
require_once '../includes/auth_check.php';

if($_SESSION['user_type'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

$message = '';
$error = '';

if(isset($_GET['action']) && $_GET['action'] == 'reorder') {
    try {
        // Get all elections
        $elections = $pdo->query("SELECT DISTINCT election_id FROM positions WHERE election_id IS NOT NULL ORDER BY election_id")->fetchAll();
        
        foreach($elections as $row) {
            $election_id = $row['election_id'];
            
            // Get all positions for this election sorted by current display_order, then by id
            $stmt = $pdo->prepare("SELECT id FROM positions WHERE election_id = ? ORDER BY display_order, id ASC");
            $stmt->execute([$election_id]);
            $positions = $stmt->fetchAll();
            
            // Reassign display_order sequentially starting from 1
            foreach($positions as $index => $position) {
                $new_order = $index + 1;
                $update = $pdo->prepare("UPDATE positions SET display_order = ? WHERE id = ?");
                $update->execute([$new_order, $position['id']]);
            }
        }
        
        $message = "✓ All positions have been reordered sequentially!";
    } catch(PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

include '../includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-lg border-0">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">⚙️ Fix Position Ordering</h5>
                </div>
                <div class="card-body p-4">
                    <?php if($message): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle"></i> <?php echo $message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if($error): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-circle"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-warning">
                        <h6>⚠️ What this does:</h6>
                        <ul class="mb-0 small">
                            <li>Fixes all position display orders to be sequential (1, 2, 3...)</li>
                            <li>Affects all elections automatically</li>
                            <li>Cannot be undone - make a backup first!</li>
                        </ul>
                    </div>
                    
                    <form method="GET">
                        <input type="hidden" name="action" value="reorder">
                        <button type="submit" class="btn btn-danger w-100" onclick="return confirm('This will reorder ALL positions in the system. Continue?')">
                            <i class="bi bi-arrow-repeat"></i> Reorder All Positions Now
                        </button>
                    </form>
                    
                    <a href="manage_positions.php" class="btn btn-secondary w-100 mt-2">
                        <i class="bi bi-arrow-left"></i> Back to Manage Positions
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
