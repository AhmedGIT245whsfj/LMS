<?php
include('./dbConnection.php');
include('./mainInclude/header.php');
?>

<div class="container jumbotron mb-5" style="margin-top: 120px;">
  <div class="row">
    <div class="col-md-6">
      <h5 class="mb-3">If Already Registered !! Login</h5>

      <form id="stuLoginForm" onsubmit="return false;">
        <div class="form-group">
          <label for="stuLogEmail" class="pl-2 font-weight-bold">Email</label>
          <input type="email" class="form-control" placeholder="Email" name="stuLogEmail" id="stuLogEmail" autocomplete="email" name="noauto_stu_email" autocomplete="off" autocapitalize="none" spellcheck="false">
        </div>

        <div class="form-group">
          <label for="stuLogPass" class="pl-2 font-weight-bold">Password</label>
          <input type="password" class="form-control" placeholder="Password" name="stuLogPass" id="stuLogPass" autocomplete="current-password" name="noauto_stu_pass" autocomplete="new-password">
        </div>

        <button type="button" class="btn btn-primary" id="stuLoginBtn" onclick="checkStuLogin()">Login</button>
      </form>

      <br />
      <small id="statusLogMsg"></small>
    </div>

    <div class="col-md-6">
      <h5 class="mb-3">New User !! Sign Up</h5>

      <form id="stuRegForm" onsubmit="return false;">
        <div class="form-group">
          <label for="stuname" class="pl-2 font-weight-bold">Name</label>
          <small id="statusMsg1"></small>
          <input type="text" class="form-control" placeholder="Name" name="stuname" id="stuname" autocomplete="name">
        </div>

        <div class="form-group">
          <label for="stuemail" class="pl-2 font-weight-bold">Email</label>
          <small id="statusMsg2"></small>
          <input type="email" class="form-control" placeholder="Email" name="stuemail" id="stuemail" autocomplete="email">
          <small class="form-text">We'll never share your email with anyone else.</small>
        </div>

        <div class="form-group">
          <label for="stupass" class="pl-2 font-weight-bold">New Password</label>
          <small id="statusMsg3"></small>
          <input type="password" class="form-control" placeholder="Password" name="stupass" id="stupass" autocomplete="new-password">
        </div>

        <div class="form-group">
          <label for="preferred_track" class="pl-2 font-weight-bold">Select Track</label>
          <select class="form-control" id="preferred_track" name="preferred_track">
            <option value="Programming">Programming</option>
            <option value="Networking">Networking</option>
            <option value="Artificial Intelligence">Artificial Intelligence</option>
            <option value="Cyber Security">Cyber Security</option>
            <option value="DevOps">DevOps</option>
            <option value="Data Science">Data Science</option>
            <option value="Operating Systems">Operating Systems</option>
            <option value="AWS Cloud">AWS Cloud</option>
          </select>
        </div>

        <div class="form-group">
          <label for="experience_level" class="pl-2 font-weight-bold">Experience Level</label>
          <select class="form-control" id="experience_level" name="experience_level">
            <option value="Beginner">Beginner</option>
            <option value="Intermediate">Intermediate</option>
            <option value="Advanced">Advanced</option>
          </select>
        </div>

        <button type="button" class="btn btn-primary" id="signup" onclick="addStu()">Sign Up</button>
      </form>

      <br />
      <small id="successMsg"></small>
      <br />
      <small id="jsHealth" style="color:#b00;"></small>
    </div>
  </div>
</div>

<?php
include('./mainInclude/footer.php');
?>

<script src="js/jquery.min.js"></script>
<script src="js/ajaxrequest.js?v=2"></script>
<script>
  (function () {
    var ok = (typeof window.addStu === 'function');
    if (!ok) {
      var el = document.getElementById('jsHealth');
      if (el) el.textContent = 'JS not loaded: addStu() is missing. Check js/ajaxrequest.js.';
      if (console && console.log) console.log('addStu is missing. ajaxrequest.js not loaded or has errors.');
    }
  })();
</script>
