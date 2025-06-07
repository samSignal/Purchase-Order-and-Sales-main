<?php require_once('../config.php') ?>
<!DOCTYPE html>
<html lang="en" class="" style="height: auto;">
<?php require_once('inc/header.php') ?>
<body class="hold-transition login-page">
  <script>
    start_loader()
  </script>
  <style>
    body {
      background-image: url("<?php echo validate_image($_settings->info('cover')) ?>");
      background-size: cover;
      background-repeat: no-repeat;
      background-position: center;
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .login-box {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      border-radius: 20px;
      padding: 20px;
      box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
      border: 1px solid rgba(255, 255, 255, 0.18);
      width: 400px;
    }
    .card {
      background: transparent !important;
      border: none !important;
      box-shadow: none !important;
    }
    .login-title {
      color: #fff;
      font-size: 2.5rem;
      text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
      margin-bottom: 2rem;
    }
    .input-group {
      background: rgba(255, 255, 255, 0.2);
      border-radius: 10px;
      overflow: hidden;
      margin-bottom: 1.5rem !important;
    }
    .form-control {
      background: transparent !important;
      border: none !important;
      color: #fff !important;
      padding: 12px 15px;
    }
    .form-control::placeholder {
      color: rgba(255, 255, 255, 0.7);
    }
    .input-group-text {
      background: transparent !important;
      border: none !important;
      color: #fff;
    }
    .btn-primary {
      width: 100%;
      padding: 12px;
      border-radius: 10px;
      background: linear-gradient(45deg, #4481eb, #04befe);
      border: none;
      font-weight: bold;
      text-transform: uppercase;
      letter-spacing: 1px;
      transition: transform 0.3s ease;
    }
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(68, 129, 235, 0.4);
    }
    .card-header {
      border-bottom: none !important;
    }
    .card-header a.h1 {
      color: #fff;
      font-size: 2rem;
      text-decoration: none;
    }
    .login-box-msg {
      color: rgba(255, 255, 255, 0.8);
      margin-bottom: 2rem;
    }
  </style>

  <div class="login-box">
    <div class="card card-outline card-primary">
      <div class="card-header text-center">
        <a href="./" class="h1"><b>Login</b></a>
      </div>
      <div class="card-body">
        <p class="login-box-msg">Sign in to start your session</p>

        <form id="login-frm" action="" method="post">
          <div class="input-group">
            <input type="text" class="form-control" autofocus name="username" placeholder="Username">
            <div class="input-group-append">
              <div class="input-group-text">
                <span class="fas fa-user"></span>
              </div>
            </div>
          </div>
          <div class="input-group">
            <input type="password" class="form-control" name="password" placeholder="Password">
            <div class="input-group-append">
              <div class="input-group-text">
                <span class="fas fa-lock"></span>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-12">
              <button type="submit" class="btn btn-primary">Sign In</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- jQuery -->
  <script src="plugins/jquery/jquery.min.js"></script>
  <!-- Bootstrap 4 -->
  <script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
  <!-- AdminLTE App -->
  <script src="dist/js/adminlte.min.js"></script>

  <script>
    $(document).ready(function(){
      end_loader();
    })
  </script>
</body>
</html>