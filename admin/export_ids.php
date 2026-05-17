<?php
// filename: admin/export_ids.php
// location: admin/export_ids.php

require_once '../config/database.php';
require_once '../includes/auth_check.php';

if($_SESSION['user_type'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="eligible_ids_' . date('Y-m-d') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add headers
fputcsv($output, ['Student ID', 'Status', 'Uploaded Date']);

// Fetch data
$ids = $pdo->query("SELECT student_id, is_registered, uploaded_at FROM eligible_ids ORDER BY uploaded_at DESC");

// Add data rows
foreach($ids as $row) {
    fputcsv($output, [
        $row['student_id'],
        $row['is_registered'] ? 'Registered' : 'Unregistered',
        date('Y-m-d H:i:s', strtotime($row['uploaded_at']))
    ]);
}

fclose($output);
exit();
?>