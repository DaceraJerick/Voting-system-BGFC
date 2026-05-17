<?php
// filename: admin/download_template.php
// location: admin/download_template.php

require_once '../config/database.php';
require_once '../includes/auth_check.php';

if($_SESSION['user_type'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="eligible_ids_template.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add instructions as comments
fputcsv($output, ['# STUDENT_ID_TEMPLATE']);
fputcsv($output, ['# Put one Student ID per row, no headers']);
fputcsv($output, ['# Example format:']);
fputcsv($output, ['2021-001']);
fputcsv($output, ['2021-002']);
fputcsv($output, ['2021-003']);
fputcsv($output, ['2022-001']);
fputcsv($output, ['2022-002']);
fputcsv($output, ['2023-001']);

fclose($output);
exit();
?>