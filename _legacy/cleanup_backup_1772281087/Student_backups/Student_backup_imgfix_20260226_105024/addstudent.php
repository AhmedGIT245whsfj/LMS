<?php
if (!isset($_SESSION)) {
  session_start();
}

include_once('../dbConnection.php');
header('Content-type: application/json');

function json_out($v) { echo json_encode($v); exit; }
function is_valid_email($email) { return filter_var($email, FILTER_VALIDATE_EMAIL) !== false; }

/**
 * Email exists check
 * returns: 0 or 1
 */
if (isset($_POST['stuemail']) && isset($_POST['checkemail'])) {
  $stuemail = trim((string)$_POST['stuemail']);
  if ($stuemail === '' || !is_valid_email($stuemail)) {
    json_out(0);
  }

  $stmt = $conn->prepare("SELECT 1 FROM student WHERE stu_email = ? LIMIT 1");
  if (!$stmt) json_out(0);
  $stmt->bind_param("s", $stuemail);
  $stmt->execute();
  $res = $stmt->get_result();
  $exists = ($res && $res->num_rows > 0) ? 1 : 0;
  $stmt->close();
  json_out($exists);
}

/**
 * Signup
 * returns: "OK" or "Failed"
 */
if (isset($_POST['stusignup'], $_POST['stuname'], $_POST['stuemail'], $_POST['stupass'])) {
  $stuname  = trim((string)$_POST['stuname']);
  $stuemail = trim((string)$_POST['stuemail']);
  $stupass  = (string)$_POST['stupass'];

  $track = isset($_POST['preferred_track']) ? trim((string)$_POST['preferred_track']) : 'Programming';
  $level = isset($_POST['experience_level']) ? trim((string)$_POST['experience_level']) : 'Beginner';

  $allowedLevels = ['Beginner','Intermediate','Advanced'];
  if (!in_array($level, $allowedLevels, true)) {
    $level = 'Beginner';
  }

  if ($stuname === '' || $stuemail === '' || $stupass === '' || !is_valid_email($stuemail)) {
    json_out("Failed");
  }

  $stmt = $conn->prepare("SELECT 1 FROM student WHERE stu_email = ? LIMIT 1");
  if (!$stmt) json_out("Failed");
  $stmt->bind_param("s", $stuemail);
  $stmt->execute();
  $res = $stmt->get_result();
  $exists = ($res && $res->num_rows > 0);
  $stmt->close();
  if ($exists) json_out("Failed");

  $default_occ = "Student";
  $default_img = "../image/stu/student1.jpg";

  $hash = password_hash($stupass, PASSWORD_BCRYPT);

  $stmt = $conn->prepare("INSERT INTO student (stu_name, stu_email, stu_pass, stu_occ, stu_img, preferred_track, experience_level)
                          VALUES (?, ?, ?, ?, ?, ?, ?)");
  if (!$stmt) json_out("Failed");
  $stmt->bind_param("sssssss", $stuname, $stuemail, $hash, $default_occ, $default_img, $track, $level);
  $ok = $stmt->execute();
  $stmt->close();

  json_out($ok ? "OK" : "Failed");
}

/**
 * Login
 * returns: 0 or 1
 * supports legacy plaintext and upgrades to bcrypt
 */
if (!isset($_SESSION['is_login'])) {
  if (isset($_POST['checkLogemail'], $_POST['stuLogEmail'], $_POST['stuLogPass'])) {
    $stuLogEmail = trim((string)$_POST['stuLogEmail']);
    $stuLogPass  = (string)$_POST['stuLogPass'];

    if ($stuLogEmail === '' || $stuLogPass === '' || !is_valid_email($stuLogEmail)) {
      json_out(0);
    }

    $stmt = $conn->prepare("SELECT stu_pass FROM student WHERE stu_email = ? LIMIT 1");
    if (!$stmt) json_out(0);
    $stmt->bind_param("s", $stuLogEmail);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = ($res && $res->num_rows === 1) ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$row) json_out(0);

    $stored = (string)$row['stu_pass'];
    $ok = false;

    if (password_verify($stuLogPass, $stored)) {
      $ok = true;
    } else if (hash_equals($stored, $stuLogPass)) {
      $ok = true;
      $newHash = password_hash($stuLogPass, PASSWORD_BCRYPT);
      $up = $conn->prepare("UPDATE student SET stu_pass = ? WHERE stu_email = ? LIMIT 1");
      if ($up) {
        $up->bind_param("ss", $newHash, $stuLogEmail);
        $up->execute();
        $up->close();
      }
    }

    if ($ok) {
      $_SESSION['is_login'] = true;
      $_SESSION['stuLogEmail'] = $stuLogEmail;
      json_out(1);
    }
    json_out(0);
  }
}

json_out("Failed");
