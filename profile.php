<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
requireLogin();

$uid = (int) $_SESSION['user_id'];
$user = currentUser();
$unread = unreadNotifications();

// Stats
$myTotal = $conn->query("SELECT COUNT(*) FROM service_requests WHERE user_id=$uid")->fetch_row()[0];
$myResolved = $conn->query("SELECT COUNT(*) FROM service_requests WHERE user_id=$uid AND status IN ('resolved','closed')")->fetch_row()[0];
$myPending = $conn->query("SELECT COUNT(*) FROM service_requests WHERE user_id=$uid AND status IN ('pending','open','in_progress')")->fetch_row()[0];
$firstName = explode(' ', $user['name'])[0];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>My Profile — ServiceHub</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="app-body">

  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <div class="sidebar-logo"><i class="fas fa-headset"></i> ServiceHub</div>
      <button class="sidebar-close" id="sidebarClose"><i class="fas fa-times"></i></button>
    </div>
    <p class="sidebar-section">Menu</p>
    <nav class="sidebar-nav">
      <a href="dashboard.php" class="nav-item"><i class="fas fa-home"></i><span>Dashboard</span></a>
      <a href="submit-request.php" class="nav-item"><i class="fas fa-plus-circle"></i><span>New Request</span></a>
      <a href="profile.php" class="nav-item active"><i class="fas fa-user"></i><span>Profile</span></a>
    </nav>
    <div class="sidebar-footer">
      <div class="user-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
      <div class="user-info">
        <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
        <div class="user-role"><?= ucfirst($user['role']) ?></div>
      </div>
      <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i></a>
    </div>
  </aside>

  <div class="main-wrapper">
    <header class="top-navbar">
      <button class="navbar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
      <div class="navbar-breadcrumb">
        <h1>My Profile</h1>
        <p>Manage your account information</p>
      </div>
      <div class="navbar-actions">
        <div class="dropdown">
          <button class="notif-btn" id="notifBtn" data-dropdown="notifPanel">
            <i class="fas fa-bell"></i>
            <span class="notif-badge" id="notifBadge"
              style="display:<?= $unread > 0 ? 'flex' : 'none' ?>"><?= $unread ?></span>
          </button>
          <div class="notif-panel" id="notifPanel">
            <div class="notif-header">
              <h4>Notifications</h4><a href="#" onclick="markAllRead();return false">Mark all read</a>
            </div>
            <div class="notif-list" id="notifList"></div>
          </div>
        </div>
        <div class="dropdown">
          <div class="user-dropdown-btn" data-dropdown="userMenu">
            <div class="avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
            <span class="uname"><?= htmlspecialchars($firstName) ?></span>
            <i class="fas fa-chevron-down"></i>
          </div>
          <div class="dropdown-menu" id="userMenu">
            <a class="dropdown-item" href="logout.php" style="color:var(--danger)"><i class="fas fa-sign-out-alt"></i>
              Sign Out</a>
          </div>
        </div>
      </div>
    </header>

    <main class="content">
      <div class="grid-2" style="align-items:start;gap:24px">
        <!-- Left: Profile Card + Stats -->
        <div>
          <!-- Profile Card -->
          <div class="card mb-24" style="text-align:center">
            <div class="card-body" style="padding:32px">
              <div
                style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--secondary));display:flex;align-items:center;justify-content:center;color:#fff;font-size:2rem;font-weight:800;margin:0 auto 16px">
                <?= strtoupper(substr($user['name'], 0, 1)) ?>
              </div>
              <h3 style="font-size:1.2rem;font-weight:700;margin-bottom:4px"><?= htmlspecialchars($user['name']) ?></h3>
              <p style="color:var(--gray-500);font-size:.88rem;margin-bottom:8px">
                <?= htmlspecialchars($user['email']) ?>
              </p>
              <span class="badge badge-primary"><?= ucfirst($user['role']) ?></span>
              <?php if ($user['department']): ?>
                <p style="color:var(--gray-500);font-size:.82rem;margin-top:10px"><i class="fas fa-building"></i>
                  <?= htmlspecialchars($user['department']) ?></p>
              <?php endif; ?>
              <?php if ($user['phone']): ?>
                <p style="color:var(--gray-500);font-size:.82rem;margin-top:4px"><i class="fas fa-phone"></i>
                  <?= htmlspecialchars($user['phone']) ?></p>
              <?php endif; ?>
              <p style="color:var(--gray-400);font-size:.78rem;margin-top:12px"><i class="fas fa-calendar"></i> Member
                since <?= date('M Y', strtotime($user['created_at'])) ?></p>
            </div>
          </div>
          <!-- Stats -->
          <div class="card">
            <div class="card-header">
              <h3>My Statistics</h3>
            </div>
            <div class="card-body">
              <div style="display:flex;flex-direction:column;gap:14px">
                <div style="display:flex;justify-content:space-between;align-items:center">
                  <span style="font-size:.88rem;color:var(--gray-600)"><i class="fas fa-ticket-alt"
                      style="color:var(--primary);width:18px"></i> Total Requests</span>
                  <strong style="font-size:1.1rem"><?= $myTotal ?></strong>
                </div>
                <div>
                  <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                    <span style="font-size:.82rem;color:var(--gray-500)">Resolved</span>
                    <span style="font-size:.82rem;font-weight:600;color:var(--success)"><?= $myResolved ?></span>
                  </div>
                  <div class="progress">
                    <div class="progress-bar success"
                      style="width:<?= $myTotal > 0 ? round($myResolved / $myTotal * 100) : 0 ?>%"></div>
                  </div>
                </div>
                <div>
                  <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                    <span style="font-size:.82rem;color:var(--gray-500)">Active</span>
                    <span style="font-size:.82rem;font-weight:600;color:var(--warning)"><?= $myPending ?></span>
                  </div>
                  <div class="progress">
                    <div class="progress-bar warning"
                      style="width:<?= $myTotal > 0 ? round($myPending / $myTotal * 100) : 0 ?>%">
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right: Edit Forms -->
        <div>
          <!-- Edit Profile -->
          <div class="card mb-24">
            <div class="card-header">
              <h3><i class="fas fa-user-edit" style="color:var(--primary)"></i> Edit Profile</h3>
            </div>
            <div class="card-body">
              <form id="profileForm" data-validate>
                <div class="form-group">
                  <label>Full Name</label>
                  <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>"
                    required minlength="2" maxlength="80" data-rule="name">
                  <div class="form-error"></div>
                </div>
                <div class="form-group">
                  <label>Email Address</label>
                  <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled
                    style="background:var(--gray-50);cursor:not-allowed">
                  <div class="form-hint">Email cannot be changed.</div>
                </div>
                <div class="form-row">
                  <div class="form-group">
                    <label>Department</label>
                    <input type="text" name="department" class="form-control"
                      value="<?= htmlspecialchars($user['department'] ?? '') ?>" placeholder="e.g. Engineering"
                      maxlength="80" data-rule="department">
                    <div class="form-error"></div>
                  </div>
                  <div class="form-group">
                    <label>Phone</label>
                    <input type="tel" name="phone" class="form-control"
                      value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="+1-555-0100" maxlength="20"
                      data-rule="phone">
                    <div class="form-error"></div>
                  </div>
                </div>
                <button type="button" class="btn btn-primary" onclick="saveProfile()"><i class="fas fa-save"></i> Save
                  Changes</button>
              </form>
            </div>
          </div>

          <!-- Change Password -->
          <div class="card">
            <div class="card-header">
              <h3><i class="fas fa-lock" style="color:var(--primary)"></i> Change Password</h3>
            </div>
            <div class="card-body">
              <form id="passwordForm" data-validate>
                <div class="form-group">
                  <label>Current Password</label>
                  <input type="password" name="current_password" class="form-control"
                    placeholder="Your current password" required minlength="8" maxlength="64"
                    data-rule="login-password">
                  <div class="form-error"></div>
                </div>
                <div class="form-row">
                  <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" class="form-control"
                      placeholder="8+ chars with upper, lower, number, symbol" required minlength="8" maxlength="64"
                      data-rule="strong-password">
                    <div class="form-error"></div>
                  </div>
                  <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password"
                      required minlength="8" maxlength="64" data-match='[name="new_password"]'>
                    <div class="form-error"></div>
                  </div>
                </div>
                <button type="button" class="btn btn-warning" onclick="changePassword()"><i class="fas fa-key"></i>
                  Update Password</button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <div class="toast-container"></div>
  <script src="assets/js/main.js"></script>
  <script>
    function saveProfile() {
      const form = document.getElementById('profileForm');
      if (!validateFormFields(form, true)) return;
      const data = {
        action: 'update_profile',
        name: form.querySelector('[name="name"]').value,
        department: form.querySelector('[name="department"]').value,
        phone: form.querySelector('[name="phone"]').value
      };
      ajax(data, (err, res) => {
        if (err || !res) { showToast('Error saving profile', 'error'); return; }
        showToast(res.message || (res.success ? 'Profile updated' : 'Error'), res.success ? 'success' : 'error');
      });
    }

    function changePassword() {
      const form = document.getElementById('passwordForm');
      if (!validateFormFields(form, true)) return;
      const data = {
        action: 'change_password',
        current_password: form.querySelector('[name="current_password"]').value,
        new_password: form.querySelector('[name="new_password"]').value,
        confirm_password: form.querySelector('[name="confirm_password"]').value
      };
      ajax(data, (err, res) => {
        if (err || !res) { showToast('Error', 'error'); return; }
        showToast(res.message || (res.success ? 'Password changed' : 'Error'), res.success ? 'success' : 'error');
        if (res.success) form.reset();
      });
    }
  </script>
</body>

</html>