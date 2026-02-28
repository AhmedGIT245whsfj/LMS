<?php
if (!isset($_SESSION)) { session_start(); }
define('TITLE', 'Add Module');
define('PAGE', 'lessons');

include('../dbConnection.php');
include('./adminInclude/header.php');

if (!isset($_SESSION['is_admin_login'])) {
  echo "<script> location.href='../index.php'; </script>";
  exit;
}

$msg = null;

$course_id = (int)($_SESSION['course_id'] ?? 0);
$course_name = (string)($_SESSION['course_name'] ?? '');

if ($course_id <= 0) {
  echo '<div class="col-sm-9 mt-5"><div class="alert alert-dark">Select a course first from Modules page.</div></div></div></div>';
  include('./adminInclude/footer.php');
  exit;
}

if (isset($_POST['lessonSubmitBtn'])) {
  $lesson_name = trim((string)($_POST['lesson_name'] ?? ''));
  $lesson_desc = trim((string)($_POST['lesson_desc'] ?? ''));
  $lesson_link = trim((string)($_POST['lesson_link'] ?? ''));
  $lesson_position = (int)($_POST['lesson_position'] ?? 0);

  if ($lesson_name === '' || $lesson_desc === '' || $lesson_link === '' || $lesson_position < 1 || $lesson_position > 12) {
    $msg = '<div class="alert alert-warning col-sm-8 mt-2" role="alert">Fill all fields (position 1..12)</div>';
  } else {
    $stmt = $conn->prepare("INSERT INTO lesson (lesson_name, lesson_desc, lesson_link, course_id, course_name, lesson_position) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt) {
      $stmt->bind_param("sssisi", $lesson_name, $lesson_desc, $lesson_link, $course_id, $course_name, $lesson_position);
      if ($stmt->execute()) {
        $msg = '<div class="alert alert-success col-sm-8 mt-2" role="alert">Module added successfully</div>';
      } else {
        $msg = '<div class="alert alert-danger col-sm-8 mt-2" role="alert">Unable to add module</div>';
      }
      $stmt->close();
    } else {
      $msg = '<div class="alert alert-danger col-sm-8 mt-2" role="alert">Unable to add module</div>';
    }
  }
}
?>
<div class="col-sm-7 mt-5 mx-3 jumbotron">
  <h3 class="text-center">Add New Module</h3>
  <form action="" method="POST">
    <div class="form-group">
      <label for="course_id">Course ID</label>
      <input type="text" class="form-control" id="course_id" name="course_id" value="<?php echo (int)$course_id; ?>" readonly>
    </div>
    <div class="form-group">
      <label for="course_name">Course Name</label>
      <input type="text" class="form-control" id="course_name" name="course_name" value="<?php echo htmlspecialchars($course_name); ?>" readonly>
    </div>
    <div class="form-group">
      <label for="lesson_position">Position (1..12)</label>
      <input type="text" class="form-control" id="lesson_position" name="lesson_position" onkeypress="isInputNumber(event)">
    </div>
    <div class="form-group">
      <label for="lesson_name">Module Name</label>
      <input type="text" class="form-control" id="lesson_name" name="lesson_name">
    </div>
    <div class="form-group">
      <label for="lesson_desc">Module Description</label>
      <textarea class="form-control" id="lesson_desc" name="lesson_desc" rows="2"></textarea>
    </div>
    <div class="form-group">
      <label for="lesson_link">YouTube URL</label>
      <input type="text" class="form-control" id="lesson_link" name="lesson_link" placeholder="https://www.youtube.com/watch?v=...">
    </div>
    <div class="text-center">
      <button type="submit" class="btn btn-danger" id="lessonSubmitBtn" name="lessonSubmitBtn">Submit</button>
      <a href="lessons.php" class="btn btn-secondary">Close</a>
    </div>
    <?php if ($msg) { echo $msg; } ?>
  </form>
</div>

<script>
  function isInputNumber(evt) {
    var ch = String.fromCharCode(evt.which);
    if (!(/[0-9]/.test(ch))) { evt.preventDefault(); }
  }
</script>

</div>
</div>
<?php include('./adminInclude/footer.php'); ?>
