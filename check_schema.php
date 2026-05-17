<?php
require 'config/database.php';

echo "=== CHECKING POSITIONS TABLE SCHEMA ===\n\n";

// Get table structure
$stmt = $pdo->query("DESCRIBE positions");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Columns in 'positions' table:\n";
foreach ($columns as $col) {
    echo "  - {$col['Field']} ({$col['Type']})\n";
}

echo "\n=== CHECKING CANDIDATES TABLE SCHEMA ===\n\n";

// Get table structure
$stmt = $pdo->query("DESCRIBE candidates");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Columns in 'candidates' table:\n";
foreach ($columns as $col) {
    echo "  - {$col['Field']} ({$col['Type']})\n";
}

echo "\n=== ALL DATA IN POSITIONS ===\n\n";
$stmt = $pdo->query("SELECT * FROM positions");
$positions = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($positions);

echo "\n=== ALL DATA IN CANDIDATES ===\n\n";
$stmt = $pdo->query("SELECT * FROM candidates");
$candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($candidates);
?>
