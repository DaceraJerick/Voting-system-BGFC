<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=student_council_voting', 'root', '');
    $pdo->exec('ALTER TABLE positions ADD COLUMN max_candidates INT NOT NULL DEFAULT 5;');
    echo "Column 'max_candidates' added successfully.";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column 'max_candidates' already exists.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>
