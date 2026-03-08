(function () {
  function txt(v) { return (v === undefined || v === null) ? "" : String(v); }
  function trim(v) { return txt(v).trim(); }

  function showMsg(elId, html) {
    var el = document.getElementById(elId);
    if (el) el.innerHTML = html;
  }

  function ensureErrorBox() {
    var el = document.getElementById("itvSignupError");
    if (!el) {
      el = document.createElement("div");
      el.id = "itvSignupError";
      el.style.marginTop = "12px";
      el.style.color = "red";
      var target = document.getElementById("successMsg") || document.body;
      target.appendChild(el);
    }
    return el;
  }

  window.itvAjaxShowError = function (msg) {
    try { ensureErrorBox().textContent = txt(msg); } catch (e) {}
  };

  window.addStu = function () {
    try {
      var stuname = trim($("#stuname").val());
      var stuemail = trim($("#stuemail").val());
      var stupass = txt($("#stupass").val());
      var track = txt($("#preferred_track").val());
      var level = txt($("#experience_level").val());

      showMsg("statusMsg1", "");
      showMsg("statusMsg2", "");
      showMsg("statusMsg3", "");
      showMsg("successMsg", "");
      window.itvAjaxShowError("");

      if (!stuname) { showMsg("statusMsg1", '<small style="color:red;">Please enter name</small>'); return; }
      if (!stuemail) { showMsg("statusMsg2", '<small style="color:red;">Please enter email</small>'); return; }
      if (!stupass || stupass.length < 6) { showMsg("statusMsg3", '<small style="color:red;">Password must be at least 6 characters</small>'); return; }

      $.ajax({
        url: "/Student/addstudent_v2.php",
        method: "POST",
        dataType: "text",
        data: {
          stusignup: 1,
          stuname: stuname,
          stuemail: stuemail,
          stupass: stupass,
          preferred_track_id: track,
          experience_level: level
        },
        success: function (data) {
          var resp = trim(data);
          if (resp === "1" || resp.toUpperCase() === "OK") {
            showMsg("successMsg", '<span style="color:green;">Registration successful. You can login now.</span>');
            var f = document.getElementById("stuRegForm");
            if (f) f.reset();
          } else if (resp === "0" || resp.toLowerCase() === "failed") {
            showMsg("successMsg", '<span style="color:red;">Registration failed. Email may be already registered.</span>');
          } else {
            showMsg("successMsg", '<span style="color:red;">Unexpected response.</span>');
            console.log("addStu response:", data);
          }
        },
        error: function (xhr) {
          var code = xhr ? xhr.status : 0;
          var body = xhr && xhr.responseText ? String(xhr.responseText).slice(0, 400) : "";
          window.itvAjaxShowError("Signup request failed: HTTP " + code + (body ? (" | " + body) : ""));
        }
      });
    } catch (e) {
      console.log("addStu exception:", e);
      window.itvAjaxShowError("Signup exception: " + e);
    }
  };

  window.checkStuLogin = function () {
    try {
      var email = trim($("#stuLogEmail").val());
      var pass = txt($("#stuLogPass").val());

      showMsg("statusLogMsg", "");
      showMsg("successMsg", "");
      window.itvAjaxShowError("");

      if (!email || !pass) {
        showMsg("statusLogMsg", '<small style="color:red;">Please enter email and password</small>');
        return;
      }

      $.ajax({
        url: "/Student/stulogin.php",
        method: "POST",
        dataType: "text",
        data: { checkLogemail: email, checkLogpass: pass },
        success: function (data) {
          var resp = trim(data);
          if (resp === "1" || resp.toUpperCase() === "OK") {
            window.location.href = "/Student/myprofile.php";
          } else {
            showMsg("statusLogMsg", '<small style="color:red;">Invalid email or password</small>');
          }
        },
        error: function (xhr) {
          var code = xhr ? xhr.status : 0;
          var body = xhr && xhr.responseText ? String(xhr.responseText).slice(0, 400) : "";
          window.itvAjaxShowError("Login request failed: HTTP " + code + (body ? (" | " + body) : ""));
        }
      });
    } catch (e) {
      console.log("checkStuLogin exception:", e);
      window.itvAjaxShowError("Login exception: " + e);
    }
  };

  $(document).on("keydown", "input", function (e) {
    if (e.key === "Enter") {
      var id = (e.target && e.target.id) ? e.target.id : "";
      if (id === "stuname" || id === "stuemail" || id === "stupass" || id === "stuLogEmail" || id === "stuLogPass") {
        e.preventDefault();
        return false;
      }
    }
  });
})();
