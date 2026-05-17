<?php
// filename: admin/export_winners.php
// location: admin/export_winners.php

require_once '../config/database.php';
require_once '../includes/auth_check.php';

if($_SESSION['user_type'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

if(!isset($_GET['election_id'])) {
    header("Location: winners.php");
    exit();
}

$election_id = $_GET['election_id'];

// Get election details
$stmt = $pdo->prepare("SELECT * FROM elections WHERE id = ?");
$stmt->execute([$election_id]);
$election = $stmt->fetch();

if(!$election) {
    header("Location: winners.php");
    exit();
}

// Get total unique voters in this election
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT student_id) FROM votes WHERE election_id = ?");
$stmt->execute([$election_id]);
$total_voters = $stmt->fetchColumn();

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

// Group by position and find winners
$position_data = [];
foreach($results as $row) {
    if(!isset($position_data[$row['position_id']])) {
        $position_data[$row['position_id']] = [
            'name' => $row['position_name'],
            'total_votes' => 0,
            'max_votes' => -1,
            'candidates' => []
        ];
    }
    $position_data[$row['position_id']]['candidates'][] = $row;
    $position_data[$row['position_id']]['total_votes'] += $row['vote_count'];
    if($row['vote_count'] > $position_data[$row['position_id']]['max_votes']) {
        $position_data[$row['position_id']]['max_votes'] = $row['vote_count'];
    }
}

// Build final winners list
$winners = [];
foreach($position_data as $pos) {
    foreach($pos['candidates'] as $cand) {
        // A winner is anyone with the max votes (handles ties)
        if($cand['vote_count'] == $pos['max_votes'] && $pos['max_votes'] > 0) {
            $winners[] = [
                'position_name' => $pos['name'],
                'winner_name' => $cand['candidate_name'],
                'student_id' => $cand['student_id'],
                'vote_count' => $cand['vote_count'],
                'position_votes' => $pos['total_votes'],
                'total_voters' => $total_voters
            ];
        }
    }
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $election['title'] . '_winners.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add headers
fputcsv($output, [
    'Position',
    'Winner Name',
    'Student ID',
    'Votes Received',
    'Position Total Votes',
    'Vote Percentage',
    'Total Voters',
    'Election'
]);

// Add data rows
foreach($winners as $winner) {
    $percentage = $winner['position_votes'] > 0 
        ? round(($winner['vote_count'] / $winner['position_votes']) * 100, 1) 
        : 0;
    
    fputcsv($output, [
        $winner['position_name'],
        $winner['winner_name'],
        $winner['student_id'],
        $winner['vote_count'],
        $winner['position_votes'],
        $percentage . '%',
        $winner['total_voters'],
        $election['title']
    ]);
}

// Add summary
fputcsv($output, []);
fputcsv($output, ['Generated on:', date('F d, Y h:i A')]);
fputcsv($output, ['Total Winners:', count($winners)]);

fclose($output);
exit();
?>