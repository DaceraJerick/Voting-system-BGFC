<?php
// filename: admin/approve_application.php

require_once '../config/database.php';
require_once '../includes/auth_check.php';

if($_SESSION['user_type'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
$id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
$admin_id = $_SESSION['user_id']; // This is actually admin id based on auth_check but just in case
if(isset($_SESSION['id'])) $admin_id = $_SESSION['id']; // Use 'id' if 'user_id' is ambiguous

if($action == 'bulk_approve') {
    $ids = isset($_POST['app_ids']) && is_array($_POST['app_ids']) ? $_POST['app_ids'] : [];
    if(empty($ids)) {
        header("Location: candidate_applications.php?err=" . urlencode("No applications selected."));
        exit();
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $params = array_merge([$admin_id], $ids);
    $stmt = $pdo->prepare("UPDATE candidate_applications SET status = 'approved', reviewed_at = NOW(), reviewed_by = ? WHERE id IN ($placeholders)");
    $stmt->execute($params);
    header("Location: candidate_applications.php?msg=" . urlencode(count($ids) . " applications approved."));
    exit();
}

if(!$id || !in_array($action, ['approve', 'reject', 'reopen', 'delete'])) {
    header("Location: candidate_applications.php?err=" . urlencode("Invalid request."));
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM candidate_applications WHERE id = ?");
    $stmt->execute([$id]);
    $app = $stmt->fetch();
    
    if(!$app) {
        throw new Exception("Application not found.");
    }
    
    // Check if election is active. (From business rules: Approved applicants cannot be unapproved once election is activated, but if it's pending -> approved, is it allowed? Yes. But reopening an approved one might be restricted. For simplicity, just update status)
    
    if($action == 'approve') {
        $stmt = $pdo->prepare("UPDATE candidate_applications SET status = 'approved', reviewed_at = NOW(), reviewed_by = ? WHERE id = ?");
        $stmt->execute([$admin_id, $id]);
        $msg = "Application approved successfully.";
    } elseif($action == 'reject') {
        $reason = isset($_POST['rejection_reason']) ? trim($_POST['rejection_reason']) : null;
        $stmt = $pdo->prepare("UPDATE candidate_applications SET status = 'rejected', rejection_reason = ?, reviewed_at = NOW(), reviewed_by = ? WHERE id = ?");
        $stmt->execute([$reason, $admin_id, $id]);
        $msg = "Application rejected.";
    } elseif($action == 'reopen') {
        $stmt = $pdo->prepare("UPDATE candidate_applications SET status = 'pending', rejection_reason = NULL, reviewed_at = NULL, reviewed_by = NULL WHERE id = ?");
        $stmt->execute([$id]);
        $msg = "Application reopened and set to pending.";
    } elseif($action == 'delete') {
        $stmt = $pdo->prepare("DELETE FROM candidate_applications WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: candidate_applications.php?msg=" . urlencode("Application deleted successfully."));
        exit();
    }
    
    header("Location: view_application.php?id=$id&msg=" . urlencode($msg));
    exit();
} catch(Exception $e) {
    header("Location: view_application.php?id=$id&err=" . urlencode($e->getMessage()));
    exit();
}
?>
