<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

$user = currentUser();
$unread = unreadNotifications();

// ── Date Range ────────────────────────────────────────────────────────
$dateFrom = $_GET['from'] ?? date('Y-m-01');    // first of this month
$dateTo = $_GET['to'] ?? date('Y-m-d');     // today
// Sanitise dates
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom))
  $dateFrom = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo))
  $dateTo = date('Y-m-d');

// ── Summary stats for range ───────────────────────────────────────────
$stmt = $conn->prepare("SELECT COUNT(*) FROM service_requests WHERE DATE(created_at) BETWEEN ? AND ?");
$stmt->bind_param('ss', $dateFrom, $dateTo);
$stmt->execute();
$rangeTotal = $stmt->get_result()->fetch_row()[0];

$stmt = $conn->prepare("SELECT COUNT(*) FROM service_requests WHERE status IN ('resolved','closed') AND DATE(resolved_at) BETWEEN ? AND ?");
$stmt->bind_param('ss', $dateFrom, $dateTo);
$stmt->execute();
$rangeResolved = $stmt->get_result()->fetch_row()[0];

$stmt = $conn->prepare("SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) FROM service_requests WHERE status IN ('resolved','closed') AND DATE(resolved_at) BETWEEN ? AND ?");
$stmt->bind_param('ss', $dateFrom, $dateTo);
$stmt->execute();
$avgResolutionHrs = round($stmt->get_result()->fetch_row()[0] ?? 0, 1);

$stmt = $conn->prepare("SELECT COUNT(DISTINCT user_id) FROM service_requests WHERE DATE(created_at) BETWEEN ? AND ?");
$stmt->bind_param('ss', $dateFrom, $dateTo);
$stmt->execute();
$rangeUsers = $stmt->get_result()->fetch_row()[0];

// ── Monthly request trend (last 6 months) ─────────────────────────────
$monthlyData = $conn->query("
    SELECT DATE_FORMAT(created_at,'%b %Y') AS month_label,
           DATE_FORMAT(created_at,'%Y-%m') AS month_key,
           COUNT(*) AS cnt
    FROM service_requests
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month_key, month_label
    ORDER BY month_key ASC
")->fetch_all(MYSQLI_ASSOC);
$monthLabels = array_column($monthlyData, 'month_label');
$monthValues = array_column($monthlyData, 'cnt');

// ── Resolution time distribution (bar) ───────────────────────────────
$resTimes = $conn->query("
    SELECT
        SUM(TIMESTAMPDIFF(HOUR, created_at, resolved_at) < 24) AS within_24h,
        SUM(TIMESTAMPDIFF(HOUR, created_at, resolved_at) BETWEEN 24 AND 72) AS d1_3,
        SUM(TIMESTAMPDIFF(HOUR, created_at, resolved_at) BETWEEN 73 AND 168) AS d3_7,
        SUM(TIMESTAMPDIFF(HOUR, created_at, resolved_at) > 168) AS over_7d
    FROM service_requests
    WHERE status IN ('resolved','closed') AND resolved_at IS NOT NULL
")->fetch_assoc();
$resLabels = ['< 24h', '1–3 days', '3–7 days', '> 7 days'];
$resValues = [$resTimes['within_24h'] ?? 0, $resTimes['d1_3'] ?? 0, $resTimes['d3_7'] ?? 0, $resTimes['over_7d'] ?? 0];

// ── Category performance ──────────────────────────────────────────────
$catPerf = $conn->query("
    SELECT c.name, COUNT(sr.id) AS total,
           SUM(sr.status IN ('resolved','closed')) AS resolved_c,
           SUM(sr.status = 'pending') AS pending_c
    FROM categories c
    LEFT JOIN service_requests sr ON sr.category_id = c.id
    GROUP BY c.id, c.name
    ORDER BY total DESC
")->fetch_all(MYSQLI_ASSOC);

// ── Priority breakdown ────────────────────────────────────────────────
$prioBreak = $conn->query("
    SELECT priority, COUNT(*) AS cnt
    FROM service_requests
    GROUP BY priority
    ORDER BY FIELD(priority,'urgent','high','medium','low')
")->fetch_all(MYSQLI_ASSOC);
$prioLabels = array_map(fn($r) => ucfirst($r['priority']), $prioBreak);
$prioValues = array_column($prioBreak, 'cnt');

// ── CSV Export ────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="servicehub_report_' . date('Ymd') . '.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['Ticket ID', 'Title', 'Category', 'Priority', 'Status', 'Requester', 'Assigned To', 'Created', 'Resolved']);
  $exp = $conn->prepare("
        SELECT sr.ticket_id,sr.title,c.name,sr.priority,sr.status,u.name,a.name,sr.created_at,sr.resolved_at
        FROM service_requests sr
        LEFT JOIN categories c ON c.id=sr.category_id
        LEFT JOIN users u ON u.id=sr.user_id
        LEFT JOIN users a ON a.id=sr.assigned_to
        WHERE DATE(sr.created_at) BETWEEN ? AND ?
        ORDER BY sr.created_at DESC
    ");
  $exp->bind_param('ss', $dateFrom, $dateTo);
  $exp->execute();
  $result = $exp->get_result();
  while ($row = $result->fetch_row()) {
    fputcsv($out, $row);
  }
  fclose($out);
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Reports & Analytics — ServiceHub</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>

<body class="app-body">

  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <div class="sidebar-logo"><i class="fas fa-headset"></i> ServiceHub</div>
      <button class="sidebar-close" id="sidebarClose"><i class="fas fa-times"></i></button>
    </div>
    <p class="sidebar-section">Administration</p>
    <nav class="sidebar-nav">
      <a href="index.php" class="nav-item"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
      <a href="requests.php" class="nav-item"><i class="fas fa-list-ul"></i><span>All Requests</span></a>
      <a href="users.php" class="nav-item"><i class="fas fa-users"></i><span>Users</span></a>
      <a href="reports.php" class="nav-item active"><i class="fas fa-chart-bar"></i><span>Reports</span></a>
    </nav>
    <div class="sidebar-footer">
      <div class="user-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
      <div class="user-info">
        <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
        <div class="user-role"><?= ucfirst($user['role']) ?></div>
      </div>
      <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i></a>
    </div>
  </aside>

  <div class="main-wrapper">
    <header class="top-navbar">
      <button class="navbar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
      <div class="navbar-breadcrumb">
        <h1>Reports &amp; Analytics</h1>
        <p>Data-driven insights for your service desk</p>
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
            <span class="uname"><?= htmlspecialchars(explode(' ', $user['name'])[0]) ?></span>
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
      <!-- Date Range Filter + Export -->
      <div class="card filter-bar mb-24">
        <form method="GET" action="reports.php" class="filter-form" style="flex-wrap:wrap">
          <div class="form-group inline-group">
            <label class="form-label" style="margin-right:8px">From</label>
            <input type="date" name="from" class="form-control" value="<?= $dateFrom ?>" style="width:150px">
          </div>
          <div class="form-group inline-group">
            <label class="form-label" style="margin-right:8px">To</label>
            <input type="date" name="to" class="form-control" value="<?= $dateTo ?>" style="width:150px">
          </div>
          <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Apply</button>
          <a href="reports.php" class="btn btn-secondary btn-sm"><i class="fas fa-redo"></i> Reset</a>
        </form>
        <div style="display:flex;gap:8px">
          <a href="reports.php?from=<?= $dateFrom ?>&to=<?= $dateTo ?>&export=csv" class="btn btn-success btn-sm"><i
              class="fas fa-file-csv"></i> Export CSV</a>
          <button onclick="window.print()" class="btn btn-secondary btn-sm"><i class="fas fa-print"></i> Print</button>
        </div>
      </div>

      <!-- Summary stats for range -->
      <div class="stats-grid mb-24">
        <div class="stat-card total">
          <div class="stat-icon"><i class="fas fa-ticket-alt"></i></div>
          <div class="stat-info">
            <div class="stat-num"><?= $rangeTotal ?></div>
            <div class="stat-label">Requests Submitted</div>
            <div class="stat-trend"><i class="fas fa-calendar"></i> In range</div>
          </div>
        </div>
        <div class="stat-card resolved">
          <div class="stat-icon"><i class="fas fa-check-double"></i></div>
          <div class="stat-info">
            <div class="stat-num"><?= $rangeResolved ?></div>
            <div class="stat-label">Resolved</div>
            <div class="stat-trend up"><i
                class="fas fa-arrow-up"></i><?= $rangeTotal > 0 ? round($rangeResolved / $rangeTotal * 100) : 0 ?>%
              resolution
              rate</div>
          </div>
        </div>
        <div class="stat-card in-progress">
          <div class="stat-icon"><i class="fas fa-clock"></i></div>
          <div class="stat-info">
            <div class="stat-num"><?= $avgResolutionHrs ?>h</div>
            <div class="stat-label">Avg Resolution Time</div>
            <div class="stat-trend"><i class="fas fa-hourglass-half"></i> Hours to resolve</div>
          </div>
        </div>
        <div class="stat-card pending">
          <div class="stat-icon"><i class="fas fa-users"></i></div>
          <div class="stat-info">
            <div class="stat-num"><?= $rangeUsers ?></div>
            <div class="stat-label">Active Requesters</div>
            <div class="stat-trend"><i class="fas fa-user-clock"></i> Unique users</div>
          </div>
        </div>
      </div>

      <!-- Chart Row 1 -->
      <div class="grid-2 mb-24" style="align-items:start">
        <!-- Monthly trend line -->
        <div class="card">
          <div class="card-header">
            <h3><i class="fas fa-chart-line" style="color:var(--primary)"></i> Monthly Request Trend</h3>
          </div>
          <div class="card-body">
            <div class="chart-wrapper" style="height:260px">
              <canvas id="requestsChart" data-values='<?= json_encode(array_values($monthValues)) ?>'
                data-labels='<?= json_encode(array_values($monthLabels)) ?>'>
              </canvas>
            </div>
          </div>
        </div>
        <!-- Resolution time bar -->
        <div class="card">
          <div class="card-header">
            <h3><i class="fas fa-stopwatch" style="color:var(--warning)"></i> Resolution Time</h3>
          </div>
          <div class="card-body">
            <div class="chart-wrapper" style="height:260px">
              <canvas id="resolutionChart" data-values='<?= json_encode(array_values($resValues)) ?>'
                data-labels='<?= json_encode($resLabels) ?>'>
              </canvas>
            </div>
          </div>
        </div>
      </div>

      <!-- Priority breakdown donut + Category table -->
      <div class="grid-2 mb-24" style="align-items:start">
        <!-- Priority donut -->
        <div class="card">
          <div class="card-header">
            <h3><i class="fas fa-chart-pie" style="color:var(--danger)"></i> Priority Breakdown</h3>
          </div>
          <div class="card-body">
            <div class="chart-wrapper" style="height:240px">
              <canvas id="statusChart" data-values='<?= json_encode(array_values($prioValues)) ?>'
                data-labels='<?= json_encode(array_values($prioLabels)) ?>'>
              </canvas>
            </div>
          </div>
        </div>
        <!-- Category performance table -->
        <div class="card">
          <div class="card-header">
            <h3><i class="fas fa-tags" style="color:var(--success)"></i> Category Performance</h3>
          </div>
          <div class="table-wrapper" style="max-height:300px;overflow-y:auto">
            <table class="table">
              <thead>
                <tr>
                  <th>Category</th>
                  <th>Total</th>
                  <th>Resolved</th>
                  <th>Pending</th>
                  <th>Rate</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($catPerf as $c):
                  $rate = $c['total'] > 0 ? round($c['resolved_c'] / $c['total'] * 100) : 0; ?>
                  <tr>
                    <td style="font-weight:600;font-size:.85rem"><?= htmlspecialchars($c['name']) ?></td>
                    <td><?= $c['total'] ?></td>
                    <td style="color:var(--success)"><?= $c['resolved_c'] ?></td>
                    <td style="color:var(--warning)"><?= $c['pending_c'] ?></td>
                    <td>
                      <div style="display:flex;align-items:center;gap:8px">
                        <div style="flex:1;background:var(--gray-100);border-radius:4px;height:6px;overflow:hidden">
                          <div style="width:<?= $rate ?>%;background:var(--success);height:100%"></div>
                        </div>
                        <span style="font-size:.78rem;color:var(--gray-500);white-space:nowrap"><?= $rate ?>%</span>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Category bar chart -->
      <div class="card mb-24">
        <div class="card-header">
          <h3><i class="fas fa-chart-bar" style="color:var(--info)"></i> Requests by Category</h3>
        </div>
        <div class="card-body">
          <div class="chart-wrapper" style="height:280px">
            <canvas id="categoryChart" data-values='<?= json_encode(array_column($catPerf, 'total')) ?>'
              data-labels='<?= json_encode(array_column($catPerf, 'name')) ?>'>
            </canvas>
          </div>
        </div>
      </div>
    </main>
  </div>

  <div class="toast-container"></div>
  <script src="../assets/js/main.js"></script>
</body>

</html>