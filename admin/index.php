<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

$user = currentUser();
$unread = unreadNotifications();

// ── Stats ────────────────────────────────────────────────────────────
$total = $conn->query("SELECT COUNT(*) FROM service_requests")->fetch_row()[0];
$pending = $conn->query("SELECT COUNT(*) FROM service_requests WHERE status IN ('pending','open')")->fetch_row()[0];
$inProgress = $conn->query("SELECT COUNT(*) FROM service_requests WHERE status='in_progress'")->fetch_row()[0];
$resolved = $conn->query("SELECT COUNT(*) FROM service_requests WHERE status IN ('resolved','closed')")->fetch_row()[0];
$totalUsers = $conn->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetch_row()[0];
$today = $conn->query("SELECT COUNT(*) FROM service_requests WHERE DATE(created_at)=CURDATE()")->fetch_row()[0];

// ── Status distribution (donut chart) ────────────────────────────────
$statusData = $conn->query("SELECT status, COUNT(*) AS cnt FROM service_requests GROUP BY status ORDER BY cnt DESC")->fetch_all(MYSQLI_ASSOC);
$statusLabels = array_column($statusData, 'status');
$statusValues = array_column($statusData, 'cnt');
$statusLabels = array_map(fn($s) => ucwords(str_replace('_', ' ', $s)), $statusLabels);

// ── Requests over last 7 days (line chart) ────────────────────────────
$lineData = $conn->query("SELECT DATE(created_at) AS d, COUNT(*) AS cnt FROM service_requests WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(created_at) ORDER BY d ASC")->fetch_all(MYSQLI_ASSOC);
$lineDates = array_map(fn($r) => date('M j', strtotime($r['d'])), $lineData);
$lineValues = array_column($lineData, 'cnt');

// ── Category distribution (bar chart) ────────────────────────────────
$catData = $conn->query("SELECT c.name, COUNT(sr.id) AS cnt FROM categories c LEFT JOIN service_requests sr ON sr.category_id=c.id GROUP BY c.id,c.name ORDER BY cnt DESC")->fetch_all(MYSQLI_ASSOC);
$catLabels = array_column($catData, 'name');
$catValues = array_column($catData, 'cnt');

// ── Recent requests ───────────────────────────────────────────────────
$recentReqs = $conn->query("SELECT sr.ticket_id,sr.title,sr.status,sr.priority,sr.created_at,u.name AS user_name,c.name AS cat_name FROM service_requests sr LEFT JOIN users u ON u.id=sr.user_id LEFT JOIN categories c ON c.id=sr.category_id ORDER BY sr.created_at DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);

// ── Recent activity ───────────────────────────────────────────────────
$activity = $conn->query("SELECT ru.comment,ru.created_at,u.name AS author,sr.ticket_id FROM request_updates ru JOIN users u ON u.id=ru.user_id JOIN service_requests sr ON sr.id=ru.request_id ORDER BY ru.created_at DESC LIMIT 6")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Admin Dashboard — ServiceHub</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>

<body class="app-body">

  <!-- Admin Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <div class="sidebar-logo"><i class="fas fa-headset"></i> ServiceHub</div>
      <button class="sidebar-close" id="sidebarClose"><i class="fas fa-times"></i></button>
    </div>
    <p class="sidebar-section">Administration</p>
    <nav class="sidebar-nav">
      <a href="index.php" class="nav-item active"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
      <a href="requests.php" class="nav-item"><i class="fas fa-list-ul"></i><span>All Requests</span>
        <?php if ($pending > 0): ?><span class="badge-count">
            <?= $pending ?>
          </span>
        <?php endif; ?>
      </a>
      <a href="users.php" class="nav-item"><i class="fas fa-users"></i><span>Users</span></a>
      <a href="reports.php" class="nav-item"><i class="fas fa-chart-bar"></i><span>Reports</span></a>
    </nav>
    <p class="sidebar-section">Quick Links</p>
    <nav class="sidebar-nav">
      <a href="../index.php" class="nav-item"><i class="fas fa-home"></i><span>Home Page</span></a>
      <a href="../submit-request.php" class="nav-item"><i class="fas fa-plus-circle"></i><span>New Request</span></a>
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
      <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i></a>
    </div>
  </aside>

  <div class="main-wrapper">
    <header class="top-navbar">
      <button class="navbar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
      <div class="navbar-breadcrumb">
        <h1>Admin Dashboard</h1>
        <p>Welcome back,
          <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?> ·
          <span style="color:var(--success)">
            <?= $today ?> new request
            <?= $today != 1 ? 's' : '' ?> today
          </span>
        </p>
      </div>
      <div class="navbar-actions">
        <div class="dropdown">
          <button class="notif-btn" id="notifBtn" data-dropdown="notifPanel">
            <i class="fas fa-bell"></i>
            <span class="notif-badge" id="notifBadge" style="display:<?= $unread > 0 ? 'flex' : 'none' ?>">
              <?= $unread ?>
            </span>
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
            <div class="avatar">
              <?= strtoupper(substr($user['name'], 0, 1)) ?>
            </div>
            <span class="uname">
              <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?>
            </span>
            <i class="fas fa-chevron-down"></i>
          </div>
          <div class="dropdown-menu" id="userMenu">
            <a class="dropdown-item" href="../profile.php"><i class="fas fa-user"></i> Profile</a>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="../logout.php" style="color:var(--danger)"><i
                class="fas fa-sign-out-alt"></i> Sign Out</a>
          </div>
        </div>
      </div>
    </header>

    <main class="content">
      <!-- Stats -->
      <div class="stats-grid">
        <div class="stat-card total">
          <div class="stat-icon"><i class="fas fa-ticket-alt"></i></div>
          <div class="stat-info">
            <div class="stat-num" data-count="<?= $total ?>">
              <?= $total ?>
            </div>
            <div class="stat-label">Total Requests</div>
            <div class="stat-trend up"><i class="fas fa-arrow-up"></i>
              <?= $today ?> today
            </div>
          </div>
        </div>
        <div class="stat-card pending">
          <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
          <div class="stat-info">
            <div class="stat-num" data-count="<?= $pending ?>">
              <?= $pending ?>
            </div>
            <div class="stat-label">Pending / Open</div>
            <div class="stat-trend <?= $pending > 5 ? 'down' : 'up' ?>"><i
                class="fas fa-<?= $pending > 5 ? 'arrow-up' : 'arrow-down' ?>"></i> Need attention</div>
          </div>
        </div>
        <div class="stat-card in-progress">
          <div class="stat-icon"><i class="fas fa-spinner"></i></div>
          <div class="stat-info">
            <div class="stat-num" data-count="<?= $inProgress ?>">
              <?= $inProgress ?>
            </div>
            <div class="stat-label">In Progress</div>
            <div class="stat-trend up"><i class="fas fa-wrench"></i> Being handled</div>
          </div>
        </div>
        <div class="stat-card resolved">
          <div class="stat-icon"><i class="fas fa-check-double"></i></div>
          <div class="stat-info">
            <div class="stat-num" data-count="<?= $resolved ?>">
              <?= $resolved ?>
            </div>
            <div class="stat-label">Resolved</div>
            <div class="stat-trend up"><i class="fas fa-arrow-up"></i>
              <?= $total > 0 ? round($resolved / $total * 100) : 0 ?>% overall
            </div>
          </div>
        </div>
      </div>

      <!-- Charts Row -->
      <div class="grid-3 mb-24" style="align-items:start">
        <!-- Line Chart: Requests over time -->
        <div class="card" style="grid-column:span 2">
          <div class="card-header">
            <h3><i class="fas fa-chart-line" style="color:var(--primary)"></i> Requests (Last 7 Days)</h3>
          </div>
          <div class="card-body">
            <div class="chart-wrapper" style="height:220px">
              <canvas id="requestsChart" data-values='<?= json_encode(array_values($lineValues)) ?>'
                data-labels='<?= json_encode(array_values($lineDates)) ?>'>
              </canvas>
            </div>
          </div>
        </div>
        <!-- Donut Chart: Status -->
        <div class="card">
          <div class="card-header">
            <h3><i class="fas fa-chart-pie" style="color:var(--secondary)"></i> By Status</h3>
          </div>
          <div class="card-body">
            <div class="chart-wrapper" style="height:220px">
              <canvas id="statusChart" data-values='<?= json_encode(array_values($statusValues)) ?>'
                data-labels='<?= json_encode(array_values($statusLabels)) ?>'>
              </canvas>
            </div>
          </div>
        </div>
      </div>

      <!-- Category Chart + Activity -->
      <div class="grid-2 mb-24" style="align-items:start">
        <!-- Bar: Category distribution -->
        <div class="card">
          <div class="card-header">
            <h3><i class="fas fa-chart-bar" style="color:var(--info)"></i> By Category</h3>
          </div>
          <div class="card-body">
            <div class="chart-wrapper" style="height:260px">
              <canvas id="categoryChart" data-values='<?= json_encode(array_values($catValues)) ?>'
                data-labels='<?= json_encode(array_values($catLabels)) ?>'>
              </canvas>
            </div>
          </div>
        </div>
        <!-- Recent Activity -->
        <div class="card">
          <div class="card-header">
            <h3><i class="fas fa-stream" style="color:var(--warning)"></i> Recent Activity</h3>
          </div>
          <div class="card-body" style="padding-top:8px">
            <?php if (empty($activity)): ?>
              <p style="text-align:center;color:var(--gray-400);padding:20px">No recent activity</p>
            <?php endif; ?>
            <?php foreach ($activity as $a): ?>
              <div style="display:flex;gap:10px;padding:10px 0;border-bottom:1px solid var(--gray-100)">
                <div class="avatar-sm">
                  <?= strtoupper(substr($a['author'], 0, 1)) ?>
                </div>
                <div style="flex:1;min-width:0">
                  <p style="font-size:.82rem;color:var(--dark)"><strong>
                      <?= htmlspecialchars($a['author']) ?>
                    </strong> updated <a href="../view-request.php?id=0" style="color:var(--primary);font-weight:600">
                      <?= htmlspecialchars($a['ticket_id']) ?>
                    </a></p>
                  <p
                    style="font-size:.78rem;color:var(--gray-500);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                    <?= htmlspecialchars(substr($a['comment'], 0, 60)) ?>
                    <?= strlen($a['comment']) > 60 ? '…' : '' ?>
                  </p>
                  <p style="font-size:.72rem;color:var(--gray-400);margin-top:2px">
                    <?= timeAgo($a['created_at']) ?>
                  </p>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="card-footer"><a href="requests.php" class="btn btn-secondary btn-sm btn-block">View All
              Requests</a></div>
        </div>
      </div>

      <!-- Recent Requests Table -->
      <div class="card">
        <div class="card-header">
          <h3><i class="fas fa-list" style="color:var(--primary)"></i> Recent Requests</h3>
          <div style="display:flex;gap:8px">
            <a href="requests.php" class="btn btn-secondary btn-sm">View All</a>
            <a href="../submit-request.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> New</a>
          </div>
        </div>
        <div class="table-wrapper">
          <table class="table">
            <thead>
              <tr>
                <th>Ticket</th>
                <th>Title</th>
                <th>By</th>
                <th>Category</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentReqs as $r): ?>
                <tr>
                  <td><span class="ticket-id">
                      <?= htmlspecialchars($r['ticket_id']) ?>
                    </span></td>
                  <td style="max-width:180px"><a href="../view-request.php?id=0&ticket=<?= urlencode($r['ticket_id']) ?>"
                      style="color:var(--dark);font-weight:600">
                      <?= htmlspecialchars(strlen($r['title']) > 35 ? substr($r['title'], 0, 35) . '…' : $r['title']) ?>
                    </a></td>
                  <td style="font-size:.82rem">
                    <?= htmlspecialchars($r['user_name']) ?>
                  </td>
                  <td style="font-size:.78rem;color:var(--gray-500)">
                    <?= htmlspecialchars($r['cat_name'] ?? '—') ?>
                  </td>
                  <td>
                    <?= priorityBadge($r['priority']) ?>
                  </td>
                  <td>
                    <?= statusBadge($r['status']) ?>
                  </td>
                  <td style="font-size:.82rem;color:var(--gray-500);white-space:nowrap">
                    <?= date('M j, Y', strtotime($r['created_at'])) ?>
                  </td>
                  <td><a href="requests.php" class="btn btn-sm btn-secondary"><i class="fas fa-eye"></i></a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>

  <div class="toast-container"></div>
  <script src="../assets/js/main.js"></script>
</body>

</html>