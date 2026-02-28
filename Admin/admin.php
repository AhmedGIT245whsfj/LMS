<?php
if (!isset($_SESSION)) {
  session_start();
}

header('Content-type: application/json');

include('../dbConnection.php');

if (!isset($_SESSION['is_admin_login'])) {
  if (isset($_POST['checkLogemail'], $_POST['adminLogEmail'], $_POST['adminLogPass'])) {
    $adminLogEmail = trim((string)$_POST['adminLogEmail']);
    $adminLogPass  = (string)$_POST['adminLogPass'];

    $stmt = $conn->prepare("SELECT admin_email, admin_pass FROM admin WHERE admin_email = ? LIMIT 1");
    if (!$stmt) {
      echo json_encode(0);
      exit;
    }

    $stmt->bind_param("s", $adminLogEmail);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows === 1) {
      $row = $res->fetch_assoc();
      $dbPass = (string)$row['admin_pass'];

      $ok = false;

      // bcrypt hash
      if (password_get_info($dbPass)['algo'] !== 0) {
        $ok = password_verify($adminLogPass, $dbPass);
      } else {
        // legacy plaintext
        $ok = hash_equals($dbPass, $adminLogPass);

        if ($ok) {
          $newHash = password_hash($adminLogPass, PASSWORD_BCRYPT);
          $up = $conn->prepare("UPDATE admin SET admin_pass = ? WHERE admin_email = ? LIMIT 1");
          if ($up) {
            $up->bind_param("ss", $newHash, $adminLogEmail);
            $up->execute();
            $up->close();
          }
        }
      }

      if ($ok) {
        $_SESSION['is_admin_login'] = true;
        $_SESSION['adminLogEmail'] = $adminLogEmail;
        echo json_encode(1);
        exit;
      }
    }

    echo json_encode(0);
    exit;
  }
}

echo json_encode(0);
