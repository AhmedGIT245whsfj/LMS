<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/../../dbConnection.php';

$stuEmail = trim((string)($_SESSION['stu_email'] ?? $_SESSION['stuLogEmail'] ?? ''));
$stuName  = 'Student';
$stuImg   = '';

if ($stuEmail !== '') {
    $st = $conn->prepare("SELECT stu_name, stu_img FROM student WHERE stu_email = ? LIMIT 1");
    if ($st) {
        $st->bind_param("s", $stuEmail);
        $st->execute();
        $res = $st->get_result();
        if ($res && ($row = $res->fetch_assoc())) {
            $stuName = trim((string)($row['stu_name'] ?? 'Student'));
            $stuImg  = trim((string)($row['stu_img'] ?? ''));
        }
        $st->close();
    }
}

if ($stuImg !== '') {
    $stuImg = str_replace('../', '/', $stuImg);
    if (!preg_match('#^https?://#i', $stuImg) && strpos($stuImg, '/') !== 0) {
        $stuImg = '/' . ltrim($stuImg, '/');
    }
} else {
    $stuImg = 'https://ui-avatars.com/api/?name=' . urlencode($stuName) . '&background=0b5ed7&color=fff&size=180';
}

$current = basename($_SERVER['PHP_SELF'] ?? '');
function itv_active($current, $pages) {
    return in_array($current, $pages, true) ? 'active' : '';
}
?>
<style>
.itv-side-wrap{
  background:#fff;
  border-radius:20px;
  box-shadow:0 10px 30px rgba(15,23,42,.08);
  overflow:hidden;
  border:1px solid #e9eef5;
}
.itv-side-head{
  background:linear-gradient(135deg,#0f172a 0%,#0b5ed7 55%,#38bdf8 100%);
  padding:24px 16px 18px;
  text-align:center;
}
.itv-side-avatar{
  width:90px;
  height:90px;
  border-radius:50%;
  object-fit:cover;
  border:4px solid #fff;
  background:#fff;
  box-shadow:0 8px 18px rgba(0,0,0,.18);
}
.itv-side-name{
  color:#fff;
  font-size:18px;
  font-weight:700;
  margin-top:12px;
  line-height:1.3;
  word-break:break-word;
}
.itv-side-sub{
  color:rgba(255,255,255,.82);
  font-size:13px;
  margin-top:4px;
}
.itv-side-links{
  padding:14px;
}
.itv-side-link{
  display:flex;
  align-items:center;
  gap:12px;
  padding:12px 14px;
  border-radius:14px;
  color:#0f172a;
  text-decoration:none !important;
  font-weight:600;
  margin-bottom:8px;
  transition:.18s ease;
}
.itv-side-link i{
  width:18px;
  text-align:center;
  color:#0b5ed7;
}
.itv-side-link:hover{
  background:#eff6ff;
  color:#0b5ed7;
}
.itv-side-link.active{
  background:#0b5ed7;
  color:#fff !important;
  box-shadow:0 8px 18px rgba(11,94,215,.22);
}
.itv-side-link.active i{
  color:#fff;
}
</style>

<div class="itv-side-wrap">
  <div class="itv-side-head">
    <img
      src="<?php echo htmlspecialchars($stuImg, ENT_QUOTES); ?>"
      alt="student"
      class="itv-side-avatar"
      onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($stuName); ?>&background=0b5ed7&color=fff&size=180';"
    >
    <div class="itv-side-name"><?php echo htmlspecialchars($stuName, ENT_QUOTES); ?></div>
    <div class="itv-side-sub">Student Panel</div>
  </div>

  <div class="itv-side-links">
    <a class="itv-side-link <?php echo itv_active($current, ['myprofile.php','studentProfile.php']); ?>" href="/Student/myprofile.php">
      <i class="fas fa-user"></i><span>Profile</span>
    </a>
    <a class="itv-side-link <?php echo itv_active($current, ['myCourse.php']); ?>" href="/Student/myCourse.php">
      <i class="fas fa-book-open"></i><span>My Courses</span>
    </a>
    <a class="itv-side-link <?php echo itv_active($current, ['stufeedback.php']); ?>" href="/Student/stufeedback.php">
      <i class="fas fa-comment-dots"></i><span>Feedback</span>
    </a>
    <a class="itv-side-link <?php echo itv_active($current, ['studentChangePass.php']); ?>" href="/Student/studentChangePass.php">
      <i class="fas fa-key"></i><span>Change Password</span>
    </a>
    <a class="itv-side-link" href="/Student/studentLogout.php">
      <i class="fas fa-sign-out-alt"></i><span>Logout</span>
    </a>
  </div>
</div>
