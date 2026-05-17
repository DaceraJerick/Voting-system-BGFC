<?php
// filename: config/cache.php
// CACHING SYSTEM FOR 5000+ USERS

class SystemCache {
    private $cache_dir;
    private $cache_ttl = 3600; // Default 1 hour in seconds
    
    public function __construct() {
        $this->cache_dir = __DIR__ . '/../cache/';
        $this->createCacheDirectory();
    }
    
    /**
     * Create cache directory if it doesn't exist
     */
    private function createCacheDirectory() {
        if (!is_dir($this->cache_dir)) {
            mkdir($this->cache_dir, 0755, true);
        }
    }
    
    /**
     * Generic cache getter
     * @param string $key Cache key
     * @param callable $callback Function to call if cache miss
     * @param int $ttl Time to live in seconds
     * @return mixed
     */
    public function remember($key, callable $callback, $ttl = null) {
        $cached = $this->get($key);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $result = $callback();
        $this->set($key, $result, $ttl ?? $this->cache_ttl);
        
        return $result;
    }
    
    /**
     * Set cache value
     * @param string $key
     * @param mixed $value
     * @param int $ttl Time to live in seconds
     */
    public function set($key, $value, $ttl = null) {
        $ttl = $ttl ?? $this->cache_ttl;
        $filename = $this->getCacheFilename($key);
        
        $data = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
        
        file_put_contents($filename, json_encode($data));
    }
    
    /**
     * Get cache value
     * @param string $key
     * @return mixed|null
     */
    public function get($key) {
        $filename = $this->getCacheFilename($key);
        
        if (!file_exists($filename)) {
            return null;
        }
        
        $data = json_decode(file_get_contents($filename), true);
        
        if (time() > $data['expires']) {
            unlink($filename);
            return null;
        }
        
        return $data['value'];
    }
    
    /**
     * Check if cache key exists and is valid
     * @param string $key
     * @return bool
     */
    public function has($key) {
        return $this->get($key) !== null;
    }
    
    /**
     * Delete cache
     * @param string $key
     */
    public function delete($key) {
        $filename = $this->getCacheFilename($key);
        if (file_exists($filename)) {
            unlink($filename);
        }
    }
    
    /**
     * Clear all cache
     */
    public function flush() {
        $files = glob($this->cache_dir . '*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
    }
    
    /**
     * Clear expired cache entries
     */
    public function clearExpired() {
        $files = glob($this->cache_dir . '*.cache');
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if (time() > $data['expires']) {
                unlink($file);
            }
        }
    }
    
    /**
     * Get cache filename from key
     * @param string $key
     * @return string
     */
    private function getCacheFilename($key) {
        $hash = hash('sha256', $key);
        return $this->cache_dir . $hash . '.cache';
    }
    
    /**
     * Get cache statistics
     * @return array
     */
    public function getStats() {
        $files = glob($this->cache_dir . '*.cache');
        $total_size = 0;
        $total_entries = count($files);
        
        foreach ($files as $file) {
            $total_size += filesize($file);
        }
        
        return [
            'total_entries' => $total_entries,
            'total_size_mb' => round($total_size / 1024 / 1024, 2),
            'cache_dir' => $this->cache_dir
        ];
    }
}

// Database query caching wrapper
class QueryCache {
    private $cache;
    private $logger;
    
    public function __construct(SystemCache $cache, SystemLogger $logger) {
        $this->cache = $cache;
        $this->logger = $logger;
    }
    
    /**
     * Execute query with caching
     * @param PDO $pdo
     * @param string $sql
     * @param array $params
     * @param int $ttl Cache time to live
     * @param bool $cache_enabled
     * @return array|null
     */
    public function query(PDO $pdo, $sql, $params = [], $ttl = 600, $cache_enabled = true) {
        // Create cache key from query and params
        $cache_key = 'query_' . md5($sql . json_encode($params));
        
        if ($cache_enabled) {
            $cached = $this->cache->get($cache_key);
            if ($cached !== null) {
                return $cached;
            }
        }
        
        try {
            $start_time = microtime(true);
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $execution_time = (microtime(true) - $start_time) * 1000;
            
            // Log database query
            $this->logger->logDatabase($sql, $params, true);
            
            // Cache the result
            if ($cache_enabled) {
                $this->cache->set($cache_key, $result, $ttl);
            }
            
            return $result;
        } catch (PDOException $e) {
            $this->logger->logDatabase($sql, $params, false, $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Clear query cache
     * @param string $pattern Optional pattern for selective clearing
     */
    public function clearCache($pattern = null) {
        if ($pattern === null) {
            $this->cache->flush();
        } else {
            // Would need additional implementation for pattern matching
            $this->cache->delete($pattern);
        }
    }
}

// Initialize global cache system
$cache = new SystemCache();
$query_cache = new QueryCache($cache, $logger);
