<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    
     <!-- Bootstrap CSS -->
    <link rel="stylesheet" type="text/css" href="css/bootstrap.min.css">

    <!-- Font Awesome CSS -->
    <link rel="stylesheet" type="text/css" href="css/all.min.css">

    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css?family=Ubuntu" rel="stylesheet">

    <!-- Student Testimonial Owl Slider CSS -->
    <link rel="stylesheet" type="text/css" href="css/owl.min.css">
    <link rel="stylesheet" type="text/css" href="css/owl.theme.min.css">
    <link rel="stylesheet" type="text/css" href="css/testyslider.css">

    <!-- Custom Style CSS -->
    <link rel="stylesheet" type="text/css" href="/css/style.css" />
    <title>ITVERSE</title>
      <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  </head>
  <body>
     <!-- Start Nagigation -->
    <nav class="navbar navbar-expand-sm navbar-dark bg-dark pl-5 fixed-top itv-main-nav">
      <a href="index.php" class="navbar-brand itv-logo">ITVERSE</a>
      <span class="navbar-text itv-tagline">Learn and Implement</span>
      <button type="button" class="navbar-toggler" data-toggle="collapse" data-target="#myMenu">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="myMenu">
        <ul class="navbar-nav pl-5 custom-nav ml-auto align-items-sm-center">
          <li class="nav-item custom-nav-item itv-nav-item"><a href="index.php" class="nav-link itv-nav-link">Home</a></li>
          <li class="nav-item custom-nav-item itv-nav-item"><a href="courses.php" class="nav-link itv-nav-link">Courses</a></li>
          <li class="nav-item custom-nav-item itv-nav-item"><a href="paymentstatus.php" class="nav-link itv-nav-link">Payment Status</a></li>
          <?php 
if (isset($_SESSION['is_login'])){
                echo '<li class="nav-item custom-nav-item itv-nav-item"><a href="/Student/myprofile.php" class="nav-link itv-nav-link">My Profile</a></li> <li class="nav-item custom-nav-item itv-nav-item"><a href="/Student/studentLogout.php" class="nav-link itv-nav-link">Logout</a></li>';
              } else {
                echo '<li class="nav-item custom-nav-item itv-nav-item"><a href="#login" class="nav-link itv-nav-link" data-toggle="modal" data-target="#stuLoginModalCenter">Login</a></li> <li class="nav-item custom-nav-item itv-nav-item"><a href="loginorsignup.php" class="nav-link itv-nav-link">Signup</a></li>';
              }
          ?> 
          <li class="nav-item custom-nav-item itv-nav-item"><a href="#Feedback" class="nav-link itv-nav-link">Feedback</a></li>
          <li class="nav-item custom-nav-item itv-nav-item"><a href="#Contact" class="nav-link itv-nav-link">Contact</a></li>
        </ul>
      </div>
    </nav> <!-- End Navigation -->