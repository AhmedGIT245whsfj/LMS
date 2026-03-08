<?php
session_start();
require_once dirname(__DIR__) . '/dbConnection.php';

if (!empty($_SESSION['is_admin_login'])) {
  header("Location: adminDashboard.php");
  exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adminLogemail'], $_POST['adminLogpass'])) {
  $email = trim((string)$_POST['adminLogemail']);
  $pass  = (string)$_POST['adminLogpass'];

  if ($email === '' || $pass === '') {
    $error = 'Email and password are required.';
  } else {
    $stmt = $conn->prepare("SELECT admin_id, admin_email, admin_pass FROM admin WHERE admin_email = ? LIMIT 1");
    if ($stmt) {
      $stmt->bind_param("s", $email);
      $stmt->execute();
      $res = $stmt->get_result();
      $row = $res ? $res->fetch_assoc() : null;
      $stmt->close();

      if ($row) {
        $stored = (string)$row['admin_pass'];

        $ok = false;
        if (password_verify($pass, $stored)) {
          $ok = true;
        } else {
          if (hash_equals($stored, $pass)) {
            $ok = true;
            $newHash = password_hash($pass, PASSWORD_BCRYPT);
            $u = $conn->prepare("UPDATE admin SET admin_pass = ? WHERE admin_id = ? LIMIT 1");
            if ($u) {
              $adminId = (int)$row['admin_id'];
              $u->bind_param("si", $newHash, $adminId);
              $u->execute();
              $u->close();
            }
          }
        }

        if ($ok) {
          $_SESSION['is_admin_login'] = true;
          $_SESSION['adminLogEmail']  = $email;
          header("Location: adminDashboard.php");
          exit;
        }
      }

      $error = 'Invalid credentials.';
    } else {
      $error = 'DB error.';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Login</title>
  <link rel="stylesheet" href="../css/bootstrap.min.css">
  <link rel="stylesheet" href="../css/all.min.css">
  <link rel="stylesheet" href="../css/adminstyle.css">
</head>
<body>
  <nav class="navbar navbar-expand-sm navbar-dark pl-5 fixed-top" style="background:#343a40;">
    <a class="navbar-brand" href="../index.php">ITVERSE</a>
  </nav>

  <div class="container" style="margin-top:110px; max-width:520px;">
    <h2 class="mb-4">Admin Login</h2>

    <?php if ($error !== ''): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="form-group">
        <label for="adminLogemail">Email</label>
        <input type="email" class="form-control" id="adminLogemail" name="adminLogemail" required>
      </div>
      <div class="form-group">
        <label for="adminLogpass">Password</label>
        <input type="password" class="form-control" id="adminLogpass" name="adminLogpass" required>
      </div>
      <button class="btn btn-primary mt-2" type="submit">Login</button>
    </form>

    <div class="mt-3">
      <a href="../index.php">Back to Home</a>
    </div>
  </div>

  <script src="../js/jquery.min.js"></script>
  <script src="../js/bootstrap.min.js"></script>
</body>
</html>
