<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../dbConnection.php';

$stuEmail = trim((string)($_SESSION['stuLogEmail'] ?? ''));
if ($stuEmail === '') {
    header("Location: /loginorsignup.php");
    exit;
}

$recommendedCourses = [];
$myCourses = [];

$stuSql = "
    SELECT s.stu_id, s.stu_email, s.preferred_track_id, s.experience_level, t.track_name
    FROM student s
    LEFT JOIN track t ON t.track_id = s.preferred_track_id
    WHERE s.stu_email = ?
    LIMIT 1
";
$stuStmt = $conn->prepare($stuSql);
$stuId = 0;
$trackId = 0;
$level = 'beginner';

if ($stuStmt) {
    $stuStmt->bind_param("s", $stuEmail);
    $stuStmt->execute();
    $stuRes = $stuStmt->get_result();
    if ($stuRes && ($stu = $stuRes->fetch_assoc())) {
        $stuId = (int)($stu['stu_id'] ?? 0);
        $trackId = (int)($stu['preferred_track_id'] ?? 0);
        $level = strtolower(trim((string)($stu['experience_level'] ?? 'beginner')));
    }
    $stuStmt->close();
}

if ($trackId > 0) {
    if ($level === 'beginner') {
        $recSql = "
            SELECT c.course_id, c.course_name, c.course_desc, c.course_img, c.course_price, c.course_original_price, t.track_name
            FROM course c
            LEFT JOIN track t ON t.track_id = c.track_id
            WHERE c.track_id = ?
            ORDER BY c.course_id ASC
            LIMIT 4
        ";
    } else {
        $recSql = "
            SELECT c.course_id, c.course_name, c.course_desc, c.course_img, c.course_price, c.course_original_price, t.track_name
            FROM course c
            LEFT JOIN track t ON t.track_id = c.track_id
            WHERE c.track_id = ?
            ORDER BY c.course_id DESC
            LIMIT 4
        ";
    }

    $recStmt = $conn->prepare($recSql);
    if ($recStmt) {
        $recStmt->bind_param("i", $trackId);
        $recStmt->execute();
        $recRes = $recStmt->get_result();
        if ($recRes && $recRes->num_rows > 0) {
            while ($r = $recRes->fetch_assoc()) {
                $recommendedCourses[] = $r;
            }
        }
        $recStmt->close();
    }
}

if ($stuId > 0) {
    $mySql = "
        SELECT DISTINCT c.course_id, c.course_name, c.course_desc, c.course_img
        FROM courseorder o
        INNER JOIN course c ON c.course_id = o.course_id
        WHERE o.stu_id = ?
        ORDER BY c.course_id DESC
    ";
    $myStmt = $conn->prepare($mySql);
    if ($myStmt) {
        $myStmt->bind_param("i", $stuId);
        $myStmt->execute();
        $myRes = $myStmt->get_result();
        if ($myRes && $myRes->num_rows > 0) {
            while ($r = $myRes->fetch_assoc()) {
                $myCourses[] = $r;
            }
        }
        $myStmt->close();
    }
}

include_once __DIR__ . '/stuInclude/header.php';
?>

<div class="container-fluid" style="margin-top:20px;">
  <div class="row">
    <div class="col-sm-3">
      <?php @include_once __DIR__ . '/stuInclude/sidebar.php'; ?>
    </div>

    <div class="col-sm-9">
      <h3 class="mb-3">Recommended for You</h3>
      <p class="text-muted">Best starting courses based on your selected track and level.</p>

      <?php if (count($recommendedCourses) > 0): ?>
        <div class="row">
          <?php foreach ($recommendedCourses as $c): ?>
            <?php $img = str_replace('..', '.', (string)($c['course_img'] ?? '')); ?>
            <div class="col-md-6 col-lg-3 mb-3">
              <div class="card h-100 border-primary">
                <img class="card-img-top" src="<?php echo htmlspecialchars($img); ?>" alt="Course">
                <div class="card-body">
                  <span class="badge badge-success mb-2">Recommended</span><br>
                  <small class="text-muted"><?php echo htmlspecialchars((string)($c['track_name'] ?? '')); ?></small>
                  <h5 class="card-title mt-2"><?php echo htmlspecialchars((string)($c['course_name'] ?? 'Course')); ?></h5>
                  <p class="card-text"><?php echo htmlspecialchars((string)($c['course_desc'] ?? '')); ?></p>
                </div>
                <div class="card-footer">
                  <a class="btn btn-primary btn-sm" href="/coursedetails.php?course_id=<?php echo (int)$c['course_id']; ?>">Open</a>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="alert alert-info">No recommendations available right now.</div>
      <?php endif; ?>

      <hr class="my-4">

      <h3 class="mb-3">My Courses</h3>

      <?php if (count($myCourses) > 0): ?>
        <div class="row">
          <?php foreach ($myCourses as $c): ?>
            <?php $img = str_replace('..', '.', (string)($c['course_img'] ?? '')); ?>
            <div class="col-md-6 col-lg-4 mb-3">
              <div class="card h-100">
                <img class="card-img-top" src="<?php echo htmlspecialchars($img); ?>" alt="Course">
                <div class="card-body">
                  <h5 class="card-title"><?php echo htmlspecialchars((string)($c['course_name'] ?? 'Course')); ?></h5>
                  <p class="card-text"><?php echo htmlspecialchars((string)($c['course_desc'] ?? '')); ?></p>
                  <a class="btn btn-primary btn-sm" href="/Student/watchcourse.php?course_id=<?php echo (int)$c['course_id']; ?>">Open</a>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="alert alert-info">No courses found yet.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include_once __DIR__ . '/stuInclude/footer.php'; ?>
