<?php
$pdo = new PDO('mysql:host=localhost;dbname=student_council_voting', 'root', '');
$stmt = $pdo->query('SHOW COLUMNS FROM positions');
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) echo $r['Field'] . "\n";
?>
