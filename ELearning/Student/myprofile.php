<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../dbConnection.php';

if (empty($_SESSION['is_login']) || empty($_SESSION['stuLogEmail'])) {
    header("Location: /loginorsignup.php");
    exit;
}

$stuEmail = trim((string)($_SESSION['stu_email'] ?? $_SESSION['stuLogEmail'] ?? ''));

$st = $conn->prepare("SELECT stu_id, stu_name, stu_email, stu_occ, stu_img FROM student WHERE stu_email = ? LIMIT 1");
$st->bind_param("s", $stuEmail);
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

$stuId   = (int)($row['stu_id'] ?? 0);
$stuName = trim((string)($row['stu_name'] ?? 'Student'));
$stuMail = trim((string)($row['stu_email'] ?? ''));
$stuOcc  = trim((string)($row['stu_occ'] ?? ''));
$stuImg  = trim((string)($row['stu_img'] ?? ''));

$avatar = $stuImg;
if ($avatar !== '') {
    $avatar = str_replace('../', '/', $avatar);
    if (!preg_match('#^https?://#i', $avatar) && strpos($avatar, '/') !== 0) {
        $avatar = '/' . ltrim($avatar, '/');
    }
} else {
    $avatar = 'https://ui-avatars.com/api/?name=' . urlencode($stuName) . '&background=0b5ed7&color=fff&size=240';
}

include_once __DIR__ . '/stuInclude/header.php';
?>
<style>
.itv-profile-page{
  background:#f4f7fb;
  min-height:calc(100vh - 110px);
  padding:30px 0 50px;
}
.itv-profile-card{
  background:#fff;
  border:0;
  border-radius:24px;
  box-shadow:0 12px 35px rgba(15,23,42,.08);
  overflow:hidden;
}
.itv-profile-cover{
  height:140px;
  background:linear-gradient(135deg,#0f172a 0%,#0b5ed7 55%,#38bdf8 100%);
}
.itv-profile-main{
  padding:0 28px 28px;
  margin-top:-58px;
}
.itv-profile-head{
  display:flex;
  align-items:flex-end;
  justify-content:space-between;
  gap:20px;
  flex-wrap:wrap;
}
.itv-profile-user{
  display:flex;
  align-items:center;
  gap:18px;
  flex-wrap:wrap;
}
.itv-profile-avatar{
  width:116px;
  height:116px;
  border-radius:50%;
  object-fit:cover;
  background:#fff;
  border:5px solid #fff;
  box-shadow:0 10px 25px rgba(0,0,0,.18);
}
.itv-profile-name{
  font-size:30px;
  font-weight:800;
  color:#0f172a;
  line-height:1.1;
  margin-bottom:6px;
}
.itv-profile-email{
  color:#64748b;
  font-size:15px;
}
.itv-badge{
  display:inline-block;
  margin-top:10px;
  padding:7px 12px;
  border-radius:999px;
  font-size:12px;
  font-weight:700;
  text-transform:capitalize;
  background:#e0f2fe;
  color:#0369a1;
}
.itv-profile-actions .btn{
  border-radius:12px;
  padding:10px 16px;
  font-weight:700;
}
.itv-info-grid{
  margin-top:28px;
}
.itv-info-box{
  background:#f8fafc;
  border:1px solid #e2e8f0;
  border-radius:18px;
  padding:18px;
  height:100%;
}
.itv-info-label{
  color:#64748b;
  font-size:13px;
  margin-bottom:8px;
}
.itv-info-value{
  color:#0f172a;
  font-size:18px;
  font-weight:700;
  word-break:break-word;
}
.itv-status{
  margin-top:24px;
  background:#fff;
  border-radius:20px;
  box-shadow:0 10px 30px rgba(15,23,42,.06);
  padding:22px;
}
.itv-status-title{
  font-size:18px;
  font-weight:800;
  color:#0f172a;
  margin-bottom:14px;
}
.itv-progress{
  height:10px;
  background:#e2e8f0;
  border-radius:999px;
  overflow:hidden;
}
.itv-progress > span{
  display:block;
  height:100%;
  width:78%;
  background:linear-gradient(90deg,#2563eb,#38bdf8);
  border-radius:999px;
}
.itv-status-text{
  margin-top:14px;
  color:#64748b;
  line-height:1.8;
}
@media (max-width: 767px){
  .itv-profile-main{
    padding:0 18px 20px;
  }
  .itv-profile-name{
    font-size:24px;
  }
  .itv-profile-avatar{
    width:94px;
    height:94px;
  }
}
</style>

<div class="container-fluid itv-profile-page">
  <div class="container">
    <div class="row">
      <div class="col-lg-3 mb-4">
        <?php @include_once __DIR__ . '/stuInclude/sidebar.php'; ?>
      </div>

      <div class="col-lg-9">
        <div class="itv-profile-card">
          <div class="itv-profile-cover"></div>

          <div class="itv-profile-main">
            <div class="itv-profile-head">
              <div class="itv-profile-user">
                <img
                  src="<?php echo htmlspecialchars($avatar, ENT_QUOTES); ?>"
                  alt="student"
                  class="itv-profile-avatar"
                  onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($stuName); ?>&background=0b5ed7&color=fff&size=240';"
                >
                <div>
                  <div class="itv-profile-name"><?php echo htmlspecialchars($stuName, ENT_QUOTES); ?></div>
                  <div class="itv-profile-email"><?php echo htmlspecialchars($stuMail, ENT_QUOTES); ?></div>
                  <span class="itv-badge"><?php echo htmlspecialchars($stuOcc !== '' ? $stuOcc : 'student', ENT_QUOTES); ?></span>
                </div>
              </div>

              <div class="itv-profile-actions">
                <a href="/Student/studentProfile.php" class="btn btn-primary mr-2">Edit Profile</a>
                <a href="/Student/studentChangePass.php" class="btn btn-outline-secondary">Change Password</a>
              </div>
            </div>

            <div class="row itv-info-grid">
              <div class="col-md-4 mb-3">
                <div class="itv-info-box">
                  <div class="itv-info-label">Student ID</div>
                  <div class="itv-info-value"><?php echo $stuId; ?></div>
                </div>
              </div>

              <div class="col-md-4 mb-3">
                <div class="itv-info-box">
                  <div class="itv-info-label">Name</div>
                  <div class="itv-info-value"><?php echo htmlspecialchars($stuName, ENT_QUOTES); ?></div>
                </div>
              </div>

              <div class="col-md-4 mb-3">
                <div class="itv-info-box">
                  <div class="itv-info-label">Email</div>
                  <div class="itv-info-value"><?php echo htmlspecialchars($stuMail, ENT_QUOTES); ?></div>
                </div>
              </div>

              <div class="col-md-4 mb-3">
                <div class="itv-info-box">
                  <div class="itv-info-label">Occupation / Level</div>
                  <div class="itv-info-value"><?php echo htmlspecialchars($stuOcc !== '' ? $stuOcc : 'Student', ENT_QUOTES); ?></div>
                </div>
              </div>

              <div class="col-md-8 mb-3">
                <div class="itv-info-box">
                  <div class="itv-info-label">Account Status</div>
                  <div class="itv-info-value">Active</div>
                </div>
              </div>
            </div>

            <div class="itv-status">
              <div class="itv-status-title">Profile Status</div>
              <div class="itv-progress"><span></span></div>
              <div class="itv-status-text">
                Your profile is in good shape. Keep your image and details updated to get a cleaner learning experience and better course recommendations.
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include_once __DIR__ . '/stuInclude/footer.php'; ?>
