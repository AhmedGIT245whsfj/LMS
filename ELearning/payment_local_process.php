<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

require_once __DIR__ . '/dbConnection.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function itv_has_column(mysqli $conn, string $table, string $col): bool {
  $sql = "SELECT 1
          FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND COLUMN_NAME = ?
          LIMIT 1";
  $st = $conn->prepare($sql);
  if (!$st) return false;
  $st->bind_param("ss", $table, $col);
  $st->execute();
  $res = $st->get_result();
  $st->close();
  return (bool)($res && $res->num_rows > 0);
}

function itv_pick_table(mysqli $conn, array $candidates): ?string {
  $sql = "SELECT 1
          FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
          LIMIT 1";
  foreach ($candidates as $t) {
    $st = $conn->prepare($sql);
    if (!$st) continue;
    $st->bind_param("s", $t);
    $st->execute();
    $res = $st->get_result();
    $st->close();
    if ($res && $res->num_rows > 0) return $t;
  }
  return null;
}

function itv_redirect(string $path): void {
  header("Location: {$path}");
  exit;
}

$stuEmail = '';
if (isset($_SESSION['stuLogEmail'])) {
  $stuEmail = (string)$_SESSION['stuLogEmail'];
} elseif (isset($_SESSION['stu_email'])) {
  $stuEmail = (string)$_SESSION['stu_email'];
}
$stuEmail = trim($stuEmail);

if ($stuEmail === '') {
  itv_redirect("/loginorsignup.php");
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  itv_redirect("/courses.php");
}

$courseId = trim((string)($_POST['course_id'] ?? ''));
$paymentMethod = trim((string)($_POST['payment_method'] ?? 'card'));

if ($courseId === '') {
  itv_redirect("/checkout.php?err=missing_course");
}

$orderTable = itv_pick_table($conn, ['courseorder', 'orders', 'course_order']);
if ($orderTable === null) {
  http_response_code(500);
  echo "Orders table not found.";
  exit;
}

$courseTable = itv_pick_table($conn, ['course', 'courses']);
if ($courseTable === null || !itv_has_column($conn, $courseTable, 'course_id')) {
  http_response_code(500);
  echo "Course table not compatible.";
  exit;
}

$selectCols = ['course_id'];
if (itv_has_column($conn, $courseTable, 'course_name')) $selectCols[] = 'course_name';
if (itv_has_column($conn, $courseTable, 'course_price')) $selectCols[] = 'course_price';

$sqlCourse = "SELECT " . implode(', ', $selectCols) . " FROM {$courseTable} WHERE course_id = ? LIMIT 1";
$st = $conn->prepare($sqlCourse);
$st->bind_param("s", $courseId);
$st->execute();
$course = $st->get_result()->fetch_assoc();
$st->close();

if (!$course) {
  itv_redirect("/checkout.php?course_id=" . urlencode($courseId) . "&err=course_not_found");
}

$courseName = (string)($course['course_name'] ?? 'Course');
$amount = (string)($course['course_price'] ?? '0');

$orderHasStuEmail = itv_has_column($conn, $orderTable, 'stu_email');
$orderHasStuId    = itv_has_column($conn, $orderTable, 'stu_id');

$stuId = null;
if ($orderHasStuId) {
  if (isset($_SESSION['stu_id']) && is_numeric($_SESSION['stu_id'])) {
    $stuId = (int)$_SESSION['stu_id'];
  } elseif (itv_has_column($conn, 'student', 'stu_id') && itv_has_column($conn, 'student', 'stu_email')) {
    $st = $conn->prepare("SELECT stu_id FROM student WHERE stu_email = ? LIMIT 1");
    if ($st) {
      $st->bind_param("s", $stuEmail);
      $st->execute();
      $r = $st->get_result()->fetch_assoc();
      $st->close();
      if ($r && isset($r['stu_id'])) {
        $stuId = (int)$r['stu_id'];
        $_SESSION['stu_id'] = $stuId;
      }
    }
  }
}

$courseIdCol = null;
foreach (['course_id', 'courseId', 'course_id_fk'] as $c) {
  if (itv_has_column($conn, $orderTable, $c)) {
    $courseIdCol = $c;
    break;
  }
}
if ($courseIdCol === null) {
  http_response_code(500);
  echo "Order table missing course id column.";
  exit;
}

$whereSql = "";
$types = "";
$vals = [];

if ($orderHasStuId && $stuId !== null) {
  $whereSql = "stu_id = ? AND {$courseIdCol} = ?";
  $types = "is";
  $vals = [$stuId, $courseId];
} elseif ($orderHasStuEmail) {
  $whereSql = "stu_email = ? AND {$courseIdCol} = ?";
  $types = "ss";
  $vals = [$stuEmail, $courseId];
} else {
  http_response_code(500);
  echo "Order table missing stu_email and stu_id.";
  exit;
}

$st = $conn->prepare("SELECT 1 FROM {$orderTable} WHERE {$whereSql} LIMIT 1");
$st->bind_param($types, ...$vals);
$st->execute();
$exists = $st->get_result();
$already = ($exists && $exists->num_rows > 0);
$st->close();

$_SESSION['last_purchased_course_id'] = (string)$courseId;
$_SESSION['last_purchased_course_name'] = $courseName;

if ($already) {
  $_SESSION['purchase_status'] = 'already_owned';
  itv_redirect("/purchase_success.php");
}

$cols = [];
$params = [];
$bindTypes = "";
$bindVals = [];

if (itv_has_column($conn, $orderTable, $courseIdCol)) {
  $cols[] = $courseIdCol;
  $params[] = "?";
  $bindTypes .= "s";
  $bindVals[] = $courseId;
}

if ($orderHasStuId && $stuId !== null && itv_has_column($conn, $orderTable, 'stu_id')) {
  $cols[] = "stu_id";
  $params[] = "?";
  $bindTypes .= "i";
  $bindVals[] = $stuId;
}

if ($orderHasStuEmail && itv_has_column($conn, $orderTable, 'stu_email')) {
  $cols[] = "stu_email";
  $params[] = "?";
  $bindTypes .= "s";
  $bindVals[] = $stuEmail;
}

if (itv_has_column($conn, $orderTable, 'amount')) {
  $cols[] = "amount";
  $params[] = "?";
  $bindTypes .= "s";
  $bindVals[] = $amount;
} elseif (itv_has_column($conn, $orderTable, 'course_price')) {
  $cols[] = "course_price";
  $params[] = "?";
  $bindTypes .= "s";
  $bindVals[] = $amount;
}

if (itv_has_column($conn, $orderTable, 'payment_method')) {
  $cols[] = "payment_method";
  $params[] = "?";
  $bindTypes .= "s";
  $bindVals[] = $paymentMethod;
}

if (itv_has_column($conn, $orderTable, 'status')) {
  $cols[] = "status";
  $params[] = "?";
  $bindTypes .= "s";
  $bindVals[] = "paid_local";
}

if (itv_has_column($conn, $orderTable, 'order_date')) {
  $cols[] = "order_date";
  $params[] = "?";
  $bindTypes .= "s";
  $bindVals[] = date("Y-m-d H:i:s");
} elseif (itv_has_column($conn, $orderTable, 'created_at')) {
  $cols[] = "created_at";
  $params[] = "?";
  $bindTypes .= "s";
  $bindVals[] = date("Y-m-d H:i:s");
}

if (empty($cols)) {
  http_response_code(500);
  echo "Order table columns not writable.";
  exit;
}

$sql = "INSERT INTO {$orderTable} (" . implode(", ", $cols) . ")
        VALUES (" . implode(", ", $params) . ")";
$st = $conn->prepare($sql);
$st->bind_param($bindTypes, ...$bindVals);
$st->execute();
$st->close();

$_SESSION['purchase_status'] = 'added';
itv_redirect("/purchase_success.php");
