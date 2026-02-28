<?php
if (!isset($_SESSION)) { session_start(); }
define('TITLE', 'Modules');
define('PAGE', 'lessons');

include('../dbConnection.php');
include('./adminInclude/header.php');

if (!isset($_SESSION['is_admin_login'])) {
  echo "<script> location.href='../index.php'; </script>";
  exit;
}

$course = null;
$courseId = 0;

if (isset($_GET['checkid']) && $_GET['checkid'] !== '') {
  $courseId = (int)$_GET['checkid'];
  if ($courseId > 0) {
    $stmt = $conn->prepare("SELECT course_id, course_name FROM course WHERE course_id = ? LIMIT 1");
    if ($stmt) {
      $stmt->bind_param("i", $courseId);
      $stmt->execute();
      $res = $stmt->get_result();
      $course = ($res && $res->num_rows === 1) ? $res->fetch_assoc() : null;
      $stmt->close();
    }
    if ($course) {
      $_SESSION['course_id'] = (int)$course['course_id'];
      $_SESSION['course_name'] = (string)$course['course_name'];
    }
  }
}

if (isset($_POST['delete']) && isset($_POST['id'])) {
  $id = (int)$_POST['id'];
  if ($id > 0) {
    $stmt = $conn->prepare("DELETE FROM lesson WHERE lesson_id = ? LIMIT 1");
    if ($stmt) {
      $stmt->bind_param("i", $id);
      $stmt->execute();
      $stmt->close();
      echo '<meta http-equiv="refresh" content="0;URL=?checkid=' . (int)($_SESSION['course_id'] ?? 0) . '&deleted" />';
      exit;
    }
  }
}

?>
<div class="col-sm-9 mt-5 mx-3">
  <form action="" method="GET" class="mt-3 form-inline d-print-none">
    <div class="form-group mr-3">
      <label for="checkid">Enter Course ID:</label>
      <input type="text" class="form-control ml-3" id="checkid" name="checkid" value="<?php echo $courseId ? (int)$courseId : ''; ?>" onkeypress="isInputNumber(event)">
    </div>
    <button type="submit" class="btn btn-danger">Search</button>
  </form>

  <?php if ($course): ?>
    <h3 class="mt-5 bg-dark text-white p-2">
      Course ID: <?php echo (int)$course['course_id']; ?> |
      Course Name: <?php echo htmlspecialchars($course['course_name']); ?>
    </h3>

    <?php
      $stmt = $conn->prepare("SELECT lesson_id, lesson_name, lesson_link, lesson_position FROM lesson WHERE course_id = ? ORDER BY lesson_position ASC, lesson_id ASC");
      $lessons = null;
      if ($stmt) {
        $stmt->bind_param("i", $courseId);
        $stmt->execute();
        $lessons = $stmt->get_result();
        $stmt->close();
      }
    ?>

    <?php if ($lessons && $lessons->num_rows > 0): ?>
      <table class="table">
        <thead>
          <tr>
            <th scope="col">Module ID</th>
            <th scope="col">Position</th>
            <th scope="col">Module Name</th>
            <th scope="col">YouTube URL</th>
            <th scope="col">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $lessons->fetch_assoc()): ?>
            <tr>
              <th scope="row"><?php echo (int)$row['lesson_id']; ?></th>
              <td><?php echo (int)$row['lesson_position']; ?></td>
              <td><?php echo htmlspecialchars($row['lesson_name']); ?></td>
              <td><?php echo htmlspecialchars($row['lesson_link']); ?></td>
              <td>
                <form action="editlesson.php" method="POST" class="d-inline">
                  <input type="hidden" name="id" value="<?php echo (int)$row['lesson_id']; ?>">
                  <button type="submit" class="btn btn-info mr-3" name="view" value="View"><i class="fas fa-pen"></i></button>
                </form>
                <form action="" method="POST" class="d-inline" onsubmit="return confirm('Delete this module?');">
                  <input type="hidden" name="id" value="<?php echo (int)$row['lesson_id']; ?>">
                  <button type="submit" class="btn btn-secondary" name="delete" value="Delete"><i class="far fa-trash-alt"></i></button>
                </form>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="alert alert-dark mt-4">No modules found for this course.</div>
    <?php endif; ?>

  <?php elseif ($courseId > 0): ?>
    <div class="alert alert-dark mt-4" role="alert">Course not found.</div>
  <?php endif; ?>
</div>

<script>
  function isInputNumber(evt) {
    var ch = String.fromCharCode(evt.which);
    if (!(/[0-9]/.test(ch))) { evt.preventDefault(); }
  }
</script>

</div>
<?php if (isset($_SESSION['course_id']) && (int)$_SESSION['course_id'] > 0): ?>
  <div><a class="btn btn-danger box" href="./addLesson.php"><i class="fas fa-plus fa-2x"></i></a></div>
<?php endif; ?>

</div>
<?php include('./adminInclude/footer.php'); ?>
