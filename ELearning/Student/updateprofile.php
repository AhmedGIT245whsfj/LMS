<?php
session_start();
require_once __DIR__ . '/../dbConnection.php';

/* ITV_PROFILE_IMAGE_FIX */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function itv_pick_student_identifier(): array {
    $sid = $_POST['stu_id'] ?? $_POST['sid'] ?? $_POST['student_id'] ?? null;
    $email = $_POST['stuemail'] ?? $_POST['stu_email'] ?? $_POST['email'] ?? ($_SESSION['stuLogEmail'] ?? null);
    return [$sid, $email];
}

function itv_detect_student_image_column(mysqli $conn): ?string {
    $result = $conn->query("SHOW COLUMNS FROM student");
    if (!$result) return null;

    $preferred = ['stu_img', 'stu_image', 'stuimage', 'student_img', 'image', 'img'];
    $cols = [];
    while ($row = $result->fetch_assoc()) {
        $cols[] = $row['Field'];
    }
    foreach ($preferred as $p) {
        if (in_array($p, $cols, true)) return $p;
    }
    return null;
}

function itv_handle_profile_upload(mysqli $conn): void {
    if (!isset($_FILES['stuImg'])) {
        return;
    }

    if (!is_array($_FILES['stuImg']) || (int)($_FILES['stuImg']['error'] ?? 4) === 4) {
        return;
    }

    $file = $_FILES['stuImg'];

    if ((int)$file['error'] !== 0) {
        return;
    }

    $tmp = $file['tmp_name'] ?? '';
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return;
    }

    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','webp'];
    if (!in_array($ext, $allowed, true)) {
        $_SESSION['profile_msg'] = "Only JPG, JPEG, PNG, WEBP are allowed.";
        return;
    }

    if ((int)($file['size'] ?? 0) > 4 * 1024 * 1024) {
        $_SESSION['profile_msg'] = "Image too large. Max 4MB.";
        return;
    }

    [$sid, $email] = itv_pick_student_identifier();
    $column = itv_detect_student_image_column($conn);
    if ($column === null) {
        $_SESSION['profile_msg'] = "Image column not found in student table.";
        return;
    }

    $baseDir = dirname(__DIR__) . '/image/stu';
    if (!is_dir($baseDir)) {
        @mkdir($baseDir, 0775, true);
    }

    $safeId = $sid ? preg_replace('/[^0-9a-zA-Z_-]/', '', (string)$sid) : 'student';
    $filename = 'stu_' . $safeId . '_' . time() . '.' . $ext;
    $destFs = $baseDir . '/' . $filename;

    if (!@move_uploaded_file($tmp, $destFs)) {
        $_SESSION['profile_msg'] = "Failed to save uploaded image.";
        return;
    }

    if ($sid) {
        $stmt = $conn->prepare("UPDATE student SET `$column`=? WHERE stu_id=?");
        if ($stmt) {
            $sid_int = (int)$sid;
            $stmt->bind_param("si", $filename, $sid_int);
            $stmt->execute();
            $stmt->close();
            $_SESSION['profile_msg'] = "Profile updated successfully.";
            return;
        }
    }

    if ($email) {
        $stmt = $conn->prepare("UPDATE student SET `$column`=? WHERE stu_email=?");
        if ($stmt) {
            $stmt->bind_param("ss", $filename, $email);
            $stmt->execute();
            $stmt->close();
            $_SESSION['profile_msg'] = "Profile updated successfully.";
            return;
        }
    }
}


itv_handle_profile_upload($conn);


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
