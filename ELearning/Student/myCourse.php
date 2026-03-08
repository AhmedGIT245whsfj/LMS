<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../dbConnection.php';

function itv_has_column(mysqli $conn, string $table, string $col): bool {
    $sql = "SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1";
    $st = $conn->prepare($sql);
    if (!$st) {
        return false;
    }
    $st->bind_param("ss", $table, $col);
    $st->execute();
    $res = $st->get_result();
    $st->close();
    return (bool)($res && $res->num_rows > 0);
}

function itv_pick_order_table(mysqli $conn): ?string {
    foreach (['courseorder', 'orders', 'course_order'] as $t) {
        $sql = "SELECT 1
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                LIMIT 1";
        $st = $conn->prepare($sql);
        if (!$st) {
            continue;
        }
        $st->bind_param("s", $t);
        $st->execute();
        $res = $st->get_result();
        $st->close();
        if ($res && $res->num_rows > 0) {
            return $t;
        }
    }
    return null;
}

function itv_get_columns(mysqli $conn, string $table): array {
    $cols = [];
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($safeTable === '') {
        return $cols;
    }

    $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}`");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cols[] = (string)$row['Field'];
        }
        $res->free();
    }
    return $cols;
}

function itv_find_first_column(array $cols, array $candidates): ?string {
    $lowerMap = [];
    foreach ($cols as $c) {
        $lowerMap[strtolower($c)] = $c;
    }
    foreach ($candidates as $cand) {
        $key = strtolower($cand);
        if (isset($lowerMap[$key])) {
            return $lowerMap[$key];
        }
    }
    return null;
}

function itv_normalize_course_img(?string $img): string {
    $img = trim((string)$img);
    if ($img === '') {
        return '';
    }

    if (preg_match('#^(https?:)?/#i', $img)) {
        return $img;
    }

    if (strpos($img, '../image/') === 0 || strpos($img, 'image/') === 0) {
        return $img;
    }

    $filename = basename(str_replace('\\', '/', $img));
    return '../image/courseimg/' . $filename;
}

$stuEmail = '';
if (isset($_SESSION['stuLogEmail'])) {
    $stuEmail = (string)$_SESSION['stuLogEmail'];
} elseif (isset($_SESSION['stu_email'])) {
    $stuEmail = (string)$_SESSION['stu_email'];
}
$stuEmail = trim($stuEmail);

if ($stuEmail === '') {
    header("Location: /loginorsignup.php");
    exit;
}

$orderTable = itv_pick_order_table($conn);
if ($orderTable === null) {
    http_response_code(500);
    echo "Orders table not found.";
    exit;
}

$orderCols = itv_get_columns($conn, $orderTable);
if (!$orderCols) {
    http_response_code(500);
    echo "Could not inspect order table columns.";
    exit;
}

$orderHasStuId = in_array('stu_id', $orderCols, true);
$orderHasStuEmail = in_array('stu_email', $orderCols, true);

$studentHasId = itv_has_column($conn, 'student', 'stu_id');
$studentHasEmail = itv_has_column($conn, 'student', 'stu_email');

$stuId = null;
if ($orderHasStuId) {
    if (isset($_SESSION['stu_id']) && is_numeric((string)$_SESSION['stu_id'])) {
        $stuId = (int)$_SESSION['stu_id'];
    } elseif ($studentHasId && $studentHasEmail) {
        $st = $conn->prepare("SELECT stu_id FROM student WHERE stu_email = ? LIMIT 1");
        if ($st) {
            $st->bind_param("s", $stuEmail);
            $st->execute();
            $res = $st->get_result();
            if ($res && ($row = $res->fetch_assoc())) {
                $stuId = (int)$row['stu_id'];
                $_SESSION['stu_id'] = $stuId;
            }
            $st->close();
        }
    }
}

$courseIdCol = itv_find_first_column($orderCols, ['course_id', 'courseId', 'course_id_fk']);
if ($courseIdCol === null) {
    http_response_code(500);
    echo "Order table does not have a course id column.";
    exit;
}

$courseTable = 'course';
if (!itv_has_column($conn, $courseTable, 'course_id')) {
    http_response_code(500);
    echo "Course table missing course_id.";
    exit;
}

$whereSql = '';
$bindTypes = '';
$bindVals = [];

if ($orderHasStuId && $stuId !== null) {
    $whereSql = "o.stu_id = ?";
    $bindTypes = "i";
    $bindVals = [$stuId];
} elseif ($orderHasStuEmail) {
    $whereSql = "o.stu_email = ?";
    $bindTypes = "s";
    $bindVals = [$stuEmail];
} else {
    http_response_code(500);
    echo "Order table does not contain a usable student reference.";
    exit;
}

$sql = "SELECT DISTINCT c.*
        FROM `{$orderTable}` o
        JOIN `{$courseTable}` c ON c.course_id = o.`{$courseIdCol}`
        WHERE {$whereSql}
        ORDER BY c.course_id DESC";

$st = $conn->prepare($sql);
if (!$st) {
    http_response_code(500);
    echo "Failed to prepare My Courses query.";
    exit;
}

$st->bind_param($bindTypes, ...$bindVals);
$st->execute();
$res = $st->get_result();

include_once __DIR__ . '/stuInclude/header.php';
?>
<div class="container-fluid" style="margin-top:20px;">
    <div class="row">
        <div class="col-sm-3">
            <?php @include_once __DIR__ . '/stuInclude/sidebar.php'; ?>
        </div>

        <div class="col-sm-9">
            <h3 class="mb-3">My Courses</h3>

            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'added'): ?>
                <div class="alert alert-success">Course added successfully to your account.</div>
            <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'already'): ?>
                <div class="alert alert-warning">This course is already in your account.</div>
            <?php endif; ?>

            <?php if (!$res || $res->num_rows === 0): ?>
                <div class="alert alert-info">No courses found yet.</div>
            <?php else: ?>
                <div class="row">
                    <?php while ($c = $res->fetch_assoc()): ?>
                        <?php $img = itv_normalize_course_img($c['course_img'] ?? ''); ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card h-100">
                                <?php if ($img !== ''): ?>
                                    <img class="card-img-top" alt="Course" src="<?php echo htmlspecialchars($img, ENT_QUOTES); ?>">
                                <?php endif; ?>
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars((string)($c['course_name'] ?? 'Course'), ENT_QUOTES); ?></h5>
                                    <p class="card-text"><?php echo htmlspecialchars((string)($c['course_desc'] ?? ''), ENT_QUOTES); ?></p>
                                    <?php if (!empty($c['course_id'])): ?>
                                        <a class="btn btn-primary btn-sm" href="/Student/watchcourse.php?course_id=<?php echo urlencode((string)$c['course_id']); ?>">Open</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
$st->close();
include_once __DIR__ . '/stuInclude/footer.php';
?>
