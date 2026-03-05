<?php
// dbConnection.php - Kubernetes/RDS friendly

$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: '';
$db_name = getenv('DB_NAME') ?: 'lms_db';
$db_port = (int)(getenv('DB_PORT') ?: 3306);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    // Fail fast with a clear message (avoid leaking secrets)
    http_response_code(500);
    echo "Database connection failed. Check DB_* environment variables.";
    exit;
}
?>
