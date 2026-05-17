<?php
$file = file_get_contents('add_candidates.php');

// 1. Election Selection Logic
$oldElec = '// Get election ID from URL or use latest
$election_id = isset($_GET[\'election_id\']) ? $_GET[\'election_id\'] : null;
$selected_position_id = isset($_GET[\'position_id\']) ? $_GET[\'position_id\'] : null;

if(!$election_id) {
    // Get latest election
    $election = $pdo->query("SELECT id, title, is_active, start_date, end_date FROM elections ORDER BY created_at DESC LIMIT 1")->fetch();
    if($election) {
        $election_id = $election[\'id\'];
    } else {
        header("Location: create_election.php?msg=create_first");
        exit();
    }
} else {
    // Get specific election with all fields
    $stmt = $pdo->prepare("SELECT id, title, is_active, start_date, end_date FROM elections WHERE id = ?");
    $stmt->execute([$election_id]);
    $election = $stmt->fetch();
    
    if(!$election) {
        header("Location: create_election.php?msg=not_found");
        exit();
    }
}';

$newElec = '// Get election ID from URL or look for an active one
$election_id = isset($_GET[\'election_id\']) ? $_GET[\'election_id\'] : null;
$selected_position_id = isset($_GET[\'position_id\']) ? $_GET[\'position_id\'] : null;

// If no election ID specified, try to find the active one
if(!$election_id) {
    $stmt = $pdo->query("SELECT id, title, is_active, start_date, end_date FROM elections WHERE is_active = 1 LIMIT 1");
    $active_election = $stmt->fetch();
    if($active_election) {
        $election_id = $active_election[\'id\'];
        $election = $active_election;
    } else {
        $election = null;
    }
} else {
    // Get specific election
    $stmt = $pdo->prepare("SELECT id, title, is_active, start_date, end_date FROM elections WHERE id = ?");
    $stmt->execute([$election_id]);
    $election = $stmt->fetch();
}

// Get all elections for the switcher
$all_elections = $pdo->query("SELECT id, title, is_active FROM elections ORDER BY created_at DESC")->fetchAll();';

$file = str_replace($oldElec, $newElec, $file);

// 2. Candidate Limit Enforcement
$oldAdd = '        if(empty($error)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO candidates (position_id, student_id, full_name, platform, photo) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                if($stmt->execute([
                    $position_id, 
                    $student_id, 
                    $student[\'full_name\'], 
                    $platform, 
                    $image_path
                ])) {
                    $message = "Candidate added successfully!";
                }
            } catch(PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }';

$newAdd = '        if(empty($error)) {
            try {
                // Check candidate limit
                $stmt = $pdo->prepare("SELECT max_candidates FROM positions WHERE id = ?");
                $stmt->execute([$position_id]);
                $pos_limit = $stmt->fetchColumn();
                $limit = $pos_limit ? intval($pos_limit) : 5;

                $stmt = $pdo->prepare("SELECT COUNT(*) FROM candidates WHERE position_id = ?");
                $stmt->execute([$position_id]);
                $current_count = $stmt->fetchColumn();

                if($current_count >= $limit) {
                    $error = "Cannot add more candidates. This position has reached its limit of $limit candidates.";
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO candidates (position_id, student_id, full_name, platform, photo) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    
                    if($stmt->execute([
                        $position_id, 
                        $student_id, 
                        $student[\'full_name\'], 
                        $platform, 
                        $image_path
                    ])) {
                        $message = "Candidate added successfully!";
                    }
                }
            } catch(PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }';

$file = str_replace($oldAdd, $newAdd, $file);

// 3. Syntax Error (Missing endif)
// We look for the footer inclusion and check if endif is before it
if (strpos($file, '<?php endif; ?>') === false) {
   $file = str_replace('<?php include \'../includes/footer.php\'; ?>', "<?php endif; ?>\n<?php include '../includes/footer.php'; ?>", $file);
} else {
    // If it's there but maybe in the wrong place, we'll try to find the last </div></div> and check
    // Actually, based on the previous view_file, we need an extra endif.
    // The previous view_file showed one endif at 771 (for the positions loop).
    // The if(!$election) starts at 465.
    // So we need one more endif.
    $file = str_replace('<?php include \'../includes/footer.php\'; ?>', "<?php endif; ?>\n<?php include '../includes/footer.php'; ?>", $file);
}

file_put_contents('add_candidates.php', $file);
echo "Fix applied.";
?>
