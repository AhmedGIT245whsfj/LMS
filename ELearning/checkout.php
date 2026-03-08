<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/dbConnection.php';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }

$stuEmail = (string)($_SESSION['stuLogEmail'] ?? '');
if ($stuEmail === '') {
    header('Location: loginorsignup.php');
    exit;
}

$courseId = '';
if (isset($_GET['course_id'])) $courseId = trim((string)$_GET['course_id']);
if ($courseId === '' && isset($_GET['courseid'])) $courseId = trim((string)$_GET['courseid']);
if ($courseId === '' && isset($_GET['cid'])) $courseId = trim((string)$_GET['cid']);

$legacyId = '';
if ($courseId === '' && isset($_GET['id'])) {
    $legacyId = trim((string)$_GET['id']);
}

$hasBuyFlag = array_key_exists('buy', $_GET);

$course = null;

// 1) Normal: course_id
if ($courseId !== '') {
    $stmt = $conn->prepare("SELECT course_id, course_name, course_price, course_desc FROM course WHERE course_id = ? LIMIT 1");
    $stmt->bind_param('s', $courseId);
    $stmt->execute();
    $res = $stmt->get_result();
    $course = $res ? $res->fetch_assoc() : null;
    $stmt->close();
}

// 2) Legacy: id might be course_id
if (!$course && $legacyId !== '') {
    $stmt = $conn->prepare("SELECT course_id, course_name, course_price, course_desc FROM course WHERE course_id = ? LIMIT 1");
    $stmt->bind_param('s', $legacyId);
    $stmt->execute();
    $res = $stmt->get_result();
    $course = $res ? $res->fetch_assoc() : null;
    $stmt->close();
}

// 3) Legacy: id might be price when buy flag exists (only if unique match)
if (!$course && $legacyId !== '' && $hasBuyFlag) {
    $stmt = $conn->prepare("SELECT course_id, course_name, course_price, course_desc FROM course WHERE course_price = ? LIMIT 2");
    $stmt->bind_param('s', $legacyId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        $rows = [];
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        if (count($rows) === 1) $course = $rows[0];
    }
    $stmt->close();
}


// Fallback for legacy links like checkout.php?id=499&buy=
// Use the last course details the student visited (session) to resolve the course deterministically.
if (!$course && $hasBuyFlag && $legacyId !== '' && !empty($_SESSION['last_course_id_viewed'])) {
    $cid = (string)$_SESSION['last_course_id_viewed'];
    $stmt = $conn->prepare("SELECT course_id, course_name, course_price, course_desc FROM course WHERE course_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $cid);
        $stmt->execute();
        $res = $stmt->get_result();
        $course = $res ? $res->fetch_assoc() : null;
        $stmt->close();
    }
}

if (!$course) {
    http_response_code(400);
    ?>
    <!doctype html>
    <html lang="en">
    <head>
      <meta charset="utf-8">
      <title>Checkout Error</title>
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <link rel="stylesheet" href="css/bootstrap.min.css">
    </head>
    <body>
    <div class="container" style="max-width:860px;margin-top:30px;">
      <div class="alert alert-danger">
        <strong>Checkout link is invalid.</strong><br>
        Could not resolve the course from URL parameters.
      </div>
      <a class="btn btn-primary" href="courses.php">Back to Courses</a>
    </div>
    </body>
    </html>
    <?php
    exit;
}

$courseId    = (string)($course['course_id'] ?? '');
$courseName  = (string)($course['course_name'] ?? 'Course');
$coursePrice = (string)($course['course_price'] ?? '0');
$courseDesc  = (string)($course['course_desc'] ?? '');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Checkout</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="css/bootstrap.min.css">
</head>
<body>
<div class="container" style="max-width: 820px; margin-top: 30px;">
  <div class="card">
    <div class="card-header"><strong>Checkout</strong></div>
    <div class="card-body">
      <h4 class="mb-1"><?php echo h($courseName); ?></h4>
      <div class="text-muted mb-3">Price: <strong><?php echo h($coursePrice); ?></strong></div>
      <?php if ($courseDesc !== ''): ?>
        <p class="mb-4"><?php echo h($courseDesc); ?></p>
      <?php endif; ?>

      <form method="post" action="payment_local_process.php">
        <input type="hidden" name="course_id" value="<?php echo h($courseId); ?>">

        <div class="mb-3">
          <label class="form-label"><strong>Payment method</strong></label>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="payment_method" id="pm_card" value="card" checked>
            <label class="form-check-label" for="pm_card">Card (Local)</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="payment_method" id="pm_wallet" value="wallet">
            <label class="form-check-label" for="pm_wallet">Wallet (Local)</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="payment_method" id="pm_cash" value="cash">
            <label class="form-check-label" for="pm_cash">Cash (Local)</label>
          </div>
        </div>

        <button type="submit" class="btn btn-success">Confirm & Add Course</button>
        <a class="btn btn-outline-secondary" href="courses.php">Back to Courses</a>
      </form>
    </div>
  </div>
</div>
</body>
</html>
