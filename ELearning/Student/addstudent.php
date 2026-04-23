<?php
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_OFF);
header('Cache-Control: no-store');

require_once __DIR__ . '/../dbConnection.php';

function json_out(string $status, string $message = '', array $extra = []): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge([
        'status' => $status,
        'message' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function pick_first_existing(array $candidates, array $available): ?string {
    foreach ($candidates as $c) {
        if (isset($available[$c])) return $c;
    }
    return null;
}

function resolve_track_value(mysqli $conn, string $raw, bool $wantId): mixed {
    $raw = trim($raw);
    if ($raw === '') return null;

    if ($wantId) {
        if (ctype_digit($raw)) return (int)$raw;

        $st = $conn->prepare("SELECT track_id FROM track WHERE LOWER(TRIM(track_name)) = LOWER(TRIM(?)) LIMIT 1");
        if ($st) {
            $st->bind_param("s", $raw);
            $st->execute();
            $res = $st->get_result();
            if ($row = $res->fetch_assoc()) {
                return (int)$row['track_id'];
            }
        }
        return null;
    }

    if (ctype_digit($raw)) {
        $id = (int)$raw;
        $st = $conn->prepare("SELECT track_name FROM track WHERE track_id = ? LIMIT 1");
        if ($st) {
            $st->bind_param("i", $id);
            $st->execute();
            $res = $st->get_result();
            if ($row = $res->fetch_assoc()) {
                return (string)$row['track_name'];
            }
        }
        return null;
    }

    return $raw;
}

/* old email check used by front-end */
if (isset($_POST['checkemail']) && isset($_POST['stuemail'])) {
    $stuemail = trim((string)$_POST['stuemail']);
    if ($stuemail === '') {
        echo "0";
        exit;
    }

    $st = $conn->prepare("SELECT 1 FROM student WHERE stu_email = ? LIMIT 1");
    if (!$st) {
        echo "0";
        exit;
    }
    $st->bind_param("s", $stuemail);
    $st->execute();
    $res = $st->get_result();
    echo ($res && $res->num_rows > 0) ? "1" : "0";
    exit;
}

/* signup */
if (!isset($_POST['stusignup'])) {
    json_out('ERROR', 'Invalid request');
}

$stuname = trim((string)($_POST['stuname'] ?? ''));
$stuemail = trim((string)($_POST['stuemail'] ?? ''));
$stupass = (string)($_POST['stupass'] ?? '');
$trackRaw = trim((string)($_POST['preferred_track'] ?? ''));
$levelRaw = trim((string)($_POST['experience_level'] ?? ''));

if ($stuname === '') json_out('ERROR', 'Name is required');
if ($stuemail === '' || !filter_var($stuemail, FILTER_VALIDATE_EMAIL)) json_out('ERROR', 'Valid email is required');
if (strlen($stupass) < 6) json_out('ERROR', 'Password must be at least 6 characters');

$check = $conn->prepare("SELECT 1 FROM student WHERE stu_email = ? LIMIT 1");
if (!$check) json_out('ERROR', 'Prepare failed on email check');
$check->bind_param("s", $stuemail);
$check->execute();
$exists = $check->get_result();
if ($exists && $exists->num_rows > 0) {
    json_out('Failed', 'Email already registered');
}

/* inspect schema dynamically */
$available = [];
$colsRes = $conn->query("SHOW COLUMNS FROM student");
while ($colsRes && ($col = $colsRes->fetch_assoc())) {
    $available[$col['Field']] = true;
}

$nameCol  = pick_first_existing(['stu_name', 'stuname', 'name'], $available);
$emailCol = pick_first_existing(['stu_email', 'email'], $available);
$passCol  = pick_first_existing(['stu_pass', 'stupass', 'password'], $available);

if (!$nameCol || !$emailCol || !$passCol) {
    json_out('ERROR', 'Student schema mismatch', [
        'debug' => [
            'nameCol' => $nameCol,
            'emailCol' => $emailCol,
            'passCol' => $passCol
        ]
    ]);
}

$data = [
    $nameCol  => $stuname,
    $emailCol => $stuemail,
    $passCol  => password_hash($stupass, PASSWORD_DEFAULT),
];

if (isset($available['preferred_track_id'])) {
    $trackId = resolve_track_value($conn, $trackRaw, true);
    $data['preferred_track_id'] = $trackId;
} elseif (isset($available['track_id'])) {
    $trackId = resolve_track_value($conn, $trackRaw, true);
    $data['track_id'] = $trackId;
} elseif (isset($available['preferred_track'])) {
    $trackName = resolve_track_value($conn, $trackRaw, false);
    $data['preferred_track'] = $trackName;
}

if (isset($available['experience_level'])) {
    $data['experience_level'] = $levelRaw !== '' ? $levelRaw : 'Beginner';
}

$columns = array_keys($data);
$placeholders = implode(',', array_fill(0, count($columns), '?'));
$sql = "INSERT INTO student (`" . implode('`,`', $columns) . "`) VALUES ($placeholders)";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    json_out('ERROR', 'Prepare failed on insert', ['debug' => ['sql' => $sql]]);
}

$types = str_repeat('s', count($columns));
$values = array_values($data);
$stmt->bind_param($types, ...$values);

if (!$stmt->execute()) {
    json_out('ERROR', 'Insert failed', ['debug' => ['db_error' => $stmt->error]]);
}

json_out('OK', 'Registration successful');
