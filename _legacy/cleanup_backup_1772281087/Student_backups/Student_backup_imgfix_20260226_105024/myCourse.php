<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../dbConnection.php';

$stuEmail = (string)($_SESSION['stuLogEmail'] ?? '');
if ($stuEmail === '') {
    header('Location: ../loginorsignup.php');
    exit;
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }

/**
 * Make course image path always work from any page.
 * DB value example: image/courseimg/php.jpg
 * We convert to: /image/courseimg/php.jpg
 */
function img_url(?string $path): string {
    $p = trim((string)$path);
    if ($p === '') return '/image/coursebanner.jpg';
    if (strpos($p, 'http://') === 0 || strpos($p, 'https://') === 0) return $p;
    return '/' . ltrim($p, '/');
}

/**
 * If redirected here after purchase, redirect to success page BEFORE any output.
 * This fixes the issue where includes/header would break header() redirect.
 */
if (isset($_GET['added']) && (string)$_GET['added'] === '1') {
    $cid = (string)($_GET['course_id'] ?? $_GET['courseid'] ?? $_GET['cid'] ?? '');
    if ($cid !== '') {
        $_SESSION['last_purchased_course_id'] = $cid;

        $stmtN = $conn->prepare("SELECT course_name FROM course WHERE course_id = ? LIMIT 1");
        if ($stmtN) {
            $stmtN->bind_param('s', $cid);
            $stmtN->execute();
            $rN = $stmtN->get_result();
            if ($rN && ($rowN = $rN->fetch_assoc())) {
                $_SESSION['last_purchased_course_name'] = (string)($rowN['course_name'] ?? '');
            }
            $stmtN->close();
        }
    }
    header('Location: ../purchase_success.php');
    exit;
}

/** Detect columns in courseorder for email + course id (legacy-safe) */
$emailCol = null;
$courseIdCol = null;
$cols = [];
$res = $conn->query("SHOW COLUMNS FROM courseorder");
if ($res) {
    while ($r = $res->fetch_assoc()) $cols[] = $r;
    $res->free();
}
foreach ($cols as $c) {
    $f = strtolower((string)$c['Field']);
    if ($emailCol === null && strpos($f, 'email') !== false) $emailCol = (string)$c['Field'];
    if ($courseIdCol === null && strpos($f, 'course') !== false && strpos($f, 'id') !== false) $courseIdCol = (string)$c['Field'];
}
if ($emailCol === null) $emailCol = 'stu_email';
if ($courseIdCol === null) $courseIdCol = 'course_id';

/** Load purchased courses */
$sql = "
SELECT DISTINCT
  c.course_id, c.course_name, c.course_desc, c.course_price, c.course_img
FROM courseorder o
JOIN course c ON c.course_id = o.$courseIdCol
WHERE o.$emailCol = ?
ORDER BY c.course_id DESC
";

$courses = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('s', $stuEmail);
    $stmt->execute();
    $r = $stmt->get_result();
    if ($r) while ($row = $r->fetch_assoc()) $courses[] = $row;
    $stmt->close();
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>My Courses</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../css/bootstrap.min.css">
  <style>
    body{background:#f6f7fb;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;}
    .wrap{max-width:1100px;margin:34px auto;padding:0 14px;}
    .topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;}
    .cardx{border-radius:14px;box-shadow:0 8px 30px rgba(0,0,0,.06);border:1px solid #eef0f4;}
    .course-card{border-radius:14px;border:1px solid #eef0f4;background:#fff;}
    .course-row{display:flex;gap:14px;align-items:flex-start;padding:14px;}
    .cimg{width:120px;height:84px;object-fit:cover;border-radius:12px;border:1px solid #eee;flex:0 0 auto;background:#fff;}
    .ctitle{font-weight:600;margin:0 0 6px 0;}
    .cdesc{color:#6c757d;font-size:14px;margin:0 0 8px 0;}
    .cmeta{color:#6c757d;font-size:14px;margin:0;}
    .actions{display:flex;flex-direction:column;gap:10px;flex:0 0 auto;}
    .btnr{border-radius:10px;padding:10px 14px;font-weight:500;}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="topbar">
      <div>
        <h3 style="margin:0;">My Courses</h3>
        <div style="color:#6c757d;font-size:14px;">Logged in as <?php echo h($stuEmail); ?></div>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end;">
        <a class="btn btn-outline-secondary btnr" href="studentProfile.php">My Profile</a>
        <a class="btn btn-outline-primary btnr" href="../courses.php">Browse Courses</a>
        <a class="btn btn-danger btnr" href="studentLogout.php">Logout</a>
      </div>
    </div>

    <?php if (!$courses): ?>
      <div class="alert alert-info cardx" style="padding:16px;">
        You have no courses yet. <a href="../courses.php" class="alert-link">Browse courses</a> to get started.
      </div>
    <?php else: ?>
      <div class="row g-3">
        <?php foreach ($courses as $c): ?>
          <?php
            $cid = (string)($c['course_id'] ?? '');
            $name = (string)($c['course_name'] ?? 'Course');
            $desc = (string)($c['course_desc'] ?? '');
            $price= (string)($c['course_price'] ?? '');
            $img  = img_url((string)($c['course_img'] ?? ''));
          ?>
          <div class="col-12">
            <div class="course-card">
              <div class="course-row">
                <img class="cimg"
                     src="<?php echo h($img); ?>"
                     alt="Course"
                     onerror="this.src='/image/coursebanner.jpg';" />

                <div style="flex:1 1 auto;">
                  <p class="ctitle"><?php echo h($name); ?></p>
                  <p class="cdesc"><?php echo h($desc); ?></p>
                  <p class="cmeta">Price: <strong><?php echo h($price); ?></strong></p>
                </div>

                <div class="actions">
                  <a class="btn btn-primary btnr" href="watchcourse.php?course_id=<?php echo urlencode($cid); ?>">Open</a>
                  <a class="btn btn-outline-secondary btnr" href="studentProfile.php">Profile</a>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
