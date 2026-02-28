<?php
if (!isset($_SESSION)) { session_start(); }
define('TITLE', 'Add Track');
define('PAGE', 'tracks');

include('../dbConnection.php');
include('./adminInclude/header.php');

if (!isset($_SESSION['is_admin_login'])) {
  echo "<script> location.href='../index.php'; </script>";
  exit;
}

$msg = null;

function save_track_image(array $file, string &$err): ?string {
  $err = '';
  if (!isset($file['tmp_name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
    return null;
  }
  if ($file['error'] !== UPLOAD_ERR_OK) {
    $err = 'Image upload failed';
    return null;
  }

  $maxBytes = 2 * 1024 * 1024;
  if ((int)$file['size'] > $maxBytes) {
    $err = 'Image is too large (max 2MB)';
    return null;
  }

  $tmp = (string)$file['tmp_name'];
  $info = @getimagesize($tmp);
  if ($info === false) {
    $err = 'Invalid image file';
    return null;
  }

  $mime = (string)($info['mime'] ?? '');
  $extMap = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
  ];
  if (!isset($extMap[$mime])) {
    $err = 'Only JPG, PNG, WEBP are allowed';
    return null;
  }

  $dirFs = realpath(__DIR__ . '/../media/track');
  if ($dirFs === false) {
    $err = 'Upload folder is missing';
    return null;
  }

  $base = 'track_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $extMap[$mime];
  $destFs = $dirFs . DIRECTORY_SEPARATOR . $base;

  if (!move_uploaded_file($tmp, $destFs)) {
    $err = 'Failed to save uploaded image';
    return null;
  }

  return '/media/track/' . $base;
}

if (isset($_POST['trackSubmitBtn'])) {
  $name = trim((string)($_POST['track_name'] ?? ''));
  $desc = trim((string)($_POST['track_desc'] ?? ''));

  if ($name === '' || $desc === '') {
    $msg = '<div class="alert alert-warning col-sm-8 mt-2" role="alert">Fill all fields</div>';
  } else {
    $imgErr = '';
    $imgPath = save_track_image($_FILES['track_img'] ?? [], $imgErr);
    if ($imgErr !== '') {
      $msg = '<div class="alert alert-warning col-sm-8 mt-2" role="alert">'.htmlspecialchars($imgErr).'</div>';
    } else {
      $stmt = $conn->prepare("INSERT INTO tracks (track_name, track_desc, track_img) VALUES (?, ?, ?)");
      if ($stmt) {
        $stmt->bind_param("sss", $name, $desc, $imgPath);
        if ($stmt->execute()) {
          $msg = '<div class="alert alert-success col-sm-8 mt-2" role="alert">Track added successfully</div>';
        } else {
          $msg = '<div class="alert alert-danger col-sm-8 mt-2" role="alert">Unable to add track</div>';
        }
        $stmt->close();
      } else {
        $msg = '<div class="alert alert-danger col-sm-8 mt-2" role="alert">Unable to add track</div>';
      }
    }
  }
}
?>
<div class="col-sm-7 mt-5 mx-3 jumbotron">
  <h3 class="text-center">Add New Track</h3>
  <form action="" method="POST" enctype="multipart/form-data">
    <div class="form-group">
      <label for="track_name">Track Name</label>
      <input type="text" class="form-control" id="track_name" name="track_name" required>
    </div>

    <div class="form-group">
      <label for="track_desc">Track Description</label>
      <textarea class="form-control" id="track_desc" name="track_desc" rows="3" required></textarea>
    </div>

    <div class="form-group">
      <label for="track_img">Track Image (optional)</label>
      <input type="file" class="form-control-file" id="track_img" name="track_img" accept="image/*">
      <small class="form-text text-muted">JPG/PNG/WEBP up to 2MB.</small>
    </div>

    <div class="text-center">
      <button type="submit" class="btn btn-danger" id="trackSubmitBtn" name="trackSubmitBtn">Submit</button>
      <a href="tracks.php" class="btn btn-secondary">Close</a>
    </div>
    <?php if ($msg) { echo $msg; } ?>
  </form>
</div>
</div>
</div>
<?php include('./adminInclude/footer.php'); ?>
