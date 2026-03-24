<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('0');
}

require_once __DIR__ . '/../dbConnection.php';

function p(string $k): string {
    return isset($_POST[$k]) ? trim((string)$_POST[$k]) : '';
}

function normalize_level(string $v): string {
    $v = strtolower(trim($v));

    if (in_array($v, ['beginner', 'basic', 'entry', 'starter'], true)) {
        return 'beginner';
    }

    if (in_array($v, ['intermediate', 'mid', 'medium'], true)) {
        return 'intermediate';
    }

    if (in_array($v, ['experienced', 'advanced', 'expert', 'senior'], true)) {
        return 'experienced';
    }

    return 'beginner';
}

function resolve_track_id(mysqli $conn, string $raw): int {
    $raw = trim($raw);
    if ($raw === '') return 0;

    if (ctype_digit($raw)) {
        $id = (int)$raw;
        $st = $conn->prepare("SELECT track_id FROM track WHERE track_id = ? LIMIT 1");
        if ($st) {
            $st->bind_param("i", $id);
            $st->execute();
            $res = $st->get_result();
            if ($res && $res->num_rows > 0) return $id;
        }
    }

    $st = $conn->prepare("SELECT track_id FROM track WHERE LOWER(TRIM(track_name)) = LOWER(TRIM(?)) LIMIT 1");
    if ($st) {
        $st->bind_param("s", $raw);
        $st->execute();
        $res = $st->get_result();
        if ($res && ($row = $res->fetch_assoc())) {
            return (int)$row['track_id'];
        }
    }

    return 0;
}

/* check email mode */
if (p('checkemail') === 'checkmail') {
    $email = p('stuemail') ?: p('email');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        exit('0');
    }

    $stmt = $conn->prepare("SELECT stu_id FROM student WHERE stu_email = ? LIMIT 1");
    if (!$stmt) {
        http_response_code(500);
        exit('0');
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    echo ($res && $res->num_rows > 0) ? 'Failed' : 'OK';
    exit;
}

/* signup mode */
$name  = p('stuname') ?: p('name');
$email = p('stuemail') ?: p('email');
$pass  = p('stupass') ?: p('password');

$trackRaw = p('preferred_track_id');
if ($trackRaw === '') $trackRaw = p('preferred_track');
if ($trackRaw === '') $trackRaw = p('track_id');
if ($trackRaw === '') $trackRaw = p('track');
if ($trackRaw === '') $trackRaw = p('stutrack');

$levelRaw = p('experience_level');
if ($levelRaw === '') $levelRaw = p('experience');
if ($levelRaw === '') $levelRaw = p('level');
if ($levelRaw === '') $levelRaw = p('stuexp');

$experience = normalize_level($levelRaw);
$trackId = resolve_track_id($conn, $trackRaw);

if ($name === '' || $email === '' || $pass === '') {
    http_response_code(400);
    exit('0');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    exit('0');
}

if (strlen($pass) < 6) {
    http_response_code(400);
    exit('0');
}

if ($trackId <= 0) {
    http_response_code(400);
    exit('0');
}

$chk = $conn->prepare("SELECT stu_id FROM student WHERE stu_email = ? LIMIT 1");
if (!$chk) {
    http_response_code(500);
    exit('0');
}
$chk->bind_param("s", $email);
$chk->execute();
$res = $chk->get_result();

if ($res && $res->num_rows > 0) {
    exit('Failed');
}

$hash = password_hash($pass, PASSWORD_BCRYPT);
if ($hash === false) {
    http_response_code(500);
    exit('0');
}

$stu_occ = $experience;
$stu_img = '';

$stmt = $conn->prepare("
  INSERT INTO student 
  (stu_name, stu_email, stu_pass, preferred_track, experience_level, preferred_track_id)
  VALUES (?, ?, ?, ?, ?, ?)
");

$track     = $_POST["track"] ?? "";
$level     = $_POST["level"] ?? "";
$track_id  = (int)($_POST["track_id"] ?? 0);

$stmt->bind_param(
  "sssssi",
  $name,
  $email,
  $password,
  $track,
  $level,
  $track_id
);

if (!$stmt->execute()) {
    http_response_code(500);
    exit('0');
}

echo 'OK';
exit;
