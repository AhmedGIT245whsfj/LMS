/* ITVERSE - stable AJAX helpers (legacy) */
(function () {
  "use strict";

  function showMsg(selector, html) {
    try { if (window.jQuery) window.jQuery(selector).html(html); } catch (e) {}
  }

  function itvAjaxShowError(msg) {
    try {
      var el = document.getElementById("itvSignupError");
      if (!el) {
        el = document.createElement("div");
        el.id = "itvSignupError";
        el.style.marginTop = "12px";
        el.style.color = "red";
        var target = document.getElementById("successMsg") || document.body;
        target.appendChild(el);
      }
      el.textContent = msg;
    } catch (e) {}
  }

  // Signup (legacy endpoint)
  window.addStu = function addStu() {
    try {
      var $ = window.jQuery;
      if (!$) return;

      var stuname = ($.trim($("#stuname").val() || ""));
      var stuemail = ($.trim($("#stuemail").val() || ""));
      var stupass = ($("#stupass").val() || "");

      $("#statusMsg1, #statusMsg2, #statusMsg3, #successMsg").html("");

      if (!stuname) { $("#statusMsg1").html('<small style="color:red;">Please enter name</small>'); return; }
      if (!stuemail) { $("#statusMsg2").html('<small style="color:red;">Please enter email</small>'); return; }
      if (!stupass || stupass.length < 6) { $("#statusMsg3").html('<small style="color:red;">Password must be at least 6 characters</small>'); return; }

      $.ajax({
        url: "/Student/addstudent.php",
        method: "POST",
        dataType: "text",
        data: { stusignup: 1, stuname: stuname, stuemail: stuemail, stupass: stupass },
        success: function (data) {
          var resp = (data == null) ? "" : String(data).trim();
          if (resp === "OK" || resp === "1") {
            $("#successMsg").html('<span style="color:green;">Registration successful. You can login now.</span>');
            var f = document.getElementById("stuRegForm"); if (f) f.reset();
          } else if (resp === "Failed" || resp === "0") {
            $("#successMsg").html('<span style="color:red;">Email already registered.</span>');
          } else {
            $("#successMsg").html('<span style="color:red;">Unexpected response.</span>');
            console.log("addStu response:", data);
          }
        },
        error: function (xhr) {
          var txt = (xhr && xhr.responseText) ? String(xhr.responseText).slice(0, 300) : "";
          itvAjaxShowError("Request failed: HTTP " + (xhr ? xhr.status : 0) + (txt ? " | " + txt : ""));
        }
      });
    } catch (e) {
      console.log("addStu exception:", e);
    }
  };

  // Login (legacy)
  window.checkStuLogin = function checkStuLogin() {
    try {
      var $ = window.jQuery;
      if (!$) return;

      var email = $.trim($("#stuLogEmail").val() || "");
      var pass = ($("#stuLogPass").val() || "");
      $("#statusLogMsg").html("");

      if (!email || !pass) {
        $("#statusLogMsg").html('<small style="color:red;">Please enter email and password</small>');
        return;
      }

      $.ajax({
        url: "Student/stulogin.php",
        method: "POST",
        dataType: "text",
        data: { checkLogemail: email, checkLogpass: pass },
        success: function (data) {
          var resp = (data == null) ? "" : String(data).trim();
          if (resp === "OK" || resp === "1") {
            window.location.href = "Student/myCourse.php";
          } else {
            $("#statusLogMsg").html('<small style="color:red;">Login failed</small>');
          }
        },
        error: function (xhr) {
          var txt = (xhr && xhr.responseText) ? String(xhr.responseText).slice(0, 300) : "";
          itvAjaxShowError("Login request failed: HTTP " + (xhr ? xhr.status : 0) + (txt ? " | " + txt : ""));
        }
      });
    } catch (e) {
      console.log("checkStuLogin exception:", e);
    }
  };
})();
