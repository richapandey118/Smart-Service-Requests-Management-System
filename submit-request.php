<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
requireLogin();

$uid = (int) $_SESSION['user_id'];
$user = currentUser();
$unread = unreadNotifications();
$error = '';
$success = '';

// Fetch categories
$categories = $conn->query("SELECT * FROM categories WHERE is_active=1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $catId = (int) ($_POST['category_id'] ?? 0);
  $title = normalizeText($_POST['title'] ?? '');
  $desc = normalizeMultilineText($_POST['description'] ?? '');
  $priority = trim($_POST['priority'] ?? 'medium');
  $location = normalizeText($_POST['location'] ?? '');
  $allowed_priorities = ['low', 'medium', 'high', 'urgent'];
  $categoryIds = array_map(static fn($cat) => (int) $cat['id'], $categories);

  if (!$catId || !in_array($catId, $categoryIds, true)) {
    $error = 'Please select a valid category.';
  } elseif (!isValidRequestTitle($title)) {
    $error = 'Enter a valid request title with at least 5 characters.';
  } elseif (!isValidTextBlock($desc, 10, 2000)) {
    $error = 'Description must be 10-2000 characters and use valid text only.';
  } elseif (!isValidLocation($location)) {
    $error = 'Enter a valid location.';
  } elseif (!in_array($priority, $allowed_priorities, true)) {
    $error = 'Invalid priority selection.';
  } else {
    $ticketId = generateTicketId();
    $attachment = null;

    // Handle file upload
    if (!empty($_FILES['attachment']['name'])) {
      $uploadDir = __DIR__ . '/assets/uploads/';
      if (!is_dir($uploadDir))
        mkdir($uploadDir, 0755, true);

      $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip'];
      $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
      $maxSize = 5 * 1024 * 1024; // 5MB

      if (!in_array($ext, $allowed, true)) {
        $error = 'File type not allowed. Allowed: ' . implode(', ', $allowed);
      } elseif ($_FILES['attachment']['size'] > $maxSize) {
        $error = 'File too large. Max 5MB allowed.';
      } elseif ($_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
        $error = 'File upload error.';
      } else {
        $filename = $ticketId . '_' . time() . '.' . $ext;
        $uploadPath = $uploadDir . $filename;
        if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadPath)) {
          $error = 'Failed to save file.';
        } else {
          $attachment = $filename;
        }
      }
    }

    if (!$error) {
      $stmt = $conn->prepare("INSERT INTO service_requests (ticket_id,user_id,category_id,title,description,priority,location,attachment) VALUES (?,?,?,?,?,?,?,?)");
      $catVal = $catId ?: null;
      $stmt->bind_param('siisssss', $ticketId, $uid, $catVal, $title, $desc, $priority, $location, $attachment);
      if ($stmt->execute()) {
        $newId = $conn->insert_id;
        // Notify admin
        $adm = $conn->query("SELECT id FROM users WHERE role='admin' LIMIT 1")->fetch_assoc();
        if ($adm) {
          $msg = "New request $ticketId submitted by {$user['name']}.";
          $n = $conn->prepare("INSERT INTO notifications (user_id,request_id,message,type) VALUES (?,?,?,'new_request')");
          $n->bind_param('iis', $adm['id'], $newId, $msg);
          $n->execute();
        }
        header("Location: " . BASE_URL . "/view-request.php?id=$newId&submitted=1");
        exit;
      } else {
        $error = 'Failed to submit request. Please try again.';
      }
    }
  }
}
$firstName = explode(' ', $user['name'])[0];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Submit Request — ServiceHub</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="app-body">

  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <div class="sidebar-logo"><i class="fas fa-headset"></i> ServiceHub</div>
      <button class="sidebar-close" id="sidebarClose"><i class="fas fa-times"></i></button>
    </div>
    <p class="sidebar-section">Main Menu</p>
    <nav class="sidebar-nav">
      <a href="dashboard.php" class="nav-item"><i class="fas fa-home"></i><span>Dashboard</span></a>
      <a href="submit-request.php" class="nav-item active"><i class="fas fa-plus-circle"></i><span>New
          Request</span></a>
      <a href="profile.php" class="nav-item"><i class="fas fa-user"></i><span>Profile</span></a>
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
        <h1>Submit New Request</h1>
        <p><a href="dashboard.php" style="color:var(--primary)">Dashboard</a> / New Request</p>
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
            <a class="dropdown-item" href="profile.php"><i class="fas fa-user"></i> Profile</a>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="logout.php" style="color:var(--danger)"><i class="fas fa-sign-out-alt"></i>
              Sign Out</a>
          </div>
        </div>
      </div>
    </header>

    <main class="content">
      <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data" id="requestForm" data-validate>
        <input type="hidden" name="category_id" id="selectedCategory" value="">

        <!-- Step 1: Category -->
        <div class="card mb-24">
          <div class="card-header">
            <h3><span
                style="background:var(--primary);color:#fff;width:24px;height:24px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.75rem;margin-right:8px">1</span>
              Choose a Category</h3>
          </div>
          <div class="card-body">
            <div class="category-grid">
              <?php foreach ($categories as $cat): ?>
                <div class="category-card" data-cat-id="<?= $cat['id'] ?>">
                  <div class="cat-icon"
                    style="background:<?= htmlspecialchars($cat['color']) ?>20;color:<?= htmlspecialchars($cat['color']) ?>">
                    <i class="fas <?= htmlspecialchars($cat['icon']) ?>"></i>
                  </div>
                  <div class="cat-name"><?= htmlspecialchars($cat['name']) ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- Step 2: Details -->
        <div class="card mb-24">
          <div class="card-header">
            <h3><span
                style="background:var(--primary);color:#fff;width:24px;height:24px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.75rem;margin-right:8px">2</span>
              Request Details</h3>
          </div>
          <div class="card-body">
            <div class="form-group">
              <label for="title">Request Title <span style="color:var(--danger)">*</span></label>
              <input type="text" name="title" id="title" class="form-control"
                placeholder="e.g. Laptop screen not working in Room 201"
                value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required minlength="5" maxlength="255"
                data-rule="request-title">
              <div class="form-error"></div>
              <div class="form-hint">Be specific so support staff can quickly understand your issue.</div>
            </div>
            <div class="form-group">
              <label for="description">Description <span style="color:var(--danger)">*</span></label>
              <textarea name="description" id="description" class="form-control" rows="5"
                placeholder="Describe the issue in detail — what happened, when it started, any error messages, steps to reproduce…"
                required minlength="10" maxlength="2000"
                data-rule="text-block"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
              <div class="form-error"></div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="priority">Priority Level <span style="color:var(--danger)">*</span></label>
                <select name="priority" id="priority" class="form-control" required>
                  <option value="low" <?= ($_POST['priority'] ?? '') == 'low' ? 'selected' : '' ?>>🟢 Low — Not urgent
                  </option>
                  <option value="medium" <?= ($_POST['priority'] ?? 'medium') == 'medium' ? 'selected' : '' ?>>🔵 Medium —
                    Moderate impact</option>
                  <option value="high" <?= ($_POST['priority'] ?? '') == 'high' ? 'selected' : '' ?>>🟠 High — Significant
                    impact
                  </option>
                  <option value="urgent" <?= ($_POST['priority'] ?? '') == 'urgent' ? 'selected' : '' ?>>🔴 Urgent —
                    Critical,
                    immediate attention</option>
                </select>
              </div>
              <div class="form-group">
                <label for="location">Location (Optional)</label>
                <input type="text" name="location" id="location" class="form-control"
                  placeholder="e.g. Building A, Room 203" value="<?= htmlspecialchars($_POST['location'] ?? '') ?>"
                  maxlength="120" data-rule="location">
                <div class="form-error"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Step 3: Attachment -->
        <div class="card mb-24">
          <div class="card-header">
            <h3><span
                style="background:var(--primary);color:#fff;width:24px;height:24px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.75rem;margin-right:8px">3</span>
              Attachment <span style="font-weight:400;color:var(--gray-500);font-size:.8rem">(Optional)</span></h3>
          </div>
          <div class="card-body">
            <div class="file-upload-area">
              <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt,.zip">
              <i class="fas fa-cloud-upload-alt"></i>
              <p><span>Click to upload</span> or drag and drop</p>
              <p style="font-size:.76rem;margin-top:4px">JPG, PNG, PDF, DOC, ZIP · Max 5MB</p>
            </div>
            <div id="filePreview" style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap"></div>
          </div>
        </div>

        <!-- Submit -->
        <div style="display:flex;gap:14px;justify-content:flex-end">
          <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
          <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-paper-plane"></i> Submit
            Request</button>
        </div>
      </form>
    </main>
  </div>

  <div class="toast-container"></div>
  <script src="assets/js/main.js"></script>
  <script>
    // Validate category selection on submit
    document.getElementById('requestForm').addEventListener('submit', function (e) {
      if (!validateFormFields(this, true)) {
        e.preventDefault();
        return;
      }
      const cat = document.getElementById('selectedCategory').value;
      if (!cat) {
        e.preventDefault();
        showToast('Please select a category first', 'error');
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    });
  </script>
</body>

</html>