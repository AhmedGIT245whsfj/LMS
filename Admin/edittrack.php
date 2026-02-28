<?php
if (!isset($_SESSION)) { session_start(); }
define('TITLE', 'Edit Track');
define('PAGE', 'tracks');

include('../dbConnection.php');
include('./adminInclude/header.php');

if (!isset($_SESSION['is_admin_login'])) {
  echo "<script> location.href='../index.php'; </script>";
  exit;
}

$msg = null;
$row = null;

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

function delete_track_image_if_local(?string $path): void {
  if (!$path) return;
  if (strpos($path, '/media/track/') !== 0) return;
  $fs = realpath(__DIR__ . '/..' . $path);
  if ($fs && is_file($fs)) {
    @unlink($fs);
  }
}

if (isset($_POST['view']) && isset($_POST['id'])) {
  $id = (int)$_POST['id'];
  $stmt = $conn->prepare("SELECT track_id, track_name, track_desc, track_img FROM tracks WHERE track_id = ? LIMIT 1");
  if ($stmt) {
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
  }
}

if (isset($_POST['requpdate'])) {
  $id = (int)($_POST['track_id'] ?? 0);
  $name = trim((string)($_POST['track_name'] ?? ''));
  $desc = trim((string)($_POST['track_desc'] ?? ''));

  if ($id <= 0 || $name === '' || $desc === '') {
    $msg = '<div class="alert alert-warning col-sm-8 mt-2" role="alert">Fill all fields</div>';
  } else {
    $currentImg = null;
    $stmt0 = $conn->prepare("SELECT track_img FROM tracks WHERE track_id = ? LIMIT 1");
    if ($stmt0) {
      $stmt0->bind_param("i", $id);
      $stmt0->execute();
      $r0 = $stmt0->get_result();
      $d0 = $r0 ? $r0->fetch_assoc() : null;
      $currentImg = $d0 ? (string)($d0['track_img'] ?? '') : null;
      $stmt0->close();
    }

    $imgErr = '';
    $newImg = save_track_image($_FILES['track_img'] ?? [], $imgErr);
    if ($imgErr !== '') {
      $msg = '<div class="alert alert-warning col-sm-8 mt-2" role="alert">'.htmlspecialchars($imgErr).'</div>';
    } else {
      if ($newImg !== null) {
        $stmt = $conn->prepare("UPDATE tracks SET track_name = ?, track_desc = ?, track_img = ? WHERE track_id = ? LIMIT 1");
        if ($stmt) {
          $stmt->bind_param("sssi", $name, $desc, $newImg, $id);
          if ($stmt->execute()) {
            delete_track_image_if_local($currentImg);
            $msg = '<div class="alert alert-success col-sm-8 mt-2" role="alert">Updated successfully</div>';
          } else {
            $msg = '<div class="alert alert-danger col-sm-8 mt-2" role="alert">Unable to update</div>';
          }
          $stmt->close();
        } else {
          $msg = '<div class="alert alert-danger col-sm-8 mt-2" role="alert">Unable to update</div>';
        }
      } else {
        $stmt = $conn->prepare("UPDATE tracks SET track_name = ?, track_desc = ? WHERE track_id = ? LIMIT 1");
        if ($stmt) {
          $stmt->bind_param("ssi", $name, $desc, $id);
          if ($stmt->execute()) {
            $msg = '<div class="alert alert-success col-sm-8 mt-2" role="alert">Updated successfully</div>';
          } else {
            $msg = '<div class="alert alert-danger col-sm-8 mt-2" role="alert">Unable to update</div>';
          }
          $stmt->close();
        } else {
          $msg = '<div class="alert alert-danger col-sm-8 mt-2" role="alert">Unable to update</div>';
        }
      }
    }

    $stmt2 = $conn->prepare("SELECT track_id, track_name, track_desc, track_img FROM tracks WHERE track_id = ? LIMIT 1");
    if ($stmt2) {
      $stmt2->bind_param("i", $id);
      $stmt2->execute();
      $res2 = $stmt2->get_result();
      $row = $res2 ? $res2->fetch_assoc() : null;
      $stmt2->close();
    }
  }
}

if (!$row) {
  echo '<div class="col-sm-9 mt-5"><div class="alert alert-dark">Track not found.</div></div></div></div>';
  include('./adminInclude/footer.php');
  exit;
}

$img = (string)($row['track_img'] ?? '');
?>
<div class="col-sm-7 mt-5 mx-3 jumbotron">
  <h3 class="text-center">Update Track Details</h3>

  <form action="" method="POST" enctype="multipart/form-data">
    <div class="form-group">
      <label for="track_id">Track ID</label>
      <input type="text" class="form-control" id="track_id" name="track_id" value="<?php echo (int)$row['track_id']; ?>" readonly>
    </div>

    <div class="form-group">
      <label for="track_name">Track Name</label>
      <input type="text" class="form-control" id="track_name" name="track_name" value="<?php echo htmlspecialchars($row['track_name']); ?>" required>
    </div>

    <div class="form-group">
      <label for="track_desc">Track Description</label>
      <textarea class="form-control" id="track_desc" name="track_desc" rows="3" required><?php echo htmlspecialchars($row['track_desc']); ?></textarea>
    </div>

    <div class="form-group">
      <label>Current Image</label><br>
      <?php if ($img !== ''): ?>
        <img src="<?php echo htmlspecialchars($img); ?>" alt="Track" style="width:140px;height:90px;object-fit:cover;border:1px solid #ddd;border-radius:6px;">
      <?php else: ?>
        <div class="text-muted">No image</div>
      <?php endif; ?>
    </div>

    <div class="form-group">
      <label for="track_img">Change Image (optional)</label>
      <input type="file" class="form-control-file" id="track_img" name="track_img" accept="image/*">
      <small class="form-text text-muted">JPG/PNG/WEBP up to 2MB.</small>
    </div>

    <div class="text-center">
      <button type="submit" class="btn btn-danger" id="requpdate" name="requpdate">Update</button>
      <a href="tracks.php" class="btn btn-secondary">Close</a>
    </div>

    <?php if ($msg) { echo $msg; } ?>
  </form>
</div>
</div>
</div>
<?php include('./adminInclude/footer.php'); ?>
