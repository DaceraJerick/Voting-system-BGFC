<?php
// filename: admin/manage_positions.php
// location: admin/manage_positions.php

require_once '../config/database.php';
require_once '../includes/auth_check.php';

if($_SESSION['user_type'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

$message = '';
$error = '';

// Get election ID from URL or use latest
$election_id = isset($_GET['election_id']) ? $_GET['election_id'] : null;

if(!$election_id) {
    // Get latest election
    $election = $pdo->query("SELECT id, title FROM elections ORDER BY created_at DESC LIMIT 1")->fetch();
    if($election) {
        $election_id = $election['id'];
    } else {
        header("Location: create_election.php?msg=create_first");
        exit();
    }
} else {
    $stmt = $pdo->prepare("SELECT * FROM elections WHERE id = ?");
    $stmt->execute([$election_id]);
    $election = $stmt->fetch();
}

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['add_position'])) {
        $position_name = trim($_POST['position_name']);
        $display_order = isset($_POST['display_order']) && is_numeric($_POST['display_order']) ? intval($_POST['display_order']) : 1;
        $max_votes = isset($_POST['max_votes']) && is_numeric($_POST['max_votes']) ? intval($_POST['max_votes']) : 1;
        $max_candidates = isset($_POST['max_candidates']) && is_numeric($_POST['max_candidates']) ? intval($_POST['max_candidates']) : 5;
        
        if(!empty($position_name)) {
            try {
                // Check if position already exists for this election
                $check = $pdo->prepare("SELECT COUNT(*) FROM positions WHERE election_id = ? AND LOWER(position_name) = LOWER(?)");
                $check->execute([$election_id, $position_name]);
                if($check->fetchColumn() > 0) {
                    $error = "Position '$position_name' already exists in this election.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO positions (election_id, position_name, display_order, max_votes, max_candidates) VALUES (?, ?, ?, ?, ?)");
                    if($stmt->execute([$election_id, $position_name, $display_order, $max_votes, $max_candidates])) {
                        $message = "Position added successfully! (Order: $display_order)";
                    }
                }
            } catch(PDOException $e) {
                $error = "Error adding position: " . $e->getMessage();
            }
        }
    } elseif(isset($_POST['delete_position'])) {
        $position_id = $_POST['position_id'];
        
        // Check if position has candidates
        $check = $pdo->prepare("SELECT COUNT(*) FROM candidates WHERE position_id = ?");
        $check->execute([$position_id]);
        $candidate_count = $check->fetchColumn();
        
        if($candidate_count > 0) {
            $error = "Cannot delete position with existing candidates!";
        } else {
            $stmt = $pdo->prepare("DELETE FROM positions WHERE id = ? AND election_id = ?");
            if($stmt->execute([$position_id, $election_id])) {
                $message = "Position deleted successfully!";
            }
        }
    } elseif(isset($_POST['add_default_positions'])) {
        // Add all default positions at once
        $default_positions = [
            ['President', 1],
            ['Vice President', 2],
            ['Secretary', 3],
            ['Treasurer', 4],
            ['Auditor', 5],
            ['PRO', 6]
        ];
        
        $added = 0;
        $skipped = 0;
        
        foreach($default_positions as $pos) {
            // Check if position already exists for this election
            $check = $pdo->prepare("SELECT COUNT(*) FROM positions WHERE election_id = ? AND position_name = ?");
            $check->execute([$election_id, $pos[0]]);
            $exists = $check->fetchColumn();
            
            if(!$exists) {
                $stmt = $pdo->prepare("INSERT INTO positions (election_id, position_name, display_order) VALUES (?, ?, ?)");
                if($stmt->execute([$election_id, $pos[0], $pos[1]])) {
                    $added++;
                }
            } else {
                $skipped++;
            }
        }
        
        $message = "Added $added default positions. Skipped $skipped existing positions.";
    }
}

// Get positions for this election
$positions = $pdo->prepare("
    SELECT p.*, 
           (SELECT COUNT(*) FROM candidates c WHERE c.position_id = p.id) as candidate_count 
    FROM positions p 
    WHERE p.election_id = ? 
    ORDER BY p.display_order
");
$positions->execute([$election_id]);
$positions = $positions->fetchAll();

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2><i class="bi bi-list-task"></i> Manage Positions</h2>
        <div class="alert alert-info d-flex align-items-center justify-content-between">
            <div>
                <i class="bi bi-info-circle-fill me-2 fs-4"></i>
                <strong>Election:</strong> <?php echo htmlspecialchars($election['title']); ?>
                <a href="create_election.php" class="btn btn-sm btn-outline-primary ms-3">
                    <i class="bi bi-plus-circle"></i> Create New Election
                </a>
            </div>
            <a href="fix_positions.php?action=reorder" class="btn btn-sm btn-warning">
                <i class="bi bi-arrow-repeat"></i> Fix Position Order
            </a>
        </div>
    </div>
</div>

<div class="row">
    <!-- Add Position Form -->
    <div class="col-md-5 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 text-primary">
                    <i class="bi bi-plus-circle"></i> Add New Position
                </h5>
            </div>
            <div class="card-body p-4">
                <?php if($message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle-fill"></i> <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Position Name</label>
                        <input type="text" name="position_name" class="form-control" 
                               placeholder="e.g., President, Vice President" required>
                        <small class="text-muted d-block">Enter the position title</small>
                    </div>
                    <?php
                    // Get next display value for convenient default
                    $def_stmt = $pdo->prepare("SELECT MAX(display_order) FROM positions WHERE election_id = ?");
                    $def_stmt->execute([$election_id]);
                    $max_o = $def_stmt->fetchColumn();
                    $next_order = ($max_o === null || $max_o === false) ? 1 : intval($max_o) + 1;
                    ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Display Order</label>
                        <input type="number" name="display_order" class="form-control" 
                               value="<?php echo $next_order; ?>" min="1" required>
                        <small class="text-muted d-block">Position's order on the ballot</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Max Winners (Candidates to vote)</label>
                        <input type="number" name="max_votes" class="form-control" 
                               value="1" min="1" required>
                        <small class="text-muted d-block">How many candidates can be elected for this position</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Max Candidates (Limit to add)</label>
                        <input type="number" name="max_candidates" class="form-control" 
                               value="5" min="1" required>
                        <small class="text-muted d-block">How many candidates can be added to this position</small>
                    </div>
                    <button type="submit" name="add_position" class="btn btn-primary px-4 w-100">
                        <i class="bi bi-plus-lg"></i> Add Position
                    </button>
                    <a href="dashboard.php" class="btn btn-outline-secondary w-100 mt-2">
                        <i class="bi bi-arrow-left"></i> Back
                    </a>
                </form>
                
                <hr class="my-4">
                
                <div class="bg-light p-3 rounded">
                    <h6 class="fw-bold mb-3"><i class="bi bi-lightning-charge"></i> Quick Add Default Positions</h6>
                    <p class="small text-muted mb-3">Add all standard positions (President, VP, Secretary, Treasurer, Auditor, PRO) at once.</p>
                    <form method="POST" action="">
                        <button type="submit" name="add_default_positions" class="btn btn-success w-100">
                            <i class="bi bi-plus-circle"></i> Add All 6 Default Positions
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Positions List -->
    <div class="col-md-7 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-success">
                    <i class="bi bi-list-check"></i> Positions for this Election
                </h5>
                <span class="badge bg-primary"><?php echo count($positions); ?> Positions</span>
            </div>
            <div class="card-body p-0">
                <?php if(count($positions) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Order</th>
                                    <th>Position</th>
                                    <th>Max Winners</th>
                                    <th>Max Candidates</th>
                                    <th>Candidates Added</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($positions as $position): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo $position['display_order']; ?></span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($position['position_name']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">
                                            <?php echo htmlspecialchars($position['max_votes'] ?? 1); ?> Winners
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-dark">
                                            <?php echo htmlspecialchars($position['max_candidates'] ?? 5); ?> Candidates Max
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo $position['candidate_count']; ?> Candidates
                                        </span>
                                    </td>
                                    <td>
                                        <a href="add_candidates.php?position_id=<?php echo $position['id']; ?>&election_id=<?php echo $election_id; ?>" 
                                           class="btn btn-sm btn-outline-success">
                                            <i class="bi bi-person-plus"></i> Add Candidate
                                        </a>
                                        
                                        <?php if($position['candidate_count'] == 0): ?>
                                            <form method="POST" action="" style="display: inline;" 
                                                  onsubmit="return confirm('Are you sure you want to delete this position?');">
                                                <input type="hidden" name="position_id" value="<?php echo $position['id']; ?>">
                                                <button type="submit" name="delete_position" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-list" style="font-size: 48px; color: #dee2e6;"></i>
                        <p class="mt-3 text-muted">No positions added yet.</p>
                        <p class="text-muted small">Click "Add All 6 Default Positions" to get started quickly.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Navigation to Add Candidates -->
        <?php if(count($positions) > 0): ?>
        <div class="card shadow-sm border-0 mt-4">
            <div class="card-body p-4 text-center">
                <h6 class="mb-3">Ready to add candidates?</h6>
                <a href="add_candidates.php?election_id=<?php echo $election_id; ?>" class="btn btn-lg btn-success px-5">
                    <i class="bi bi-person-plus-fill"></i> Go to Add Candidates
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>