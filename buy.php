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

$courseId = '';
if (isset($_GET['course_id'])) $courseId = trim((string)$_GET['course_id']);
if ($courseId === '' && isset($_GET['courseid'])) $courseId = trim((string)$_GET['courseid']);
if ($courseId === '' && isset($_GET['cid'])) $courseId = trim((string)$_GET['cid']);

if ($courseId !== '') {
    header('Location: checkout.php?course_id=' . urlencode($courseId));
    exit;
}

// No course id: just go to checkout picker (no redirect loop)
header('Location: checkout.php');
exit;
