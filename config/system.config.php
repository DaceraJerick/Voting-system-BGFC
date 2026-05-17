<?php
// filename: config/system.config.php
// SYSTEM CONFIGURATION FOR 5000+ USERS

/**
 * SYSTEM CONFIGURATION MANAGEMENT
 * Centralized configuration for the entire application
 */

class SystemConfig {
    
    // =========================================
    // PERFORMANCE SETTINGS
    // =========================================
    
    // Cache TTL (Time To Live) in seconds
    public static $CACHE_TTL = [
        'elections' => 600,        // 10 minutes - Elections don't change often
        'candidates' => 300,       // 5 minutes
        'positions' => 300,        // 5 minutes
        'results' => 120,          // 2 minutes - Results update more frequently
        'students' => 1800,        // 30 minutes - Static data
        'query_default' => 600     // 10 minutes - General queries
    ];
    
    // Database connection settings
    public static $DATABASE = [
        'host' => 'localhost',
        'name' => 'student_council_voting',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
        'max_connections_per_hour' => 5000,
        'connection_timeout' => 5,
        'persistent_connections' => true
    ];
    
    // =========================================
    // LOGGING SETTINGS
    // =========================================
    
    public static $LOGGING = [
        'error_logging_enabled' => true,
        'database_logging_enabled' => true,
        'voting_logging_enabled' => true,
        'admin_logging_enabled' => true,
        'access_logging_enabled' => true,
        'performance_logging_enabled' => true,
        'log_retention_days' => 30,
        'log_level' => 'INFO'  // ERROR, WARNING, INFO, DEBUG
    ];
    
    // =========================================
    // SECURITY SETTINGS
    // =========================================
    
    public static $SECURITY = [
        'session_timeout' => 3600,           // 1 hour in seconds
        'disable_ssl_verification' => false,
        'require_https' => false,            // Set to true in production
        'max_login_attempts' => 5,
        'lockout_duration' => 900,           // 15 minutes
        'enable_rate_limiting' => true,
        'rate_limit_requests' => 100,        // per minute
        'rate_limit_window' => 60             // seconds
    ];
    
    // =========================================
    // UI/UX SETTINGS
    // =========================================
    
    public static $UI = [
        'loading_duration_ms' => 1000,       // 1 second
        'toast_duration_ms' => 3000,         // 3 seconds
        'dialog_animation_duration' => 400,  // milliseconds
        'theme' => 'modern',                 // modern, classic
        'enable_animations' => true,
        'dark_mode' => false
    ];
    
    // =========================================
    // VOTING SYSTEM SETTINGS
    // =========================================
    
    public static $VOTING = [
        'enable_blank_votes' => false,
        'vote_confirmation_required' => true,
        'enable_vote_preview' => true,
        'prevent_duplicate_votes' => true,
        'prevent_same_person_multiple_positions' => true,
        'log_all_votes' => true,
        'enable_vote_statistics' => true
    ];
    
    // =========================================
    // EMAIL SETTINGS (Optional)
    // =========================================
    
    public static $EMAIL = [
        'enabled' => false,
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => 587,
        'smtp_user' => 'your-email@gmail.com',
        'smtp_pass' => 'your-app-password',
        'from_address' => 'noreply@votingsystem.com',
        'from_name' => 'Student Council Voting'
    ];
    
    // =========================================
    // NOTIFICATION SETTINGS
    // =========================================
    
    public static $NOTIFICATIONS = [
        'send_vote_confirmation' => true,
        'send_admin_notifications' => true,
        'send_results_email' => true,
        'enable_toast_notifications' => true,
        'enable_push_notifications' => false
    ];
    
    // =========================================
    // PERFORMANCE TUNING
    // =========================================
    
    public static $PERFORMANCE = [
        'enable_query_caching' => true,
        'enable_page_caching' => false,
        'enable_gzip_compression' => true,
        'enable_cloudflare' => false,
        'cdn_enabled' => false,
        'lazy_load_images' => true,
        'optimize_database_queries' => true,
        'max_query_execution_time' => 30  // seconds
    ];
    
    // =========================================
    // SYSTEM RESOURCES
    // =========================================
    
    public static $RESOURCES = [
        'memory_limit_mb' => 256,
        'max_upload_size_mb' => 50,
        'max_execution_time_seconds' => 60,
        'max_input_vars' => 10000,
        'max_post_size_mb' => 50
    ];
    
    // =========================================
    // MAINTENANCE SETTINGS
    // =========================================
    
    public static $MAINTENANCE = [
        'maintenance_mode' => false,
        'maintenance_message' => 'System is under maintenance. Please try again later.',
        'auto_cache_cleanup' => true,
        'cache_cleanup_interval' => 3600,  // Every hour
        'auto_log_cleanup' => true,
        'log_cleanup_interval' => 86400    // Daily
    ];
    
    // =========================================
    // FEATURE FLAGS
    // =========================================
    
    public static $FEATURES = [
        'enable_voting' => true,
        'enable_results' => true,
        'enable_admin_panel' => true,
        'enable_student_dashboard' => true,
        'enable_email_verification' => false,
        'enable_two_factor_auth' => false,
        'enable_api' => false,
        'enable_export_features' => true,
        'enable_vote_audit' => true
    ];
    
    // =========================================
    // HELPER METHODS
    // =========================================
    
    /**
     * Get cache TTL for specific data type
     */
    public static function getCacheTTL($datatype) {
        return self::$CACHE_TTL[$datatype] ?? self::$CACHE_TTL['query_default'];
    }
    
    /**
     * Check if feature is enabled
     */
    public static function isFeatureEnabled($feature) {
        return self::$FEATURES[$feature] ?? false;
    }
    
    /**
     * Get all configuration as array
     */
    public static function getAll() {
        return [
            'cache' => self::$CACHE_TTL,
            'database' => self::$DATABASE,
            'logging' => self::$LOGGING,
            'security' => self::$SECURITY,
            'ui' => self::$UI,
            'voting' => self::$VOTING,
            'email' => self::$EMAIL,
            'notifications' => self::$NOTIFICATIONS,
            'performance' => self::$PERFORMANCE,
            'resources' => self::$RESOURCES,
            'maintenance' => self::$MAINTENANCE,
            'features' => self::$FEATURES
        ];
    }
    
    /**
     * Get specific configuration section
     */
    public static function get($section) {
        $all = self::getAll();
        return $all[$section] ?? [];
    }
    
    /**
     * Validate system resources
     */
    public static function validateResources() {
        $warnings = [];
        
        // Check memory limit
        $memory_limit = ini_get('memory_limit');
        $memory_mb = (int)$memory_limit;
        if ($memory_mb < self::$RESOURCES['memory_limit_mb']) {
            $warnings[] = "Memory limit is {$memory_limit}, recommended " . self::$RESOURCES['memory_limit_mb'] . "MB+";
        }
        
        // Check upload size
        $upload_max = ini_get('upload_max_filesize');
        $upload_mb = (int)$upload_max;
        if ($upload_mb < self::$RESOURCES['max_upload_size_mb']) {
            $warnings[] = "Upload limit is {$upload_max}, recommended " . self::$RESOURCES['max_upload_size_mb'] . "MB+";
        }
        
        // Check execution time
        $max_exec = ini_get('max_execution_time');
        if ($max_exec > 0 && $max_exec < self::$RESOURCES['max_execution_time_seconds']) {
            $warnings[] = "Max execution time is {$max_exec}s, recommended " . self::$RESOURCES['max_execution_time_seconds'] . "s+";
        }
        
        return $warnings;
    }
    
    /**
     * Get system health status
     */
    public static function getHealthStatus() {
        return [
            'memory_usage_percent' => round(memory_get_usage(true) / (1024 * 1024) / self::$RESOURCES['memory_limit_mb'] * 100, 2),
            'disk_usage_percent' => round(disk_total_space(__DIR__ . '/..') > 0 ? (disk_used_space(__DIR__ . '/..') / disk_total_space(__DIR__ . '/..') * 100) : 0, 2),
            'php_version' => phpversion(),
            'database_connected' => isset($GLOBALS['pdo']) && $GLOBALS['pdo'] ? true : false
        ];
    }
}

?>
