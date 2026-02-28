<?php
if (!isset($_SESSION)) { session_start(); }
include('../dbConnection.php');

if (!isset($_SESSION['is_login'])) {
  header("Location: ../index.php");
  exit;
}

$stuEmail = $_SESSION['stuLogEmail'];

/* Get student image */
$stuSql = "SELECT stu_img FROM student WHERE stu_email = ?";
$stuStmt = $conn->prepare($stuSql);
$stuStmt->bind_param("s", $stuEmail);
$stuStmt->execute();
$stuResult = $stuStmt->get_result();
$stuData = $stuResult->fetch_assoc();

$profileImg = "../image/stu/default.jpg";
if (!empty($stuData) && !empty($stuData['stu_img'])) {
  $profileImg = $stuData['stu_img'];
}

/* Fetch purchased courses */
$sql = "
SELECT DISTINCT c.course_id, c.course_name, c.course_desc, c.course_price, c.course_img
FROM courseorder o
JOIN course c ON c.course_id = o.course_id
WHERE o.stu_email = ?
ORDER BY o.order_date DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $stuEmail);
$stmt->execute();
$result = $stmt->get_result();

function course_image_src(array $row): string {
  $raw = isset($row['course_img']) ? trim((string)$row['course_img']) : '';
  $raw = str_replace('\\', '/', $raw);
  $file = $raw !== '' ? basename($raw) : '';

  $defaultWeb = "../image/courseimg/php.jpg";
  if ($file === '') {
    return $defaultWeb;
  }

  $fsPath = __DIR__ . "/../image/courseimg/" . $file;
  if (!is_file($fsPath)) {
    return $defaultWeb;
  }

  return "../image/courseimg/" . $file;
}
?>
<!DOCTYPE html>
<html>
<head>
<title>My Courses</title>
<link rel="stylesheet" href="../css/bootstrap.min.css">
<style>
body { background:#f4f6f9; }

.top-bar{
  background:#0d6efd;
  color:#fff;
  padding:10px 20px;
  display:flex;
  justify-content:space-between;
  align-items:center;
}

.top-bar a{
  color:#fff;
  text-decoration:none;
  font-weight:bold;
  font-size:20px;
}

.profile-section{
  display:flex;
  align-items:center;
  gap:15px;
}

.profile-section img{
  width:40px;
  height:40px;
  border-radius:50%;
  object-fit:cover;
  border:2px solid #fff;
}

.course-card{
  background:#fff;
  border-radius:12px;
  padding:15px;
  margin-bottom:20px;
  display:flex;
  align-items:center;
  justify-content:space-between;
  box-shadow:0 2px 8px rgba(0,0,0,0.05);
}

.course-img{
  width:120px;
  height:80px;
  object-fit:cover;
  border-radius:8px;
}

.course-info{ flex:1; margin-left:20px; }
</style>
</head>

<body>

<div class="top-bar">
  <a href="../index.php">ITVERSE</a>
  <div class="profile-section">
    <img src="<?php echo htmlspecialchars($profileImg, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile">
    <a href="myprofile.php" class="btn btn-warning btn-sm" style="font-weight:700;">My Profile</a>
  </div>
</div>

<div class="container mt-4">
<h2 class="mb-4">My Courses</h2>

<?php if ($result->num_rows == 0): ?>
  <div class="alert alert-info">You have not enrolled in any courses yet.</div>
<?php endif; ?>

<?php while ($row = $result->fetch_assoc()):
  $imgPath = course_image_src($row);
?>
  <div class="course-card">
    <img src="<?php echo htmlspecialchars($imgPath, ENT_QUOTES, 'UTF-8'); ?>" class="course-img" alt="Course">
    <div class="course-info">
      <h5><?php echo htmlspecialchars((string)$row['course_name'], ENT_QUOTES, 'UTF-8'); ?></h5>
      <p class="text-muted mb-1"><?php echo htmlspecialchars((string)$row['course_desc'], ENT_QUOTES, 'UTF-8'); ?></p>
      <strong>₹ <?php echo htmlspecialchars((string)$row['course_price'], ENT_QUOTES, 'UTF-8'); ?></strong>
    </div>
    <a href="watchcourse.php?course_id=<?php echo urlencode((string)$row['course_id']); ?>" class="btn btn-primary btn-sm">Open</a>
  </div>
<?php endwhile; ?>

</div>
</body>
</html>
<?php
$stuStmt->close();
$stmt->close();
?>
