<?php
if(!isset($_SESSION)){ session_start(); }
include('../dbConnection.php');

if(!isset($_SESSION['is_login'])){
  header("Location: ../index.php");
  exit;
}

$stuEmail = $_SESSION['stuLogEmail'];

/* Redirect to success page after purchase */
if(isset($_GET['added']) && isset($_GET['course_id'])){
  header("Location: ../purchase_success.php");
  exit;
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
?>
<!DOCTYPE html>
<html>
<head>
<title>My Courses</title>
<link rel="stylesheet" href="../css/bootstrap.min.css">
<style>
body { background:#f4f6f9; }
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
.course-info h5{ margin-bottom:6px; }
</style>
</head>

<body>

<div class="container mt-5">
<h2 class="mb-4">My Courses</h2>

<?php if($result->num_rows == 0): ?>
  <div class="alert alert-info">You have not enrolled in any courses yet.</div>
<?php endif; ?>

<?php while($row = $result->fetch_assoc()): 
  $imgPath = "../" . $row['course_img'];
  if(!file_exists($imgPath) || empty($row['course_img'])){
    $imgPath = "../image/courseimg/php.jpg";
  }
?>
  <div class="course-card">
    <img src="<?php echo $imgPath; ?>" class="course-img">
    <div class="course-info">
      <h5><?php echo htmlspecialchars($row['course_name']); ?></h5>
      <p class="text-muted mb-1"><?php echo htmlspecialchars($row['course_desc']); ?></p>
      <strong>₹ <?php echo $row['course_price']; ?></strong>
    </div>
    <a href="watchcourse.php?course_id=<?php echo $row['course_id']; ?>" class="btn btn-primary btn-sm">Open</a>
  </div>
<?php endwhile; ?>

</div>
</body>
</html>
