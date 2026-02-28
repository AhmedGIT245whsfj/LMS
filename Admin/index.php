<?php
if (!isset($_SESSION)) {
  session_start();
}
if (isset($_SESSION['is_admin_login']) && $_SESSION['is_admin_login'] === true) {
  header('Location: adminDashboard.php');
  exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login</title>
  <link rel="stylesheet" type="text/css" href="../css/bootstrap.min.css">
</head>
<body class="bg-light">
  <div class="container" style="max-width: 480px; margin-top: 80px;">
    <div class="card shadow-sm">
      <div class="card-body">
        <h4 class="mb-3">Admin Login</h4>

        <div class="form-group">
          <label for="adminEmail">Email</label>
          <input type="email" class="form-control" id="adminEmail" autocomplete="off" value="" name="admin_email_input">
        </div>

        <div class="form-group">
          <label for="adminPass">Password</label>
          <input type="password" class="form-control" id="adminPass" autocomplete="current-password" value="admin">
        </div>

        <button id="btnLogin" class="btn btn-primary btn-block">Login</button>
        <div id="msg" class="mt-3"></div>
      </div>
    </div>
  </div>

<script>
(function () {
  const btn = document.getElementById('btnLogin');
  const msg = document.getElementById('msg');

  function setMsg(text, ok) {
    msg.className = ok ? 'mt-3 alert alert-success' : 'mt-3 alert alert-danger';
    msg.textContent = text;
  }

  btn.addEventListener('click', async function () {
    msg.className = 'mt-3';
    msg.textContent = '';

    const email = document.getElementById('adminEmail').value.trim();
    const pass  = document.getElementById('adminPass').value;

    const body = new URLSearchParams();
    body.set('checkLogemail', '1');
    body.set('adminLogEmail', email);
    body.set('adminLogPass', pass);

    try {
      const res = await fetch('admin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
      });

      const data = await res.json();
      if (data === 1) {
        setMsg('Login successful. Redirecting...', true);
        setTimeout(() => { window.location.href = 'adminDashboard.php'; }, 400);
      } else {
        setMsg('Invalid email or password.', false);
      }
    } catch (e) {
      setMsg('Request failed.', false);
    }
  });
})();
</script>
<script>window.addEventListener("load",function(){var e=document.getElementById("adminEmail"); if(e) e.value="";});</script>
</body>
</html>
