<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
include('./dbConnection.php');
include('./mainInclude/header.php');
?>

<!-- Start Hero -->
<div class="container-fluid remove-vid-marg hero-fallback">
  <div class="vid-parent desktop-video">
    <video playsinline autoplay muted loop>
      <source src="video/vecteezy_hacking-animation-footage-of-blue-coding-lines-typing-on-a_52103764.mp4" type="video/mp4" />
    </video>
    <div class="vid-overlay"></div>
  </div>

  <div class="vid-content">
    <h1 class="my-content">Welcome to ITVERSE</h1>
    <small class="my-content">Learn and Implement</small><br />
    <a class="btn btn-danger mt-3" href="loginorsignup.php">Get Started</a>
  </div>
</div>
<!-- End Hero -->

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
function itv_index_img(string $img): string {
  $img = trim($img);
  if ($img === '') return '';
  if (preg_match('#^(https?:)?/#i', $img)) return $img;
  if (strpos($img, '../') === 0 || strpos($img, './') === 0) return $img;
  return './' . ltrim($img, '/');
}

function itv_get_login_email(): string {
  $keys = ['stuLogEmail', 'stu_email', 'stuemail', 'email', 'student_email'];
  foreach ($keys as $k) {
    if (!empty($_SESSION[$k]) && is_string($_SESSION[$k])) {
      return trim($_SESSION[$k]);
    }
  }
  return '';
}

function itv_has_column(mysqli $conn, string $table, string $col): bool {
  $sql = "SELECT 1
          FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND COLUMN_NAME = ?
          LIMIT 1";
  $st = $conn->prepare($sql);
  if (!$st) return false;
  $st->bind_param("ss", $table, $col);
  $st->execute();
  $res = $st->get_result();
  $st->close();
  return (bool)($res && $res->num_rows > 0);
}

$isStudentLoggedIn = !empty($_SESSION['is_login']);
$loginEmail = itv_get_login_email();

$studentTrackId = null;
$studentLevel   = 'beginner';
$studentTrackName = '';

if ($isStudentLoggedIn && $loginEmail !== '') {
  $hasPref = itv_has_column($conn, 'student', 'preferred_track_id');
  $hasExp  = itv_has_column($conn, 'student', 'experience_level');

  if ($hasPref || $hasExp) {
    $fields = ["stu_email"];
    if ($hasPref) $fields[] = "preferred_track_id";
    if ($hasExp)  $fields[] = "experience_level";

    $sqlStu = "SELECT " . implode(", ", $fields) . " FROM student WHERE stu_email = ? LIMIT 1";
    $stStu = $conn->prepare($sqlStu);
    if ($stStu) {
      $stStu->bind_param("s", $loginEmail);
      $stStu->execute();
      $resStu = $stStu->get_result();
      if ($resStu && ($rowStu = $resStu->fetch_assoc())) {
        if ($hasPref && isset($rowStu['preferred_track_id']) && $rowStu['preferred_track_id'] !== null && $rowStu['preferred_track_id'] !== '') {
          $studentTrackId = (int)$rowStu['preferred_track_id'];
        }
        if ($hasExp && !empty($rowStu['experience_level'])) {
          $studentLevel = strtolower(trim((string)$rowStu['experience_level']));
        }
      }
      $stStu->close();
    }
  }

  if ($studentTrackId !== null) {
    $stTrack = $conn->prepare("SELECT track_name FROM track WHERE track_id = ? LIMIT 1");
    if ($stTrack) {
      $stTrack->bind_param("i", $studentTrackId);
      $stTrack->execute();
      $resTrack = $stTrack->get_result();
      if ($resTrack && ($rowTrack = $resTrack->fetch_assoc())) {
        $studentTrackName = (string)($rowTrack['track_name'] ?? '');
      }
      $stTrack->close();
    }
  }
}

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
  JOIN track t ON t.track_id = c.track_id
  ORDER BY t.track_id ASC
";
$resTop = $conn->query($sqlTop);
if ($resTop && $resTop->num_rows > 0) {
  while ($r = $resTop->fetch_assoc()) {
    $topCourses[] = $r;
  }
}

$recommendedCourses = [];
$recTitle = "Recommended for You";
$recSubtitle = "Best starting courses based on your selected track and level.";

if ($isStudentLoggedIn) {
  if ($studentTrackId !== null) {
    if ($studentLevel === 'experienced') {
      $sqlRec = "
        SELECT c.course_id, c.course_name, c.course_desc, c.course_img, c.course_price, c.course_original_price, c.track_id, t.track_name
        FROM course c
        JOIN track t ON t.track_id = c.track_id
        WHERE c.track_id = ?
        ORDER BY c.course_id DESC
        LIMIT 4
      ";
    } else {
      $sqlRec = "
        SELECT c.course_id, c.course_name, c.course_desc, c.course_img, c.course_price, c.course_original_price, c.track_id, t.track_name
        FROM course c
        JOIN track t ON t.track_id = c.track_id
        WHERE c.track_id = ?
        ORDER BY c.course_id ASC
        LIMIT 4
      ";
    }

    $stRec = $conn->prepare($sqlRec);
    if ($stRec) {
      $stRec->bind_param("i", $studentTrackId);
      $stRec->execute();
      $resRec = $stRec->get_result();
      if ($resRec) {
        while ($r = $resRec->fetch_assoc()) {
          $recommendedCourses[] = $r;
        }
      }
      $stRec->close();
    }

    if ($studentTrackName !== '') {
      if ($studentLevel === 'experienced') {
        $recSubtitle = "Advanced picks for your {$studentTrackName} track.";
      } else {
        $recSubtitle = "Starter picks for your {$studentTrackName} track.";
      }
    }
  }

  if (count($recommendedCourses) === 0) {
    $sqlFallback = "
      SELECT c.course_id, c.course_name, c.course_desc, c.course_img, c.course_price, c.course_original_price, c.track_id, t.track_name
      FROM course c
      LEFT JOIN track t ON t.track_id = c.track_id
      ORDER BY c.course_id ASC
      LIMIT 4
    ";
    $resFallback = $conn->query($sqlFallback);
    if ($resFallback) {
      while ($r = $resFallback->fetch_assoc()) {
        $recommendedCourses[] = $r;
      }
    }
    $recSubtitle = "General recommendations for your account until track preferences are available.";
  }
}

$track = [];
$resTracks = $conn->query("SELECT track_id, track_name, track_desc, track_img FROM track ORDER BY track_id ASC");
if ($resTracks && $resTracks->num_rows > 0) {
  while ($r = $resTracks->fetch_assoc()) {
    $track[] = $r;
  }
}
?>

<div class="container mt-5">
  <?php if ($isStudentLoggedIn): ?>
    <h1 class="text-center"><?php echo htmlspecialchars($recTitle); ?></h1>
    <p class="text-center text-muted mb-4"><?php echo htmlspecialchars($recSubtitle); ?></p>

    <div class="row mt-4">
      <?php if (count($recommendedCourses) > 0): ?>
        <?php foreach ($recommendedCourses as $row): ?>
          <?php
            $course_id = (int)$row['course_id'];
            $img = itv_index_img((string)($row['course_img'] ?? ''));
          ?>
          <div class="col-sm-6 col-lg-3 mb-4">
            <a href="coursedetails.php?course_id=<?php echo $course_id; ?>" class="btn" style="text-align:left; padding:0px; width:100%;">
              <div class="card h-100">
                <?php if ($img !== ''): ?>
                  <img src="<?php echo htmlspecialchars($img); ?>" class="card-img-top" alt="course" />
                <?php endif; ?>
                <div class="card-body">
                  <div class="mb-2">
                    <span class="badge badge-success">Recommended</span>
                  </div>
                  <small class="text-muted"><?php echo htmlspecialchars((string)($row['track_name'] ?? 'General')); ?></small>
                  <h5 class="card-title mt-2"><?php echo htmlspecialchars($row['course_name']); ?></h5>
                  <p class="card-text"><?php echo htmlspecialchars($row['course_desc']); ?></p>
                </div>
                <div class="card-footer">
                  <p class="card-text d-inline">
                    Price:
                    <small><del>&#8377 <?php echo (float)$row['course_original_price']; ?></del></small>
                    <span class="font-weight-bolder">&#8377 <?php echo (float)$row['course_price']; ?></span>
                  </p>
                  <a class="btn btn-primary text-white font-weight-bolder float-right" href="coursedetails.php?course_id=<?php echo $course_id; ?>">Enroll</a>
                </div>
              </div>
            </a>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="col-12">
          <div class="alert alert-dark">No recommendations found.</div>
        </div>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <h1 class="text-center">Top Courses</h1>
    <p class="text-center text-muted mb-4">One featured course from each track</p>

    <div class="row mt-4">
      <?php if (count($topCourses) > 0): ?>
        <?php foreach ($topCourses as $row): ?>
          <?php
            $course_id = (int)$row['course_id'];
            $img = itv_index_img((string)($row['course_img'] ?? ''));
          ?>
          <div class="col-sm-6 col-lg-3 mb-4">
            <a href="coursedetails.php?course_id=<?php echo $course_id; ?>" class="btn" style="text-align:left; padding:0px; width:100%;">
              <div class="card h-100">
                <?php if ($img !== ''): ?>
                  <img src="<?php echo htmlspecialchars($img); ?>" class="card-img-top" alt="course" />
                <?php endif; ?>
                <div class="card-body">
                  <small class="text-muted"><?php echo htmlspecialchars($row['track_name']); ?></small>
                  <h5 class="card-title mt-2"><?php echo htmlspecialchars($row['course_name']); ?></h5>
                  <p class="card-text"><?php echo htmlspecialchars($row['course_desc']); ?></p>
                </div>
                <div class="card-footer">
                  <p class="card-text d-inline">
                    Price:
                    <small><del>&#8377 <?php echo (float)$row['course_original_price']; ?></del></small>
                    <span class="font-weight-bolder">&#8377 <?php echo (float)$row['course_price']; ?></span>
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
  <?php endif; ?>

  <div class="text-center m-2">
    <a class="btn btn-danger btn-sm" href="courses.php">Browse Tracks</a>
  </div>
</div>

<div class="container mt-5">
  <h1 class="text-center">Tracks</h1>
  <p class="text-center text-muted mb-4">Choose a track to view its courses</p>

  <div class="row mt-4">
    <?php if (count($track) > 0): ?>
      <?php foreach ($track as $t): ?>
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
        <div class="alert alert-dark">No track found.</div>
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
