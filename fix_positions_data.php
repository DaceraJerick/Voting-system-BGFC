<?php
require 'config/database.php';

echo "=== FIXING POSITION DISPLAY ORDER FOR ELECTION 7 ===\n\n";

try {
    // Fix President to display_order 1
    $stmt = $pdo->prepare("UPDATE positions SET display_order = 1 WHERE id = 38 AND election_id = 7");
    $stmt->execute();
    echo "✓ President (id: 38) → display_order = 1\n";

    // Fix Vice President to display_order 2
    $stmt = $pdo->prepare("UPDATE positions SET display_order = 2 WHERE id = 39 AND election_id = 7");
    $stmt->execute();
    echo "✓ Vice President (id: 39) → display_order = 2\n";

    echo "\n=== VERIFICATION ===\n\n";
    
    // Verify the fix
    $stmt = $pdo->query("SELECT id, position_name, display_order FROM positions WHERE election_id = 7 ORDER BY display_order");
    $positions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Positions in Election 7 (after fix):\n";
    foreach ($positions as $pos) {
        echo "  Order #{$pos['display_order']}: {$pos['position_name']}\n";
    }

    echo "\n✅ Position ordering has been FIXED!\n";
    echo "Refresh your admin dashboard to see the changes.\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
