<?php
declare(strict_types=1);

$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('DB_USER') ?: 'itverse';
$db_pass = getenv('DB_PASS') ?: '';
$db_name = getenv('DB_NAME') ?: 'lms_db';

if ($db_pass === '') {
    http_response_code(500);
    die('DB_PASS is not set');
}

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    http_response_code(500);
    die('DB connection failed');
}
