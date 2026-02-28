<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/dbConnection.php';

function post(string $k, string $d=''): string { return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $d; }

$stuEmail = (string)($_SESSION['stuLogEmail'] ?? '');
if ($stuEmail === '') {
    header('Location: loginorsignup.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

$courseId = post('course_id', post('courseid', post('cid', '')));
$method   = post('payment_method', 'card');

if ($courseId === '') {
    $_SESSION['flash_error'] = 'Missing course id.';
    header('Location: checkout.php');
    exit;
}

$courseName = 'Course';
$amount = '0';

$stmt = $conn->prepare("SELECT course_name, course_price FROM course WHERE course_id = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param('s', $courseId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && ($row = $res->fetch_assoc())) {
        $courseName = (string)($row['course_name'] ?? $courseName);
        $amount = (string)($row['course_price'] ?? $amount);
    }
    $stmt->close();
}

$orderId = 'LOCAL' . date('YmdHis') . random_int(1000, 9999);
$status  = 'TXN_SUCCESS';
$created = date('Y-m-d H:i:s');

function default_for_column(array $c): string {
    $type = strtolower((string)($c['Type'] ?? ''));
    $default = $c['Default'] ?? null;

    if ($default !== null) return (string)$default;

    // ENUM / SET: pick first value
    if (preg_match('/^enum\((.+)\)$/i', $type, $m) || preg_match('/^set\((.+)\)$/i', $type, $m)) {
        $inside = $m[1];
        // first quoted value
        if (preg_match("/'([^']*)'/", $inside, $mm)) return (string)$mm[1];
        return '';
    }

    // Numeric
    if (preg_match('/int|decimal|float|double|bit/', $type)) return '0';

    // Date/time
    if (strpos($type, 'datetime') !== false || strpos($type, 'timestamp') !== false) return date('Y-m-d H:i:s');
    if (strpos($type, 'date') !== false) return date('Y-m-d');
    if (strpos($type, 'time') !== false) return date('H:i:s');

    // Everything else string-ish
    return '';
}

function is_auto_inc(array $c): bool {
    return isset($c['Extra']) && stripos((string)$c['Extra'], 'auto_increment') !== false;
}

$insertOk = false;
$insertErr = '';

try {
    $cols = [];
    $res = $conn->query("SHOW COLUMNS FROM courseorder");
    if ($res) {
        while ($r = $res->fetch_assoc()) $cols[] = $r;
        $res->free();
    }

    if (!$cols) {
        throw new RuntimeException('courseorder table not found or unreadable.');
    }

    $insCols = [];
    $insVals = [];
    $types = '';

    foreach ($cols as $c) {
        if (is_auto_inc($c)) continue;

        $field = (string)$c['Field'];
        $lower = strtolower($field);
        $nullAllowed = ((string)$c['Null'] === 'YES');
        $hasDefault = ($c['Default'] !== null);
        $isRequired = (!$nullAllowed && !$hasDefault);

        $val = null;

        if (strpos($lower, 'order') !== false && strpos($lower, 'id') !== false) $val = $orderId;
        if (strpos($lower, 'course') !== false && strpos($lower, 'id') !== false) $val = $courseId;
        if (strpos($lower, 'email') !== false) $val = $stuEmail;
        if (strpos($lower, 'amount') !== false || strpos($lower, 'price') !== false) $val = $amount;
        if (strpos($lower, 'status') !== false) $val = $status;
        if (strpos($lower, 'method') !== false) $val = $method;
        if (strpos($lower, 'date') !== false || strpos($lower, 'time') !== false || strpos($lower, 'created') !== false) $val = $created;

        if ($val === null && $isRequired) {
            $val = default_for_column($c);
        }

        // Optional columns: skip if unknown
        if ($val !== null) {
            $insCols[] = $field;
            $insVals[] = (string)$val;
            $types .= 's';
        }
    }

    if (!$insCols) {
        throw new RuntimeException('Could not build insert columns for courseorder.');
    }

    $sql = "INSERT INTO courseorder (" . implode(',', $insCols) . ") VALUES (" . implode(',', array_fill(0, count($insCols), '?')) . ")";
    $st = $conn->prepare($sql);
    if (!$st) {
        throw new RuntimeException('Prepare failed: ' . $conn->error);
    }

    $st->bind_param($types, ...$insVals);
    $insertOk = $st->execute();
    if (!$insertOk) {
        $insertErr = $st->error ?: $conn->error;
    }
    $st->close();

} catch (Throwable $e) {
    $insertOk = false;
    $insertErr = $e->getMessage();
}

if ($insertOk) {
    $_SESSION['last_purchased_course_name'] = $courseName;
    $_SESSION['last_purchased_course_id'] = $courseId;

    $_SESSION['flash_success'] = 'Course added to your account: ' . $courseName;
    header('Location: Student/myCourse.php?added=1&course_id=' . urlencode($courseId));
    exit;
}

// If insert failed, show error clearly (no silent failure)
$_SESSION['flash_error'] = 'Checkout failed: ' . ($insertErr !== '' ? $insertErr : 'Unknown DB error');
header('Location: checkout.php?course_id=' . urlencode($courseId));
exit;
