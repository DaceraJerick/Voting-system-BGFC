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
        $max_candidates = isset($_POST['max_candidates']) && is_numeric($_POST['max_candidates']) ? intval($_POST['max_candidates']) : 2;
        
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
    } elseif(isset($_POST['edit_position'])) {
        $position_id     = intval($_POST['position_id']);
        $max_candidates  = isset($_POST['max_candidates']) && is_numeric($_POST['max_candidates']) ? intval($_POST['max_candidates']) : 2;
        $max_votes       = isset($_POST['max_votes'])      && is_numeric($_POST['max_votes'])      ? intval($_POST['max_votes'])      : 1;
        $position_name   = trim($_POST['position_name'] ?? '');
        $display_order   = isset($_POST['display_order'])  && is_numeric($_POST['display_order'])  ? intval($_POST['display_order'])  : 1;

        if($max_candidates < 1) $max_candidates = 1;
        if($max_votes      < 1) $max_votes = 1;

        $stmt = $pdo->prepare("UPDATE positions SET max_candidates = ?, max_votes = ?, position_name = ?, display_order = ? WHERE id = ? AND election_id = ?");
        if($stmt->execute([$max_candidates, $max_votes, $position_name, $display_order, $position_id, $election_id])) {
            $message = "Position updated successfully!";
        } else {
            $error = "Failed to update position.";
        }
    } elseif(isset($_POST['add_default_positions'])) {
        // Add all default positions at once
        $default_positions = [
            ['President', 1],
            ['Vice President', 2],
            ['Treasurer', 3],
            ['Internal Secretary', 4],
            ['External Secretary', 5],
            ['P.R.O', 6],
            ['Auditor 1', 7],
            ['Auditor 2( Assistant Auditor)', 8],
            ['Business Manager', 9],
            ['Marshall 1', 10],
            ['Marshall 2', 11]
        ];
        
        $added = 0;
        $skipped = 0;
        
        foreach($default_positions as $pos) {
            // Check if position already exists for this election
            $check = $pdo->prepare("SELECT COUNT(*) FROM positions WHERE election_id = ? AND position_name = ?");
            $check->execute([$election_id, $pos[0]]);
            $exists = $check->fetchColumn();
            
            if(!$exists) {
                $max_candidates = 2;
                $max_votes = 1;
                $stmt = $pdo->prepare("INSERT INTO positions (election_id, position_name, display_order, max_votes, max_candidates) VALUES (?, ?, ?, ?, ?)");
                if($stmt->execute([$election_id, $pos[0], $pos[1], $max_votes, $max_candidates])) {
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

<style>
/* =========================================
   DARK MODE OVERRIDES
   ========================================= */
[data-theme="dark"] h2 { color: #e2e8f0 !important; }
[data-theme="dark"] .alert-info { background: rgba(56,189,248,0.08) !important; border-color: rgba(56,189,248,0.15) !important; color: #7dd3fc !important; }
[data-theme="dark"] .alert-info strong { color: #bae6fd !important; }
[data-theme="dark"] .card { background: rgba(20,22,35,0.85) !important; border-color: rgba(99,102,241,0.15) !important; box-shadow: 0 4px 24px rgba(0,0,0,0.25) !important; }
[data-theme="dark"] .card-header.bg-white { background: rgba(15,17,27,0.9) !important; border-bottom-color: rgba(99,102,241,0.1) !important; }
[data-theme="dark"] .card-header h5 { color: #e2e8f0 !important; }
[data-theme="dark"] .card-body { color: #e2e8f0 !important; }
[data-theme="dark"] .form-label { color: #94a3b8 !important; }
[data-theme="dark"] .form-control { background: rgba(15,17,27,0.9) !important; border-color: rgba(99,102,241,0.15) !important; color: #e2e8f0 !important; }
[data-theme="dark"] .form-control:focus { border-color: rgba(99,102,241,0.4) !important; box-shadow: 0 0 0 3px rgba(99,102,241,0.1) !important; }
[data-theme="dark"] .text-muted { color: #94a3b8 !important; }
[data-theme="dark"] .bg-light { background: rgba(15,17,27,0.6) !important; }
[data-theme="dark"] .bg-light h6 { color: #e2e8f0 !important; }
[data-theme="dark"] .bg-light p { color: #94a3b8 !important; }
[data-theme="dark"] .table { color: #e2e8f0 !important; }
[data-theme="dark"] .table thead.bg-light th { background: rgba(15,17,27,0.8) !important; color: #94a3b8 !important; border-color: rgba(99,102,241,0.1) !important; }
[data-theme="dark"] .table tbody tr td { border-color: rgba(99,102,241,0.08) !important; color: #e2e8f0 !important; }
[data-theme="dark"] .table tbody tr:hover td { background: rgba(99,102,241,0.04) !important; }
[data-theme="dark"] .card-body h6 { color: #e2e8f0 !important; }
</style>

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
                        <input type="text" name="position_name" class="form-control" list="positionSuggestions"
                               placeholder="e.g., President, Vice President" required autocomplete="off">
                        <datalist id="positionSuggestions">
                            <option value="President">
                            <option value="Vice President">
                            <option value="Treasurer">
                            <option value="Internal Secretary">
                            <option value="External Secretary">
                            <option value="P.R.O">
                            <option value="Auditor 1">
                            <option value="Auditor 2( Assistant Auditor)">
                            <option value="Business Manager">
                            <option value="Marshall 1">
                            <option value="Marshall 2">
                        </datalist>
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
                               value="2" min="1" required>
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
                    <p class="small text-muted mb-3">Add all 11 standard positions at once.</p>
                    <form method="POST" action="">
                        <button type="submit" name="add_default_positions" class="btn btn-success w-100">
                            <i class="bi bi-plus-circle"></i> Add All 11 Default Positions
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
                                    <td style="white-space:nowrap;">
                                        <!-- Add Candidate -->
                                        <a href="add_candidates.php?position_id=<?php echo $position['id']; ?>&election_id=<?php echo $election_id; ?>"
                                           style="display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border-radius:20px;border:1.5px solid #22c55e;color:#22c55e;font-weight:700;font-size:12px;text-decoration:none;transition:all 0.2s;background:transparent;"
                                           onmouseover="this.style.background='#22c55e';this.style.color='#fff';"
                                           onmouseout="this.style.background='transparent';this.style.color='#22c55e';">
                                            <i class="bi bi-person-plus"></i> Add
                                        </a>

                                        <!-- Edit Button -->
                                        <button type="button"
                                            data-bs-toggle="modal" data-bs-target="#editPositionModal<?php echo $position['id']; ?>"
                                            style="display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border-radius:20px;border:1.5px solid #6366f1;color:#6366f1;font-weight:700;font-size:12px;cursor:pointer;transition:all 0.2s;background:transparent;margin-left:4px;"
                                            onmouseover="this.style.background='#6366f1';this.style.color='#fff';"
                                            onmouseout="this.style.background='transparent';this.style.color='#6366f1';">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>

                                        <!-- Delete Button (always show, modal will handle locked state) -->
                                        <button type="button"
                                            data-bs-toggle="modal" data-bs-target="#deletePositionModal<?php echo $position['id']; ?>"
                                            style="display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border-radius:20px;border:1.5px solid #ef4444;color:#ef4444;font-weight:700;font-size:12px;cursor:pointer;transition:all 0.2s;background:transparent;margin-left:4px;"
                                            onmouseover="this.style.background='#ef4444';this.style.color='#fff';"
                                            onmouseout="this.style.background='transparent';this.style.color='#ef4444';">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>

                                <!-- Edit Position Modal -->
                                <div class="modal fade" id="editPositionModal<?php echo $position['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content" style="border-radius:20px; overflow:hidden; border:none; box-shadow:0 25px 50px rgba(0,0,0,0.3);">
                                            <div class="modal-header border-0 pb-0" style="background: linear-gradient(135deg,#6366f1,#8b5cf6); color:#fff; padding: 24px 28px 20px;">
                                                <div>
                                                    <div style="font-size:13px; opacity:0.8; margin-bottom:4px;">Editing position</div>
                                                    <h5 class="modal-title fw-bold mb-0" style="font-size:20px;">
                                                        <i class="bi bi-pencil-square me-2"></i><?php echo htmlspecialchars($position['position_name']); ?>
                                                    </h5>
                                                </div>
                                                <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST" action="">
                                                <div class="modal-body p-4" style="background: var(--bs-body-bg, #fff);">
                                                    <input type="hidden" name="position_id" value="<?php echo $position['id']; ?>">

                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold" style="font-size:13px; color:#6366f1;">POSITION NAME</label>
                                                        <input type="text" name="position_name" class="form-control" style="border-radius:12px; border:2px solid rgba(99,102,241,0.2); font-weight:600; padding:10px 14px;"
                                                               value="<?php echo htmlspecialchars($position['position_name']); ?>" required>
                                                    </div>

                                                    <div class="row g-3">
                                                        <div class="col-4">
                                                            <label class="form-label fw-bold" style="font-size:13px; color:#6366f1;">ORDER</label>
                                                            <input type="number" name="display_order" class="form-control" style="border-radius:12px; border:2px solid rgba(99,102,241,0.2); font-weight:600; padding:10px 14px; text-align:center;"
                                                                   value="<?php echo intval($position['display_order']); ?>" min="1" required>
                                                        </div>
                                                        <div class="col-4">
                                                            <label class="form-label fw-bold" style="font-size:13px; color:#6366f1;">MAX WINNERS</label>
                                                            <input type="number" name="max_votes" class="form-control" style="border-radius:12px; border:2px solid rgba(99,102,241,0.2); font-weight:600; padding:10px 14px; text-align:center;"
                                                                   value="<?php echo intval($position['max_votes'] ?? 1); ?>" min="1" required>
                                                        </div>
                                                        <div class="col-4">
                                                            <label class="form-label fw-bold" style="font-size:13px; color:#6366f1;">MAX CANDIDATES</label>
                                                            <input type="number" name="max_candidates" class="form-control" style="border-radius:12px; border:2px solid rgba(99,102,241,0.2); font-weight:600; padding:10px 14px; text-align:center;"
                                                                   value="<?php echo intval($position['max_candidates'] ?? 2); ?>" min="1" required>
                                                        </div>
                                                    </div>
                                                    <div class="row g-0 mt-1">
                                                        <div class="col-4"><small class="text-muted" style="font-size:11px;">Ballot order</small></div>
                                                        <div class="col-4 text-center"><small class="text-muted" style="font-size:11px;">Can be elected</small></div>
                                                        <div class="col-4 text-end"><small class="text-muted" style="font-size:11px;">Slots to register</small></div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer border-0 pt-0 px-4 pb-4" style="background: var(--bs-body-bg, #fff);">
                                                    <button type="button" class="btn btn-light px-4 fw-bold" style="border-radius:12px;" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" name="edit_position" class="btn px-5 fw-bold" style="border-radius:12px; background:linear-gradient(135deg,#6366f1,#8b5cf6); color:#fff; border:none;">
                                                        <i class="bi bi-check-lg me-1"></i> Save Changes
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Delete Position Modal -->
                                <div class="modal fade" id="deletePositionModal<?php echo $position['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered modal-sm">
                                        <div class="modal-content" style="border-radius:20px; overflow:hidden; border:none; box-shadow:0 25px 50px rgba(0,0,0,0.3);">
                                            <div class="modal-body text-center p-5" style="background: var(--bs-body-bg, #fff);">
                                                <!-- Icon -->
                                                <div style="width:70px;height:70px;border-radius:50%;background:rgba(239,68,68,0.1);display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                                                    <i class="bi bi-trash3-fill" style="font-size:30px;color:#ef4444;"></i>
                                                </div>
                                                <h5 class="fw-bold mb-1">Delete Position?</h5>
                                                <p class="fw-bold mb-1" style="color:#6366f1;"><?php echo htmlspecialchars($position['position_name']); ?></p>

                                                <?php if($position['candidate_count'] > 0): ?>
                                                    <div style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);border-radius:12px;padding:12px;margin:16px 0;">
                                                        <i class="bi bi-exclamation-triangle-fill text-danger me-1"></i>
                                                        <span style="font-size:13px;color:#dc2626;font-weight:600;">Cannot delete — this position has <strong><?php echo $position['candidate_count']; ?> candidate(s)</strong> registered.</span>
                                                    </div>
                                                    <button type="button" class="btn w-100 fw-bold" style="border-radius:12px;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;border:none;padding:12px;" data-bs-dismiss="modal">
                                                        Got it
                                                    </button>
                                                <?php else: ?>
                                                    <p class="text-muted small mb-4">This action is permanent and cannot be undone.</p>
                                                    <div class="d-flex gap-2">
                                                        <button type="button" class="btn btn-light w-50 fw-bold" style="border-radius:12px;" data-bs-dismiss="modal">Cancel</button>
                                                        <form method="POST" action="" class="w-50">
                                                            <input type="hidden" name="position_id" value="<?php echo $position['id']; ?>">
                                                            <button type="submit" name="delete_position" class="btn w-100 fw-bold" style="border-radius:12px;background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;border:none;">
                                                                <i class="bi bi-trash-fill me-1"></i> Delete
                                                            </button>
                                                        </form>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
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