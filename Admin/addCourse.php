<?php
if (!isset($_SESSION)) { session_start(); }
define('TITLE', 'Add Course');
define('PAGE', 'courses');

include('../dbConnection.php');
include('./adminInclude/header.php');

if (!isset($_SESSION['is_admin_login'])) {
  echo "<script> location.href='../index.php'; </script>";
  exit;
}

$msg = null;

$tracks = [];
$tr = $conn->query("SELECT track_id, track_name FROM tracks ORDER BY track_id ASC");
if ($tr) {
  while ($r = $tr->fetch_assoc()) {
    $tracks[] = $r;
  }
}

if (isset($_POST['courseSubmitBtn'])) {
  $track_id = (int)($_POST['track_id'] ?? 0);
  $course_name = trim((string)($_POST['course_name'] ?? ''));
  $course_desc = trim((string)($_POST['course_desc'] ?? ''));
  $course_author = trim((string)($_POST['course_author'] ?? ''));
  $course_duration = trim((string)($_POST['course_duration'] ?? ''));
  $course_price = (int)($_POST['course_price'] ?? 0);
  $course_original_price = (int)($_POST['course_original_price'] ?? 0);

  if ($track_id <= 0 || $course_name === '' || $course_desc === '' || $course_author === '' || $course_duration === '' || $course_price <= 0 || $course_original_price <= 0) {
    $msg = '<div class="alert alert-warning col-sm-8 mt-2" role="alert">Fill all fields</div>';
  } else {
    $img_folder = 'image/courseimg/php.jpg';

    if (isset($_FILES['course_img']) && is_uploaded_file($_FILES['course_img']['tmp_name'])) {
      $course_image = basename((string)$_FILES['course_img']['name']);
      $course_image_temp = $_FILES['course_img']['tmp_name'];
      $img_folder = '../image/courseimg/' . $course_image;
      @move_uploaded_file($course_image_temp, $img_folder);
    }

    $stmt = $conn->prepare("INSERT INTO course (track_id, course_name, course_desc, course_author, course_img, course_duration, course_price, course_original_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
      $stmt->bind_param("isssssii", $track_id, $course_name, $course_desc, $course_author, $img_folder, $course_duration, $course_price, $course_original_price);
      if ($stmt->execute()) {
        $msg = '<div class="alert alert-success col-sm-8 mt-2" role="alert">Course added successfully</div>';
      } else {
        $msg = '<div class="alert alert-danger col-sm-8 mt-2" role="alert">Unable to add course</div>';
      }
      $stmt->close();
    } else {
      $msg = '<div class="alert alert-danger col-sm-8 mt-2" role="alert">Unable to add course</div>';
    }
  }
}
?>
<div class="col-sm-7 mt-5 mx-3 jumbotron">
  <h3 class="text-center">Add New Course</h3>
  <form action="" method="POST" enctype="multipart/form-data">

    <div class="form-group">
      <label for="track_id">Track</label>
      <select class="form-control" id="track_id" name="track_id" required>
        <option value="">Select track</option>
        <?php foreach ($tracks as $t): ?>
          <option value="<?php echo (int)$t['track_id']; ?>"><?php echo htmlspecialchars($t['track_name']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label for="course_name">Course Name</label>
      <input type="text" class="form-control" id="course_name" name="course_name">
    </div>

    <div class="form-group">
      <label for="course_desc">Course Description</label>
      <textarea class="form-control" id="course_desc" name="course_desc" rows="2"></textarea>
    </div>

    <div class="form-group">
      <label for="course_author">Author</label>
      <input type="text" class="form-control" id="course_author" name="course_author" value="ITVERSE">
    </div>

    <div class="form-group">
      <label for="course_duration">Course Duration</label>
      <input type="text" class="form-control" id="course_duration" name="course_duration" value="10 hours">
    </div>

    <div class="form-group">
      <label for="course_original_price">Course Original Price</label>
      <input type="text" class="form-control" id="course_original_price" name="course_original_price" onkeypress="isInputNumber(event)">
    </div>

    <div class="form-group">
      <label for="course_price">Course Selling Price</label>
      <input type="text" class="form-control" id="course_price" name="course_price" onkeypress="isInputNumber(event)">
    </div>

    <div class="form-group">
      <label for="course_img">Course Image</label>
      <input type="file" class="form-control-file" id="course_img" name="course_img">
      <small class="form-text text-muted">If empty, a default image will be used.</small>
    </div>

    <div class="text-center">
      <button type="submit" class="btn btn-danger" id="courseSubmitBtn" name="courseSubmitBtn">Submit</button>
      <a href="courses.php" class="btn btn-secondary">Close</a>
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
