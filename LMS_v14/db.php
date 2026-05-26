<?php
$host     = "localhost";
$username = "root";
$password = "";
$database = "library_management_system";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    // Don't expose raw error details to the browser in production
    error_log("DB connection failed: " . $conn->connect_error);
    die(json_encode(['error' => 'Database connection failed. Please try again later.']));
}

// ── SQL Injection protection ──────────────────────────────────────────────────
// 1. Force UTF-8 charset so multi-byte encoding tricks cannot escape quotes
$conn->set_charset("utf8mb4");

// 2. Enable strict error reporting so any failed query throws instead of silently failing
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// 3. All queries elsewhere in the project already use prepared statements with
//    bind_param — this file ensures the connection layer is also hardened.
//    Never interpolate raw user input into query strings; always use ? placeholders.
?>