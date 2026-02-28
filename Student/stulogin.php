<?php
session_start();
require_once __DIR__ . '/../dbConnection.php';

header('Content-Type: application/json; charset=UTF-8');

function out($v) {
  echo json_encode($v);
  exit;
}

if (!isset($_POST['checkLogemail'], $_POST['checkLogpass'])) {
  out(0);
}

$email = trim((string)$_POST['checkLogemail']);
$pass  = (string)$_POST['checkLogpass'];

if ($email === '' || $pass === '') {
  out(0);
}

$stmt = $conn->prepare("SELECT stu_id, stu_pass FROM student WHERE stu_email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row) {
  out(0);
}

$stuId = (int)$row['stu_id'];
$dbPass = (string)$row['stu_pass'];

$ok = false;

if (password_verify($pass, $dbPass)) {
  $ok = true;
} else {
  // Legacy plaintext support + upgrade
  if (hash_equals($dbPass, $pass)) {
    $ok = true;
    $newHash = password_hash($pass, PASSWORD_BCRYPT);
    $up = $conn->prepare("UPDATE student SET stu_pass = ? WHERE stu_id = ?");
    $up->bind_param("si", $newHash, $stuId);
    $up->execute();
    $up->close();
  }
}

if (!$ok) {
  out(0);
}

$_SESSION['is_login'] = true;
$_SESSION['stuLogEmail'] = $email;
$_SESSION['stu_id'] = $stuId;

out(1);
