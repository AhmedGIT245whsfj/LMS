<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

require_once __DIR__ . '/../dbConnection.php';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$stuEmail = '';
if (!empty($_SESSION['stuLogEmail'])) $stuEmail = (string)$_SESSION['stuLogEmail'];
elseif (!empty($_SESSION['stu_email'])) $stuEmail = (string)$_SESSION['stu_email'];

if (trim($stuEmail) === '') {
  echo "<script> location.href='../index.php'; </script>";
  exit;
}

/**
 * Accept multiple param names for compatibility:
 * ?course_id=23, ?courseid=23, ?cid=23, ?id=23
 */
$courseId = '';
foreach (['course_id', 'courseid', 'cid', 'id'] as $k) {
  if (isset($_GET[$k]) && trim((string)$_GET[$k]) !== '') {
    $courseId = trim((string)$_GET[$k]);
    break;
  }
}
$courseId = preg_replace('/[^0-9]/', '', $courseId);
if ($courseId === '') {
  http_response_code(400);
  echo "Missing course id.";
  exit;
}

/**
 * Detect the real course-id column in lesson table (some variants use courseId).
 */
function itv_find_lesson_course_col(mysqli $conn): string {
  $cols = [];
  $res = $conn->query("SHOW COLUMNS FROM lesson");
  while ($row = $res->fetch_assoc()) $cols[] = $row['Field'];

  foreach (['course_id', 'courseId', 'courseID', 'cid'] as $c) {
    if (in_array($c, $cols, true)) return $c;
  }

  // fallback: first column that contains 'course'
  foreach ($cols as $c) {
    if (stripos($c, 'course') !== false) return $c;
  }

  return 'course_id';
}

$lessonCourseCol = itv_find_lesson_course_col($conn);

$lessons = [];
$firstVideo = '';

$sql = "SELECT lesson_name, lesson_link
        FROM lesson
        WHERE {$lessonCourseCol} = ?
        ORDER BY lesson_id ASC";

$stmt = $conn->prepare($sql);
if ($stmt) {
  $stmt->bind_param("i", $courseId);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($res) {
    while ($row = $res->fetch_assoc()) {
      $lessons[] = $row;
    }
  }
  $stmt->close();
}

if (!empty($lessons) && !empty($lessons[0]['lesson_link'])) {
  $firstVideo = (string)$lessons[0]['lesson_link'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Watch Course</title>

  <link rel="stylesheet" href="../css/bootstrap.min.css">
  <link rel="stylesheet" href="../css/all.min.css">
  <link href="https://fonts.googleapis.com/css?family=Ubuntu" rel="stylesheet">
  <link rel="stylesheet" href="../css/stustyle.css">
  <link rel="stylesheet" type="text/css" href="../css/watchcourse-polish.css">
</head>

<body>
  <div class="container-fluid bg-success p-2">
    <h3>Welcome to ITVERSE</h3>
    <a class="btn btn-danger" href="./myCourse.php">My Courses</a>
  </div>

  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-3 border-right">
        <h4 class="text-center">Lessons</h4>

        <?php if (empty($lessons)): ?>
          <div class="alert alert-warning m-2">
            No lessons found for course_id=<?php echo h($courseId); ?>.
          </div>
        <?php endif; ?>

        <ul id="playlist" class="nav flex-column">
          <?php foreach ($lessons as $i => $row): ?>
            <?php
              $name = $row['lesson_name'] ?? ('Lesson ' . ($i+1));
              $link = $row['lesson_link'] ?? '';
            ?>
            <li
              class="nav-item border-bottom py-2"
              data-movieurl="<?php echo h($link); ?>"
              style="cursor:pointer;"
            >
              <?php echo h($name); ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div class="col-sm-8">
        <video id="videoarea" src="<?php echo h($firstVideo); ?>" class="mt-5 w-75 ml-2" controls></video>
      </div>
    </div>
  </div>

  <script src="../js/jquery.min.js"></script>
  <script src="../js/popper.min.js"></script>
  <script src="../js/bootstrap.min.js"></script>
  <script src="../js/all.min.js"></script>
  <script src="../js/custom.js"></script>

  <script>
    // Robust playlist click binding (works even if custom.js breaks)
    (function () {
      var list = document.getElementById('playlist');
      var video = document.getElementById('videoarea');
      if (!list || !video) return;

      list.addEventListener('click', function (e) {
        var li = e.target;
        if (!li || !li.getAttribute) return;
        var url = li.getAttribute('data-movieurl');
        if (!url) return;
        video.src = url;
        try { video.play(); } catch (err) {}
      });
    })();
  </script>
</body>
</html>
