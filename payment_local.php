<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/dbConnection.php';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }

function req(string $k, string $d = ''): string {
    if (isset($_POST[$k])) return trim((string)$_POST[$k]);
    if (isset($_GET[$k])) return trim((string)$_GET[$k]);
    return $d;
}

function fetch_courses(mysqli $conn): array {
    $courses = [];
    $res = $conn->query("SELECT course_id, course_name, course_price FROM course ORDER BY course_id DESC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $courses[] = $row;
        }
        $res->free();
    }
    return $courses;
}

function detect_courseorder_columns(mysqli $conn): array {
    $cols = [];
    $res = $conn->query("SHOW COLUMNS FROM courseorder");
    if ($res) {
        while ($r = $res->fetch_assoc()) $cols[] = $r['Field'];
        $res->free();
    }
    return $cols;
}

function insert_courseorder_best_effort(mysqli $conn, string $orderId, string $courseId, string $email, string $amount, string $status): void {
    $cols = detect_courseorder_columns($conn);
    if (!$cols) return;

    $colsLower = array_map('strtolower', $cols);

    $map = [
        'order_id'   => ['order_id','orderid','ord_id'],
        'course_id'  => ['course_id','courseid','cid'],
        'stu_email'  => ['stu_email','stuemail','email'],
        'amount'     => ['amount','amount_paid','txnamount','price'],
        'status'     => ['status','txn_status','payment_status'],
        'created_at' => ['created_at','createdon','order_date','date'],
    ];

    $values = [
        'order_id'   => $orderId,
        'course_id'  => $courseId,
        'stu_email'  => $email,
        'amount'     => $amount,
        'status'     => $status,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    $insertCols = [];
    $insertVals = [];
    $types = '';

    foreach ($map as $key => $cands) {
        foreach ($cands as $cand) {
            $idx = array_search(strtolower($cand), $colsLower, true);
            if ($idx !== false) {
                $insertCols[] = $cols[$idx];
                $insertVals[] = (string)$values[$key];
                $types .= 's';
                break;
            }
        }
    }

    if (!$insertCols) return;

    $sql = "INSERT INTO courseorder (" . implode(',', $insertCols) . ") VALUES (" . implode(',', array_fill(0, count($insertCols), '?')) . ")";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return;

    $stmt->bind_param($types, ...$insertVals);
    $stmt->execute();
    $stmt->close();
}

$courseId = req('course_id', req('courseid', req('cid', '')));
$email = req('buyer_email', '');
if ($email === '' && !empty($_SESSION['stuLogEmail'])) $email = (string)$_SESSION['stuLogEmail'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $courseId === '') {
    $courses = fetch_courses($conn);
    http_response_code(200);
    ?>
    <!doctype html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Local Payment</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 24px; }
            .box { max-width: 520px; border: 1px solid #ddd; padding: 16px; border-radius: 8px; }
            label { display:block; margin-top: 12px; }
            select, input { width: 100%; padding: 10px; margin-top: 6px; }
            button { margin-top: 16px; padding: 10px 14px; }
            .hint { color:#666; font-size: 13px; margin-top: 10px; }
        </style>
    </head>
    <body>
        <div class="box">
            <h2>Local Payment (Dev)</h2>
            <form method="post" action="payment_local.php">
                <label>Course</label>
                <select name="course_id" required>
                    <option value="">-- Select --</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?php echo h((string)$c['course_id']); ?>">
                            <?php echo h((string)$c['course_name']); ?> (<?php echo h((string)$c['course_price']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <label>Buyer email (optional)</label>
                <input type="email" name="buyer_email" value="<?php echo h($email); ?>" placeholder="you@example.com">

                <label>Amount override (optional)</label>
                <input type="number" step="0.01" name="amount" placeholder="Leave empty to use course price">

                <button type="submit">Pay (Local Success)</button>
            </form>
            <div class="hint">This page works even if opened directly. It creates a successful local order and redirects to paymentstatus.php.</div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$amount = req('amount', '');
if ($amount === '' || (float)$amount <= 0) {
    $stmt = $conn->prepare("SELECT course_price FROM course WHERE course_id = ? LIMIT 1");
    $stmt->bind_param('s', $courseId);
    $stmt->execute();
    $stmt->bind_result($price);
    if ($stmt->fetch()) {
        $amount = (string)$price;
    } else {
        $amount = '0';
    }
    $stmt->close();
}

$orderId = 'LOCAL' . date('YmdHis') . random_int(1000, 9999);
$status  = 'TXN_SUCCESS';

insert_courseorder_best_effort($conn, $orderId, $courseId, $email, $amount, $status);

$_SESSION['LOCAL_ORDER_ID'] = $orderId;
$_SESSION['LOCAL_TXN_STATUS'] = $status;

header('Location: paymentstatus.php?order_id=' . urlencode($orderId) . '&status=' . urlencode($status));
exit;
