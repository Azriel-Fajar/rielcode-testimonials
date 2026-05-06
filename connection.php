<?php
/**
 * connection.php — testimonials subdomain
 * Reuses main rielcode.com config (shared DB).
 *
 * Production config path : /home/rier5192/config.php
 * Local fallback         : ../Rielcode/config.php (same XAMPP root)
 */

$_rcConfigPath = '/home/rier5192/config.php';
if (file_exists($_rcConfigPath)) {
    $_rcCfg = require $_rcConfigPath;
} else {
    $_rcCfg = require __DIR__ . '/../Rielcode/config.php';
}

$conn = mysqli_connect(
    $_rcCfg['DB_HOST'] ?? 'localhost',
    $_rcCfg['DB_USER'] ?? 'root',
    $_rcCfg['DB_PASS'] ?? '',
    $_rcCfg['DB_NAME'] ?? 'rielcode'
);

if (mysqli_connect_errno()) {
    die("Connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, 'utf8mb4');

unset($_rcConfigPath, $_rcCfg);
