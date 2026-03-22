<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
requireLogin();

$uid = (int) $_SESSION['user_id'];
$user = currentUser();

// Stats
$total = $conn->query("SELECT COUNT(*) FROM service_requests WHERE user_id=$uid")->fetch_row()[0];
$pending = $conn->query("SELECT COUNT(*) FROM service_requests WHERE user_id=$uid AND status IN ('pending','open')")->fetch_row()[0];
$progress = $conn->query("SELECT COUNT(*) FROM service_requests WHERE user_id=$uid AND status='in_progress'")->fetch_row()[0];
$resolved = $conn->query("SELECT COUNT(*) FROM service_requests WHERE user_id=$uid AND status IN ('resolved','closed')")->fetch_row()[0];

// Recent requests
$stmt = $conn->prepare("SELECT sr.*,c.name AS cat_name,c.color AS cat_color FROM service_requests sr LEFT JOIN categories c ON c.id=sr.category_id WHERE sr.user_id=? ORDER BY sr.created_at DESC LIMIT 8");
$stmt->bind_param('i', $uid);
$stmt->execute();
$requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$unread = unreadNotifications();
$firstName = explode(' ', $user['name'])[0];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Dashboard — ServiceHub</title>
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
      <a href="dashboard.php" class="nav-item active"><i class="fas fa-home"></i><span>Dashboard</span></a>
      <a href="submit-request.php" class="nav-item"><i class="fas fa-plus-circle"></i><span>New Request</span></a>
      <a href="dashboard.php?filter=all" class="nav-item"><i class="fas fa-list"></i><span>My Requests</span></a>
      <a href="profile.php" class="nav-item"><i class="fas fa-user"></i><span>Profile</span></a>
    </nav>
    <p class="sidebar-section">Status</p>
    <nav class="sidebar-nav">
      <a href="dashboard.php?filter=pending" class="nav-item"><i class="fas fa-clock"></i><span>Pending</span>
        <?php if ($pending > 0): ?><span class="badge-count">
            <?= $pending ?>
          </span>
        <?php endif; ?>
      </a>
      <a href="dashboard.php?filter=in_progress" class="nav-item"><i class="fas fa-spinner"></i><span>In Progress</span>
        <?php if ($progress > 0): ?><span class="badge-count">
            <?= $progress ?>
          </span>
        <?php endif; ?>
      </a>
      <a href="dashboard.php?filter=resolved" class="nav-item"><i
          class="fas fa-check-circle"></i><span>Resolved</span></a>
    </nav>
    <div class="sidebar-footer">
      <div class="user-avatar">
        <?= strtoupper(substr($user['name'], 0, 1)) ?>
      </div>
      <div class="user-info">
        <div class="user-name">
          <?= htmlspecialchars($user['name']) ?>
        </div>
        <div class="user-role">
          <?= ucfirst($user['role']) ?>
        </div>
      </div>
      <a href="logout.php" class="logout-btn" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
    </div>
  </aside>

  <!-- Main -->
  <div class="main-wrapper">
    <header class="top-navbar">
      <button class="navbar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
      <div class="navbar-breadcrumb">
        <h1>Dashboard</h1>
        <p>Welcome back,
          <?= htmlspecialchars($firstName) ?>!
        </p>
      </div>
      <div class="navbar-actions">
        <!-- Notifications -->
        <div class="dropdown">
          <button class="notif-btn" id="notifBtn" data-dropdown="notifPanel">
            <i class="fas fa-bell"></i>
            <span class="notif-badge" id="notifBadge" style="display:<?= $unread > 0 ? 'flex' : 'none' ?>">
              <?= $unread ?>
            </span>
          </button>
          <div class="notif-panel" id="notifPanel">
            <div class="notif-header">
              <h4><i class="fas fa-bell"></i> Notifications</h4>
              <a href="#" onclick="markAllRead();return false">Mark all read</a>
            </div>
            <div class="notif-list" id="notifList">
              <div class="notif-empty"><i class="far fa-bell"></i>
                <p> Click bell to load</p>
              </div>
            </div>
          </div>
        </div>
        <!-- User Menu -->
        <div class="dropdown">
          <div class="user-dropdown-btn" data-dropdown="userMenu">
            <div class="avatar">
              <?= strtoupper(substr($user['name'], 0, 1)) ?>
            </div>
            <span class="uname">
              <?= htmlspecialchars($firstName) ?>
            </span>
            <i class="fas fa-chevron-down"></i>
          </div>
          <div class="dropdown-menu" id="userMenu">
            <a class="dropdown-item" href="profile.php"><i class="fas fa-user"></i> Profile</a>
            <a class="dropdown-item" href="submit-request.php"><i class="fas fa-plus"></i> New Request</a>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="logout.php" style="color:var(--danger)"><i class="fas fa-sign-out-alt"></i>
              Sign Out</a>
          </div>
        </div>
      </div>
    </header>

    <main class="content">
      <!-- Welcome Banner -->
      <div
        style="background:linear-gradient(135deg,var(--primary),var(--secondary));border-radius:var(--radius);padding:24px 28px;color:#fff;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px">
        <div>
          <h2 style="font-size:1.3rem;margin-bottom:4px">Good
            <?= date('H') < 12 ? 'Morning' : (date('H') < 18 ? 'Afternoon' : 'Evening') ?>,
            <?= htmlspecialchars($firstName) ?>! 👋
          </h2>
          <p style="opacity:.85;font-size:.9rem">You have <strong>
              <?= $pending + $progress ?>
            </strong> active request
            <?= ($pending + $progress) != 1 ? 's' : '' ?> awaiting attention.
          </p>
        </div>
        <a href="submit-request.php" class="btn"
          style="background:rgba(255,255,255,.2);color:#fff;border:2px solid rgba(255,255,255,.4);"><i
            class="fas fa-plus"></i> New Request</a>
      </div>

      <!-- Stats Grid -->
      <div class="stats-grid">
        <div class="stat-card total">
          <div class="stat-icon"><i class="fas fa-ticket-alt"></i></div>
          <div class="stat-info">
            <div class="stat-num" data-count="<?= $total ?>">
              <?= $total ?>
            </div>
            <div class="stat-label">Total Requests</div>
          </div>
        </div>
        <div class="stat-card pending">
          <div class="stat-icon"><i class="fas fa-clock"></i></div>
          <div class="stat-info">
            <div class="stat-num" data-count="<?= $pending ?>">
              <?= $pending ?>
            </div>
            <div class="stat-label">Pending / Open</div>
          </div>
        </div>
        <div class="stat-card in-progress">
          <div class="stat-icon"><i class="fas fa-spinner"></i></div>
          <div class="stat-info">
            <div class="stat-num" data-count="<?= $progress ?>">
              <?= $progress ?>
            </div>
            <div class="stat-label">In Progress</div>
          </div>
        </div>
        <div class="stat-card resolved">
          <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
          <div class="stat-info">
            <div class="stat-num" data-count="<?= $resolved ?>">
              <?= $resolved ?>
            </div>
            <div class="stat-label">Resolved</div>
          </div>
        </div>
      </div>

      <!-- Requests Table -->
      <div class="card">
        <div class="card-header">
          <h3><i class="fas fa-list" style="color:var(--primary)"></i> My Requests</h3>
          <a href="submit-request.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> New Request</a>
        </div>
        <?php if (empty($requests)): ?>
          <div style="text-align:center;padding:60px 20px">
            <i class="fas fa-inbox" style="font-size:3rem;color:var(--gray-300);margin-bottom:16px;display:block"></i>
            <h3 style="color:var(--gray-500);margin-bottom:8px">No requests yet</h3>
            <p style="color:var(--gray-400);margin-bottom:20px">Submit your first service request to get started.</p>
            <a href="submit-request.php" class="btn btn-primary"><i class="fas fa-plus"></i> Submit Request</a>
          </div>
        <?php else: ?>
          <div class="table-wrapper">
            <table class="table">
              <thead>
                <tr>
                  <th>Ticket ID</th>
                  <th>Title</th>
                  <th>Category</th>
                  <th>Priority</th>
                  <th>Status</th>
                  <th>Date</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($requests as $r): ?>
                  <tr>
                    <td><span class="ticket-id">
                        <?= htmlspecialchars($r['ticket_id']) ?>
                      </span></td>
                    <td style="max-width:200px">
                      <a href="view-request.php?id=<?= $r['id'] ?>" style="color:var(--dark);font-weight:600">
                        <?= htmlspecialchars(strlen($r['title']) > 40 ? substr($r['title'], 0, 40) . '…' : $r['title']) ?>
                      </a>
                    </td>
                    <td>
                      <?php if ($r['cat_name']): ?>
                        <span
                          style="background:<?= htmlspecialchars($r['cat_color']) ?>20;color:<?= htmlspecialchars($r['cat_color']) ?>;padding:3px 9px;border-radius:20px;font-size:.75rem;font-weight:700">
                          <?= htmlspecialchars($r['cat_name']) ?>
                        </span>
                      <?php else: ?><span style="color:var(--gray-400)">—</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?= priorityBadge($r['priority']) ?>
                    </td>
                    <td>
                      <?= statusBadge($r['status']) ?>
                    </td>
                    <td style="white-space:nowrap;color:var(--gray-500);font-size:.82rem">
                      <?= date('M j, Y', strtotime($r['created_at'])) ?>
                    </td>
                    <td>
                      <div style="display:flex;gap:6px">
                        <a href="view-request.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-secondary" title="View"><i
                            class="fas fa-eye"></i></a>
                        <?php if ($r['status'] === 'pending'): ?>
                          <button onclick="deleteRequest(<?= $r['id'] ?>)" class="btn btn-sm btn-danger" title="Delete"><i
                              class="fas fa-trash"></i></button>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php if (count($requests) >= 8): ?>
            <div class="card-footer" style="text-align:center">
              <a href="dashboard.php?filter=all" class="btn btn-secondary btn-sm">View All Requests</a>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </main>
  </div>

  <div class="toast-container"></div>
  <script src="assets/js/main.js"></script>
</body>

</html>