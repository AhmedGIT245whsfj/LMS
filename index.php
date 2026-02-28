<?php
include('./dbConnection.php');
include('./mainInclude/header.php');
?>

<!-- Start Video Background-->
<div class="container-fluid remove-vid-marg">
  <div class="vid-parent">
    <video playsinline autoplay muted loop>
      <source src="video/banvid.mp4" />
    </video>
    <div class="vid-overlay"></div>
  </div>
  <div class="vid-content">
    <h1 class="my-content">Welcome to ITVERSE</h1>
    <small class="my-content">Learn and Implement</small><br />
    <?php
      if (!isset($_SESSION['is_login'])) {
        echo '<a class="btn btn-danger mt-3" href="loginorsignup.php">Get Started</a>';
      } else {
        echo '<a class="btn btn-primary mt-3" href="/Student/myprofile.php">My Profile</a>';
      }
    ?>
  </div>
</div>
<!-- End Video Background -->

<div class="container-fluid bg-danger txt-banner">
  <div class="row bottom-banner">
    <div class="col-sm">
      <h5><i class="fas fa-book-open mr-3"></i> 100+ Online Courses</h5>
    </div>
    <div class="col-sm">
      <h5><i class="fas fa-users mr-3"></i> Expert Instructors</h5>
    </div>
    <div class="col-sm">
      <h5><i class="fas fa-keyboard mr-3"></i> Lifetime Access</h5>
    </div>
    <div class="col-sm">
      <h5><i class="fas fa-rupee-sign mr-3"></i> Money Back Guarantee*</h5>
    </div>
  </div>
</div>

<?php
$topCourses = [];
$sqlTop = "
  SELECT c.course_id, c.course_name, c.course_desc, c.course_img, c.course_price, c.course_original_price, c.track_id, t.track_name
  FROM course c
  JOIN (
    SELECT track_id, MIN(course_id) AS course_id
    FROM course
    WHERE track_id IS NOT NULL
    GROUP BY track_id
  ) x ON x.course_id = c.course_id
  JOIN tracks t ON t.track_id = c.track_id
  ORDER BY t.track_id ASC
";
$resTop = $conn->query($sqlTop);
if ($resTop && $resTop->num_rows > 0) {
  while ($r = $resTop->fetch_assoc()) {
    $topCourses[] = $r;
  }
}

$tracks = [];
$resTracks = $conn->query("SELECT track_id, track_name, track_desc, track_img FROM tracks ORDER BY track_id ASC");
if ($resTracks && $resTracks->num_rows > 0) {
  while ($r = $resTracks->fetch_assoc()) {
    $tracks[] = $r;
  }
}
?>

<div class="container mt-5">
  <h1 class="text-center">Top Courses</h1>
  <p class="text-center text-muted mb-4">One featured course from each track</p>

  <div class="row mt-4">
    <?php if (count($topCourses) > 0): ?>
      <?php foreach ($topCourses as $row): ?>
        <?php
          $course_id = (int)$row['course_id'];
          $img = str_replace('..', '.', (string)$row['course_img']);
        ?>
        <div class="col-sm-6 col-lg-3 mb-4">
          <a href="coursedetails.php?course_id=<?php echo $course_id; ?>" class="btn" style="text-align:left; padding:0px; width:100%;">
            <div class="card h-100">
              <img src="<?php echo htmlspecialchars($img); ?>" class="card-img-top" alt="course" />
              <div class="card-body">
                <small class="text-muted"><?php echo htmlspecialchars($row['track_name']); ?></small>
                <h5 class="card-title mt-2"><?php echo htmlspecialchars($row['course_name']); ?></h5>
                <p class="card-text"><?php echo htmlspecialchars($row['course_desc']); ?></p>
              </div>
              <div class="card-footer">
                <p class="card-text d-inline">
                  Price:
                  <small><del>&#8377 <?php echo (int)$row['course_original_price']; ?></del></small>
                  <span class="font-weight-bolder">&#8377 <?php echo (int)$row['course_price']; ?></span>
                </p>
                <a class="btn btn-primary text-white font-weight-bolder float-right" href="coursedetails.php?course_id=<?php echo $course_id; ?>">Enroll</a>
              </div>
            </div>
          </a>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="col-12">
        <div class="alert alert-dark">No courses found.</div>
      </div>
    <?php endif; ?>
  </div>

  <div class="text-center m-2">
    <a class="btn btn-danger btn-sm" href="courses.php">Browse Tracks</a>
  </div>
</div>

<div class="container mt-5">
  <h1 class="text-center">Tracks</h1>
  <p class="text-center text-muted mb-4">Choose a track to view its courses</p>

  <div class="row mt-4">
    <?php if (count($tracks) > 0): ?>
      <?php foreach ($tracks as $t): ?>
        <div class="col-sm-6 col-lg-3 mb-4">
          <a href="courses.php?track_id=<?php echo (int)$t['track_id']; ?>" class="btn" style="text-align:left; padding:0px; width:100%;">
            <div class="card h-100">
<?php
$timg = '';
if (isset($t['track_img'])) { $timg = trim((string)$t['track_img']); }
?>
<?php if ($timg !== ''): ?>
  <img src="<?php echo htmlspecialchars($timg); ?>" class="card-img-top" style="height:180px;object-fit:cover;" alt="Track">
<?php else: ?>
  <div style="height:180px;background:#f3f4f6;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:center;color:#6b7280;font-size:14px;">No image</div>
<?php endif; ?>

<div class="card-body">
                <h5 class="card-title"><?php echo htmlspecialchars($t['track_name']); ?></h5>
                <p class="card-text"><?php echo htmlspecialchars($t['track_desc']); ?></p>
              </div>
              <div class="card-footer">
                <span class="text-primary font-weight-bolder">View Courses</span>
              </div>
            </div>
          </a>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="col-12">
        <div class="alert alert-dark">No tracks found.</div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include('./contact.php'); ?>

<div class="container-fluid mt-5" style="background-color: #4B7289" id="Feedback">
  <h1 class="text-center testyheading p-4"> Student's Feedback </h1>
  <div class="row">
    <div class="col-md-12">
      <div id="testimonial-slider" class="owl-carousel">
        <?php
          $sql = "SELECT s.stu_name, s.stu_occ, s.stu_img, f.f_content FROM student AS s JOIN feedback AS f ON s.stu_id = f.stu_id";
          $result = $conn->query($sql);
          if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
              $s_img = (string)$row['stu_img'];
              $n_img = str_replace('../', '', $s_img);
        ?>
          <div class="testimonial">
            <p class="description"><?php echo htmlspecialchars($row['f_content']); ?></p>
            <div class="pic">
              <img src="<?php echo htmlspecialchars($n_img); ?>" alt=""/>
            </div>
            <div class="testimonial-prof">
              <h4><?php echo htmlspecialchars($row['stu_name']); ?></h4>
              <small><?php echo htmlspecialchars($row['stu_occ']); ?></small>
            </div>
          </div>
        <?php
            }
          }
        ?>
      </div>
    </div>
  </div>
</div>

<div class="container-fluid bg-danger">
  <div class="row text-white text-center p-1">
    <div class="col-sm">
      <a class="text-white social-hover" href="#"><i class="fab fa-facebook-f"></i> Facebook</a>
    </div>
    <div class="col-sm">
      <a class="text-white social-hover" href="#"><i class="fab fa-twitter"></i> Twitter</a>
    </div>
    <div class="col-sm">
      <a class="text-white social-hover" href="#"><i class="fab fa-whatsapp"></i> WhatsApp</a>
    </div>
    <div class="col-sm">
      <a class="text-white social-hover" href="#"><i class="fab fa-instagram"></i> Instagram</a>
    </div>
  </div>
</div>

<div class="container-fluid p-4" style="background-color:#E9ECEF">
  <div class="container" style="background-color:#E9ECEF">
    <div class="row text-center">
      <div class="col-sm">
        <h5>About Us</h5>
        <p>ITVERSE provides universal access to the world’s best education, partnering with top universities and organizations to offer courses online.</p>
      </div>
      <div class="col-sm">
        <h5>Category</h5>
        <a class="text-dark" href="#">Cybersecurity</a><br />
        <a class="text-dark" href="#">Artificial Intelligence</a><br />
        <a class="text-dark" href="#">DevOps</a><br />
        <a class="text-dark" href="#">Operating Systems</a><br />
        <a class="text-dark" href="#">AWS Cloud</a><br />
      </div>
      <div class="col-sm">
        <h5>Contact Us</h5>
        <p>ITVERSE Pvt Ltd <br> Near Police Camp II <br> Bokaro, Jharkhand <br> Ph. 000000000 </p>
      </div>
    </div>
  </div>
</div>

<?php include('./mainInclude/footer.php'); ?>
