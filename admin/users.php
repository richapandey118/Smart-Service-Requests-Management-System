<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

$user = currentUser();
$unread = unreadNotifications();

// ── Handle role/status update (POST) ──────────────────────────────────
$message = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $uid = intval($_POST['user_id'] ?? 0);
  if ($action === 'toggle' && $uid) {
    $stmt = $conn->prepare("UPDATE users SET is_active = NOT is_active WHERE id=?");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $message = 'User status updated.';
  } elseif ($action === 'change_role' && $uid) {
    $newRole = in_array($_POST['role'], ['user', 'staff', 'admin']) ? $_POST['role'] : 'user';
    $stmt = $conn->prepare("UPDATE users SET role=? WHERE id=?");
    $stmt->bind_param('si', $newRole, $uid);
    $stmt->execute();
    $message = 'User role updated.';
  }
}

// ── Filters ────────────────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$fRole = trim($_GET['role'] ?? '');
$fActive = trim($_GET['active'] ?? '');

$where = ['1=1'];
$params = [];
$types = '';

if ($search !== '') {
  $where[] = '(u.name LIKE ? OR u.email LIKE ? OR u.department LIKE ?)';
  $s = "%$search%";
  $params = array_merge($params, [$s, $s, $s]);
  $types .= 'sss';
}
if ($fRole !== '') {
  $where[] = 'u.role=?';
  $params[] = $fRole;
  $types .= 's';
}
if ($fActive !== '') {
  $where[] = 'u.is_active=?';
  $params[] = intval($fActive);
  $types .= 'i';
}

$whereSQL = implode(' AND ', $where);

// ── Pagination ─────────────────────────────────────────────────────────
$perPage = 15;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$countStmt = $conn->prepare("SELECT COUNT(*) FROM users u WHERE $whereSQL");
if ($params)
  $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalRows = $countStmt->get_result()->fetch_row()[0];
$totalPages = ceil($totalRows / $perPage);

$sql = "SELECT u.id, u.name, u.email, u.role, u.department, u.phone, u.is_active, u.created_at,
                (SELECT COUNT(*) FROM service_requests sr WHERE sr.user_id = u.id) AS req_count
         FROM users u
         WHERE $whereSQL
         ORDER BY u.created_at DESC
         LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$allParams = array_merge($params, [$perPage, $offset]);
$allTypes = $types . 'ii';
$stmt->bind_param($allTypes, ...$allParams);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Summary counts
$totalCount = $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
$adminCount = $conn->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetch_row()[0];
$staffCount = $conn->query("SELECT COUNT(*) FROM users WHERE role='staff'")->fetch_row()[0];
$userCount = $conn->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetch_row()[0];
$activeCount = $conn->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetch_row()[0];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Manage Users — ServiceHub</title>
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
      <a href="requests.php" class="nav-item"><i class="fas fa-list-ul"></i><span>All Requests</span></a>
      <a href="users.php" class="nav-item active"><i class="fas fa-users"></i><span>Users</span></a>
      <a href="reports.php" class="nav-item"><i class="fas fa-chart-bar"></i><span>Reports</span></a>
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
        <h1>User Management</h1>
        <p>Manage accounts, roles and permissions</p>
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
      <?php if ($message): ?>
        <div class="alert alert-success mb-16"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger mb-16"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <!-- Stats mini cards -->
      <div class="stats-grid" style="grid-template-columns:repeat(5,1fr)" class="mb-24">
        <div class="stat-card total">
          <div class="stat-icon"><i class="fas fa-users"></i></div>
          <div class="stat-info">
            <div class="stat-num"><?= $totalCount ?></div>
            <div class="stat-label">Total</div>
          </div>
        </div>
        <div class="stat-card in-progress">
          <div class="stat-icon"><i class="fas fa-user-shield"></i></div>
          <div class="stat-info">
            <div class="stat-num"><?= $adminCount ?></div>
            <div class="stat-label">Admins</div>
          </div>
        </div>
        <div class="stat-card pending">
          <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
          <div class="stat-info">
            <div class="stat-num"><?= $staffCount ?></div>
            <div class="stat-label">Staff</div>
          </div>
        </div>
        <div class="stat-card resolved">
          <div class="stat-icon"><i class="fas fa-user"></i></div>
          <div class="stat-info">
            <div class="stat-num"><?= $userCount ?></div>
            <div class="stat-label">Users</div>
          </div>
        </div>
        <div class="stat-card total" style="--accent:#10b981">
          <div class="stat-icon"><i class="fas fa-user-check"></i></div>
          <div class="stat-info">
            <div class="stat-num"><?= $activeCount ?></div>
            <div class="stat-label">Active</div>
          </div>
        </div>
      </div>

      <!-- Filter Bar -->
      <div class="card filter-bar mb-24">
        <form method="GET" action="users.php" class="filter-form">
          <div class="search-wrap">
            <i class="fas fa-search"></i>
            <input type="search" name="search" class="search-input" placeholder="Search by name, email, department…"
              value="<?= htmlspecialchars($search) ?>">
          </div>
          <select name="role" class="filter-select">
            <option value="">All Roles</option>
            <?php foreach (['admin', 'staff', 'user'] as $r): ?>
              <option value="<?= $r ?>" <?= $fRole === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
            <?php endforeach; ?>
          </select>
          <select name="active" class="filter-select">
            <option value="">All Statuses</option>
            <option value="1" <?= $fActive === '1' ? 'selected' : '' ?>>Active</option>
            <option value="0" <?= $fActive === '0' ? 'selected' : '' ?>>Inactive</option>
          </select>
          <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
          <?php if ($search || $fRole || $fActive !== ''): ?>
            <a href="users.php" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Clear</a>
          <?php endif; ?>
        </form>
        <div class="filter-summary"><?= number_format($totalRows) ?> user<?= $totalRows != 1 ? 's' : '' ?> found</div>
      </div>

      <!-- Users Table -->
      <div class="card">
        <div class="table-wrapper">
          <table class="table">
            <thead>
              <tr>
                <th>#</th>
                <th>User</th>
                <th>Role</th>
                <th>Department</th>
                <th>Requests</th>
                <th>Status</th>
                <th>Joined</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($users)): ?>
                <tr>
                  <td colspan="8" style="text-align:center;padding:40px;color:var(--gray-400)">No users found.</td>
                </tr>
              <?php endif; ?>
              <?php foreach ($users as $u): ?>
                <tr>
                  <td style="color:var(--gray-400);font-size:.8rem"><?= $u['id'] ?></td>
                  <td>
                    <div style="display:flex;align-items:center;gap:10px">
                      <div class="avatar"><?= strtoupper(substr($u['name'], 0, 1)) ?></div>
                      <div>
                        <div style="font-weight:600;font-size:.9rem;color:var(--dark)"><?= htmlspecialchars($u['name']) ?>
                        </div>
                        <div style="font-size:.78rem;color:var(--gray-400)"><?= htmlspecialchars($u['email']) ?></div>
                      </div>
                    </div>
                  </td>
                  <td>
                    <span class="badge-role badge-<?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span>
                  </td>
                  <td style="font-size:.82rem;color:var(--gray-500)"><?= htmlspecialchars($u['department'] ?? '—') ?></td>
                  <td>
                    <a href="requests.php?search=<?= urlencode($u['email']) ?>" class="req-count-badge"
                      title="View requests">
                      <?= $u['req_count'] ?>
                    </a>
                  </td>
                  <td>
                    <span class="status-pill <?= $u['is_active'] ? 'active' : 'inactive' ?>">
                      <span class="status-dot"></span><?= $u['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                  </td>
                  <td style="font-size:.78rem;color:var(--gray-400)"><?= date('M j, Y', strtotime($u['created_at'])) ?>
                  </td>
                  <td>
                    <div class="action-btns">
                      <!-- Toggle Status -->
                      <?php if ($u['id'] !== $user['id']): ?>
                        <button onclick="toggleUserStatus(<?= $u['id'] ?>,<?= $u['is_active'] ?>)"
                          class="btn btn-sm <?= $u['is_active'] ? 'btn-warning' : 'btn-success' ?>"
                          title="<?= $u['is_active'] ? 'Disable' : 'Enable' ?>">
                          <i class="fas fa-<?= $u['is_active'] ? 'user-slash' : 'user-check' ?>"></i>
                        </button>
                        <!-- Role change -->
                        <button
                          onclick="openRoleModal(<?= $u['id'] ?>,'<?= htmlspecialchars($u['name']) ?>','<?= $u['role'] ?>')"
                          class="btn btn-sm btn-secondary" title="Change Role">
                          <i class="fas fa-user-cog"></i>
                        </button>
                      <?php else: ?>
                        <span style="font-size:.75rem;color:var(--gray-400)">(you)</span>
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
            <?php $baseUrl = 'users.php?' . http_build_query(array_filter(['search' => $search, 'role' => $fRole, 'active' => $fActive])); ?>
            <?php if ($page > 1): ?><a href="<?= $baseUrl ?>&page=<?= $page - 1 ?>" class="page-btn"><i
                  class="fas fa-chevron-left"></i></a><?php endif; ?>
            <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
              <a href="<?= $baseUrl ?>&page=<?= $p ?>" class="page-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?><a href="<?= $baseUrl ?>&page=<?= $page + 1 ?>" class="page-btn"><i
                  class="fas fa-chevron-right"></i></a><?php endif; ?>
            <span class="page-info">Page <?= $page ?> of <?= $totalPages ?></span>
          </div>
        <?php endif; ?>
      </div>
    </main>
  </div>

  <!-- Role Modal -->
  <div class="modal-overlay" id="roleModal">
    <div class="modal" style="max-width:400px">
      <div class="modal-header">
        <h3><i class="fas fa-user-cog"></i> Change Role</h3>
        <button onclick="closeModal('roleModal')" class="modal-close"><i class="fas fa-times"></i></button>
      </div>
      <form method="POST"
        action="users.php<?= $search || $fRole || $fActive !== '' ? '?' . http_build_query(array_filter(['search' => $search, 'role' => $fRole, 'active' => $fActive])) : '' ?>">
        <input type="hidden" name="action" value="change_role">
        <input type="hidden" name="user_id" id="roleUserId">
        <div class="modal-body">
          <p id="roleUserName" style="font-weight:600;color:var(--dark);margin-bottom:16px"></p>
          <div class="form-group">
            <label class="form-label">New Role</label>
            <select name="role" id="roleSelect" class="form-control">
              <option value="user">User</option>
              <option value="staff">Staff</option>
              <option value="admin">Admin</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" onclick="closeModal('roleModal')" class="btn btn-secondary">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Role</button>
        </div>
      </form>
    </div>
  </div>

  <div class="toast-container"></div>
  <script src="../assets/js/main.js"></script>
  <script>
    function openRoleModal(id, name, currentRole) {
      document.getElementById('roleUserId').value = id;
      document.getElementById('roleUserName').textContent = 'User: ' + name;
      document.getElementById('roleSelect').value = currentRole;
      openModal('roleModal');
    }
    function toggleUserStatus(id, current) {
      if (!confirm((current ? 'Disable' : 'Enable') + ' this user?')) return;
      const fd = new FormData();
      fd.append('action', 'toggle_user');
      fd.append('id', id);
      fetch('/ThinkFest/api/ajax.php', { method: 'POST', body: fd })
        .then(r => r.json()).then(d => {
          if (d.success) { showToast(d.message || 'Status updated', 'success'); setTimeout(() => location.reload(), 900); }
          else showToast(d.message || d.error || 'Error', 'error');
        }).catch(() => showToast('Network error', 'error'));
    }
  </script>
</body>

</html>