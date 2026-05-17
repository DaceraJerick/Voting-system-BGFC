# QUICK START GUIDE - 5000+ Users System Features

## 🚀 Quick Integration Examples

### 1. **Basic Page Setup with Logging**

```php
<?php
require_once 'config/database.php';
require_once 'config/cache.php';
require_once 'config/logger.php';

// Log page access
$logger->logAccess(__FILE__, $_SESSION['student_id'] ?? 'GUEST');

try {
    // Your code here
} catch (Exception $e) {
    $logger->logError($e->getMessage(), 'EXCEPTION', __FILE__, __LINE__);
    die('An error occurred');
}
?>
```

---

### 2. **Loading & Confirmation in HTML**

```html
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Your content -->
    
    <button onclick="submitForm()">Submit</button>
    
    <script src="assets/js/script.js"></script>
    <script>
        function submitForm() {
            loader.show(); // Show loading
            
            confirmDialog.show({
                title: 'Confirm Action',
                message: 'Are you sure?',
                confirmText: 'Proceed',
                cancelText: 'Cancel',
                onConfirm: () => {
                    loader.show(1000); // Show for 1 second then submit
                    // Submit form
                }
            });
        }
    </script>
</body>
</html>
```

---

### 3. **Using Toast Notifications**

```javascript
// Success
toast.success('Operation completed!');

// Error
toast.error('Something went wrong!');

// Warning
toast.warning('Please be careful');

// Custom
toast.show('Custom message', 'info', 5000);
```

---

### 4. **Database Query with Caching**

```php
<?php
// Cache query for 10 minutes
$students = $query_cache->query(
    $pdo,
    "SELECT * FROM students WHERE is_verified = 1 LIMIT 100",
    [],
    600  // TTL in seconds
);

// Or use the remember pattern
$elections = $cache->remember('all_elections', function() use ($pdo) {
    return $pdo->query("SELECT * FROM elections")->fetchAll();
}, 300); // 5 minute cache
?>
```

---

### 5. **Voting Activity Logging**

```php
<?php
// Log when user votes
$logger->logVoting('vote_cast', $student_id, $election_id, [
    'candidate_id' => $candidate_id,
    'position_id' => $position_id,
    'votes_count' => 15
]);

// Log eligibility check
$logger->logVoting('eligibility_check', $student_id, $election_id, [
    'eligible' => true,
    'already_voted' => false
]);
?>
```

---

### 6. **Admin Action Logging**

```php
<?php
// Log admin creating election
$logger->logAdmin('election_created', $admin_id, [
    'election_name' => 'President 2024',
    'start_date' => '2024-03-15',
    'end_date' => '2024-03-20',
    'positions_count' => 5
]);

// Log admin uploading IDs
$logger->logAdmin('ids_uploaded', $admin_id, [
    'file_name' => 'student_ids.csv',
    'count' => 5000
]);
?>
```

---

### 7. **Delete with Modern Confirmation**

```html
<form method="POST" action="delete.php">
    <input type="hidden" name="id" value="123">
    <button type="button" class="confirm-delete" data-item="User #123">
        Delete
    </button>
</form>

<script src="assets/js/script.js"></script>
<!-- Automatically shows modern dialog instead of browser confirm -->
```

---

### 8. **Performance Monitoring**

```php
<?php
$start = microtime(true);

// Perform operation
$result = performLongOperation();

$execution_time = (microtime(true) - $start) * 1000; // milliseconds

$logger->logPerformance('long_operation', $execution_time, [
    'items_processed' => count($result),
    'status' => 'success'
]);
?>
```

---

### 9. **Check System Health**

```php
<?php
// Check log statistics
$log_stats = $logger->getLogStats();
echo "Error log size: " . $log_stats['error']['size'] . " bytes";
echo "Total entries: " . $log_stats['error']['lines'];

// Check cache statistics
$cache_stats = $cache->getStats();
echo "Cache size: " . $cache_stats['total_size_mb'] . " MB";
echo "Cached items: " . $cache_stats['total_entries'];

// Clear old logs (keep last 30 days)
$logger->clearOldLogs(30);

// Clear expired cache
$cache->clearExpired();
?>
```

---

### 10. **Error Handling Templates**

```php
<?php
// Graceful error handling
try {
    // Database operation
    $stmt = $pdo->prepare("INSERT INTO votes ...");
    $stmt->execute($params);
    
    $logger->logDatabase($sql, $params, true);
    toast.success('Vote recorded successfully!');
    
} catch (PDOException $e) {
    $logger->logDatabase($sql, $params, false, $e->getMessage());
    toast.error('Failed to record vote. Please try again.');
}
?>
```

---

## 📊 CSS Classes Reference

### Loading Overlay
```css
.modern-loading-overlay      /* Main overlay */
.modern-loader               /* Loader container */
.loader-circle               /* Animated circles */
.loader-dot                  /* Center pulse dot */
.loading-text                /* "LOADING" text */
```

### Confirmation Dialog
```css
.modal-backdrop-modern       /* Backdrop */
.modern-dialog               /* Dialog box */
.modern-dialog-icon          /* Icon container */
.modern-dialog-title         /* Title text */
.modern-dialog-message       /* Message text */
.modern-dialog-buttons       /* Button container */
.modern-btn                  /* Button base */
.modern-btn-primary          /* Primary button */
.modern-btn-secondary        /* Secondary button */
.modern-btn-danger           /* Danger button */
```

### Toast Notifications
```css
.toast-container             /* Container */
.toast-notification          /* Individual toast */
.toast-notification.success  /* Success toast */
.toast-notification.error    /* Error toast */
.toast-notification.warning  /* Warning toast */
.toast-icon                  /* Icon */
.toast-message               /* Message text */
```

---

## 📁 File Structure

```
student_council_voting/
├── config/
│   ├── database.php          ← Database with 5000+ optimizations
│   ├── logger.php            ← Error logging system
│   └── cache.php             ← Caching system
├── logs/                      ← Auto-created, all logs stored here
├── cache/                     ← Auto-created, cache files stored here
├── assets/
│   ├── css/
│   │   └── style.css         ← Modern UI styles
│   └── js/
│       └── script.js         ← Modern UI JavaScript
├── student/
│   ├── vote.php              ← Original voting page
│   └── vote_enhanced.php     ← Example with new systems
├── student_council_voting_indexes.sql  ← Database indexes
└── OPTIMIZATION_GUIDE.md     ← Full documentation
```

---

## 🔧 Setup Checklist

- [ ] Create `logs/` directory (chmod 755)
- [ ] Create `cache/` directory (chmod 755)
- [ ] Include `config/logger.php` in all PHP files
- [ ] Include `config/cache.php` where needed
- [ ] Run `student_council_voting_indexes.sql` in database
- [ ] Include `assets/css/style.css` in header
- [ ] Include `assets/js/script.js` before closing body tag
- [ ] Test loading animation: `loader.show()`
- [ ] Test confirmation: `confirmDialog.show({...})`
- [ ] Test toast: `toast.success('Test')`

---

## 🎯 Performance Tips

1. **Cache frequently accessed data** (elections, positions)
2. **Use query caching** for read-heavy operations
3. **Log critical operations** (votes, admin actions)
4. **Clear old logs** weekly or monthly
5. **Monitor system health** via log statistics
6. **Use database indexes** (run SQL file)
7. **Avoid N+1 queries** with proper joining
8. **Use persistent connections** (already configured)

---

## 🐛 Debugging

### View Recent Errors
```php
$errors = $logger->getLogs('error', 50);
foreach ($errors as $error) {
    echo $error;
}
```

### View Database Logs
```php
$db_logs = $logger->getLogs('database', 50);
```

### View Voting Activity
```php
$voting_logs = $logger->getLogs('voting', 100);
```

### Monitor Performance
```php
$perf_logs = $logger->getLogs('performance', 50);
```

---

## 🚨 Common Issues & Solutions

| Issue | Solution |
|-------|----------|
| Loading doesn't show | Include `assets/js/script.js` and `assets/css/style.css` |
| Confirmation doesn't work | Make sure JavaScript is enabled |
| Logs not being created | Check `logs/` directory permissions |
| Cache not working | Check `cache/` directory exists and writable |
| Slow queries | Run database indexes SQL file |
| High memory usage | Clear cache: `$cache->flush()` |

---

## 📞 Support

For issues, check:
1. `logs/error.log` - PHP errors
2. `logs/database.log` - Query errors
3. `logs/performance.log` - Slow operations
4. Browser console - JavaScript errors

---

**System optimized for 5000+ concurrent users with modern UI/UX** ✨
