<?php
// Show all errors in the browser
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Enable logging to file
ini_set('log_errors', 1);

// Log file path
ini_set('error_log', __DIR__ . '/../../Purchase-Order-and-Sales-main/logs/php_error_log.txt');

// Optional: Custom error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $msg = "[ERROR] [$errno] $errstr in $errfile on line $errline";
    error_log($msg);  // Log to file
    echo "<pre style='color:red;'>$msg</pre>"; // Show in browser
});
?>
