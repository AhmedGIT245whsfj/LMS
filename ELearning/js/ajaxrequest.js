/* ITVERSE - AJAX helpers */
(function () {
  "use strict";

  function txt(v) {
    return String(v == null ? "" : v).trim();
  }

  function setHtml(id, html) {
    var el = document.getElementById(id);
    if (el) el.innerHTML = html;
  }

  function showError(msg) {
    setHtml("successMsg", '<span style="color:red;">' + msg + '</span>');
  }

  window.addStu = function addStu() {
    try {
      var $ = window.jQuery;
      if (!$) {
        showError("jQuery not loaded");
        return;
      }

      var stuname = txt($("#stuname").val());
      var stuemail = txt($("#stuemail").val());
      var stupass = txt($("#stupass").val());
      var preferred_track = txt($("#preferred_track option:selected").text() || $("#preferred_track").val());
      var experience_level = txt($("#experience_level").val());

      $("#statusMsg1, #statusMsg2, #statusMsg3, #successMsg").html("");

      if (!stuname) {
        $("#statusMsg1").html('<small style="color:red;">Please enter name</small>');
        return;
      }
      if (!stuemail) {
        $("#statusMsg2").html('<small style="color:red;">Please enter email</small>');
        return;
      }
      if (!stupass || stupass.length < 6) {
        $("#statusMsg3").html('<small style="color:red;">Password must be at least 6 characters</small>');
        return;
      }

      $.ajax({
        url: "/Student/addstudent.php",
        type: "POST",
        dataType: "text",
        data: {
          stusignup: 1,
          stuname: stuname,
          stuemail: stuemail,
          stupass: stupass,
          preferred_track: preferred_track,
          experience_level: experience_level
        },
        success: function (data) {
          var resp = txt(data);
          if (resp === "OK" || resp === "1") {
            $("#successMsg").html('<span style="color:green;">Registration successful. You can login now.</span>');
            var f = document.getElementById("stuRegForm");
            if (f) f.reset();
          } else if (resp === "Failed" || resp === "0") {
            $("#successMsg").html('<span style="color:red;">Email already registered.</span>');
          } else {
            $("#successMsg").html('<span style="color:red;">' + resp + '</span>');
          }
        },
        error: function (xhr) {
          var body = xhr && xhr.responseText ? String(xhr.responseText).slice(0, 500) : "";
          showError("Signup request failed: HTTP " + (xhr ? xhr.status : 0) + (body ? " | " + body : ""));
        }
      });
    } catch (e) {
      showError("JS exception: " + e);
    }
  };

  window.checkStuLogin = function checkStuLogin() {
    try {
      var $ = window.jQuery;
      if (!$) return;

      var email = txt($("#stuLogEmail").val());
      var pass = txt($("#stuLogPass").val());
      $("#statusLogMsg").html("");

      if (!email || !pass) {
        $("#statusLogMsg").html('<small style="color:red;">Please enter email and password</small>');
        return;
      }

      $.ajax({
        url: "Student/stulogin.php",
        type: "POST",
        dataType: "text",
        data: { checkLogemail: email, checkLogpass: pass },
        success: function (data) {
          var resp = txt(data);
          if (resp === "OK" || resp === "1") {
            window.location.href = "Student/myCourse.php";
          } else {
            $("#statusLogMsg").html('<small style="color:red;">Login failed</small>');
          }
        },
        error: function (xhr) {
          var body = xhr && xhr.responseText ? String(xhr.responseText).slice(0, 500) : "";
          $("#statusLogMsg").html('<small style="color:red;">HTTP ' + (xhr ? xhr.status : 0) + (body ? " | " + body : "") + '</small>');
        }
      });
    } catch (e) {
      $("#statusLogMsg").html('<small style="color:red;">' + e + '</small>');
    }
  };
})();
