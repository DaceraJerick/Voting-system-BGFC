<?php
// filename: config/database.php

// Set PHP timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

$host = 'sql113.infinityfree.com';
$dbname = 'if0_41945775_student_council_voting';
$username = 'if0_41945775';
$password = 'Jerick10282003';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Set MySQL session timezone to Asia/Manila (UTC+8)
    $pdo->exec("SET time_zone = '+08:00'");
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

/**
 * Get current server time synchronized with database timezone (Asia/Manila)
 * @return string MySQL datetime string
 */
function getServerNow()
{
    return date('Y-m-d H:i:s');
}

/**
 * Calculate remaining seconds until a given datetime
 * @param string $end_datetime MySQL datetime string
 * @return int Remaining seconds (0 if already past)
 */
function getRemainingSecs($end_datetime)
{
    $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $end = new DateTime($end_datetime, new DateTimeZone('Asia/Manila'));
    $diff = $end->getTimestamp() - $now->getTimestamp();
    return max(0, $diff);
}

/**
 * Format remaining seconds as human-readable string (e.g., "1d 2h 30m 45s")
 * @param int $seconds Remaining seconds
 * @return string Formatted string
 */
function formatRemainingTime($seconds)
{
    if ($seconds <= 0)
        return '0s';
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    $parts = [];
    if ($days > 0)
        $parts[] = $days . 'd';
    if ($hours > 0)
        $parts[] = $hours . 'h';
    if ($minutes > 0)
        $parts[] = $minutes . 'm';
    $parts[] = $secs . 's';
    return implode(' ', $parts);
}

session_start();
?>