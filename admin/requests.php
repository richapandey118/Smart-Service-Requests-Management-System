<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

$user = currentUser();
$unread = unreadNotifications();

// ── Staff list for assignment dropdown ────────────────────────────────
$staffList = $conn->query("SELECT id, name FROM users WHERE role IN ('admin','staff') AND is_active=1")->fetch_all(MYSQLI_ASSOC);

// ── Filter / Search ────────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$fStatus = trim($_GET['status'] ?? '');
$fPrio = trim($_GET['priority'] ?? '');
$fCat = trim($_GET['category'] ?? '');

// ── Pagination ─────────────────────────────────────────────────────────
$perPage = 12;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$where = ['1=1'];
$params = [];
$types = '';

if ($search !== '') {
  $where[] = '(sr.title LIKE ? OR sr.ticket_id LIKE ? OR u.name LIKE ?)';
  $s = "%$search%";
  $params = array_merge($params, [$s, $s, $s]);
  $types .= 'sss';
}
if ($fStatus !== '') {
  $where[] = 'sr.status=?';
  $params[] = $fStatus;
  $types .= 's';
}
if ($fPrio !== '') {
  $where[] = 'sr.priority=?';
  $params[] = $fPrio;
  $types .= 's';
}
if ($fCat !== '') {
  $where[] = 'sr.category_id=?';
  $params[] = intval($fCat);
  $types .= 'i';
}

$whereSQL = implode(' AND ', $where);

// Count
$countSQL = "SELECT COUNT(*) FROM service_requests sr LEFT JOIN users u ON u.id=sr.user_id WHERE $whereSQL";
$countStmt = $conn->prepare($countSQL);
if ($params)
  $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalRows = $countStmt->get_result()->fetch_row()[0];
$totalPages = ceil($totalRows / $perPage);

// Fetch
$sql = "SELECT sr.id, sr.ticket_id, sr.title, sr.status, sr.priority, sr.created_at, sr.updated_at, sr.location,
                u.name AS user_name, c.name AS cat_name, a.name AS assigned_name
         FROM service_requests sr
         LEFT JOIN users u  ON u.id  = sr.user_id
         LEFT JOIN categories c ON c.id = sr.category_id
         LEFT JOIN users a  ON a.id  = sr.assigned_to
         WHERE $whereSQL
         ORDER BY sr.created_at DESC
         LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$allParams = array_merge($params, [$perPage, $offset]);
$allTypes = $types . 'ii';
$stmt->bind_param($allTypes, ...$allParams);
$stmt->execute();
$requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ── Categories for filter ──────────────────────────────────────────────
$categories = $conn->query("SELECT id, name FROM categories WHERE is_active=1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Manage Requests — ServiceHub</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
      <a href="requests.php" class="nav-item active"><i class="fas fa-list-ul"></i><span>All Requests</span></a>
      <a href="users.php" class="nav-item"><i class="fas fa-users"></i><span>Users</span></a>
      <a href="reports.php" class="nav-item"><i class="fas fa-chart-bar"></i><span>Reports</span></a>
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
        <h1>All Requests</h1>
        <p>Manage and update service requests</p>
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
      <!-- Filter Bar -->
      <div class="card filter-bar mb-24">
        <form method="GET" action="requests.php" class="filter-form">
          <div class="search-wrap">
            <i class="fas fa-search"></i>
            <input type="search" name="search" id="searchInput" class="search-input"
              placeholder="Search by title, ticket ID, user…" value="<?= htmlspecialchars($search) ?>">
          </div>
          <select name="status" class="filter-select">
            <option value="">All Statuses</option>
            <?php foreach (['pending', 'open', 'in_progress', 'resolved', 'closed', 'rejected'] as $s): ?>
              <option value="<?= $s ?>" <?= $fStatus === $s ? 'selected' : '' ?>>
                <?= ucwords(str_replace('_', ' ', $s)) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <select name="priority" class="filter-select">
            <option value="">All Priorities</option>
            <?php foreach (['low', 'medium', 'high', 'urgent'] as $p): ?>
              <option value="<?= $p ?>" <?= $fPrio === $p ? 'selected' : '' ?>>
                <?= ucfirst($p) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <select name="category" class="filter-select">
            <option value="">All Categories</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>" <?= $fCat == $c['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
          <?php if ($search || $fStatus || $fPrio || $fCat): ?>
            <a href="requests.php" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Clear</a>
          <?php endif; ?>
        </form>
        <div class="filter-summary">
          <?= number_format($totalRows) ?> request
          <?= $totalRows != 1 ? 's' : '' ?> found
        </div>
      </div>

      <!-- Requests Table -->
      <div class="card">
        <div class="table-wrapper">
          <table class="table" id="requestsTable">
            <thead>
              <tr>
                <th>Ticket</th>
                <th>Title</th>
                <th>Requester</th>
                <th>Category</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Assigned To</th>
                <th>Created</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="requestsTableBody">
              <?php if (empty($requests)): ?>
                <tr>
                  <td colspan="9" style="text-align:center;padding:40px;color:var(--gray-400)"><i class="fas fa-inbox"
                      style="font-size:2rem;display:block;margin-bottom:8px"></i>No requests match your filters.</td>
                </tr>
              <?php endif; ?>
              <?php foreach ($requests as $r): ?>
                <tr>
                  <td><span class="ticket-id">
                      <?= htmlspecialchars($r['ticket_id']) ?>
                    </span></td>
                  <td style="max-width:200px">
                    <a href="../view-request.php?id=<?= $r['id'] ?>"
                      style="color:var(--dark);font-weight:600;text-decoration:none">
                      <?= htmlspecialchars(strlen($r['title']) > 40 ? substr($r['title'], 0, 40) . '…' : $r['title']) ?>
                    </a>
                    <?php if ($r['location']): ?>
                      <div style="font-size:.72rem;color:var(--gray-400)"><i class="fas fa-map-marker-alt"></i>
                        <?= htmlspecialchars($r['location']) ?>
                      </div>
                    <?php endif; ?>
                  </td>
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
                  <td style="font-size:.82rem;color:var(--gray-500)">
                    <?= htmlspecialchars($r['assigned_name'] ?? 'Unassigned') ?>
                  </td>
                  <td style="font-size:.78rem;color:var(--gray-400);white-space:nowrap">
                    <?= date('M j, Y', strtotime($r['created_at'])) ?>
                  </td>
                  <td>
                    <div class="action-btns">
                      <a href="../view-request.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-secondary" title="View"><i
                          class="fas fa-eye"></i></a>
                      <button
                        onclick="openStatusModal(<?= $r['id'] ?>,'<?= $r['status'] ?>',<?= ($r['assigned_name'] ? $r['id'] : 0) ?>)"
                        class="btn btn-sm btn-primary" title="Update"><i class="fas fa-edit"></i></button>
                      <?php if (!in_array($r['status'], ['resolved', 'closed'])): ?>
                        <button onclick="deleteRequest(<?= $r['id'] ?>,this)" class="btn btn-sm btn-danger"
                          title="Delete"><i class="fas fa-trash"></i></button>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
          <div class="pagination">
            <?php
            $baseUrl = 'requests.php?' . http_build_query(array_filter(['search' => $search, 'status' => $fStatus, 'priority' => $fPrio, 'category' => $fCat]));
            ?>
            <?php if ($page > 1): ?>
              <a href="<?= $baseUrl ?>&page=<?= $page - 1 ?>" class="page-btn"><i class="fas fa-chevron-left"></i></a>
            <?php endif; ?>
            <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
              <a href="<?= $baseUrl ?>&page=<?= $p ?>" class="page-btn <?= $p === $page ? 'active' : '' ?>">
                <?= $p ?>
              </a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
              <a href="<?= $baseUrl ?>&page=<?= $page + 1 ?>" class="page-btn"><i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>
            <span class="page-info">Page
              <?= $page ?> of
              <?= $totalPages ?>
            </span>
          </div>
        <?php endif; ?>
      </div>
    </main>
  </div>

  <!-- Status Update Modal -->
  <div class="modal-overlay" id="statusModal">
    <div class="modal">
      <div class="modal-header">
        <h3><i class="fas fa-edit"></i> Update Request</h3>
        <button onclick="closeModal('statusModal')" class="modal-close"><i class="fas fa-times"></i></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="statusReqId">
        <div class="form-group">
          <label class="form-label">Status</label>
          <select id="statusSelect" class="form-control">
            <?php foreach (['pending', 'open', 'in_progress', 'resolved', 'closed', 'rejected'] as $s): ?>
              <option value="<?= $s ?>">
                <?= ucwords(str_replace('_', ' ', $s)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Assign To</label>
          <select id="assignSelect" class="form-control">
            <option value="">— Unassigned —</option>
            <?php foreach ($staffList as $s): ?>
              <option value="<?= $s['id'] ?>">
                <?= htmlspecialchars($s['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Internal Note / Update</label>
          <textarea id="statusNote" class="form-control" rows="3"
            placeholder="Add a note for the requester…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button onclick="closeModal('statusModal')" class="btn btn-secondary">Cancel</button>
        <button onclick="submitStatusUpdate()" class="btn btn-primary"><i class="fas fa-save"></i> Update
          Request</button>
      </div>
    </div>
  </div>

  <div class="toast-container"></div>
  <script src="../assets/js/main.js"></script>
</body>

</html>