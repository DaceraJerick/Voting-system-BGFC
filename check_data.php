<?php
require 'config/database.php';

// Get all positions with candidate counts
$stmt = $pdo->query("
    SELECT 
        p.position_id, 
        p.position_name, 
        p.display_order, 
        COUNT(c.candidate_id) as candidate_count
    FROM positions p 
    LEFT JOIN candidates c ON p.position_id = c.position_id 
    GROUP BY p.position_id 
    ORDER BY p.display_order
");

$positions = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== POSITIONS & CANDIDATES ===\n\n";
echo "Total Positions: " . count($positions) . "\n";
$total_candidates = 0;
foreach ($positions as $pos) {
    echo "\nPosition #{$pos['display_order']}: {$pos['position_name']}\n";
    echo "  Location: {$pos['candidate_count']} candidates\n";
    $total_candidates += $pos['candidate_count'];
}

echo "\n\nTotal Candidates: $total_candidates\n";
echo "\nExpected: ";
echo count($positions) . " positions, ";
echo $total_candidates . " candidates\n";
?>
