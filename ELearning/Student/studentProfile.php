<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../dbConnection.php';

if (empty($_SESSION['is_login']) || empty($_SESSION['stuLogEmail'])) {
    header("Location: /loginorsignup.php");
    exit;
}

$stuEmailSession = trim((string)$_SESSION['stuLogEmail']);
$msg = '';

$st = $conn->prepare("SELECT stu_id, stu_name, stu_email, stu_pass, stu_occ, stu_img FROM student WHERE stu_email = ? LIMIT 1");
$st->bind_param("s", $stuEmailSession);
$st->execute();
$res = $st->get_result();
$row = $res ? $res->fetch_assoc() : null;
$st->close();

if (!$row) {
    include_once __DIR__ . '/stuInclude/header.php';
    echo '<div class="container mt-5"><div class="alert alert-danger">Student record not found.</div></div>';
    include_once __DIR__ . '/stuInclude/footer.php';
    exit;
}

$stuId   = (int)$row['stu_id'];
$stuName = (string)$row['stu_name'];
$stuMail = (string)$row['stu_email'];
$stuPass = (string)$row['stu_pass'];
$stuOcc  = (string)$row['stu_occ'];
$stuImg  = (string)$row['stu_img'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['updateStuName'])) {
    $newName = trim((string)($_POST['stuName'] ?? ''));
    $newMail = trim((string)($_POST['stuEmail'] ?? ''));
    $newOcc  = trim((string)($_POST['stuOcc'] ?? ''));

    if ($newName === '' || $newMail === '' || $newOcc === '') {
        $msg = '<div class="alert alert-warning">All fields are required.</div>';
    } elseif (!filter_var($newMail, FILTER_VALIDATE_EMAIL)) {
        $msg = '<div class="alert alert-warning">Invalid email format.</div>';
    } else {
        $finalImgForDb = $stuImg;

        if (isset($_FILES['stuImg']) && !empty($_FILES['stuImg']['name'])) {
            $uploadDirFs = '/var/www/html/image/stu/';
            $uploadDirDb = '../image/stu/';

            if (!is_dir($uploadDirFs)) {
                @mkdir($uploadDirFs, 0777, true);
            }

            $tmp  = $_FILES['stuImg']['tmp_name'];
            $name = $_FILES['stuImg']['name'];
            $err  = (int)($_FILES['stuImg']['error'] ?? 0);

            if ($err === 0 && is_uploaded_file($tmp)) {
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $allowed = ['jpg','jpeg','png','webp','gif'];

                if (in_array($ext, $allowed, true)) {
                    $fileName = 'stu_' . $stuId . '_' . time() . '.' . $ext;
                    $targetFs = $uploadDirFs . $fileName;

                    if (@move_uploaded_file($tmp, $targetFs)) {
                        @chmod($targetFs, 0777);
                        $finalImgForDb = $uploadDirDb . $fileName;
                    } else {
                        $msg = '<div class="alert alert-danger">Image upload failed. Folder permission issue.</div>';
                    }
                } else {
                    $msg = '<div class="alert alert-warning">Allowed image types: jpg, jpeg, png, webp, gif.</div>';
                }
            } else {
                $msg = '<div class="alert alert-danger">Image upload failed.</div>';
            }
        }

        if ($msg === '') {
            $chk = $conn->prepare("SELECT stu_id FROM student WHERE stu_email = ? AND stu_id <> ? LIMIT 1");
            $chk->bind_param("si", $newMail, $stuId);
            $chk->execute();
            $chkRes = $chk->get_result();
            $exists = $chkRes && $chkRes->num_rows > 0;
            $chk->close();

            if ($exists) {
                $msg = '<div class="alert alert-warning">This email is already used by another account.</div>';
            } else {
                $up = $conn->prepare("UPDATE student SET stu_name = ?, stu_email = ?, stu_occ = ?, stu_img = ? WHERE stu_id = ? LIMIT 1");
                $up->bind_param("ssssi", $newName, $newMail, $newOcc, $finalImgForDb, $stuId);

                if ($up->execute()) {
                    $_SESSION['stuLogEmail'] = $newMail;
                    $stuName = $newName;
                    $stuMail = $newMail;
                    $stuOcc  = $newOcc;
                    $stuImg  = $finalImgForDb;
                    $msg = '<div class="alert alert-success">Updated successfully.</div>';
                } else {
                    $msg = '<div class="alert alert-danger">Update failed.</div>';
                }
                $up->close();
            }
        }
    }
}

$imgView = trim((string)$stuImg);
if ($imgView !== '') {
    $imgView = str_replace('../', '/', $imgView);
    if (!preg_match('#^https?://#i', $imgView) && strpos($imgView, '/') !== 0) {
        $imgView = '/' . ltrim($imgView, '/');
    }
} else {
    $imgView = 'https://ui-avatars.com/api/?name=' . urlencode($stuName) . '&background=0b5ed7&color=fff&size=200';
}

include_once __DIR__ . '/stuInclude/header.php';
?>
<style>
.student-edit-page{
  background:#f4f7fb;
  min-height:calc(100vh - 120px);
  padding:30px 0 50px;
}
.edit-card{
  background:#fff;
  border:0;
  border-radius:22px;
  box-shadow:0 10px 30px rgba(20,37,63,.08);
  padding:28px;
}
.edit-title{
  font-size:28px;
  font-weight:700;
  color:#0f172a;
  margin-bottom:20px;
}
.avatar-preview{
  width:92px;
  height:92px;
  border-radius:50%;
  object-fit:cover;
  border:4px solid #fff;
  box-shadow:0 8px 18px rgba(0,0,0,.12);
  background:#fff;
}
.form-control{
  border-radius:12px;
  min-height:46px;
}
.btn{
  border-radius:12px;
}
</style>

<div class="container-fluid student-edit-page">
  <div class="container">
    <div class="row">
      <div class="col-lg-3 mb-4">
        <?php @include_once __DIR__ . '/stuInclude/sidebar.php'; ?>
      </div>

      <div class="col-lg-9">
        <div class="edit-card">
          <div class="d-flex align-items-center justify-content-between flex-wrap mb-4" style="gap:14px;">
            <div>
              <div class="edit-title mb-1">Edit Profile</div>
              <div class="text-muted">Update your account details and profile image.</div>
            </div>
            <img
              src="<?php echo htmlspecialchars($imgView, ENT_QUOTES); ?>"
              class="avatar-preview"
              alt="student"
              onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($stuName); ?>&background=0b5ed7&color=fff&size=200';"
            >
          </div>

          <?php echo $msg; ?>

          <form action="" method="POST" enctype="multipart/form-data">
            <div class="form-group">
              <label>Student ID</label>
              <input type="text" class="form-control" value="<?php echo $stuId; ?>" readonly>
            </div>

            <div class="form-group">
              <label>Email</label>
              <input type="email" class="form-control" name="stuEmail" value="<?php echo htmlspecialchars($stuMail, ENT_QUOTES); ?>">
            </div>

            <div class="form-group">
              <label>Name</label>
              <input type="text" class="form-control" name="stuName" value="<?php echo htmlspecialchars($stuName, ENT_QUOTES); ?>">
            </div>

            <div class="form-group">
              <label>Occupation</label>
              <input type="text" class="form-control" name="stuOcc" value="<?php echo htmlspecialchars($stuOcc, ENT_QUOTES); ?>">
            </div>

            <div class="form-group">
              <label>Upload Image</label>
              <input type="file" class="form-control-file" name="stuImg" accept=".jpg,.jpeg,.png,.webp,.gif">
            </div>

            <button type="submit" class="btn btn-primary" name="updateStuName">Update</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include_once __DIR__ . '/stuInclude/footer.php'; ?>
