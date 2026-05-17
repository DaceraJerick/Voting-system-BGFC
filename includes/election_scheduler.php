<?php
// filename: includes/election_scheduler.php
// purpose: Automatically start and end elections based on start_date/end_date

if(!isset($GLOBALS['pdo'])) {
    // No DB available in this context
    return;
}

$pdo = $GLOBALS['pdo'];

try {
    // 1) End elections whose end_date has passed
    $endStmt = $pdo->prepare("SELECT id, title FROM elections WHERE end_date <= NOW() AND (is_active = 1 OR status != 'ended')");
    $endStmt->execute();
    $toEnd = $endStmt->fetchAll();

    foreach($toEnd as $e) {
        try {
            $pdo->beginTransaction();
            $upd = $pdo->prepare("UPDATE elections SET is_active = 0, status = 'ended' WHERE id = ?");
            $upd->execute([$e['id']]);
            $pdo->commit();
            error_log("[Scheduler] Election ID {$e['id']} ({$e['title']}) ended automatically at " . date('Y-m-d H:i:s'));
        } catch(Exception $ex) {
            $pdo->rollBack();
            error_log("[Scheduler] Failed to end election {$e['id']}: " . $ex->getMessage());
        }
    }

    // 2) Start elections whose start_date has arrived and are within their end_date
    // Order by start_date ascending so earliest scheduled starts first
    $startStmt = $pdo->query("SELECT id, title FROM elections WHERE start_date <= NOW() AND end_date > NOW() AND (is_active = 0 OR status != 'active') ORDER BY start_date ASC");
    $toStart = $startStmt->fetchAll();

    foreach($toStart as $e) {
        // Verify readiness: has positions, candidates and at least one filled position
        $posCheck = $pdo->prepare("SELECT COUNT(*) FROM positions WHERE election_id = ?");
        $posCheck->execute([$e['id']]);
        if($posCheck->fetchColumn() == 0) continue;

        $canCheck = $pdo->prepare("SELECT COUNT(*) FROM candidates c JOIN positions p ON c.position_id = p.id WHERE p.election_id = ?");
        $canCheck->execute([$e['id']]);
        if($canCheck->fetchColumn() == 0) continue;

        $filledCheck = $pdo->prepare("SELECT COUNT(DISTINCT p.id) FROM positions p LEFT JOIN candidates c ON c.position_id = p.id WHERE p.election_id = ? AND c.id IS NOT NULL");
        $filledCheck->execute([$e['id']]);
        if($filledCheck->fetchColumn() == 0) continue;

        // All checks passed, activate this election and deactivate others
        try {
            $pdo->beginTransaction();
            // Deactivate any currently active election
            $pdo->prepare("UPDATE elections SET is_active = 0, status = 'completed' WHERE is_active = 1")->execute();
            // Activate this one
            $pdo->prepare("UPDATE elections SET is_active = 1, status = 'active' WHERE id = ?")->execute([$e['id']]);
            $pdo->commit();
            error_log("[Scheduler] Election ID {$e['id']} ({$e['title']}) started automatically at " . date('Y-m-d H:i:s'));
            // Only activate the first qualifying election to avoid flip-flopping
            break;
        } catch(Exception $ex) {
            $pdo->rollBack();
            error_log("[Scheduler] Failed to activate election {$e['id']}: " . $ex->getMessage());
        }
    }

} catch(Exception $e) {
    error_log("[Scheduler] Unexpected error: " . $e->getMessage());
}

?>
