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
$all_elections = $pdo->query("SELECT id, title, start_date, end_date, is_active FROM elections ORDER BY created_at DESC")->fetchAll();

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

// Handle AUTO ADD candidates
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['auto_add_candidates'])) {
    $stmt = $pdo->prepare("
        SELECT s.*, 
               ca.platform_statement,
               ca.position_applied
        FROM students s 
        JOIN candidate_applications ca ON s.student_id = ca.student_id
        WHERE ca.election_id = ? AND ca.status = 'approved'
        AND s.student_id NOT IN (
            SELECT c.student_id FROM candidates c 
            JOIN positions p ON c.position_id = p.id 
            WHERE p.election_id = ?
        )
    ");
    $stmt->execute([$election_id, $election_id]);
    $pending_students = $stmt->fetchAll();
    
    $added = 0;
    $skipped = 0;
    
    foreach($pending_students as $student) {
        $pos_name = $student['position_applied'];
        
        $stmt_pos = $pdo->prepare("SELECT id, max_candidates, (SELECT COUNT(*) FROM candidates WHERE position_id = positions.id) as current_count FROM positions WHERE election_id = ? AND position_name = ?");
        $stmt_pos->execute([$election_id, $pos_name]);
        $pos_info = $stmt_pos->fetch();
        
        if($pos_info && $pos_info['current_count'] < $pos_info['max_candidates']) {
            $stmt_insert = $pdo->prepare("
                INSERT INTO candidates (position_id, student_id, full_name, platform, photo) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            if($stmt_insert->execute([
                $pos_info['id'], 
                $student['student_id'], 
                $student['full_name'], 
                $student['platform_statement'] ?? '', 
                $student['profile_photo'] ?? ''
            ])) {
                $added++;
            } else {
                $skipped++;
            }
        } else {
            $skipped++;
        }
    }
    
    $message = "Auto-added $added candidates. Skipped $skipped candidates.";
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

// Get eligible registered students with their profile photos (must have approved application)
$students = $pdo->prepare("
    SELECT s.*, 
           CASE 
               WHEN s.profile_photo IS NOT NULL AND s.profile_photo != '' THEN 1 
               ELSE 0 
           END as has_photo,
           ca.platform_statement,
           ca.position_applied
    FROM students s 
    JOIN candidate_applications ca ON s.student_id = ca.student_id
    WHERE ca.election_id = ? AND ca.status = 'approved'
    AND s.student_id NOT IN (
        SELECT c.student_id FROM candidates c 
        JOIN positions p ON c.position_id = p.id 
        WHERE p.election_id = ?
    )
    ORDER BY 
        CASE ca.position_applied
            WHEN 'President' THEN 1
            WHEN 'Vice President' THEN 2
            WHEN 'Treasurer' THEN 3
            WHEN 'Internal Secretary' THEN 4
            WHEN 'External Secretary' THEN 5
            WHEN 'P.R.O' THEN 6
            WHEN 'Auditor 1' THEN 7
            WHEN 'Auditor 2( Assistant Auditor)' THEN 8
            WHEN 'Business Manager' THEN 9
            WHEN 'Marshall 1' THEN 10
            WHEN 'Marshall 2' THEN 11
            ELSE 99 
        END, 
        s.full_name
");
$students->execute([$election_id, $election_id]);
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

<style>
  @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@600;700&display=swap');
  
  * { box-sizing: border-box; }
  
  body {
    font-family: 'Plus Jakarta Sans', sans-serif;
    min-height: 100vh;
    background:
      radial-gradient(ellipse at 10% 20%, rgba(99,179,237,0.25) 0%, transparent 50%),
      radial-gradient(ellipse at 85% 10%, rgba(167,139,250,0.2) 0%, transparent 45%),
      radial-gradient(ellipse at 60% 80%, rgba(110,231,183,0.2) 0%, transparent 45%),
      radial-gradient(ellipse at 20% 85%, rgba(251,191,36,0.15) 0%, transparent 40%),
      #eef2ff;
    background-attachment: fixed;
    color: #1e293b;
  }
  
  /* floating blobs */
  body::before, body::after {
    content: '';
    position: fixed;
    border-radius: 50%;
    filter: blur(80px);
    pointer-events: none;
    z-index: 0;
  }
  body::before {
    width: 500px; height: 500px;
    top: -100px; left: -100px;
    background: rgba(99,179,237,0.2);
    animation: drift 12s ease-in-out infinite alternate;
  }
  body::after {
    width: 400px; height: 400px;
    bottom: -80px; right: -80px;
    background: rgba(167,139,250,0.2);
    animation: drift 15s ease-in-out infinite alternate-reverse;
  }
  @keyframes drift {
    from { transform: translate(0,0) scale(1); }
    to   { transform: translate(40px,30px) scale(1.08); }
  }

    .ac-page-wrap {
    position: relative; z-index: 1;
    max-width: 1400px; margin: 0 auto;
    padding: 1.5rem 1.75rem 4rem;
  }
  
    .glass-panel {
        background: rgba(15,17,23,0.78);
        backdrop-filter: blur(18px) saturate(150%);
        -webkit-backdrop-filter: blur(18px) saturate(150%);
        border: 1px solid rgba(99,102,241,0.12);
        border-radius: 20px;
        padding: 24px;
        box-shadow: 0 18px 50px rgba(0,0,0,0.45);
        height: fit-content;
        color: #e2e8f0;
    }
  
    .ac-header {
        background: rgba(15,17,23,0.86);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border-radius: 20px;
        border: 1px solid rgba(99,102,241,0.12);
        padding: 1.2rem 1.8rem;
        display: flex; align-items: center; justify-content: space-between;
        margin-bottom: 2rem;
        box-shadow: 0 18px 50px rgba(0,0,0,0.45);
        color: #e2e8f0;
    }
  
  .ac-logo { display:flex; align-items:center; gap:12px; }
  .ac-logo-icon { width:36px; height:36px; background:linear-gradient(135deg,#6366f1,#8b5cf6); border-radius:10px; display:flex; align-items:center; justify-content:center; box-shadow:0 4px 12px rgba(99,102,241,0.3); }
  .ac-logo-icon svg { width:20px; height:20px; stroke:#fff; fill:none; stroke-width:3; }
    .ac-logo-text { font-family:'Sora',sans-serif; font-size:18px; font-weight:800; color:#e2e8f0; margin:0;}
    .ac-logo-text span { color:#34d399; }
  
    .ac-election-badge { background:rgba(15,17,23,0.7); border:1px solid rgba(99,102,241,0.08); color:#cbd5e1; font-size:11px; font-weight:700; padding:6px 14px; border-radius:20px; text-transform:uppercase; letter-spacing:0.02em; }
    .ac-election-badge.active { background:rgba(34,197,94,0.08); border-color: rgba(34,197,94,0.12); color:#86efac; }
  
    .ac-page-title { display:flex; align-items:center; gap:12px; margin-bottom:1.5rem; padding-left:0.5rem; }
    .ac-page-title h1 { font-family:'Sora',sans-serif; font-size:24px; font-weight:800; color:#e2e8f0; margin:0; letter-spacing:-0.01em;}
  
    .ac-info-banner { margin-bottom: 24px; background:rgba(20,22,35,0.6); border:1px solid rgba(99,102,241,0.08); border-radius:18px; padding:16px 20px; display:flex; gap:14px; align-items:flex-start; backdrop-filter:blur(8px); }
    .ac-info-banner p { font-size:13.5px; color:#c7d2fe; line-height:1.6; margin:0; }
  
    .ac-body { display:grid; grid-template-columns:400px 1fr; gap:24px; margin-bottom: 40px; }
  @media (max-width: 1100px) { .ac-body { grid-template-columns: 1fr; } }
  
    .ac-form-title { font-family:'Sora',sans-serif; font-size:15px; font-weight:700; color:#cbd5e1; text-transform:uppercase; letter-spacing:0.04em; display:flex; align-items:center; gap:10px; margin-bottom:24px; padding-bottom:12px; border-bottom:1px solid rgba(99,102,241,0.06); }
    .ac-label { font-size:12px; font-weight:700; color:#94a3b8; margin-bottom:10px; display:flex; align-items:center; gap:6px; text-transform:uppercase; letter-spacing:0.03em; }
  
  .ac-field { margin-bottom: 22px; }
  .ac-select, .ac-textarea { 
      width:100%; border:1.5px solid rgba(99,102,241,0.08); border-radius:14px; padding:13px 18px; 
      font-size:14px; color:#e2e8f0; background:rgba(20,22,35,0.7); outline:none; transition:all .3s; 
      font-family: inherit;
  }
  .ac-select:focus, .ac-textarea:focus { border-color:#6366f1; background:rgba(30,32,48,0.95); box-shadow:0 0 0 6px rgba(99,102,241,0.06); }
  .ac-select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236366f1' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 18px center; }
  
  .ac-photo-box {
      border: 2px dashed rgba(99,102,241,0.18);
      border-radius: 16px;
      padding: 30px 20px;
      text-align: center;
      cursor: pointer;
      transition: all 0.3s;
      position: relative;
      background: rgba(15,17,23,0.6);
  }
  .ac-photo-box:hover { border-color: #6366f1; background: rgba(99,102,241,0.04); }
  .ac-photo-preview { width: 72px; height: 72px; background: rgba(255,255,255,0.02); border-radius: 50%; margin: 0 auto 15px; display: flex; align-items: center; justify-content: center; box-shadow: 0 6px 20px rgba(0,0,0,0.5); border: 2px solid rgba(99,102,241,0.08); }
  .ac-photo-preview svg { width: 34px; height: 34px; stroke: #94a3b8; stroke-width: 1.5; fill: none; }
  .ac-photo-preview img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
  .ac-photo-hint { font-size: 13px; color: #64748b; line-height: 1.5; font-weight: 500; }
  .ac-photo-hint span { display: block; font-size: 11px; color: #94a3b8; margin-top: 4px; font-weight: 400; }
  .ac-file-input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
  
  .ac-btn-add { 
      width:100%; padding:15px; background:linear-gradient(135deg, #10b981, #059669); color:#fff; border:none; border-radius:14px; 
      font-weight:700; font-size:15px; margin-top:10px; transition:all 0.3s;
      box-shadow: 0 8px 26px rgba(16,185,129,0.16); display: flex; align-items: center; justify-content: center; gap: 8px;
  }
  .ac-btn-add:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 12px 30px rgba(16,185,129,0.18); }
  .ac-btn-add:disabled { opacity: 0.6; cursor: not-allowed; box-shadow: none; }
  
  .ac-btn-back { 
      display:flex; align-items:center; justify-content:center; gap:6px; margin-top:16px; font-size:13px; color:#cbd5e1; 
      text-decoration:none; padding:12px; border:1.5px solid rgba(99,102,241,0.08); border-radius:14px; transition:all 0.3s; font-weight: 600;
  }
  .ac-btn-back:hover { background: rgba(255,255,255,0.02); color:#fff; border-color: rgba(99,102,241,0.12); }
  
  .ac-list-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:28px; }
  .ac-list-title { font-family:'Sora',sans-serif; font-size:18px; font-weight:800; display:flex; align-items:center; gap:12px; color:#1e293b; }
  .ac-total-badge { background:rgba(99, 102, 241, 0.1); color:#6366f1; font-size:12px; font-weight:700; padding:6px 14px; border-radius:20px; box-shadow: 0 2px 8px rgba(99,102,241,0.05); }
  
  .ac-position-group { margin-bottom: 35px; }
  .ac-position-label { display:flex; align-items:center; gap:14px; margin-bottom:24px; padding-bottom:14px; border-bottom:1px solid rgba(0,0,0,0.04); }
  .ac-position-name { font-size:15px; font-weight:800; color:#1e293b; display:flex; align-items:center; gap:8px; }
  .ac-position-count { font-size:12px; color:#94a3b8; font-weight:600; background:rgba(0,0,0,0.03); padding:3px 10px; border-radius:10px; margin-left: auto; }
  
  .ac-candidates-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; }
  
  .ac-cand-card {
      background: rgba(15,17,23,0.78);
      border: 1.5px solid rgba(99,102,241,0.12);
      border-radius: 20px;
      padding: 24px 20px;
      text-align: center;
      transition: all 0.3s cubic-bezier(.34,1.56,.64,1);
      position: relative;
      display: flex; flex-direction: column;
      box-shadow: 0 12px 36px rgba(0,0,0,0.5);
      color: #e2e8f0;
  }
  .ac-cand-card:hover { transform: translateY(-6px); border-color: #6366f1; box-shadow: 0 18px 44px rgba(0,0,0,0.55); background: rgba(99,102,241,0.06); }
  
  .ac-student-tag, .ac-upload-tag {
      position: absolute; top: 12px; right: 12px; font-size: 10px; font-weight: 800; 
      padding: 3px 10px; border-radius: 8px; text-transform: uppercase; letter-spacing: 0.03em;
  }
  .ac-student-tag { background: rgba(16, 185, 129, 0.1); color: #059669; }
  .ac-upload-tag { background: rgba(99, 102, 241, 0.1); color: #6366f1; }

  .ac-cand-avatar {
      width: 80px; height: 80px; border-radius: 50%;
      background: linear-gradient(135deg, #0ea5a9, #059669);
      color: #051923;
      font-weight: 800; font-size: 24px;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 18px;
      overflow: hidden;
      border: 4px solid rgba(99,102,241,0.08);
      box-shadow: 0 10px 30px rgba(0,0,0,0.5);
  }
  .ac-cand-avatar img { width: 100%; height: 100%; object-fit: cover; }
  
  .ac-cand-name { font-family:'Sora',sans-serif; font-size:15px; font-weight:800; color:#1e293b; margin-bottom:6px; line-height:1.2; }
  .ac-cand-id { font-size:12px; color:#94a3b8; font-weight:600; margin-bottom:14px; background: rgba(0,0,0,0.02); display: inline-block; padding: 2px 10px; border-radius: 8px; }
  .ac-cand-votes { background:rgba(16, 185, 129, 0.08); color:#059669; font-size:11px; font-weight:700; padding:6px 14px; border-radius:20px; display:inline-flex; align-items:center; gap:5px; margin-bottom:20px; align-self: center; }
  
  .ac-cand-actions { display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-top:auto; }
  .ac-btn-edit, .ac-btn-del {
      padding: 10px; border-radius: 12px; border: 1.5px solid rgba(0,0,0,0.04);
      background: rgba(255,255,255,0.7); color: #475569; font-size: 13px; font-weight: 700;
      transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 6px;
  }
  .ac-btn-edit:hover { background: #f1f5f9; border-color: #6366f1; color: #6366f1; }
  .ac-btn-del:hover { background: rgba(239, 68, 68, 0.05); border-color: rgba(239, 68, 68, 0.2); color: #ef4444; }
  
  .ac-btn-voters {
      display: flex; align-items: center; justify-content: center; gap: 6px;
      margin-top: 10px; padding: 9px; border-radius: 12px;
      font-size: 12px; font-weight: 700; text-decoration: none;
      background: rgba(99,102,241,0.08); color: #6366f1;
      border: 1.5px solid rgba(99,102,241,0.18); transition: all 0.2s;
  }
  .ac-btn-voters:hover { background: rgba(99,102,241,0.14); border-color: #6366f1; color: #4338ca; transform: translateY(-1px); }
  
  .ac-empty { text-align:center; padding:40px 20px; color:#94a3b8; }
  .ac-empty svg { width: 64px; height: 64px; margin-bottom: 20px; stroke: currentColor; opacity: 0.5; }
  .ac-divider { height:1px; background:rgba(0,0,0,0.05); margin:40px 0; border:none; }
  
  /* MODAL OVERRIDES */
  .modal-content { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); border: 1px solid white; border-radius: 24px; overflow: hidden; box-shadow: 0 25px 60px rgba(0,0,0,0.15); }
  .modal-header { border-bottom: 1px solid rgba(0,0,0,0.04); padding: 20px 24px; }
  .modal-title { font-family:'Sora',sans-serif; font-weight:800; color: #1e293b; font-size: 18px; }
  .modal-body { padding: 24px; }
  .modal-body.bg-light { background: rgba(248, 250, 252, 0.5) !important; }
  .modal-footer { border-top: 1px solid rgba(0,0,0,0.04); padding: 18px 24px; }
  
  .btn-close { opacity: 0.8; transition: all 0.2s; }
  .btn-close:hover { opacity: 1; transform: rotate(90deg); }

  .alert { border: none; border-radius: 16px; padding: 16px 20px; font-weight: 600; font-size: 14px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); }
  .alert-success { background: #dcfce7; color: #166534; }
  .alert-danger { background: #fee2e2; color: #991b1b; }
</style>

<style>
  :root {
    --glass-bg: rgba(255, 255, 255, 0.9);
    --glass-border: rgba(15, 127, 99, 0.14);
    --text-main: #0f172a;
    --text-muted: #64748b;
    --surface: rgba(255,255,255,0.95);
  }

  body {
    color: var(--text-main) !important;
    background:
      radial-gradient(at 0% 0%, rgba(15,127,99,0.22) 0px, transparent 55%),
      radial-gradient(at 100% 0%, rgba(16,185,129,0.18) 0px, transparent 45%),
      radial-gradient(at 100% 100%, rgba(15,127,99,0.10) 0px, transparent 45%),
      radial-gradient(at 0% 100%, rgba(94,234,212,0.09) 0px, transparent 40%),
      #f4fbf7;
    background-attachment: fixed;
  }

  .ac-page-wrap { padding: 1.5rem 1.75rem 4rem; max-width: 1320px; margin: 0 auto; }
  .glass-panel {
    background: var(--surface) !important;
    border: 1px solid var(--glass-border) !important;
    backdrop-filter: blur(20px) saturate(180%) !important;
    -webkit-backdrop-filter: blur(20px) saturate(180%) !important;
    border-radius: 24px !important;
    box-shadow: 0 22px 60px rgba(15,127,99,0.08) !important;
  }
  .glass-panel h3 { color: var(--text-main) !important; }
  .ac-header, .ac-body, .modal-content, .modal-head, .modal-footer { background: transparent !important; }

  .ac-page-wrap .accordion-button,
  .ac-page-wrap .accordion-body,
  .ac-page-wrap .ac-info,
  .ac-page-wrap .ac-cand-card,
  .ac-page-wrap .ac-photo-box,
  .ac-page-wrap .dropzone,
  .ac-page-wrap .input-group-text,
  .ac-page-wrap .btn-add,
  .ac-page-wrap .btn-back,
  .ac-page-wrap .btn-action {
    border-radius: 18px !important;
  }

  .ac-header { padding: 1.4rem 1.6rem !important; }
  .ac-body { padding: 1.4rem 1.6rem !important; }

  .ac-photo-box {
    background: rgba(255,255,255,0.95) !important;
    border: 1px solid rgba(15,127,99,0.12) !important;
    box-shadow: 0 18px 40px rgba(15,127,99,0.06) !important;
  }
  .ac-cand-card {
    background: rgba(255,255,255,0.94) !important;
    border: 1px solid rgba(15,127,99,0.12) !important;
    box-shadow: 0 18px 40px rgba(15,127,99,0.06) !important;
  }

  .dropzone {
    background: rgba(248,250,252,0.85) !important;
    border: 1px dashed rgba(15,127,99,0.24) !important;
  }
  .input-group-text { background: rgba(255,255,255,0.92) !important; border-color: rgba(15,127,99,0.12) !important; color: var(--text-main) !important; }
  .form-control, .form-select, .form-control:focus, .form-select:focus { background: rgba(255,255,255,0.95) !important; border-color: rgba(15,127,99,0.2) !important; color: var(--text-main) !important; box-shadow: none !important; }

  .btn-add,
  .btn-action,
  .btn-save { background: linear-gradient(135deg, #6366f1, #818cf8) !important; color: #fff !important; border: none !important; }
  .btn-back,
  .btn-secondary { background: rgba(255,255,255,0.92) !important; color: #475569 !important; border: 1px solid rgba(15,127,99,0.12) !important; }

  .alert { box-shadow: 0 18px 40px rgba(15,127,99,0.06) !important; }

  [data-theme="dark"] body {
    background:
      radial-gradient(at 0% 0%, rgba(99,102,241,0.08) 0px, transparent 50%),
      radial-gradient(at 100% 0%, rgba(139,92,246,0.06) 0px, transparent 50%),
      radial-gradient(at 100% 100%, rgba(16,185,129,0.05) 0px, transparent 50%),
      #0d0f1a !important;
    color: #e2e8f0 !important;
  }
  [data-theme="dark"] .glass-panel { background: rgba(20,22,35,0.88) !important; border-color: rgba(99,102,241,0.14) !important; box-shadow: 0 18px 44px rgba(0,0,0,0.45) !important; }
  [data-theme="dark"] .ac-photo-box,
  [data-theme="dark"] .ac-cand-card,
  [data-theme="dark"] .dropzone,
  [data-theme="dark"] .form-control,
  [data-theme="dark"] .form-select,
  [data-theme="dark"] .input-group-text { background: rgba(15,17,23,0.9) !important; border-color: rgba(99,102,241,0.12) !important; color: #e2e8f0 !important; }
  [data-theme="dark"] .btn-back,
  [data-theme="dark"] .btn-secondary { background: rgba(15,17,23,0.85) !important; color: #94a3b8 !important; border-color: rgba(99,102,241,0.12) !important; }
  [data-theme="dark"] .btn-add,
  [data-theme="dark"] .btn-action,
  [data-theme="dark"] .btn-save { background: linear-gradient(135deg, #6366f1, #818cf8) !important; }
  [data-theme="dark"] .alert { background: rgba(15,17,23,0.84) !important; color: #e2e8f0 !important; border-color: rgba(99,102,241,0.12) !important; }
</style>

<?php if($message): ?>
    <div class="alert alert-success alert-dismissible fade show mb-4">
        <i class="bi bi-check-circle-fill"></i> <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if($error): ?>
    <div class="alert alert-danger alert-dismissible fade show mb-4">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="ac-page-wrap">

<div class="ac-header">
  <div class="ac-logo">
    <div class="ac-logo-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
    </div>
    <div class="ac-logo-text">Council<span>Vote</span></div>
  </div>
  <div class="ac-header-right">
    <?php if($election): ?>
        <span class="ac-election-badge <?php echo $election['is_active'] ? 'active' : ''; ?>">
            <?php echo $election['is_active'] ? 'ACTIVE ELECTION' : 'ENDED ELECTION'; ?>
        </span>
        <div class="dropdown d-inline-block">
            <button class="btn btn-sm btn-outline-primary dropdown-toggle ac-date-badge" type="button" data-bs-toggle="dropdown" style="border:1.5px solid #c7d2fe; background:#f1f0fe; color:#4338ca; font-size:11px; font-weight:600; padding:5px 12px; border-radius:20px;">
                <i class="bi bi-arrow-left-right"></i> Switch Election
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="border-radius:12px; font-size:13px;">
                <li class="dropdown-header">Choose Election</li>
                <?php foreach($all_elections as $elec): ?>
                    <li>
                        <a class="dropdown-item <?php echo $elec['id'] == $election_id ? 'active bg-primary' : ''; ?>" href="?election_id=<?php echo $elec['id']; ?>">
                            <?php echo htmlspecialchars($elec['title']); ?>
                            <?php 
                                $now = time();
                                $el_start = strtotime($elec['start_date']);
                                $el_end   = strtotime($elec['end_date']);
                                if($now < $el_start) { $el_status = 'upcoming'; }
                                elseif($now >= $el_start && $now <= $el_end) { $el_status = 'active'; }
                                else { $el_status = 'ended'; }
                            ?>
                            <?php if($el_status === 'active'): ?>
                                <span class="badge bg-success">Active</span>
                            <?php elseif($el_status === 'upcoming'): ?>
                                <span class="badge" style="background:#6366f1;">Upcoming</span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
  </div>
</div>

<div class="ac-page-title">
  <div style="width: 42px; height: 42px; background: rgba(99,102,241,0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">👥</div>
  <h1>Add Candidates</h1>
</div>

<div class="ac-info-banner">
  <div style="font-size: 1.2rem;">💡</div>
  <p><strong>Candidate Photos:</strong> Candidates can automatically use their student profile photo if available. You can also upload a separate photo for the candidate. If a student updates their profile photo later, it will NOT automatically update in the election.</p>
</div>

<?php if(!$election): ?>
    <div class="glass-panel text-center py-5 mx-auto" style="max-width: 700px; margin-top: 20px;">
        <div class="ac-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <h4 class="mb-3" style="font-family:'Sora',sans-serif; font-weight:700;">No Active Election Found</h4>
            <p class="text-muted mb-4">Please select an election from the list below to manage its candidates.</p>

            
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="list-group shadow-none" style="border-radius:12px; background:transparent;">
                        <?php foreach($all_elections as $elec): ?>
                            <a href="?election_id=<?php echo $elec['id']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3" style="background:rgba(255,255,255,0.3); border:1px solid rgba(150,150,255,0.1); backdrop-filter:blur(10px); -webkit-backdrop-filter:blur(10px); transition:all .2s; border-radius:10px; margin-bottom:8px; color: #4b5563; font-weight: 500;">
                                <span class="fw-bold"><?php echo htmlspecialchars($elec['title']); ?></span>
                                <?php 
                                    $now2 = time();
                                    $el_start2 = strtotime($elec['start_date']);
                                    $el_end2   = strtotime($elec['end_date']);
                                    if($now2 < $el_start2) { $el_status2 = 'upcoming'; }
                                    elseif($now2 >= $el_start2 && $now2 <= $el_end2) { $el_status2 = 'active'; }
                                    else { $el_status2 = 'ended'; }
                                ?>
                                <?php if($el_status2 === 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php elseif($el_status2 === 'upcoming'): ?>
                                    <span class="badge" style="background:#6366f1;">Upcoming</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Ended</span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                        <?php if(empty($all_elections)): ?>
                            <div class="list-group-item py-4">
                                <p class="mb-0">No elections created yet.</p>
                                <a href="create_election.php" class="btn btn-primary mt-3 rounded-pill">Create First Election</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
<div class="ac-body">
  <!-- FORM -->
  <div class="glass-panel">
    <div class="ac-form-title">
      <span style="font-size: 1.1rem;">📝</span>
      Register New Candidate
    </div>
    <form method="POST" action="" enctype="multipart/form-data" id="candidateForm">
      
      <div class="ac-field">
        <div class="ac-label">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
          Position
        </div>
        <select name="position_id" class="ac-select" required>
          <option value="">-- Select Position --</option>
          <?php foreach($positions as $position): 
              $is_full = ($position['current_candidates'] >= $position['max_candidates']);
              $cap_text = "(" . $position['current_candidates'] . "/" . $position['max_candidates'] . ")";
          ?>
              <option value="<?php echo $position['id']; ?>" 
                  data-pos-name="<?php echo htmlspecialchars($position['position_name']); ?>"
                  <?php echo $is_full ? 'disabled style="color:#cbd5e1;"' : ''; ?>
                  <?php echo ($selected_position_id == $position['id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($position['position_name']); ?> 
                  <?php echo $cap_text; ?>
                  <?php echo $is_full ? ' — FULL' : ''; ?>
              </option>
          <?php endforeach; ?>
        </select>
        <?php if(count($positions) == 0): ?>
            <p style="font-size:11px;color:#d97706;margin-top:5px;">No positions found. <a href="manage_positions.php?election_id=<?php echo $election_id; ?>">Add positions first</a>.</p>
        <?php endif; ?>
      </div>

      <div class="ac-field">
        <div class="ac-label">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          Student
        </div>
        <select name="student_id" class="ac-select" id="stu-select" required>
          <option value="">-- Select Student --</option>
          <?php foreach($students as $student): ?>
              <option value="<?php echo $student['student_id']; ?>" 
                      data-photo="<?php echo $student['profile_photo'] ?? ''; ?>"
                      data-name="<?php echo htmlspecialchars($student['full_name']); ?>"
                      data-has-photo="<?php echo $student['has_photo']; ?>"
                      data-platform="<?php echo htmlspecialchars($student['platform_statement']); ?>"
                      data-position="<?php echo htmlspecialchars($student['position_applied']); ?>">
                  <?php echo htmlspecialchars('(' . $student['position_applied'] . ') ' . $student['full_name'] . ' – ' . $student['student_id']); ?>
              </option>
          <?php endforeach; ?>
        </select>
        <!-- Hidden input set by JS if using student photo -->
        <input type="hidden" name="use_student_photo" id="hdn-use-photo" value="0">
      </div>


      <div class="ac-field">
        <div class="ac-label">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          Platform / Manifesto
        </div>
        <textarea name="platform" class="ac-textarea" placeholder="What are their goals and promises?"></textarea>
      </div>

      <button type="submit" name="add_candidate" class="ac-btn-add" <?php echo (count($students) == 0 || count($positions) == 0) ? 'disabled' : ''; ?>>
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
        Add Candidate
      </button>
      <a href="dashboard.php" class="ac-btn-back">← Back to Dashboard</a>
    </form>
    
    <hr style="border-color: rgba(99,102,241,0.2); margin: 20px 0;">
    <div style="font-size: 13px; color: #94a3b8; margin-bottom: 10px; text-align: center;">Or automatically add all approved applicants</div>
    <form method="POST" action="">
        <button type="submit" name="auto_add_candidates" class="ac-btn-add" style="background: linear-gradient(135deg, #3b82f6, #2563eb); margin-top: 0;" <?php echo (count($students) == 0) ? 'disabled' : ''; ?> onclick="return confirm('Auto add all approved applicants as candidates in their applied positions?');">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
          Auto Add Approved Candidates
        </button>
    </form>
  </div>

  <!-- CANDIDATE LIST INLINE -->
  <div class="glass-panel" style="padding: 0; overflow: hidden;">
    <!-- Header with View All button -->
    <div style="display:flex; align-items:center; justify-content:space-between; padding: 18px 22px; border-bottom: 1px solid rgba(99,102,241,0.12);">
      <div style="display:flex; align-items:center; gap:10px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        <span style="font-family:'Sora',sans-serif; font-weight:800; font-size:15px; color:var(--text-main);">Current Candidates</span>
        <span class="ac-total-badge"><?php echo $total_candidates; ?> Total</span>
      </div>
      <?php if($total_candidates > 0): ?>
      <button type="button" data-bs-toggle="modal" data-bs-target="#viewAllCandidatesModal"
        style="display:inline-flex; align-items:center; gap:7px; padding:8px 18px; border-radius:30px; border:none; background:linear-gradient(135deg,#6366f1,#8b5cf6); color:#fff; font-weight:700; font-size:13px; cursor:pointer; transition:all 0.25s; box-shadow:0 4px 12px rgba(99,102,241,0.3);"
        onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 20px rgba(99,102,241,0.45)';"
        onmouseout="this.style.transform=''; this.style.boxShadow='0 4px 12px rgba(99,102,241,0.3)';">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        View All
      </button>
      <?php endif; ?>
    </div>

    <!-- Candidate list body -->
    <div style="padding: 16px 22px; max-height: 520px; overflow-y: auto;">
      <?php if(count($candidates_by_position) > 0): ?>
        <?php foreach($candidates_by_position as $position_name => $position_candidates): ?>
          <div style="margin-bottom: 20px;">
            <!-- Position label -->
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
              <span style="font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:#6366f1; background:rgba(99,102,241,0.1); padding:4px 10px; border-radius:20px;">
                <?php echo htmlspecialchars($position_name); ?>
              </span>
              <span style="font-size:11px; color:var(--text-muted);"><?php echo count($position_candidates); ?> candidates</span>
            </div>
            <!-- Candidate rows -->
            <?php foreach($position_candidates as $candidate):
              $img = '';
              if(!empty($candidate['photo']) && file_exists('../' . $candidate['photo'])) $img = $candidate['photo'];
              elseif(!empty($candidate['student_photo']) && file_exists('../' . $candidate['student_photo'])) $img = $candidate['student_photo'];
              $initials = '';
              if(!$img){ foreach(explode(' ', $candidate['full_name']) as $p){ if($p) $initials .= strtoupper($p[0]); } $initials = substr($initials,0,2); }
            ?>
            <div style="display:flex; align-items:center; gap:12px; padding:10px 12px; border-radius:14px; background:rgba(99,102,241,0.04); border:1px solid rgba(99,102,241,0.08); margin-bottom:8px; transition:background 0.2s;"
              onmouseover="this.style.background='rgba(99,102,241,0.1)';" onmouseout="this.style.background='rgba(99,102,241,0.04)';">
              <!-- Avatar -->
              <div style="width:40px; height:40px; border-radius:50%; overflow:hidden; flex-shrink:0; background:linear-gradient(135deg,#6366f1,#8b5cf6); display:flex; align-items:center; justify-content:center; font-weight:800; color:#fff; font-size:14px;">
                <?php if($img): ?><img src="../<?php echo $img; ?>" style="width:100%;height:100%;object-fit:cover;"><?php else: ?><?php echo $initials; ?><?php endif; ?>
              </div>
              <!-- Info -->
              <div style="flex:1; min-width:0;">
                <div style="font-weight:700; font-size:13px; color:var(--text-main); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo htmlspecialchars($candidate['full_name']); ?></div>
                <div style="font-size:11px; color:var(--text-muted);"><?php echo htmlspecialchars($candidate['student_id']); ?> &bull; <?php echo $candidate['vote_count'] ?? 0; ?> votes</div>
              </div>
              <!-- Actions -->
              <div style="display:flex; gap:6px; flex-shrink:0;">
                <button type="button" class="ac-btn-edit" data-bs-toggle="modal" data-bs-target="#editCandidateModal<?php echo $candidate['id']; ?>" style="padding:5px 10px; font-size:11px;">Edit</button>
                <button type="button" class="ac-btn-del" data-bs-toggle="modal" data-bs-target="#deleteCandidateModal<?php echo $candidate['id']; ?>" style="padding:5px 10px; font-size:11px;">Del</button>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="ac-empty" style="padding: 40px 0;">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 7a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/></svg>
          <h6>No candidates added yet</h6>
          <p class="small">Use the form on the left to add candidates.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>
</div>

<!-- VIEW ALL CANDIDATES MODAL -->
<div class="modal fade" id="viewAllCandidatesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content glass-panel" style="border: 1px solid rgba(99,102,241,0.2); box-shadow: 0 30px 60px rgba(0,0,0,0.4); border-radius: 24px;">
      <div class="modal-header border-0 pb-0 pt-4 px-4">
        <h4 class="modal-title" style="font-family:'Sora',sans-serif; font-weight:800; display:flex; align-items:center; gap:12px; color: var(--text-main);">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
          All Current Candidates
        </h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" <?php echo isset($_COOKIE['cv_theme']) && $_COOKIE['cv_theme'] == 'dark' ? 'style="filter:invert(1);"' : ''; ?>></button>
      </div>
      <div class="modal-body px-4 py-4" style="background: transparent;">
        <?php foreach($candidates_by_position as $position_name => $position_candidates): ?>
            <div class="ac-position-group">
              <div class="ac-position-label">
                <span class="ac-position-name"><?php echo htmlspecialchars($position_name); ?></span>
                <span class="ac-position-count"><?php echo count($position_candidates); ?> candidates</span>
              </div>
              
              <div class="ac-candidates-grid">
                  <?php foreach($position_candidates as $candidate): ?>
                      <div class="ac-cand-card" style="background: var(--surface);">
                        
                        <?php if($candidate['uses_student_photo']): ?>
                            <span class="ac-student-tag">Student</span>
                        <?php elseif(!empty($candidate['photo'])): ?>
                            <span class="ac-upload-tag">Uploaded</span>
                        <?php endif; ?>
                        
                        <?php 
                        $image_to_show = '';
                        if(!empty($candidate['photo']) && file_exists('../' . $candidate['photo'])) {
                            $image_to_show = $candidate['photo'];
                        } elseif(!empty($candidate['student_photo']) && file_exists('../' . $candidate['student_photo'])) {
                            $image_to_show = $candidate['student_photo'];
                        }
                        
                        if(!$image_to_show) {
                            $initials = '';
                            $parts = explode(' ', $candidate['full_name']);
                            foreach($parts as $p) { if(!empty($p)) $initials .= strtoupper(substr($p, 0, 1)); }
                            $initials = substr($initials, 0, 2);
                        }
                        ?>
                        <div class="ac-cand-avatar">
                            <?php if($image_to_show): ?>
                                <img src="../<?php echo $image_to_show; ?>" alt="Photo">
                            <?php else: ?>
                                <?php echo $initials; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="ac-cand-name"><?php echo htmlspecialchars($candidate['full_name']); ?></div>
                        
                        <div class="ac-cand-id">
                            <?php echo htmlspecialchars($candidate['student_id']); ?>
                        </div>
                        
                        <div class="ac-cand-votes">
                            <?php echo $candidate['vote_count'] ?? 0; ?> votes
                        </div>
                        
                        <div class="ac-cand-actions">
                            <button type="button" class="ac-btn-edit" data-bs-toggle="modal" data-bs-target="#editCandidateModal<?php echo $candidate['id']; ?>">
                                Edit
                            </button>
                            <button type="button" class="ac-btn-del" data-bs-toggle="modal" data-bs-target="#deleteCandidateModal<?php echo $candidate['id']; ?>">
                                Delete
                            </button>
                        </div>
                        
                        <a href="candidate_voters.php?candidate_id=<?php echo $candidate['id']; ?>&election_id=<?php echo $election_id; ?>" 
                           class="ac-btn-voters" title="View all voters">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            View Voters (<?php echo $candidate['vote_count'] ?? 0; ?>)
                        </a>
                        
                      </div>
                  <?php endforeach; ?>
              </div>
            </div>
            <div class="ac-divider"></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- ACTION MODALS -->
<?php foreach($candidates_by_position as $position_name => $position_candidates): ?>
    <?php foreach($position_candidates as $candidate): ?>
        <!-- Edit Candidate Modal -->
        <div class="modal fade" id="editCandidateModal<?php echo $candidate['id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <span style="margin-right:8px;">✏️</span> Edit Candidate
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="modal-body bg-light">
                            <input type="hidden" name="candidate_id" value="<?php echo $candidate['id']; ?>">
                            
                            <div class="text-center mb-4">
                                <?php if(!empty($candidate['photo']) && file_exists('../' . $candidate['photo'])): ?>
                                    <img src="../<?php echo $candidate['photo']; ?>" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid #fff; box-shadow:0 4px 12px rgba(0,0,0,0.1);">
                                <?php else: ?>
                                    <div style="width: 80px; height: 80px; border-radius: 50%; background-color: #fff; box-shadow:0 4px 12px rgba(0,0,0,0.1); margin: 0 auto; display: flex; align-items: center; justify-content: center;">
                                        <i class="bi bi-person-circle" style="font-size: 40px; color: #adb5bd;"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="ac-field">
                                <label class="ac-label">Position</label>
                                <select name="position_id" class="ac-select" required>
                                    <?php foreach($positions as $pos): 
                                        $is_full = ($pos['current_candidates'] >= $pos['max_candidates']);
                                        $is_current = ($pos['id'] == $candidate['position_id']);
                                        $cap_text = "(" . $pos['current_candidates'] . "/" . $pos['max_candidates'] . ")";
                                    ?>
                                        <option value="<?php echo $pos['id']; ?>" 
                                                <?php echo ($is_full && !$is_current) ? 'disabled style="color:#cbd5e1;"' : ''; ?>
                                                <?php echo $is_current ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($pos['position_name']); ?>
                                            <?php echo $cap_text; ?>
                                            <?php echo ($is_full && !$is_current) ? ' — FULL' : ''; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <?php if(!empty($candidate['student_photo'])): ?>
                                <div class="mb-3 p-3 bg-white border rounded">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="use_student_photo" id="editUseStudentPhoto<?php echo $candidate['id']; ?>" value="1" <?php echo $candidate['uses_student_photo'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-bold" for="editUseStudentPhoto<?php echo $candidate['id']; ?>">
                                            Use student's current profile photo
                                        </label>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="ac-field">
                                <label class="ac-label">Platform/Manifesto</label>
                                <textarea name="platform" class="ac-textarea"><?php echo htmlspecialchars($candidate['platform'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="ac-field">
                                <label class="ac-label">Update Photo (Optional)</label>
                                <input type="file" name="candidate_image" class="form-control" style="font-size:13px;" accept="image/*">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius:12px; font-weight:600;">Cancel</button>
                            <button type="submit" name="edit_candidate" class="ac-btn-add" style="margin-top:0; width:auto; padding:10px 24px;">Update Candidate</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Delete Candidate Modal -->
        <div class="modal fade" id="deleteCandidateModal<?php echo $candidate['id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header" style="background: rgba(239, 68, 68, 0.05);">
                        <h5 class="modal-title" style="color: #dc2626;">
                            <span style="margin-right:8px;">⚠️</span> Confirm Delete
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center">
                        <div style="width: 64px; height: 64px; background: rgba(239, 68, 68, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                            <i class="bi bi-person-x text-danger" style="font-size: 32px;"></i>
                        </div>
                        <h5 class="mt-3 fw-bold">Delete <?php echo htmlspecialchars($candidate['full_name']); ?>?</h5>
                        <p class="text-muted small">
                            Position: <?php echo htmlspecialchars($candidate['position_name']); ?><br>
                            Student ID: <?php echo htmlspecialchars($candidate['student_id']); ?>
                        </p>
                        
                        <?php if(($candidate['vote_count'] ?? 0) > 0): ?>
                            <div class="alert alert-warning mt-3">
                                <i class="bi bi-exclamation-triangle"></i>
                                This candidate has <strong><?php echo $candidate['vote_count']; ?> votes</strong>.<br>
                                Deleting them will remove all their votes!
                            </div>
                        <?php endif; ?>
                        
                        <p class="text-danger fw-bold mt-2 mb-0">This action cannot be undone!</p>
                    </div>
                    <div class="modal-footer" style="background: rgba(248, 250, 252, 0.5);">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius:12px; font-weight:600;">Cancel</button>
                        <a href="?delete_candidate=<?php echo $candidate['id']; ?>&election_id=<?php echo $election_id; ?>" 
                           class="btn btn-danger" style="border-radius:12px; font-weight:700; padding:10px 24px;">
                            <i class="bi bi-trash-fill"></i> Yes, Delete
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endforeach; ?>

<script>
// Filter Student dropdown based on selected Position
document.querySelector('select[name="position_id"]')?.addEventListener('change', function() {
    const selectedOpt = this.options[this.selectedIndex];
    const posName = selectedOpt.dataset.posName ? selectedOpt.dataset.posName.toLowerCase() : '';
    const studentSelect = document.getElementById('stu-select');
    
    if(!studentSelect) return;

    if (!this.value) {
        // Show all if no position selected
        Array.from(studentSelect.options).forEach(opt => {
            opt.hidden = false;
            opt.disabled = false;
            opt.style.display = '';
        });
        studentSelect.value = '';
        return;
    }

    // Filter students
    Array.from(studentSelect.options).forEach(opt => {
        if (!opt.value) return; // Keep the placeholder visible
        const studentPos = (opt.dataset.position || '').toLowerCase();
        
        if (studentPos === posName) {
            opt.hidden = false;
            opt.disabled = false;
            opt.style.display = '';
        } else {
            opt.hidden = true;
            opt.disabled = true;
            opt.style.display = 'none';
        }
    });
    
    // Reset student selection when position changes
    studentSelect.value = '';
});

async function seedTestingData() {
    const btn = document.getElementById("btnSeed");
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = "<i class='bi bi-hourglass-split me-2'></i> Seeding...";
    
    try {
        const response = await fetch("seed_test_data.php");
        const data = await response.json();
        if(data.status === "success") {
            alert(data.message);
            window.location.href = "add_candidates.php"; // Redirect to refresh and pick up new election
        } else {
            alert("Error: " + data.message);
        }
    } catch(e) {
        console.error(e);
        alert("Failed to connect to seeder. Check console for details.");
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

// Logic for selecting student and automatically checking if we have a photo
document.getElementById('stu-select')?.addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    if(!selected.value) return;
    
    const hasPhoto = selected.dataset.hasPhoto == '1';
    const photoUrl = selected.dataset.photo;
    const platform = selected.dataset.platform;
    const position = selected.dataset.position;
    
    document.getElementById('hdn-use-photo').value = hasPhoto ? '1' : '0';
    
    const preview = document.getElementById('photo-preview');
    if (hasPhoto && photoUrl) {
        preview.innerHTML = '<img src="../' + photoUrl + '" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">';
    } else {
        preview.innerHTML = '<svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';
    }

    if (platform) {
        const pInput = document.querySelector('textarea[name="platform"]');
        if(pInput) pInput.value = platform;
    }

    if (position) {
        const pSelect = document.querySelector('select[name="position_id"]');
        if(pSelect) {
            for(let i=0; i<pSelect.options.length; i++) {
                if(pSelect.options[i].text.toLowerCase().includes(position.toLowerCase())) {
                    pSelect.selectedIndex = i;
                    break;
                }
            }
        }
    }
});

// Photo upload preview logic
function previewPhoto(input) {
    const preview = document.getElementById('photo-preview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `<img src="${e.target.result}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">`;
        }
        reader.readAsDataURL(input.files[0]);
        // Since user uploaded custom, disable auto student photo
        document.getElementById('hdn-use-photo').value = '0';
    }
}
</script>

<?php include '../includes/footer.php'; ?>
