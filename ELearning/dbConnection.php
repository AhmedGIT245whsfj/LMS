<?php
declare(strict_types=1);

function require_env(string $key): string {
    $value = getenv($key);
    if ($value === false || trim($value) === '') {
        http_response_code(500);
        die($key . ' is not set');
    }
    return trim($value);
}

$db_host = require_env('DB_HOST');
$db_port = (int) require_env('DB_PORT');
$db_user = require_env('DB_USER');
$db_pass = require_env('DB_PASS');
$db_name = require_env('DB_NAME');

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);

if ($conn->connect_error) {
    http_response_code(500);
    die('DB connection failed');
}

$conn->set_charset('utf8mb4');
