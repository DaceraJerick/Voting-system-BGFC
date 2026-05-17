<?php
// filename: admin/export_results.php
// location: admin/export_results.php

require_once '../config/database.php';
require_once '../includes/auth_check.php';

if($_SESSION['user_type'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

if(!isset($_GET['election_id'])) {
    header("Location: results.php");
    exit();
}

$election_id = $_GET['election_id'];

// Get election details
$stmt = $pdo->prepare("SELECT * FROM elections WHERE id = ?");
$stmt->execute([$election_id]);
$election = $stmt->fetch();

if(!$election) {
    header("Location: results.php");
    exit();
}

// Get total unique voters in this election
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT student_id) FROM votes WHERE election_id = ?");
$stmt->execute([$election_id]);
$total_voters_cast = $stmt->fetchColumn();

// Get total registered students
$registered_count = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();

// Get all candidates and their vote counts
$stmt = $pdo->prepare("
    SELECT 
        p.id as position_id,
        p.position_name,
        p.display_order,
        c.full_name as candidate_name,
        c.student_id,
        COUNT(v.id) as vote_count
    FROM positions p
    LEFT JOIN candidates c ON c.position_id = p.id
    LEFT JOIN votes v ON v.candidate_id = c.id AND v.election_id = p.election_id
    WHERE p.election_id = ?
    GROUP BY p.id, c.id
    ORDER BY p.display_order, vote_count DESC
");
$stmt->execute([$election_id]);
$results = $stmt->fetchAll();

// Group by position to calculate totals and percentages
$position_data = [];
foreach($results as $row) {
    if(!isset($position_data[$row['position_id']])) {
        $position_data[$row['position_id']] = [
            'name' => $row['position_name'],
            'total_votes' => 0,
            'candidates' => []
        ];
    }
    $position_data[$row['position_id']]['candidates'][] = $row;
    $position_data[$row['position_id']]['total_votes'] += $row['vote_count'];
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . str_replace(' ', '_', $election['title']) . '_Results.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add Election Info
fputcsv($output, ['Election Title:', $election['title']]);
fputcsv($output, ['Period:', date('M d, Y h:i A', strtotime($election['start_date'])) . ' to ' . date('M d, Y h:i A', strtotime($election['end_date']))]);
fputcsv($output, ['Total Registered Voters:', $registered_count]);
fputcsv($output, ['Total Votes Cast:', $total_voters_cast]);
fputcsv($output, ['Turnout Percentage:', ($registered_count > 0 ? round(($total_voters_cast / $registered_count) * 100, 2) : 0) . '%']);
fputcsv($output, []); // Empty row

// Add headers
fputcsv($output, [
    'Position',
    'Candidate Name',
    'Student ID',
    'Votes Received',
    'Vote Percentage (of Position)',
    'Winner Status'
]);

// Add data rows
foreach($position_data as $pos_id => $pos) {
    $max_votes = 0;
    foreach($pos['candidates'] as $c) {
        if($c['vote_count'] > $max_votes) $max_votes = $c['vote_count'];
    }
    
    foreach($pos['candidates'] as $candidate) {
        if(empty($candidate['candidate_name'])) continue; // Skip empty positions

        $pct = $pos['total_votes'] > 0 
            ? round(($candidate['vote_count'] / $pos['total_votes']) * 100, 2) . '%' 
            : '0%';
        
        $status = '';
        if($candidate['vote_count'] == $max_votes && $max_votes > 0) {
            $is_tie = 0;
            foreach($pos['candidates'] as $c2) {
                if($c2['vote_count'] == $max_votes) $is_tie++;
            }
            $status = ($is_tie > 1) ? 'Tie' : 'Winner';
        }

        fputcsv($output, [
            $pos['name'],
            $candidate['candidate_name'],
            $candidate['student_id'],
            $candidate['vote_count'],
            $pct,
            $status
        ]);
    }
    fputcsv($output, []); // Spacer between positions
}

// Add footer
fputcsv($output, ['Report Generated On:', date('F d, Y h:i A')]);

fclose($output);
exit();
?>
