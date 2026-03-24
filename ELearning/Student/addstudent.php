<?php
if (!isset($conn)) {
    include_once __DIR__ . '/../dbConnection.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['stusignup'])) {
    $stuname = trim($_POST['stuname'] ?? '');
    $stuemail = trim($_POST['stuemail'] ?? '');
    $stupass = trim($_POST['stupass'] ?? '');
    $preferred_track = trim($_POST['preferred_track'] ?? '');
    $experience_level = trim($_POST['experience_level'] ?? 'beginner');

    if ($stuname === '' || $stuemail === '' || $stupass === '') {
        http_response_code(400);
        exit('Missing required fields');
    }

    if (!filter_var($stuemail, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        exit('Invalid email');
    }

    if (strlen($stupass) < 6) {
        http_response_code(400);
        exit('Password must be at least 6 characters');
    }

    $check = $conn->prepare("SELECT stu_id FROM student WHERE stu_email = ?");
    if (!$check) {
        http_response_code(500);
        exit('PREPARE ERROR: ' . $conn->error);
    }

    $check->bind_param("s", $stuemail);
    if (!$check->execute()) {
        http_response_code(500);
        exit('EXECUTE ERROR: ' . $check->error);
    }

    $check->store_result();
    if ($check->num_rows > 0) {
        exit('Failed');
    }
    $check->close();

    $hash = password_hash($stupass, PASSWORD_DEFAULT);
    $stu_occ = $experience_level;
    $stu_img = '';

    $stmt = $conn->prepare("
        INSERT INTO student
        (stu_name, stu_email, stu_pass, stu_occ, stu_img, preferred_track, experience_level)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) {
        http_response_code(500);
        exit('PREPARE ERROR: ' . $conn->error);
    }

    $stmt->bind_param(
        "sssssss",
        $stuname,
        $stuemail,
        $hash,
        $stu_occ,
        $stu_img,
        $preferred_track,
        $experience_level
    );

    if (!$stmt->execute()) {
        http_response_code(500);
        exit('EXECUTE ERROR: ' . $stmt->error);
    }

    echo 'OK';
    exit;
}
?>
