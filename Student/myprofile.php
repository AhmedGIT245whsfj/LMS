<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * We rely on the logged-in email (stable in your project).
 * Accept both keys to be compatible with old/new login flows.
 */
$stuEmail = $_SESSION['stu_email'] ?? $_SESSION['stuLogEmail'] ?? '';
$stuEmail = is_string($stuEmail) ? trim($stuEmail) : '';

if ($stuEmail === '') {
    header('Location: ../index.php');
    exit;
}

$pageTitle = 'My Profile';

require_once __DIR__ . '/../dbConnection.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

include __DIR__ . '/stuInclude/header.php';

/**
 * Detect real student table column names (project variants).
 * We'll pick the first match to avoid "unknown column" errors.
 */
function itv_find_student_email_column(mysqli $conn): string
{
    $candidates = ['stu_email', 'email', 'stuEmail', 'stuLogEmail'];
    $cols = [];
    $res = $conn->query("SHOW COLUMNS FROM student");
    while ($row = $res->fetch_assoc()) {
        $cols[] = $row['Field'];
    }
    foreach ($candidates as $c) {
        if (in_array($c, $cols, true)) return $c;
    }
    // fallback to first reasonable column containing 'email'
    foreach ($cols as $c) {
        if (stripos($c, 'email') !== false) return $c;
    }
    return 'stu_email';
}

$emailCol = itv_find_student_email_column($conn);

$student = null;
try {
    $sql = "SELECT * FROM student WHERE {$emailCol} = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $stuEmail);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    // render friendly error (no stack trace in UI)
    echo '<div class="container-fluid p-4">';
    echo '<div class="alert alert-danger">';
    echo '<h5 class="mb-1">Profile error</h5>';
    echo '<div>Database query failed. Please try again.</div>';
    echo '</div></div>';
    include __DIR__ . '/stuInclude/footer.php';
    exit;
}

if (!$student) {
    echo '<div class="container-fluid p-4">';
    echo '<div class="alert alert-warning mb-0">Student record not found for this session.</div>';
    echo '</div>';
    include __DIR__ . '/stuInclude/footer.php';
    exit;
}

// pick safe display fields (works with different schemas)
$stuName = $student['stu_name'] ?? $student['stuname'] ?? $student['name'] ?? 'Student';
$stuOcc  = $student['stu_occ'] ?? $student['stuOcc'] ?? $student['occupation'] ?? '';
$stuImg  = $student['stu_img'] ?? $student['stu_img_path'] ?? $student['image'] ?? '';

$stuName = is_string($stuName) ? $stuName : 'Student';
$stuOcc  = is_string($stuOcc) ? $stuOcc : '';
$stuImg  = is_string($stuImg) ? $stuImg : '';

$stuNameEsc = htmlspecialchars($stuName, ENT_QUOTES, 'UTF-8');
$stuOccEsc  = htmlspecialchars($stuOcc, ENT_QUOTES, 'UTF-8');
$stuEmailEsc = htmlspecialchars($stuEmail, ENT_QUOTES, 'UTF-8');

// image fallback
$imgSrc = trim($stuImg) !== '' ? $stuImg : '../image/stu_default.png';
$imgSrcEsc = htmlspecialchars($imgSrc, ENT_QUOTES, 'UTF-8');
?>

<div class="container-fluid px-4 py-4">
  <div class="row">
    <div class="col-lg-8">
      <div class="card shadow-sm mb-4">
        <div class="card-body">
          <div class="d-flex align-items-center gap-3">
            <img src="<?php echo $imgSrcEsc; ?>" alt="Student" style="width:84px;height:84px;object-fit:cover;border-radius:50%;border:1px solid #eee;">
            <div>
              <h4 class="mb-1"><?php echo $stuNameEsc; ?></h4>
              <div class="text-muted"><?php echo $stuEmailEsc; ?></div>
              <?php if ($stuOccEsc !== ''): ?>
                <div class="mt-1"><span class="badge bg-secondary"><?php echo $stuOccEsc; ?></span></div>
              <?php endif; ?>
            </div>
          </div>

          <hr>

          <div class="row g-3">
            <div class="col-md-6">
              <div class="small text-muted">Name</div>
              <div class="fw-semibold"><?php echo $stuNameEsc; ?></div>
            </div>
            <div class="col-md-6">
              <div class="small text-muted">Email</div>
              <div class="fw-semibold"><?php echo $stuEmailEsc; ?></div>
            </div>
            <div class="col-md-6">
              <div class="small text-muted">Occupation</div>
              <div class="fw-semibold"><?php echo $stuOccEsc !== '' ? $stuOccEsc : '-'; ?></div>
            </div>
          </div>

          <div class="mt-4 d-flex gap-2">
            <a class="btn btn-primary" href="studentProfile.php">Edit Profile</a>
            <a class="btn btn-outline-secondary" href="studentChangePass.php">Change Password</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
include __DIR__ . '/stuInclude/footer.php';
