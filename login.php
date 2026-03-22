<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

if (isLoggedIn()) {
  header('Location: ' . BASE_URL . (isAdmin() ? '/admin/index.php' : '/dashboard.php'));
  exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = strtolower(trim($_POST['email'] ?? ''));
  $password = $_POST['password'] ?? '';

  if (!isValidEmailAddress($email) || strlen($password) < 8 || strlen($password) > 64) {
    $error = 'Please fill in all fields.';
  } else {
    $stmt = $conn->prepare("SELECT id,name,email,password,role,is_active FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user || !password_verify($password, $user['password'])) {
      $error = 'Invalid email or password.';
    } elseif (!$user['is_active']) {
      $error = 'Your account has been deactivated. Contact support.';
    } else {
      session_regenerate_id(true);
      $_SESSION['user_id'] = $user['id'];
      $_SESSION['user_name'] = $user['name'];
      $_SESSION['role'] = $user['role'];
      header('Location: ' . BASE_URL . (in_array($user['role'], ['admin', 'staff']) ? '/admin/index.php' : '/dashboard.php'));
      exit;
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Sign In — ServiceHub</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="auth-body">

  <!-- Left Panel -->
  <div class="auth-left">
    <div class="auth-left-inner">
      <div class="auth-brand">
        <div class="logo-icon"><i class="fas fa-headset"></i></div>
        <span>ServiceHub</span>
      </div>
      <h2 class="auth-tagline">Welcome<br>back to <span>ServiceHub</span></h2>
      <p class="auth-desc">The smart platform to submit, track and resolve service requests — all in one place.</p>
      <div class="auth-features">
        <div class="auth-feature"><i class="fas fa-check"></i> Real-time request tracking</div>
        <div class="auth-feature"><i class="fas fa-bell"></i> Instant status notifications</div>
        <div class="auth-feature"><i class="fas fa-chart-bar"></i> Analytics & reporting</div>
        <div class="auth-feature"><i class="fas fa-shield-alt"></i> Secure, role-based access</div>
      </div>
    </div>
  </div>

  <!-- Right: Form -->
  <div class="auth-right">
    <div class="auth-form-box fade-in">
      <h2>Sign In</h2>
      <p class="subtitle">Enter your credentials to access your account</p>

      <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="" data-validate>
        <div class="form-group">
          <label for="email">Email Address</label>
          <input type="email" id="email" name="email" class="form-control" placeholder="you@example.com"
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autocomplete="email" maxlength="120"
            data-rule="email">
          <div class="form-error"></div>
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <div style="position:relative">
            <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required
              autocomplete="current-password" minlength="8" maxlength="64" data-rule="login-password">
            <button type="button" onclick="togglePwd()"
              style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--gray-400);cursor:pointer;"><i
                class="fas fa-eye" id="eyeIcon"></i></button>
          </div>
          <div class="form-error"></div>
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:8px">
          <i class="fas fa-sign-in-alt"></i> Sign In
        </button>
      </form>

      <div class="auth-footer-text">
        Don't have an account? <a href="register.php">Create one &rarr;</a>
      </div>

      <div style="text-align:center;margin-top:12px">
        <a href="index.php" style="font-size:.82rem;color:var(--gray-500)"><i class="fas fa-arrow-left"></i> Back to
          Home</a>
      </div>
    </div>
  </div>

  <script src="assets/js/main.js"></script>
  <script>
    function togglePwd() {
      const inp = document.getElementById('password');
      const ico = document.getElementById('eyeIcon');
      if (inp.type === 'password') { inp.type = 'text'; ico.className = 'fas fa-eye-slash'; }
      else { inp.type = 'password'; ico.className = 'fas fa-eye'; }
    }
  </script>
</body>

</html>