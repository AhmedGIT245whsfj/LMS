$(document).ready(function () {
  $("#stuemail").on("keypress blur", function () {
    var reg = /^[A-Z0-9._%+-]+@([A-Z0-9-]+\.)+[A-Z]{2,}$/i;
    var stuemail = $("#stuemail").val().trim();

    $.ajax({
      url: "Student/addstudent.php",
      type: "post",
      data: {
        checkemail: "checkmail",
        stuemail: stuemail
      },
      success: function (data) {
        if (data != 0 && stuemail !== "") {
          $("#statusMsg2").html('<small style="color:red;"> Email ID Already Registered ! </small>');
          $("#signup").attr("disabled", true);
        } else if (data == 0 && reg.test(stuemail)) {
          $("#statusMsg2").html('<small style="color:green;"> There you go ! </small>');
          $("#signup").attr("disabled", false);
        } else if (!reg.test(stuemail) && stuemail !== "") {
          $("#statusMsg2").html('<small style="color:red;"> Please Enter Valid Email e.g. example@mail.com </small>');
          $("#signup").attr("disabled", false);
        } else if (stuemail === "") {
          $("#statusMsg2").html('<small style="color:red;"> Please Enter Email ! </small>');
        }
      }
    });
  });

  $("#stuname").keypress(function () {
    if ($("#stuname").val() !== "") $("#statusMsg1").html("");
  });

  $("#stupass").keypress(function () {
    if ($("#stupass").val() !== "") $("#statusMsg3").html("");
  });
});

function addStu() {
  try {
    var stuname = $("#stuname").val().trim();
    var stuemail = $("#stuemail").val().trim();
    var stupass = $("#stupass").val();
    var track = $("#preferred_track").val();
    var level = $("#experience_level").val();

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
      url: "Student/addstudent.php",
      method: "POST",
      dataType: "json",
      data: {
        stusignup: 1,
        stuname: stuname,
        stuemail: stuemail,
        stupass: stupass,
        preferred_track: track,
        experience_level: level
      },
      success: function (data) {
        var status = null;
        var message = "";

        if (typeof data === "string") {
          status = data;
        } else if (data && typeof data === "object") {
          status = data.status || data.result || data.code || null;
          message = data.message || "";
        }

        if (status === "OK" || status === "success" || status === "Success") {
          $("#successMsg").html('<span style="color:green;">' + (message || 'Registration successful. You can login now.') + '</span>');
          if ($("#stuRegForm").length) $("#stuRegForm")[0].reset();
        } else if (status === "Failed" || status === "failed") {
          $("#successMsg").html('<span style="color:red;">' + (message || 'Email already registered.') + '</span>');
        } else {
          $("#successMsg").html('<span style="color:red;">' + (message || 'Unexpected response.') + '</span>');
          console.log("addStu response:", data);
        }
      },
      error: function (xhr) {
        var msg = "Request failed.";
        try {
          if (xhr.responseJSON && xhr.responseJSON.message) {
            msg = xhr.responseJSON.message;
          } else if (xhr.responseText) {
            msg = xhr.responseText;
          }
        } catch (e) {}
        $("#successMsg").html('<span style="color:red;">' + msg + '</span>');
        console.log("addStu ajax error:", xhr.status, xhr.responseText);
      }
    });
  } catch (e) {
    console.log("addStu exception:", e);
  }
}

function checkStuLogin() {
  try {
    var email = $("#stuLogEmail").val().trim();
    var pass = $("#stuLogPass").val();

    $("#statusLogMsg").html("");

    if (!email || !pass) {
      $("#statusLogMsg").html('<small style="color:red;">Please enter email and password</small>');
      return;
    }

    $.ajax({
      url: "Student/stulogin.php",
      method: "POST",
      dataType: "json",
      data: {
        checkLogemail: email,
        checkLogpass: pass
      },
      success: function (data) {
        if (data === 1 || data === "1") {
          window.location.href = "index.php";
        } else {
          $("#statusLogMsg").html('<small style="color:red;">Invalid email or password</small>');
        }
      },
      error: function (xhr) {
        $("#statusLogMsg").html('<small style="color:red;">Login request failed. Check console.</small>');
        console.log("login ajax error:", xhr.status, xhr.responseText);
      }
    });
  } catch (e) {
    console.log("checkStuLogin exception:", e);
  }
}
