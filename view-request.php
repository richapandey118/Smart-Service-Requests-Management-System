<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
requireLogin();

$uid = (int) $_SESSION['user_id'];
$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
  header('Location: ' . BASE_URL . '/dashboard.php');
  exit;
}

// Fetch request (users see only their own, admins see all)
if (isAdmin()) {
  $stmt = $conn->prepare("SELECT sr.*,c.name AS cat_name,c.color AS cat_color,c.icon AS cat_icon,u.name AS submitter_name,u.email AS submitter_email,u.department AS submitter_dept,a.name AS assigned_name FROM service_requests sr LEFT JOIN categories c ON c.id=sr.category_id LEFT JOIN users u ON u.id=sr.user_id LEFT JOIN users a ON a.id=sr.assigned_to WHERE sr.id=?");
} else {
  $stmt = $conn->prepare("SELECT sr.*,c.name AS cat_name,c.color AS cat_color,c.icon AS cat_icon,u.name AS submitter_name,u.email AS submitter_email,u.department AS submitter_dept,a.name AS assigned_name FROM service_requests sr LEFT JOIN categories c ON c.id=sr.category_id LEFT JOIN users u ON u.id=sr.user_id LEFT JOIN users a ON a.id=sr.assigned_to WHERE sr.id=? AND sr.user_id=?");
}
if (isAdmin()) {
  $stmt->bind_param('i', $id);
} else {
  $stmt->bind_param('ii', $id, $uid);
}
$stmt->execute();
$req = $stmt->get_result()->fetch_assoc();
if (!$req) {
  header('Location: ' . BASE_URL . '/dashboard.php');
  exit;
}

// Fetch timeline
$tl_stmt = $conn->prepare("SELECT ru.*,u.name AS author,u.role AS author_role FROM request_updates ru JOIN users u ON u.id=ru.user_id WHERE ru.request_id=? AND (ru.is_internal=0 OR ?) ORDER BY ru.created_at ASC");
$isAdm = isAdmin() ? 1 : 0;
$tl_stmt->bind_param('ii', $id, $isAdm);
$tl_stmt->execute();
$timeline = $tl_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Staff list (for admin assignment)
$staffList = [];
if (isAdmin()) {
  $staffList = $conn->query("SELECT id,name FROM users WHERE role IN ('admin','staff') AND is_active=1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);
}

$user = currentUser();
$unread = unreadNotifications();
$submitted = isset($_GET['submitted']);
$firstName = explode(' ', $user['name'])[0];

function tlDotClass($status): string
{
  $map = ['resolved' => 'success', 'closed' => 'gray', 'rejected' => 'danger', 'in_progress' => '', 'pending' => 'warning', 'open' => ''];
  return $map[$status] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title><?= htmlspecialchars($req['ticket_id']) ?> — ServiceHub</title>
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
    <p class="sidebar-section">Menu</p>
    <nav class="sidebar-nav">
      <?php if (isAdmin()): ?>
        <a href="admin/index.php" class="nav-item"><i class="fas fa-tachometer-alt"></i><span>Admin Dashboard</span></a>
        <a href="admin/requests.php" class="nav-item active"><i class="fas fa-list-ul"></i><span>All Requests</span></a>
        <a href="admin/users.php" class="nav-item"><i class="fas fa-users"></i><span>Users</span></a>
        <a href="admin/reports.php" class="nav-item"><i class="fas fa-chart-bar"></i><span>Reports</span></a>
      <?php else: ?>
        <a href="dashboard.php" class="nav-item"><i class="fas fa-home"></i><span>Dashboard</span></a>
        <a href="submit-request.php" class="nav-item"><i class="fas fa-plus-circle"></i><span>New Request</span></a>
        <a href="profile.php" class="nav-item"><i class="fas fa-user"></i><span>Profile</span></a>
      <?php endif; ?>
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
        <h1><?= htmlspecialchars($req['ticket_id']) ?></h1>
        <p><a href="<?= isAdmin() ? 'admin/requests.php' : 'dashboard.php' ?>" style="color:var(--primary)">← Back</a>
        </p>
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
      <?php if ($submitted): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> Request submitted successfully! We'll get
          back to you soon.</div>
      <?php endif; ?>

      <div class="grid-2" style="align-items:start">
        <!-- Left: Details -->
        <div>
          <!-- Request Header Card -->
          <div class="card mb-24">
            <div class="card-body">
              <div
                style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:16px">
                <div>
                  <div style="display:flex;gap:8px;margin-bottom:8px;flex-wrap:wrap">
                    <?= statusBadge($req['status']) ?>
                    <?= priorityBadge($req['priority']) ?>
                    <?php if ($req['cat_name']): ?>
                      <span
                        style="background:<?= htmlspecialchars($req['cat_color']) ?>20;color:<?= htmlspecialchars($req['cat_color']) ?>;padding:3px 9px;border-radius:20px;font-size:.72rem;font-weight:700">
                        <i class="fas <?= htmlspecialchars($req['cat_icon']) ?>"></i>
                        <?= htmlspecialchars($req['cat_name']) ?>
                      </span>
                    <?php endif; ?>
                  </div>
                  <h2 style="font-size:1.15rem;font-weight:700;margin-bottom:0"><?= htmlspecialchars($req['title']) ?>
                  </h2>
                </div>
                <?php if ($req['user_id'] == $uid && $req['status'] === 'pending'): ?>
                  <button onclick="deleteRequest(<?= $req['id'] ?>)" class="btn btn-sm btn-danger"><i
                      class="fas fa-trash"></i> Delete</button>
                <?php endif; ?>
              </div>
              <div
                style="background:var(--gray-50);border-radius:8px;padding:16px;font-size:.9rem;color:var(--gray-700);line-height:1.7;margin-bottom:16px">
                <?= nl2br(htmlspecialchars($req['description'])) ?>
              </div>
              <!-- Meta Grid -->
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div>
                  <p
                    style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:var(--gray-400);margin-bottom:2px">
                    Submitted By</p>
                  <p style="font-size:.88rem;font-weight:600"><?= htmlspecialchars($req['submitter_name']) ?></p>
                  <?php if ($req['submitter_dept']): ?>
                    <p style="font-size:.78rem;color:var(--gray-500)"><?= htmlspecialchars($req['submitter_dept']) ?></p>
                  <?php endif; ?>
                </div>
                <div>
                  <p
                    style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:var(--gray-400);margin-bottom:2px">
                    Submitted On</p>
                  <p style="font-size:.88rem;font-weight:600"><?= date('M j, Y g:i A', strtotime($req['created_at'])) ?>
                  </p>
                </div>
                <?php if ($req['location']): ?>
                  <div>
                    <p
                      style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:var(--gray-400);margin-bottom:2px">
                      Location</p>
                    <p style="font-size:.88rem"><i class="fas fa-map-marker-alt" style="color:var(--danger)"></i>
                      <?= htmlspecialchars($req['location']) ?></p>
                  </div>
                <?php endif; ?>
                <?php if ($req['assigned_name']): ?>
                  <div>
                    <p
                      style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:var(--gray-400);margin-bottom:2px">
                      Assigned To</p>
                    <p style="font-size:.88rem;font-weight:600"><i class="fas fa-user-check"
                        style="color:var(--success)"></i> <?= htmlspecialchars($req['assigned_name']) ?></p>
                  </div>
                <?php endif; ?>
                <?php if ($req['resolved_at']): ?>
                  <div>
                    <p
                      style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:var(--gray-400);margin-bottom:2px">
                      Resolved On</p>
                    <p style="font-size:.88rem;color:var(--success);font-weight:600">
                      <?= date('M j, Y g:i A', strtotime($req['resolved_at'])) ?>
                    </p>
                  </div>
                <?php endif; ?>
              </div>
              <!-- Attachment -->
              <?php if ($req['attachment']): ?>
                <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--gray-100)">
                  <p style="font-size:.78rem;font-weight:700;color:var(--gray-500);margin-bottom:6px">ATTACHMENT</p>
                  <a href="assets/uploads/<?= htmlspecialchars($req['attachment']) ?>" target="_blank"
                    class="btn btn-secondary btn-sm">
                    <i class="fas fa-paperclip"></i> <?= htmlspecialchars($req['attachment']) ?>
                  </a>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Admin: Update Status -->
          <?php if (isAdmin()): ?>
            <div class="card mb-24">
              <div class="card-header">
                <h3><i class="fas fa-edit" style="color:var(--primary)"></i> Update Request</h3>
              </div>
              <div class="card-body">
                <form id="commentForm" data-validate>
                  <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                  <div class="form-row">
                    <div class="form-group">
                      <label>Change Status</label>
                      <select name="new_status" class="form-control">
                        <?php foreach (['pending', 'open', 'in_progress', 'resolved', 'closed', 'rejected'] as $s): ?>
                          <option value="<?= $s ?>" <?= $req['status'] == $s ? 'selected' : '' ?>>
                            <?= ucwords(str_replace('_', ' ', $s)) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="form-group">
                      <label>Assign To</label>
                      <select class="form-control" onchange="updateAssign(this.value)">
                        <option value="">— Unassigned —</option>
                        <?php foreach ($staffList as $s): ?>
                          <option value="<?= $s['id'] ?>" <?= $req['assigned_to'] == $s['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['name']) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                  <div class="form-group">
                    <label>Update Note <span style="color:var(--danger)">*</span></label>
                    <textarea name="comment" class="form-control" rows="3"
                      placeholder="Add a comment or update for the user…" required minlength="2" maxlength="1000"
                      data-rule="text-block-short"></textarea>
                    <div class="form-error"></div>
                  </div>
                  <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Post Update</button>
                </form>
              </div>
            </div>
          <?php else: ?>
            <!-- User: Add Comment -->
            <?php if (!in_array($req['status'], ['closed', 'rejected', 'resolved'])): ?>
              <div class="card mb-24">
                <div class="card-header">
                  <h3><i class="fas fa-comment" style="color:var(--primary)"></i> Add Comment</h3>
                </div>
                <div class="card-body">
                  <form id="commentForm" data-validate>
                    <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                    <div class="form-group">
                      <textarea name="comment" class="form-control" rows="3"
                        placeholder="Add additional information or follow up on this request…" required minlength="2"
                        maxlength="1000" data-rule="text-block-short"></textarea>
                      <div class="form-error"></div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Post Update</button>
                  </form>
                </div>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>

        <!-- Right: Timeline -->
        <div>
          <div class="card">
            <div class="card-header">
              <h3><i class="fas fa-history" style="color:var(--primary)"></i> Activity Timeline</h3>
            </div>
            <div class="card-body">
              <!-- Initial submission event -->
              <div id="timeline">
                <div class="timeline">
                  <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                      <div class="timeline-meta"><strong><?= htmlspecialchars($req['submitter_name']) ?></strong> ·
                        <?= timeAgo($req['created_at']) ?>
                      </div>
                      <div class="timeline-text">Request submitted</div>
                      <span class="badge badge-warning timeline-status">Status: Pending</span>
                    </div>
                  </div>
                  <?php foreach ($timeline as $t): ?>
                    <div class="timeline-item">
                      <div class="timeline-dot <?= tlDotClass($t['status_changed_to'] ?? '') ?>"></div>
                      <div class="timeline-content">
                        <div class="timeline-meta">
                          <strong><?= htmlspecialchars($t['author']) ?></strong>
                          <?php if (in_array($t['author_role'], ['admin', 'staff'])): ?><span class="badge badge-primary"
                              style="font-size:.65rem;margin-left:4px">Staff</span><?php endif; ?>
                          · <?= timeAgo($t['created_at']) ?>
                        </div>
                        <div class="timeline-text"><?= nl2br(htmlspecialchars($t['comment'])) ?></div>
                        <?php if ($t['status_changed_to']): ?>
                          <span class="badge badge-info timeline-status">Status →
                            <?= ucwords(str_replace('_', ' ', $t['status_changed_to'])) ?></span>
                        <?php endif; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
              <?php if (empty($timeline)): ?>
                <p style="text-align:center;color:var(--gray-400);font-size:.85rem;padding:20px 0">No updates yet. Check
                  back soon.</p>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <div class="toast-container"></div>
  <script src="assets/js/main.js"></script>
  <script>
    function updateAssign(val) {
      // Store selected assignment for the comment form (handled server-side via update_status)
    }
    // Reload timeline after comment
    const origLoadTl = window.loadTimeline;
  </script>
</body>

</html>