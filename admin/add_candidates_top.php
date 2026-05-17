<?php
// filename: admin/add_candidates.php
// location: admin/add_candidates.php

require_once '../config/database.php';
require_once '../includes/auth_check.php';

if($_SESSION['user_type'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

$message = '';
$error = '';
$msg_param = isset($_GET['msg']) ? $_GET['msg'] : '';

if($msg_param == 'deleted') {
    $message = "Candidate deleted successfully!";
}

// Get all elections for the switcher (Define this early to avoid warnings)
$all_elections = $pdo->query("SELECT id, title, is_active FROM elections ORDER BY created_at DESC")->fetchAll();

// Get election ID from URL or look for an active one
$election_id = isset($_GET['election_id']) ? $_GET['election_id'] : null;
$selected_position_id = isset($_GET['position_id']) ? $_GET['position_id'] : null;

// If no election ID specified, try to find the active one
if(!$election_id) {
    $stmt = $pdo->query("SELECT id, title, is_active, start_date, end_date FROM elections WHERE is_active = 1 LIMIT 1");
    $active_election = $stmt->fetch();
    if($active_election) {
        $election_id = $active_election['id'];
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

if(isset($_GET['delete_candidate'])) {
    $candidate_id = $_GET['delete_candidate'];
    
    try {
        $pdo->beginTransaction();
        
        // Get candidate info for message
        $stmt = $pdo->prepare("SELECT full_name, photo FROM candidates WHERE id = ?");
        $stmt->execute([$candidate_id]);
        $candidate = $stmt->fetch();
        
        if($candidate) {
            // Delete photo file if exists (only if it's not a student profile photo)
            if(!empty($candidate['photo']) && file_exists('../' . $candidate['photo'])) {
                // Check if this photo is used by any student
                $check_student = $pdo->prepare("SELECT COUNT(*) FROM students WHERE profile_photo = ?");
                $check_student->execute([$candidate['photo']]);
                if($check_student->fetchColumn() == 0) {
                    unlink('../' . $candidate['photo']);
                }
            }
            
            // Delete votes for this candidate to prevent FK constraint failures
            $stmt = $pdo->prepare("DELETE FROM votes WHERE candidate_id = ?");
            $stmt->execute([$candidate_id]);

            // Delete candidate
            $stmt = $pdo->prepare("DELETE FROM candidates WHERE id = ?");
            $stmt->execute([$candidate_id]);
            
            $pdo->commit();
            
            // Redirect to remove GET parameter
            header("Location: add_candidates.php?election_id=" . $election_id . "&msg=deleted");
            exit();
        }
    } catch(PDOException $e) {
        $pdo->rollBack();
        $error = "Error deleting candidate: " . $e->getMessage();
    }
}

// Handle EDIT candidate
if(isset($_POST['edit_candidate'])) {
    $candidate_id = $_POST['candidate_id'];
    $position_id = $_POST['position_id'];
    $platform = $_POST['platform'] ?? '';
    $use_student_photo = isset($_POST['use_student_photo']) ? 1 : 0;
    
    try {
        $pdo->beginTransaction();
        
        // Get candidate current info
        $stmt = $pdo->prepare("SELECT student_id, position_id, photo FROM candidates WHERE id = ?");
        $stmt->execute([$candidate_id]);
        $current = $stmt->fetch();
        
        // Handle image upload
        $image_path = null;
        
        if($use_student_photo) {
            // Use student's profile photo
            $stmt = $pdo->prepare("SELECT profile_photo FROM students WHERE student_id = ?");
            $stmt->execute([$current['student_id']]);
            $student_photo = $stmt->fetchColumn();
            
            if($student_photo) {
                $image_path = $student_photo;
            }
        } elseif(isset($_FILES['candidate_image']) && $_FILES['candidate_image']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['candidate_image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $filesize = $_FILES['candidate_image']['size'];
            
            if(in_array($ext, $allowed) && $filesize <= 5 * 1024 * 1024) {
                // Get old photo to delete (if not student photo)
                if(!empty($current['photo']) && file_exists('../' . $current['photo'])) {
                    // Check if this photo is used by any student
                    $check_student = $pdo->prepare("SELECT COUNT(*) FROM students WHERE profile_photo = ?");
                    $check_student->execute([$current['photo']]);
                    if($check_student->fetchColumn() == 0) {
                        unlink('../' . $current['photo']);
                    }
                }
                
                // Upload new photo
                $upload_dir = '../assets/uploads/candidates/';
                if(!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $new_filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9.]/', '', $filename);
                $upload_path = $upload_dir . $new_filename;
                
                if(move_uploaded_file($_FILES['candidate_image']['tmp_name'], $upload_path)) {
                    $image_path = 'assets/uploads/candidates/' . $new_filename;
                }
            }
        }
        
        // Update candidate
        $can_update = true;
        if($position_id != $current['position_id']) {
            $stmt = $pdo->prepare("SELECT max_candidates, (SELECT COUNT(*) FROM candidates WHERE position_id = ?) as current_count FROM positions WHERE id = ?");
            $stmt->execute([$position_id, $position_id]);
            $pos_info = $stmt->fetch();
            
            if($pos_info && $pos_info['current_count'] >= $pos_info['max_candidates']) {
                $can_update = false;
                $error = "The selected position has already reached its maximum candidate limit!";
            }
        }

        if($can_update) {
            if($image_path) {
                $stmt = $pdo->prepare("UPDATE candidates SET position_id = ?, platform = ?, photo = ? WHERE id = ?");
                $stmt->execute([$position_id, $platform, $image_path, $candidate_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE candidates SET position_id = ?, platform = ? WHERE id = ?");
                $stmt->execute([$position_id, $platform, $candidate_id]);
            }
            $pdo->commit();
            $message = "Candidate updated successfully!";
        } else {
            $pdo->rollBack();
        }
        
    } catch(PDOException $e) {
        $pdo->rollBack();
        $error = "Error updating candidate: " . $e->getMessage();
    }
}

// Handle ADD candidate
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_candidate'])) {
    $position_id = $_POST['position_id'];
    $student_id = $_POST['student_id'];
    $platform = $_POST['platform'] ?? '';
    $use_student_photo = isset($_POST['use_student_photo']) ? 1 : 0;
    
    // Get student details including profile photo
    $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    if(!$student) {
        $error = "Student not found!";
    } else {
        // Determine candidate photo
        $image_path = '';
        
        if($use_student_photo && !empty($student['profile_photo'])) {
            // Use student's profile photo
            $image_path = $student['profile_photo'];
        } elseif(isset($_FILES['candidate_image']) && $_FILES['candidate_image']['error'] == 0) {
            // Upload new photo
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['candidate_image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $filesize = $_FILES['candidate_image']['size'];
            
            if(!in_array($ext, $allowed)) {
                $error = "Only JPG, JPEG, PNG, and GIF files are allowed!";
            } elseif($filesize > 5 * 1024 * 1024) {
                $error = "File size must be less than 5MB!";
            } else {
                $upload_dir = '../assets/uploads/candidates/';
                if(!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $new_filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9.]/', '', $filename);
                $upload_path = $upload_dir . $new_filename;
                
                if(move_uploaded_file($_FILES['candidate_image']['tmp_name'], $upload_path)) {
                    $image_path = 'assets/uploads/candidates/' . $new_filename;
                } else {
                    $error = "Failed to upload image!";
                }
            }
        }
        
        // Final capacity check before insertion
        if(empty($error)) {
            $stmt = $pdo->prepare("SELECT max_candidates, (SELECT COUNT(*) FROM candidates WHERE position_id = ?) as current_count FROM positions WHERE id = ?");
            $stmt->execute([$position_id, $position_id]);
            $pos_info = $stmt->fetch();
            
            if($pos_info && $pos_info['current_count'] >= $pos_info['max_candidates']) {
                $error = "This position has already reached its maximum candidate limit (" . $pos_info['max_candidates'] . ")!";
            }
        }
        
        if(empty($error)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO candidates (position_id, student_id, full_name, platform, photo) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                if($stmt->execute([
                    $position_id, 
                    $student_id, 
                    $student['full_name'], 
                    $platform, 
                    $image_path
                ])) {
                    $message = "Candidate added successfully!";
                }
            } catch(PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Get positions for this election with current candidate counts
$positions = $pdo->prepare("
    SELECT p.*, (SELECT COUNT(*) FROM candidates c WHERE c.position_id = p.id) as current_candidates 
    FROM positions p 
    WHERE p.election_id = ? 
    ORDER BY p.display_order
");
$positions->execute([$election_id]);
$positions = $positions->fetchAll();

// Get eligible registered students with their profile photos
$students = $pdo->prepare("
    SELECT s.*, 
           CASE 
               WHEN s.profile_photo IS NOT NULL AND s.profile_photo != '' THEN 1 
               ELSE 0 
           END as has_photo
    FROM students s 
    WHERE s.student_id NOT IN (
        SELECT c.student_id FROM candidates c 
        JOIN positions p ON c.position_id = p.id 
        WHERE p.election_id = ?
    )
    ORDER BY s.full_name
");
$students->execute([$election_id]);
$students = $students->fetchAll();

// Get candidates grouped by position with student photo info
$candidates_by_position = [];
if($election_id) {
    $candidates = $pdo->prepare("
        SELECT 
            c.*,
            p.position_name,
            p.id as position_id,
            p.display_order,
            s.profile_photo as student_photo,
            (SELECT COUNT(*) FROM votes v WHERE v.candidate_id = c.id) as vote_count,
            CASE 
                WHEN c.photo = s.profile_photo AND s.profile_photo IS NOT NULL THEN 1 
                ELSE 0 
            END as uses_student_photo
        FROM candidates c
        JOIN positions p ON c.position_id = p.id
        LEFT JOIN students s ON c.student_id = s.student_id
        WHERE p.election_id = ?
        ORDER BY p.display_order, c.full_name
    ");
    $candidates->execute([$election_id]);
    $candidates = $candidates->fetchAll();
    
    foreach($candidates as $candidate) {
        if(!isset($candidates_by_position[$candidate['position_name']])) {
            $candidates_by_position[$candidate['position_name']] = [];
        }
        $candidates_by_position[$candidate['position_name']][] = $candidate;
    }
}

// Calculate total candidates
$total_candidates = 0;
foreach($candidates_by_position as $pos_candidates) {
    $total_candidates += count($pos_candidates);
}

include '../includes/header.php';
?>
