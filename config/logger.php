<?php
// filename: config/logger.php
// Comprehensive error logging system for 5000+ users

class SystemLogger {
    private $log_dir;
    private $log_files = [
        'error' => 'error.log',
        'access' => 'access.log',
        'database' => 'database.log',
        'voting' => 'voting.log',
        'admin' => 'admin.log',
        'performance' => 'performance.log'
    ];
    
    public function __construct() {
        $this->log_dir = __DIR__ . '/../logs/';
        $this->createLogDirectory();
    }
    
    /**
     * Create logs directory if it doesn't exist
     */
    private function createLogDirectory() {
        if (!is_dir($this->log_dir)) {
            mkdir($this->log_dir, 0755, true);
        }
    }
    
    /**
     * Log system errors
     * @param string $error_message
     * @param string $error_type
     * @param string $file
     * @param int $line
     */
    public function logError($error_message, $error_type = 'ERROR', $file = '', $line = 0) {
        $timestamp = $this->getTimestamp();
        $user_id = isset($_SESSION['student_id']) ? $_SESSION['student_id'] : 'GUEST';
        $user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'UNKNOWN';
        
        $log_entry = "[{$timestamp}] | TYPE: {$error_type} | USER_ID: {$user_id} | USER_TYPE: {$user_type} | " .
                     "IP: {$_SERVER['REMOTE_ADDR']} | FILE: {$file}:{$line} | MESSAGE: {$error_message}\n";
        
        file_put_contents($this->log_dir . $this->log_files['error'], $log_entry, FILE_APPEND);
    }
    
    /**
     * Log database operations
     * @param string $query
     * @param array $params
     * @param bool $success
     * @param string $error_msg
     */
    public function logDatabase($query, $params = [], $success = true, $error_msg = '') {
        $timestamp = $this->getTimestamp();
        $user_id = isset($_SESSION['student_id']) ? $_SESSION['student_id'] : 'GUEST';
        $param_str = !empty($params) ? json_encode($params) : 'NO_PARAMS';
        $status = $success ? 'SUCCESS' : 'FAILED';
        
        $log_entry = "[{$timestamp}] | STATUS: {$status} | USER_ID: {$user_id} | " .
                     "QUERY: {$query} | PARAMS: {$param_str}";
        
        if (!$success) {
            $log_entry .= " | ERROR: {$error_msg}";
        }
        
        $log_entry .= "\n";
        
        file_put_contents($this->log_dir . $this->log_files['database'], $log_entry, FILE_APPEND);
    }
    
    /**
     * Log voting activities
     * @param string $action
     * @param int $student_id
     * @param int $election_id
     * @param array $details
     */
    public function logVoting($action, $student_id, $election_id, $details = []) {
        $timestamp = $this->getTimestamp();
        $details_str = !empty($details) ? json_encode($details) : 'NO_DETAILS';
        
        $log_entry = "[{$timestamp}] | ACTION: {$action} | STUDENT_ID: {$student_id} | " .
                     "ELECTION_ID: {$election_id} | IP: {$_SERVER['REMOTE_ADDR']} | " .
                     "DETAILS: {$details_str}\n";
        
        file_put_contents($this->log_dir . $this->log_files['voting'], $log_entry, FILE_APPEND);
    }
    
    /**
     * Log admin activities
     * @param string $action
     * @param int $admin_id
     * @param array $details
     */
    public function logAdmin($action, $admin_id, $details = []) {
        $timestamp = $this->getTimestamp();
        $details_str = !empty($details) ? json_encode($details) : 'NO_DETAILS';
        
        $log_entry = "[{$timestamp}] | ACTION: {$action} | ADMIN_ID: {$admin_id} | " .
                     "IP: {$_SERVER['REMOTE_ADDR']} | DETAILS: {$details_str}\n";
        
        file_put_contents($this->log_dir . $this->log_files['admin'], $log_entry, FILE_APPEND);
    }
    
    /**
     * Log page access
     * @param string $page
     * @param int $user_id
     * @param string $user_type
     */
    public function logAccess($page, $user_id = 'GUEST', $user_type = 'UNKNOWN') {
        $timestamp = $this->getTimestamp();
        $method = $_SERVER['REQUEST_METHOD'];
        
        $log_entry = "[{$timestamp}] | PAGE: {$page} | METHOD: {$method} | USER_ID: {$user_id} | " .
                     "USER_TYPE: {$user_type} | IP: {$_SERVER['REMOTE_ADDR']}\n";
        
        file_put_contents($this->log_dir . $this->log_files['access'], $log_entry, FILE_APPEND);
    }
    
    /**
     * Log performance metrics
     * @param string $operation
     * @param float $execution_time (in milliseconds)
     * @param array $details
     */
    public function logPerformance($operation, $execution_time, $details = []) {
        $timestamp = $this->getTimestamp();
        $memory_usage = memory_get_usage(true) / 1024 / 1024;
        $details_str = !empty($details) ? json_encode($details) : 'NO_DETAILS';
        
        $log_entry = "[{$timestamp}] | OPERATION: {$operation} | TIME: {$execution_time}ms | " .
                     "MEMORY: {$memory_usage}MB | DETAILS: {$details_str}\n";
        
        file_put_contents($this->log_dir . $this->log_files['performance'], $log_entry, FILE_APPEND);
    }
    
    /**
     * Get formatted timestamp
     * @return string
     */
    private function getTimestamp() {
        date_default_timezone_set('Asia/Manila');
        return date('Y-m-d H:i:s');
    }
    
    /**
     * Get logs by type
     * @param string $type
     * @param int $limit
     * @return array
     */
    public function getLogs($type = 'error', $limit = 100) {
        if (!isset($this->log_files[$type])) {
            return [];
        }
        
        $log_file = $this->log_dir . $this->log_files[$type];
        if (!file_exists($log_file)) {
            return [];
        }
        
        $lines = file($log_file, FILE_SKIP_EMPTY_LINES);
        return array_slice(array_reverse($lines), 0, $limit);
    }
    
    /**
     * Clear old logs (older than specified days)
     * @param int $days
     */
    public function clearOldLogs($days = 30) {
        $cutoff_time = time() - ($days * 24 * 60 * 60);
        
        foreach ($this->log_files as $type => $filename) {
            $log_file = $this->log_dir . $filename;
            if (!file_exists($log_file)) continue;
            
            if (filemtime($log_file) < $cutoff_time) {
                unlink($log_file);
            }
        }
    }
    
    /**
     * Get log statistics
     * @return array
     */
    public function getLogStats() {
        $stats = [];
        
        foreach ($this->log_files as $type => $filename) {
            $log_file = $this->log_dir . $filename;
            $stats[$type] = [
                'exists' => file_exists($log_file),
                'size' => file_exists($log_file) ? filesize($log_file) : 0,
                'lines' => file_exists($log_file) ? count(file($log_file)) : 0
            ];
        }
        
        return $stats;
    }
}

// Initialize global logger
$logger = new SystemLogger();

// Set custom error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    global $logger;
    $error_type = match($errno) {
        E_ERROR => 'FATAL_ERROR',
        E_WARNING => 'WARNING',
        E_PARSE => 'PARSE_ERROR',
        E_NOTICE => 'NOTICE',
        E_CORE_ERROR => 'CORE_ERROR',
        E_CORE_WARNING => 'CORE_WARNING',
        E_COMPILE_ERROR => 'COMPILE_ERROR',
        E_COMPILE_WARNING => 'COMPILE_WARNING',
        E_USER_ERROR => 'USER_ERROR',
        E_USER_WARNING => 'USER_WARNING',
        E_USER_NOTICE => 'USER_NOTICE',
        default => 'UNKNOWN_ERROR'
    };
    
    $logger->logError($errstr, $error_type, $errfile, $errline);
    
    // Return false to continue with PHP's standard error handling
    return false;
});

// Set exception handler
set_exception_handler(function($exception) {
    global $logger;
    $logger->logError(
        $exception->getMessage(),
        'EXCEPTION',
        $exception->getFile(),
        $exception->getLine()
    );
    
    // Display user-friendly error message
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'An unexpected error occurred. Please try again later.'
    ]);
    exit;
});
