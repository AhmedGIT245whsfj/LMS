<?php
if (!isset($_SESSION)) { session_start(); }
define('TITLE', 'Edit Course');
define('PAGE', 'courses');

include('../dbConnection.php');
include('./adminInclude/header.php');

if (!isset($_SESSION['is_admin_login'])) {
  echo "<script> location.href='../index.php'; </script>";
  exit;
}

$msg = null;
$row = null;

$tracks = [];
$tr = $conn->query("SELECT track_id, track_name FROM tracks ORDER BY track_id ASC");
if ($tr) {
  while ($r = $tr->fetch_assoc()) { $tracks[] = $r; }
}

if (isset($_POST['view']) && isset($_POST['id'])) {
  $id = (int)$_POST['id'];
  $stmt = $conn->prepare("SELECT * FROM course WHERE course_id = ? LIMIT 1");
  if ($stmt) {
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
  }
}

if (isset($_POST['requpdate'])) {
  $cid = (int)($_POST['course_id'] ?? 0);
  $track_id = (int)($_POST['track_id'] ?? 0);

  $cname = trim((string)($_POST['course_name'] ?? ''));
  $cdesc = trim((string)($_POST['course_desc'] ?? ''));
  $cauthor = trim((string)($_POST['course_author'] ?? ''));
  $cduration = trim((string)($_POST['course_duration'] ?? ''));
  $cprice = (int)($_POST['course_price'] ?? 0);
  $coriginalprice = (int)($_POST['course_original_price'] ?? 0);

  if ($cid <= 0 || $track_id <= 0 || $cname === '' || $cdesc === '' || $cauthor === '' || $cduration === '' || $cprice <= 0 || $coriginalprice <= 0) {
    $msg = '<div class="alert alert-warning col-sm-8 mt-2" role="alert">Fill all fields</div>';
  } else {
    $stmt0 = $conn->prepare("SELECT course_img FROM course WHERE course_id = ? LIMIT 1");
    $currentImg = null;
    if ($stmt0) {
      $stmt0->bind_param("i", $cid);
      $stmt0->execute();
      $res0 = $stmt0->get_result();
      $currentImg = ($res0 && $res0->num_rows === 1) ? (string)$res0->fetch_assoc()['course_img'] : null;
      $stmt0->close();
    }

    $cimg = $currentImg ?: 'image/courseimg/php.jpg';

    if (isset($_FILES['course_img']) && is_uploaded_file($_FILES['course_img']['tmp_name'])) {
      $course_image = basename((string)$_FILES['course_img']['name']);
      $course_image_temp = $_FILES['course_img']['tmp_name'];
      $cimg = '../image/courseimg/' . $course_image;
      @move_uploaded_file($course_image_temp, $cimg);
    }

    $stmt = $conn->prepare("UPDATE course SET track_id = ?, course_name = ?, course_desc = ?, course_author = ?, course_duration = ?, course_price = ?, course_original_price = ?, course_img = ? WHERE course_id = ? LIMIT 1");
    if ($stmt) {
      $stmt->bind_param("issssiisi", $track_id, $cname, $cdesc, $cauthor, $cduration, $cprice, $coriginalprice, $cimg, $cid);
      if ($stmt->execute()) {
        $msg = '<div class="alert alert-success col-sm-8 mt-2" role="alert">Updated successfully</div>';
      } else {
        $msg = '<div class="alert alert-danger col-sm-8 mt-2" role="alert">Unable to update</div>';
      }
      $stmt->close();
    } else {
      $msg = '<div class="alert alert-danger col-sm-8 mt-2" role="alert">Unable to update</div>';
    }

    $stmt2 = $conn->prepare("SELECT * FROM course WHERE course_id = ? LIMIT 1");
    if ($stmt2) {
      $stmt2->bind_param("i", $cid);
      $stmt2->execute();
      $res2 = $stmt2->get_result();
      $row = $res2 ? $res2->fetch_assoc() : null;
      $stmt2->close();
    }
  }
}

if (!$row) {
  echo '<div class="col-sm-9 mt-5"><div class="alert alert-dark">Course not found.</div></div></div></div>';
  include('./adminInclude/footer.php');
  exit;
}
?>
<div class="col-sm-7 mt-5 mx-3 jumbotron">
  <h3 class="text-center">Update Course Details</h3>

  <form action="" method="POST" enctype="multipart/form-data">
    <div class="form-group">
      <label for="course_id">Course ID</label>
      <input type="text" class="form-control" id="course_id" name="course_id" value="<?php echo (int)$row['course_id']; ?>" readonly>
    </div>

    <div class="form-group">
      <label for="track_id">Track</label>
      <select class="form-control" id="track_id" name="track_id" required>
        <option value="">Select track</option>
        <?php foreach ($tracks as $t): ?>
          <option value="<?php echo (int)$t['track_id']; ?>" <?php if ((int)$row['track_id'] === (int)$t['track_id']) echo 'selected'; ?>>
            <?php echo htmlspecialchars($t['track_name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label for="course_name">Course Name</label>
      <input type="text" class="form-control" id="course_name" name="course_name" value="<?php echo htmlspecialchars($row['course_name']); ?>">
    </div>

    <div class="form-group">
      <label for="course_desc">Course Description</label>
      <textarea class="form-control" id="course_desc" name="course_desc" rows="2"><?php echo htmlspecialchars($row['course_desc']); ?></textarea>
    </div>

    <div class="form-group">
      <label for="course_author">Author</label>
      <input type="text" class="form-control" id="course_author" name="course_author" value="<?php echo htmlspecialchars($row['course_author']); ?>">
    </div>

    <div class="form-group">
      <label for="course_duration">Course Duration</label>
      <input type="text" class="form-control" id="course_duration" name="course_duration" value="<?php echo htmlspecialchars($row['course_duration']); ?>">
    </div>

    <div class="form-group">
      <label for="course_original_price">Course Original Price</label>
      <input type="text" class="form-control" id="course_original_price" name="course_original_price" onkeypress="isInputNumber(event)" value="<?php echo (int)$row['course_original_price']; ?>">
    </div>

    <div class="form-group">
      <label for="course_price">Course Selling Price</label>
      <input type="text" class="form-control" id="course_price" name="course_price" onkeypress="isInputNumber(event)" value="<?php echo (int)$row['course_price']; ?>">
    </div>

    <div class="form-group">
      <label for="course_img">Course Image</label>
      <div class="mb-2">
        <img src="<?php echo htmlspecialchars($row['course_img']); ?>" alt="courseimage" class="img-thumbnail" style="max-width: 220px;">
      </div>
      <input type="file" class="form-control-file" id="course_img" name="course_img">
      <small class="form-text text-muted">If empty, the current image will remain unchanged.</small>
    </div>

    <div class="text-center">
      <button type="submit" class="btn btn-danger" id="requpdate" name="requpdate">Update</button>
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
