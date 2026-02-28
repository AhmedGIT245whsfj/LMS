<?php
include('./dbConnection.php');
include('./mainInclude/header.php');

$trackId = 0;
if (isset($_GET['track_id']) && $_GET['track_id'] !== '') {
  $trackId = (int)$_GET['track_id'];
}

$tracks = [];
$resTracks = $conn->query("SELECT track_id, track_name, track_desc, track_img FROM tracks ORDER BY track_id ASC");
if ($resTracks && $resTracks->num_rows > 0) {
  while ($r = $resTracks->fetch_assoc()) {
    $tracks[] = $r;
  }
}

$track = null;
$courses = [];
if ($trackId > 0) {
  $stmtT = $conn->prepare("SELECT track_id, track_name, track_desc, track_img FROM tracks WHERE track_id = ? LIMIT 1");
  if ($stmtT) {
    $stmtT->bind_param("i", $trackId);
    $stmtT->execute();
    $resT = $stmtT->get_result();
    $track = ($resT && $resT->num_rows === 1) ? $resT->fetch_assoc() : null;
    $stmtT->close();
  }

  if ($track) {
    $stmtC = $conn->prepare("SELECT * FROM course WHERE track_id = ? ORDER BY course_id ASC");
    if ($stmtC) {
      $stmtC->bind_param("i", $trackId);
      $stmtC->execute();
      $resC = $stmtC->get_result();
      if ($resC && $resC->num_rows > 0) {
        while ($r = $resC->fetch_assoc()) {
          $courses[] = $r;
        }
      }
      $stmtC->close();
    }
  }
}
?>

<div class="container-fluid bg-dark">
  <div class="row">
    <img src="./image/coursebanner.jpg" alt="courses" style="height:500px; width:100%; object-fit:cover; box-shadow:10px;"/>
  </div>
</div>

<div class="container mt-5">

  <?php if ($trackId > 0 && $track): ?>
    <h1 class="text-center"><?php echo htmlspecialchars($track['track_name']); ?></h1>
    <p class="text-center text-muted mb-4"><?php echo htmlspecialchars($track['track_desc']); ?></p>

    <div class="text-center mb-4">
      <a class="btn btn-outline-dark btn-sm" href="courses.php">Back to Tracks</a>
    </div>

    <div class="row mt-4">
      <?php if (count($courses) > 0): ?>
        <?php foreach ($courses as $row): ?>
          <?php
            $course_id = (int)$row['course_id'];
            $img = str_replace('..', '.', (string)$row['course_img']);
          ?>
          <div class="col-sm-4 mb-4">
            <a href="coursedetails.php?course_id=<?php echo $course_id; ?>" class="btn" style="text-align:left; padding:0px; width:100%;">
              <div class="card h-100">
                <img src="<?php echo htmlspecialchars($img); ?>" class="card-img-top" alt="course" />
                <div class="card-body">
                  <h5 class="card-title"><?php echo htmlspecialchars($row['course_name']); ?></h5>
                  <p class="card-text"><?php echo htmlspecialchars($row['course_desc']); ?></p>
                </div>
                <div class="card-footer">
                  <p class="card-text d-inline">
                    Price: <small><del>&#8377 <?php echo (int)$row['course_original_price']; ?></del></small>
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
          <div class="alert alert-dark">No courses found for this track.</div>
        </div>
      <?php endif; ?>
    </div>

  <?php else: ?>
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
  <?php endif; ?>

</div>

<?php include('./contact.php'); ?>
<?php include('./mainInclude/footer.php'); ?>
