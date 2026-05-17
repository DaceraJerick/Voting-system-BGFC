<?php
/**
 * Election Scheduler
 * 
 * This script automatically:
 * 1. Activates elections when their start time is reached
 * 2. Ends elections when their end time is reached
 * 
 * Run this script via cron job every minute:
 * * * * * php /path/to/cron/election_scheduler.php
 * 
 * Or include it in your application header to run on every page load.
 */

// Load database configuration
require_once __DIR__ . '/../config/database.php';

// Suppress headers already sent warning when run from CLI
if (php_sapi_name() === 'cli') {
    ob_start();
}

// Log function
function log_message($message, $type = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$type] $message\n";
    
    // Log to file
    $logFile = __DIR__ . '/election_scheduler.log';
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    // Also output to console if running from CLI
    if (php_sapi_name() === 'cli') {
        echo $logMessage;
    }
}

try {
    log_message("Election Scheduler started");
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // ==========================================
    // PART 1: Auto-activate elections that have reached their start time
    // ==========================================
    
    // Find elections that:
    // - Are not already active (is_active = 0)
    // - Have status 'pending'
    // - Have start_date in the past (start time has passed)
    // - Have at least one position and one candidate per position
    
    $pendingElections = $pdo->query("
        SELECT e.id, e.title, e.start_date, e.end_date
        FROM elections e
        WHERE e.is_active = 0 
        AND e.status = 'pending'
        AND e.start_date <= NOW()
        AND e.end_date > NOW()
    ")->fetchAll();
    
    $activatedCount = 0;
    
    foreach ($pendingElections as $election) {
        // Check if election has positions
        $posCheck = $pdo->prepare("SELECT COUNT(*) FROM positions WHERE election_id = ?");
        $posCheck->execute([$election['id']]);
        $positionCount = $posCheck->fetchColumn();
        
        if ($positionCount == 0) {
            log_message("Election ID {$election['id']} ({$election['title']}) cannot auto-activate: no positions defined", 'WARNING');
            continue;
        }
        
        // Check if election has candidates for each position
        $canCheck = $pdo->prepare("
            SELECT COUNT(DISTINCT p.id) 
            FROM positions p
            LEFT JOIN candidates c ON c.position_id = p.id
            WHERE p.election_id = ? AND c.id IS NOT NULL
        ");
        $canCheck->execute([$election['id']]);
        $filledPositions = $canCheck->fetchColumn();
        
        if ($filledPositions == 0) {
            log_message("Election ID {$election['id']} ({$election['title']}) cannot auto-activate: no candidates assigned to positions", 'WARNING');
            continue;
        }
        
        // Deactivate all other active elections
        $pdo->query("UPDATE elections SET is_active = 0, status = 'completed' WHERE is_active = 1");
        
        // Activate this election
        $stmt = $pdo->prepare("UPDATE elections SET is_active = 1, status = 'active' WHERE id = ?");
        $stmt->execute([$election['id']]);
        
        $activatedCount++;
        log_message("Election ID {$election['id']} ({$election['title']}) automatically activated at " . date('Y-m-d H:i:s'));
    }
    
    // ==========================================
    // PART 2: Auto-end elections that have passed their end time
    // ==========================================
    
    // Find elections that:
    // - Are currently active (is_active = 1)
    // - Have status 'active'
    // - Have end_date in the past (end time has passed)
    
    $activeElections = $pdo->query("
        SELECT e.id, e.title, e.start_date, e.end_date
        FROM elections e
        WHERE e.is_active = 1 
        AND e.status = 'active'
        AND e.end_date <= NOW()
    ")->fetchAll();
    
    $endedCount = 0;
    
    foreach ($activeElections as $election) {
        // Deactivate the election
        $stmt = $pdo->prepare("UPDATE elections SET is_active = 0, status = 'completed' WHERE id = ?");
        $stmt->execute([$election['id']]);
        
        $endedCount++;
        log_message("Election ID {$election['id']} ({$election['title']}) automatically ended at " . date('Y-m-d H:i:s') . " - Winners are now available!");
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Summary
    if ($activatedCount > 0 || $endedCount > 0) {
        log_message("Election Scheduler completed: $activatedCount elections activated, $endedCount elections ended");
    } else {
        log_message("Election Scheduler completed: No elections needed status changes");
    }
    
} catch (Exception $e) {
    // Rollback on error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    log_message("Election Scheduler error: " . $e->getMessage(), 'ERROR');
}

// If running from CLI, clean output buffer
if (php_sapi_name() === 'cli') {
    ob_end_clean();
}
?>
