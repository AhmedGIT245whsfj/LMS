<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo "0";
  exit;
}

require_once __DIR__ . '/../dbConnection.php';

function p(string $k): string {
  return isset($_POST[$k]) ? trim((string)$_POST[$k]) : '';
}


// checkemail mode (used by AJAX blur)
if (p('checkemail') === 'checkmail') {
  $e = p('stuemail') ?: p('email');
  if ($e === '' || !filter_var($e, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo "0";
    exit;
  }

  try {
    $chk = $conn->prepare("SELECT stu_id FROM student WHERE stu_email = ? LIMIT 1");
    $chk->bind_param("s", $e);
    $chk->execute();
    $res = $chk->get_result();

    if ($res && $res->num_rows > 0) {
      echo "Failed"; // email exists
    } else {
      echo "OK";     // email available
    }
  } catch (Throwable $e) {
    http_response_code(500);
    echo "0";
  }
  exit;
}

$name  = p('stuname') ?: p('name');
$email = p('stuemail') ?: p('email');
$pass  = p('stupass') ?: p('password');

$track = p('preferred_track_id');
if ($track === '') $track = p('track_id');
if ($track === '') $track = p('stutrack');
if ($track === '') $track = 'NULL';

$exp = strtolower(p('experience_level'));
if ($exp === '') $exp = strtolower(p('experience'));
if ($exp === '') $exp = strtolower(p('stuexp'));
if ($exp === '') $exp = 'beginner';

if (!in_array($exp, ['beginner','experienced'], true)) {
  $exp = 'beginner';
}

if ($name === '' || $email === '' || $pass === '') {
  http_response_code(400);
  echo "0";
  exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo "0";
  exit;
}

$hash = password_hash($pass, PASSWORD_BCRYPT);
if ($hash === false) {
  http_response_code(500);
  echo "0";
  exit;
}

$occ = p('stuocc');
$img = p('stuimg');

$trackSql = 'NULL';
$trackId = null;
if ($track !== 'NULL') {
  if (ctype_digit($track)) {
    $trackId = (int)$track;
    $trackSql = '?';
  }
}

try {
  // Check existing email
  $chk = $conn->prepare("SELECT stu_id FROM student WHERE stu_email = ? LIMIT 1");
  $chk->bind_param("s", $email);
  $chk->execute();
  $res = $chk->get_result();
  if ($res && $res->num_rows > 0) {
    http_response_code(409);
    echo "0";
    exit;
  }

  if ($trackSql === '?') {
    $sql = "INSERT INTO student (stu_name, stu_email, stu_pass, stu_occ, stu_img, preferred_track_id, experience_level)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $st = $conn->prepare($sql);
    $st->bind_param("sssssis", $name, $email, $hash, $occ, $img, $trackId, $exp);
  } else {
    $sql = "INSERT INTO student (stu_name, stu_email, stu_pass, stu_occ, stu_img, preferred_track_id, experience_level)
            VALUES (?, ?, ?, ?, ?, NULL, ?)";
    $st = $conn->prepare($sql);
    $st->bind_param("ssssss", $name, $email, $hash, $occ, $img, $exp);
  }

  $ok = $st->execute();
  echo $ok ? "1" : "0";
} catch (Throwable $e) {
  http_response_code(500);
  echo "0";
}
