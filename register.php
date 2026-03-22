<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

if (isLoggedIn()) {
  header('Location: ' . BASE_URL . '/dashboard.php');
  exit;
}

$errors = [];
$success = false;
$data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = normalizeText($_POST['name'] ?? '');
  $email = strtolower(trim($_POST['email'] ?? ''));
  $dept = normalizeText($_POST['department'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $password = $_POST['password'] ?? '';
  $confirm = $_POST['confirm'] ?? '';
  $data = compact('name', 'email', 'dept', 'phone');

  if (!isValidPersonName($name))
    $errors['name'] = 'Enter a valid full name using letters, spaces, apostrophes, dots or hyphens.';
  if (!isValidEmailAddress($email))
    $errors['email'] = 'Enter a valid email address.';
  if (!isValidDepartment($dept))
    $errors['department'] = 'Department can contain letters, numbers and basic symbols only.';
  if (!isValidPhone($phone))
    $errors['phone'] = 'Enter a valid phone number.';
  if (!isStrongPassword($password))
    $errors['password'] = 'Password must be 8-64 chars and include uppercase, lowercase, number and special character.';
  if ($password !== $confirm)
    $errors['confirm'] = 'Passwords do not match.';

  if (empty($errors)) {
    $chk = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
    $chk->bind_param('s', $email);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
      $errors['email'] = 'Email already registered.';
    } else {
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $stmt = $conn->prepare("INSERT INTO users (name,email,password,role,department,phone) VALUES (?,?,?,'user',?,?)");
      $stmt->bind_param('sssss', $name, $email, $hash, $dept, $phone);
      if ($stmt->execute()) {
        $newId = $conn->insert_id;
        session_regenerate_id(true);
        $_SESSION['user_id'] = $newId;
        $_SESSION['user_name'] = $name;
        $_SESSION['role'] = 'user';
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
      } else {
        $errors['general'] = 'Registration failed. Please try again.';
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Create Account — ServiceHub</title>
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
      <h2 class="auth-tagline">Join <span>ServiceHub</span><br>Today</h2>
      <p class="auth-desc">Create your account and start submitting service requests with full tracking and real-time
        notifications.</p>
      <div class="auth-features">
        <div class="auth-feature"><i class="fas fa-user-plus"></i> Free to register</div>
        <div class="auth-feature"><i class="fas fa-paper-plane"></i> Submit requests instantly</div>
        <div class="auth-feature"><i class="fas fa-eye"></i> Track every request live</div>
        <div class="auth-feature"><i class="fas fa-comments"></i> Communicate with support</div>
      </div>
    </div>
  </div>

  <!-- Right: Form -->
  <div class="auth-right">
    <div class="auth-form-box fade-in">
      <h2>Create Account</h2>
      <p class="subtitle">Fill in the details below to get started</p>

      <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-danger"><i class="fas fa-times-circle"></i> <?= htmlspecialchars($errors['general']) ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="" data-validate>
        <div class="form-row">
          <div class="form-group">
            <label for="name">Full Name <span style="color:var(--danger)">*</span></label>
            <input type="text" id="name" name="name" class="form-control <?= isset($errors['name']) ? 'error' : '' ?>"
              placeholder="Enter Full Name" value="<?= htmlspecialchars($data['name'] ?? '') ?>" required minlength="2"
              maxlength="80" data-rule="name">
            <?php if (isset($errors['name'])): ?>
              <div class="form-error"><?= htmlspecialchars($errors['name']) ?></div><?php endif; ?>
          </div>
          <div class="form-group">
            <label for="email">Email Address <span style="color:var(--danger)">*</span></label>
            <input type="email" id="email" name="email"
              class="form-control <?= isset($errors['email']) ? 'error' : '' ?>" placeholder="you@example.com"
              value="<?= htmlspecialchars($data['email'] ?? '') ?>" required maxlength="120" data-rule="email">
            <?php if (isset($errors['email'])): ?>
              <div class="form-error"><?= htmlspecialchars($errors['email']) ?></div><?php endif; ?>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="department">Department</label>
            <input type="text" id="department" name="department"
              class="form-control <?= isset($errors['department']) ? 'error' : '' ?>" placeholder="e.g. Engineering"
              value="<?= htmlspecialchars($data['dept'] ?? '') ?>" maxlength="80" data-rule="department">
            <?php if (isset($errors['department'])): ?>
              <div class="form-error"><?= htmlspecialchars($errors['department']) ?></div><?php endif; ?>
          </div>
          <div class="form-group">
            <label for="phone">Phone Number</label>
            <input type="tel" id="phone" name="phone" class="form-control <?= isset($errors['phone']) ? 'error' : '' ?>"
              placeholder="xxxxx xxxxx" value="<?= htmlspecialchars($data['phone'] ?? '') ?>" maxlength="20"
              data-rule="phone">
            <?php if (isset($errors['phone'])): ?>
              <div class="form-error"><?= htmlspecialchars($errors['phone']) ?></div><?php endif; ?>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="password">Password <span style="color:var(--danger)">*</span></label>
            <input type="password" id="password" name="password"
              class="form-control <?= isset($errors['password']) ? 'error' : '' ?>"
              placeholder="8+ chars with upper, lower, number, symbol" required minlength="8" maxlength="64"
              data-rule="strong-password">
            <?php if (isset($errors['password'])): ?>
              <div class="form-error"><?= htmlspecialchars($errors['password']) ?></div><?php endif; ?>
          </div>
          <div class="form-group">
            <label for="confirm">Confirm Password <span style="color:var(--danger)">*</span></label>
            <input type="password" id="confirm" name="confirm"
              class="form-control <?= isset($errors['confirm']) ? 'error' : '' ?>" placeholder="Repeat password"
              required minlength="8" maxlength="64" data-match="#password">
            <?php if (isset($errors['confirm'])): ?>
              <div class="form-error"><?= htmlspecialchars($errors['confirm']) ?></div><?php endif; ?>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:4px">
          <i class="fas fa-user-plus"></i> Create Account
        </button>
      </form>
      <div class="auth-footer-text">
        Already have an account? <a href="login.php">Sign In &rarr;</a>
      </div>
      <div style="text-align:center;margin-top:12px">
        <a href="index.php" style="font-size:.82rem;color:var(--gray-500)"><i class="fas fa-arrow-left"></i> Back to
          Home</a>
      </div>
    </div>
  </div>

  <script src="assets/js/main.js"></script>
</body>

</html>