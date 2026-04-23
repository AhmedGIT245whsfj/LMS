<?php
declare(strict_types=1);

require_once __DIR__ . '/../dbConnection.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$studentEmail = trim((string)($_SESSION['stuLogEmail'] ?? $_SESSION['stu_email'] ?? ''));
if ($studentEmail === '') {
    header('Location: ../loginorsignup.php');
    exit;
}

function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function db_column_exists(mysqli $conn, string $table, string $column): bool {
    $sql = "SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    return (bool)$res->fetch_row();
}

function first_existing_column(mysqli $conn, string $table, array $candidates): ?string {
    foreach ($candidates as $c) {
        if (db_column_exists($conn, $table, $c)) {
            return $c;
        }
    }
    return null;
}

function normalize_course_img(?string $img, ?int $trackId = null): string {
    $img = trim((string)$img);

    $fallbackByTrack = [
        1 => '../image/courseimg/PythonFoundations.png',
        2 => '../image/courseimg/Data Science Fundamentals.webp',
        3 => '../image/courseimg/Security Fundamentals.png',
        4 => '../image/courseimg/DevOps Fundamentals.jpeg',
        5 => '../image/courseimg/AWS Cloud Fundamentals & Core Services.jpg',
        6 => '../image/courseimg/AIFundamentals.png',
        7 => '../image/courseimg/Networking Fundamentals.jpg',
        8 => '../image/courseimg/OS Fundamentals.jpg',
    ];

    if ($img === '') {
        $img = $fallbackByTrack[(int)$trackId] ?? '../image/courseimg/PythonFoundations.png';
    }

    if (preg_match('#^https?://#i', $img)) {
        return $img;
    }

    if (str_starts_with($img, '../')) {
        $final = $img;
    } elseif (str_starts_with($img, 'image/')) {
        $final = '../' . $img;
    } elseif (str_starts_with($img, '/image/')) {
        $final = $img;
    } else {
        $final = '../image/courseimg/' . ltrim($img, '/');
    }

    return str_replace('#', '%23', $final);
}

$student = [
    'stu_id' => null,
    'stu_name' => '',
    'stu_email' => $studentEmail,
    'preferred_track_id' => null,
    'experience_level' => 'Beginner',
    'track_name' => '',
];

$sqlStudent = "
    SELECT s.stu_id, s.stu_name, s.stu_email, s.preferred_track_id, s.experience_level, t.track_name
    FROM student s
    LEFT JOIN track t ON t.track_id = s.preferred_track_id
    WHERE LOWER(TRIM(s.stu_email)) = LOWER(TRIM(?))
    LIMIT 1
";
$stStudent = $conn->prepare($sqlStudent);
if ($stStudent) {
    $stStudent->bind_param("s", $studentEmail);
    $stStudent->execute();
    $resStudent = $stStudent->get_result();
    if ($row = $resStudent->fetch_assoc()) {
        $student = array_merge($student, $row);
    }
}

$coEmailCol = first_existing_column($conn, 'courseorder', ['stu_email', 'student_email', 'email']);
$coIdCol    = first_existing_column($conn, 'courseorder', ['stu_id', 'student_id']);
$coSortCol  = first_existing_column($conn, 'courseorder', ['co_id', 'id', 'order_date', 'created_at', 'order_id']);

$orderWhere = '1=0';
$orderBindType = '';
$orderBindValue = null;

if ($coEmailCol !== null) {
    $orderWhere = "LOWER(TRIM(co.`$coEmailCol`)) = LOWER(TRIM(?))";
    $orderBindType = 's';
    $orderBindValue = (string)$studentEmail;
} elseif ($coIdCol !== null && !empty($student['stu_id'])) {
    $orderWhere = "co.`$coIdCol` = ?";
    $orderBindType = 'i';
    $orderBindValue = (int)$student['stu_id'];
}

$orderExpr = $coSortCol !== null ? "co.`$coSortCol`" : "c.course_id";
$orderAggExpr = $coSortCol !== null ? "MAX(co.`$coSortCol`)" : "MAX(c.course_id)";

if (empty($student['preferred_track_id']) && $orderBindType !== '') {
    $sqlLatestTrack = "
        SELECT c.track_id, t.track_name
        FROM courseorder co
        JOIN course c ON c.course_id = co.course_id
        LEFT JOIN track t ON t.track_id = c.track_id
        WHERE $orderWhere
        ORDER BY $orderExpr DESC
        LIMIT 1
    ";
    $stLatest = $conn->prepare($sqlLatestTrack);
    if ($stLatest) {
        $stLatest->bind_param($orderBindType, $orderBindValue);
        $stLatest->execute();
        $resLatest = $stLatest->get_result();
        if ($rowLatest = $resLatest->fetch_assoc()) {
            $student['preferred_track_id'] = (int)($rowLatest['track_id'] ?? 0);
            $student['track_name'] = (string)($rowLatest['track_name'] ?? '');

            if (!empty($student['stu_id']) && !empty($student['preferred_track_id'])) {
                $stuId = (int)$student['stu_id'];
                $trackId = (int)$student['preferred_track_id'];
                $up = $conn->prepare("UPDATE student SET preferred_track_id = ? WHERE stu_id = ? LIMIT 1");
                if ($up) {
                    $up->bind_param("ii", $trackId, $stuId);
                    $up->execute();
                }
            }
        }
    }
}

$myCourses = [];
$purchasedIds = [];

if ($orderBindType !== '') {
    $sqlMyCourses = "
        SELECT c.course_id, c.course_name, c.course_desc, c.course_img,
               c.course_price, c.course_original_price, c.track_id, t.track_name,
               $orderAggExpr AS last_order_marker
        FROM courseorder co
        JOIN course c ON c.course_id = co.course_id
        LEFT JOIN track t ON t.track_id = c.track_id
        WHERE $orderWhere
        GROUP BY c.course_id, c.course_name, c.course_desc, c.course_img,
                 c.course_price, c.course_original_price, c.track_id, t.track_name
        ORDER BY last_order_marker DESC
    ";
    $stMy = $conn->prepare($sqlMyCourses);
    if ($stMy) {
        $stMy->bind_param($orderBindType, $orderBindValue);
        $stMy->execute();
        $resMy = $stMy->get_result();
        while ($row = $resMy->fetch_assoc()) {
            $row['img_src'] = normalize_course_img($row['course_img'] ?? '', (int)($row['track_id'] ?? 0));
            $myCourses[] = $row;
            $purchasedIds[] = (int)$row['course_id'];
        }
    }
}

$recommendedCourses = [];
$preferredTrackId = (int)($student['preferred_track_id'] ?? 0);
$level = strtolower(trim((string)($student['experience_level'] ?? 'Beginner')));

if ($preferredTrackId > 0) {
    $order = ($level === 'advanced' || $level === 'experienced' || $level === 'intermediate') ? 'DESC' : 'ASC';

    $params = [$preferredTrackId];
    $types = "i";
    $notInSql = "";

    if (!empty($purchasedIds)) {
        $placeholders = implode(',', array_fill(0, count($purchasedIds), '?'));
        $notInSql = " AND c.course_id NOT IN ($placeholders) ";
        foreach ($purchasedIds as $cid) {
            $types .= "i";
            $params[] = $cid;
        }
    }

    $sqlRec = "
        SELECT c.course_id, c.course_name, c.course_desc, c.course_img,
               c.course_price, c.course_original_price, c.track_id, t.track_name
        FROM course c
        LEFT JOIN track t ON t.track_id = c.track_id
        WHERE c.track_id = ? $notInSql
        ORDER BY c.course_id $order
        LIMIT 4
    ";
    $stRec = $conn->prepare($sqlRec);
    if ($stRec) {
        $stRec->bind_param($types, ...$params);
        $stRec->execute();
        $resRec = $stRec->get_result();
        while ($row = $resRec->fetch_assoc()) {
            $row['img_src'] = normalize_course_img($row['course_img'] ?? '', (int)($row['track_id'] ?? 0));
            $recommendedCourses[] = $row;
        }
    }
}

include('./stuInclude/header.php');
?>
<div class="container-fluid" style="margin-top:20px;">
  <div class="row">
    <?php include('./stuInclude/sidebar.php'); ?>

    <div class="col-sm-9">
      <div class="mb-4">
        <h2>Recommended for You</h2>
        <p class="text-muted mb-2">Best starting courses based on your selected track and level.</p>

        <?php if (!empty($recommendedCourses)): ?>
          <div class="row">
            <?php foreach ($recommendedCourses as $c): ?>
              <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100 shadow-sm">
                  <img src="<?php echo h($c['img_src']); ?>" class="card-img-top" alt="Course" style="height:220px; object-fit:cover;" onerror="this.onerror=null;this.src='../image/courseimg/PythonFoundations.png';">
                  <div class="card-body d-flex flex-column">
                    <small class="text-muted"><?php echo h($c['track_name'] ?? ''); ?></small>
                    <h5 class="card-title mt-2"><?php echo h($c['course_name']); ?></h5>
                    <p class="card-text"><?php echo h($c['course_desc']); ?></p>
                  </div>
                  <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                    <div>
                      <span style="text-decoration:line-through;color:#555;">₹ <?php echo h($c['course_original_price']); ?></span>
                      <strong>₹ <?php echo h($c['course_price']); ?></strong>
                    </div>
                    <a class="btn btn-primary btn-sm" href="watchcourse.php?course_id=<?php echo (int)$c['course_id']; ?>">Open</a>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="alert alert-info">No recommendations available right now.</div>
        <?php endif; ?>
      </div>

      <hr>

      <div class="mb-4">
        <h2>My Courses</h2>

        <?php if (!empty($myCourses)): ?>
          <div class="row">
            <?php foreach ($myCourses as $c): ?>
              <div class="col-md-6 col-lg-6 mb-4">
                <div class="card h-100 shadow-sm">
                  <img src="<?php echo h($c['img_src']); ?>" class="card-img-top" alt="Course" style="height:220px; object-fit:cover;" onerror="this.onerror=null;this.src='../image/courseimg/PythonFoundations.png';">
                  <div class="card-body d-flex flex-column">
                    <small class="text-muted"><?php echo h($c['track_name'] ?? ''); ?></small>
                    <h4 class="card-title mt-2"><?php echo h($c['course_name']); ?></h4>
                    <p class="card-text"><?php echo h($c['course_desc']); ?></p>
                  </div>
                  <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                    <div>
                      <span style="text-decoration:line-through;color:#555;">₹ <?php echo h($c['course_original_price']); ?></span>
                      <strong>₹ <?php echo h($c['course_price']); ?></strong>
                    </div>
                    <a class="btn btn-primary btn-sm" href="watchcourse.php?course_id=<?php echo (int)$c['course_id']; ?>">Open</a>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="alert alert-info">You have not purchased any courses yet.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php include('./stuInclude/footer.php'); ?>
