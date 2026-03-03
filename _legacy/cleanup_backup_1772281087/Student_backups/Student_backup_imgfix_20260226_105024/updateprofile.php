<?php
session_start();
require_once __DIR__ . '/../dbConnection.php';

if (empty($_SESSION['is_login']) || empty($_SESSION['stu_id']) || empty($_SESSION['stuLogEmail'])) {
  header("Location: ../loginorsignup.php");
  exit;
}

if (!isset($_POST['update_profile'])) {
  header("Location: myprofile.php?err=Invalid%20request");
  exit;
}

$stuId = (int)$_SESSION['stu_id'];
$currentEmail = (string)$_SESSION['stuLogEmail'];

$name = isset($_POST['stu_name']) ? trim((string)$_POST['stu_name']) : '';
$email = isset($_POST['stu_email']) ? trim((string)$_POST['stu_email']) : '';
$newPass = isset($_POST['stu_pass']) ? (string)$_POST['stu_pass'] : '';

if ($name === '' || $email === '') {
  header("Location: myprofile.php?err=Name%20and%20email%20are%20required");
  exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  header("Location: myprofile.php?err=Invalid%20email");
  exit;
}

$chk = $conn->prepare("SELECT stu_id FROM student WHERE stu_email = ? AND stu_id <> ? LIMIT 1");
$chk->bind_param("si", $email, $stuId);
$chk->execute();
$chk->store_result();
if ($chk->num_rows > 0) {
  $chk->close();
  header("Location: myprofile.php?err=Email%20already%20in%20use");
  exit;
}
$chk->close();

$imgUpdate = null;

if (!empty($_FILES['stu_img']) && isset($_FILES['stu_img']['tmp_name']) && is_uploaded_file($_FILES['stu_img']['tmp_name'])) {
  $maxBytes = 2 * 1024 * 1024;
  if ((int)$_FILES['stu_img']['size'] > $maxBytes) {
    header("Location: myprofile.php?err=Image%20too%20large%20(max%202MB)");
    exit;
  }

  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime = $finfo->file($_FILES['stu_img']['tmp_name']);
  $allowed = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
  ];

  if (!isset($allowed[$mime])) {
    header("Location: myprofile.php?err=Invalid%20image%20type");
    exit;
  }

  $ext = $allowed[$mime];
  $dir = __DIR__ . '/../image/stu';
  if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
  }

  $filename = 'stu_' . $stuId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
  $targetFs = $dir . '/' . $filename;

  if (!move_uploaded_file($_FILES['stu_img']['tmp_name'], $targetFs)) {
    header("Location: myprofile.php?err=Failed%20to%20save%20image");
    exit;
  }

  $imgUpdate = '../image/stu/' . $filename;
}

$passHash = null;
if ($newPass !== '') {
  if (strlen($newPass) < 6) {
    header("Location: myprofile.php?err=Password%20too%20short");
    exit;
  }
  $passHash = password_hash($newPass, PASSWORD_BCRYPT);
}

$conn->begin_transaction();
try {
  if ($passHash !== null && $imgUpdate !== null) {
    $st = $conn->prepare("UPDATE student SET stu_name=?, stu_email=?, stu_pass=?, stu_img=? WHERE stu_id=? LIMIT 1");
    $st->bind_param("ssssi", $name, $email, $passHash, $imgUpdate, $stuId);
  } elseif ($passHash !== null) {
    $st = $conn->prepare("UPDATE student SET stu_name=?, stu_email=?, stu_pass=? WHERE stu_id=? LIMIT 1");
    $st->bind_param("sssi", $name, $email, $passHash, $stuId);
  } elseif ($imgUpdate !== null) {
    $st = $conn->prepare("UPDATE student SET stu_name=?, stu_email=?, stu_img=? WHERE stu_id=? LIMIT 1");
    $st->bind_param("sssi", $name, $email, $imgUpdate, $stuId);
  } else {
    $st = $conn->prepare("UPDATE student SET stu_name=?, stu_email=? WHERE stu_id=? LIMIT 1");
    $st->bind_param("ssi", $name, $email, $stuId);
  }

  $st->execute();
  $st->close();

  $conn->commit();
} catch (Throwable $e) {
  $conn->rollback();
  header("Location: myprofile.php?err=Update%20failed");
  exit;
}

$_SESSION['stuLogEmail'] = $email;

header("Location: myprofile.php?ok=1");
exit;
