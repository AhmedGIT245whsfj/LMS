<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$stuEmail = (string)($_SESSION['stuLogEmail'] ?? '');
if ($stuEmail === '') {
    header('Location: loginorsignup.php');
    exit;
}

$courseName = (string)($_SESSION['last_purchased_course_name'] ?? '');
$courseId   = (string)($_SESSION['last_purchased_course_id'] ?? '');
$status     = (string)($_SESSION['purchase_status'] ?? 'added');

unset(
    $_SESSION['last_purchased_course_name'],
    $_SESSION['last_purchased_course_id'],
    $_SESSION['purchase_status']
);

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES);
}

$title = 'Course added to your account';
$subtitle = ($courseName !== '')
    ? ($courseName . ' is now available in your dashboard.')
    : 'Your purchase has been completed successfully.';

$iconBg = '#e9f9ef';
$iconColor = '#2e7d32';
$iconText = '✓';

if ($status === 'already_owned') {
    $title = 'Course already in your account';
    $subtitle = ($courseName !== '')
        ? ($courseName . ' is already available in your My Courses page.')
        : 'This course was already added to your account before.';
    $iconBg = '#fff8e1';
    $iconColor = '#f57c00';
    $iconText = '!';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Purchase Status</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="css/bootstrap.min.css">
<style>
body{
    background:#f4f6f9;
    font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
}
.success-card{
    max-width:720px;
    margin:70px auto;
    background:#ffffff;
    border-radius:16px;
    padding:40px;
    box-shadow:0 8px 30px rgba(0,0,0,0.06);
}
.success-header{
    display:flex;
    align-items:center;
    gap:15px;
}
.success-icon{
    width:48px;
    height:48px;
    border-radius:12px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:26px;
    font-weight:700;
}
.success-title{
    font-size:22px;
    font-weight:600;
}
.success-sub{
    color:#6c757d;
    margin-top:4px;
}
.btn-modern{
    padding:10px 16px;
    border-radius:8px;
    font-weight:500;
}
.btn-primary-modern{
    background:#0d6efd;
    color:#fff;
    border:none;
}
.btn-primary-modern:hover{
    background:#0b5ed7;
    color:#fff;
}
.btn-outline-modern{
    border:1px solid #dee2e6;
    background:#fff;
    color:#333;
}
.btn-outline-modern:hover{
    background:#f1f3f5;
    color:#333;
}
.actions{
    margin-top:30px;
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:12px;
}
.footer-text{
    text-align:center;
    margin-top:25px;
    color:#888;
    font-size:14px;
}
@media (max-width: 576px) {
    .actions{
        grid-template-columns:1fr;
    }
}
</style>
</head>
<body>

<div class="success-card">
    <div class="success-header">
        <div class="success-icon" style="background: <?php echo h($iconBg); ?>; color: <?php echo h($iconColor); ?>;">
            <?php echo h($iconText); ?>
        </div>
        <div>
            <div class="success-title"><?php echo h($title); ?></div>
            <div class="success-sub"><?php echo h($subtitle); ?></div>
        </div>
    </div>

    <div class="actions">
        <a href="Student/myCourse.php" class="btn btn-modern btn-primary-modern text-center">
            View My Courses
        </a>

        <?php if ($courseId !== ''): ?>
        <a href="Student/watchcourse.php?course_id=<?php echo urlencode($courseId); ?>" class="btn btn-modern btn-outline-modern text-center">
            Start Learning
        </a>
        <?php endif; ?>

        <a href="courses.php" class="btn btn-modern btn-outline-modern text-center">
            Browse More Courses
        </a>

        <a href="Student/myCourse.php" class="btn btn-modern btn-outline-modern text-center">
            Back to Dashboard
        </a>
    </div>

    <div class="footer-text">
        Logged in as <?php echo h($stuEmail); ?>
    </div>
</div>

</body>
</html>
