<?php
if (!isset($_SESSION)) { session_start(); }
define('TITLE', 'Edit Module');
define('PAGE', 'lessons');

include('../dbConnection.php');
include('./adminInclude/header.php');

if (!isset($_SESSION['is_admin_login'])) {
  echo "<script> location.href='../index.php'; </script>";
  exit;
}

function youtube_embed_url(string $url): string {
  $u = trim($url);
  if ($u === '') return '';
  if (preg_match('~youtu\.be/([A-Za-z0-9_-]{6,})~', $u, $m)) {
    return 'https://www.youtube.com/embed/' . $m[1];
  }
  if (preg_match('~watch\?v=([A-Za-z0-9_-]{6,})~', $u, $m)) {
    return 'https://www.youtube.com/embed/' . $m[1];
  }
  if (preg_match('~/embed/([A-Za-z0-9_-]{6,})~', $u, $m)) {
    return 'https://www.youtube.com/embed/' . $m[1];
  }
  return $u;
}

$msg = null;
$row = null;

if (isset($_POST['view']) && isset($_POST['id'])) {
  $id = (int)$_POST['id'];
  $stmt = $conn->prepare("SELECT * FROM lesson WHERE lesson_id = ? LIMIT 1");
  if ($stmt) {
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
  }
}

if (isset($_POST['requpdate'])) {
  $lid = (int)($_POST['lesson_id'] ?? 0);
  $lname = trim((string)($_POST['lesson_name'] ?? ''));
  $ldesc = trim((string)($_POST['lesson_desc'] ?? ''));
  $llink = trim((string)($_POST['lesson_link'] ?? ''));
  $lpos = (int)($_POST['lesson_position'] ?? 0);

  if ($lid <= 0 || $lname === '' || $ldesc === '' || $llink === '' || $lpos < 1 || $lpos > 12) {
    $msg = '<div class="alert alert-warning col-sm-8 mt-2" role="alert">Fill all fields (position 1..12)</div>';
  } else {
    $stmt = $conn->prepare("UPDATE lesson SET lesson_name = ?, lesson_desc = ?, lesson_link = ?, lesson_position = ? WHERE lesson_id = ? LIMIT 1");
    if ($stmt) {
      $stmt->bind_param("sssii", $lname, $ldesc, $llink, $lpos, $lid);
      if ($stmt->execute()) {
        $msg = '<div class="alert alert-success col-sm-8 mt-2" role="alert">Updated successfully</div>';
      } else {
        $msg = '<div class="alert alert-danger col-sm-8 mt-2" role="alert">Unable to update</div>';
      }
      $stmt->close();
    } else {
      $msg = '<div class="alert alert-danger col-sm-8 mt-2" role="alert">Unable to update</div>';
    }

    $stmt2 = $conn->prepare("SELECT * FROM lesson WHERE lesson_id = ? LIMIT 1");
    if ($stmt2) {
      $stmt2->bind_param("i", $lid);
      $stmt2->execute();
      $res2 = $stmt2->get_result();
      $row = $res2 ? $res2->fetch_assoc() : null;
      $stmt2->close();
    }
  }
}

if (!$row) {
  echo '<div class="col-sm-9 mt-5"><div class="alert alert-dark">Module not found.</div></div></div></div>';
  include('./adminInclude/footer.php');
  exit;
}

$embed = youtube_embed_url((string)$row['lesson_link']);
?>
<div class="col-sm-7 mt-5 mx-3 jumbotron">
  <h3 class="text-center">Update Module Details</h3>

  <form action="" method="POST">
    <div class="form-group">
      <label for="lesson_id">Module ID</label>
      <input type="text" class="form-control" id="lesson_id" name="lesson_id" value="<?php echo (int)$row['lesson_id']; ?>" readonly>
    </div>

    <div class="form-group">
      <label for="lesson_position">Position (1..12)</label>
      <input type="text" class="form-control" id="lesson_position" name="lesson_position" onkeypress="isInputNumber(event)" value="<?php echo (int)$row['lesson_position']; ?>">
    </div>

    <div class="form-group">
      <label for="lesson_name">Module Name</label>
      <input type="text" class="form-control" id="lesson_name" name="lesson_name" value="<?php echo htmlspecialchars($row['lesson_name']); ?>">
    </div>

    <div class="form-group">
      <label for="lesson_desc">Module Description</label>
      <textarea class="form-control" id="lesson_desc" name="lesson_desc" rows="2"><?php echo htmlspecialchars($row['lesson_desc']); ?></textarea>
    </div>

    <div class="form-group">
      <label for="lesson_link">YouTube URL</label>
      <input type="text" class="form-control" id="lesson_link" name="lesson_link" value="<?php echo htmlspecialchars($row['lesson_link']); ?>">
      <?php if ($embed !== ''): ?>
        <div class="embed-responsive embed-responsive-16by9 mt-3">
          <iframe class="embed-responsive-item" src="<?php echo htmlspecialchars($embed); ?>" allowfullscreen></iframe>
        </div>
      <?php endif; ?>
    </div>

    <div class="text-center">
      <button type="submit" class="btn btn-danger" id="requpdate" name="requpdate">Update</button>
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
