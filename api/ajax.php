<?php
/**
 * ServiceHub — Central AJAX API Handler
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'message' => 'Method not allowed']);
  exit;
}

$action = trim($_POST['action'] ?? '');

switch ($action) {

  /* ── Notification count ──────────────────────────────────────────── */
  case 'get_notif_count':
    requireLogin();
    $uid = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $stmt->bind_result($c);
    $stmt->fetch();
    echo json_encode(['success' => true, 'count' => (int) $c]);
    break;

  /* ── Get notifications ───────────────────────────────────────────── */
  case 'get_notifications':
    requireLogin();
    $uid = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT id,request_id,message,type,is_read,created_at FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 15");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$r)
      $r['time_ago'] = timeAgo($r['created_at']);
    echo json_encode(['success' => true, 'notifications' => $rows]);
    break;

  /* ── Mark one notification read ──────────────────────────────────── */
  case 'mark_notification_read':
    requireLogin();
    $id = (int) ($_POST['id'] ?? 0);
    $uid = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?");
    $stmt->bind_param('ii', $id, $uid);
    $stmt->execute();
    echo json_encode(['success' => true]);
    break;

  /* ── Mark all read ───────────────────────────────────────────────── */
  case 'mark_all_read':
    requireLogin();
    $uid = (int) $_SESSION['user_id'];
    $conn->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->bind_param('i', $uid);
    $stmt = $conn->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    echo json_encode(['success' => true]);
    break;

  /* ── Get timeline for a request ──────────────────────────────────── */
  case 'get_timeline':
    requireLogin();
    $reqId = (int) ($_POST['request_id'] ?? 0);
    $uid = (int) $_SESSION['user_id'];
    // Verify ownership or admin
    if (!isAdmin()) {
      $chk = $conn->prepare("SELECT id FROM service_requests WHERE id=? AND user_id=?");
      $chk->bind_param('ii', $reqId, $uid);
      $chk->execute();
      if (!$chk->get_result()->fetch_assoc()) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        break;
      }
    }
    $stmt = $conn->prepare("SELECT ru.comment,ru.status_changed_to,ru.created_at,u.name AS author, u.role FROM request_updates ru JOIN users u ON u.id=ru.user_id WHERE ru.request_id=? AND (ru.is_internal=0 OR ?) ORDER BY ru.created_at ASC");
    $isAdm = isAdmin() ? 1 : 0;
    $stmt->bind_param('ii', $reqId, $isAdm);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$r)
      $r['time_ago'] = timeAgo($r['created_at']);
    echo json_encode(['success' => true, 'timeline' => $rows]);
    break;

  /* ── Add comment / status update ────────────────────────────────── */
  case 'add_comment':
    requireLogin();
    $reqId = (int) ($_POST['request_id'] ?? 0);
    $comment = normalizeMultilineText($_POST['comment'] ?? '');
    $newStat = trim($_POST['new_status'] ?? '');
    $uid = (int) $_SESSION['user_id'];
    if (!$reqId || !$comment) {
      echo json_encode(['success' => false, 'message' => 'Missing data']);
      break;
    }
    if (!isValidTextBlock($comment, 2, 1000)) {
      echo json_encode(['success' => false, 'message' => 'Comment must be 2-1000 valid characters']);
      break;
    }

    // Verify access
    if (!isAdmin()) {
      $chk = $conn->prepare("SELECT id FROM service_requests WHERE id=? AND user_id=?");
      $chk->bind_param('ii', $reqId, $uid);
      $chk->execute();
      if (!$chk->get_result()->fetch_assoc()) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        break;
      }
      $newStat = ''; // users cannot change status
    }

    $internal = isAdmin() && isset($_POST['internal']) ? 1 : 0;
    $stmt = $conn->prepare("INSERT INTO request_updates (request_id,user_id,comment,status_changed_to,is_internal) VALUES (?,?,?,?,?)");
    if (!$stmt) {
      echo json_encode(['success' => false, 'message' => 'Unable to prepare update query']);
      break;
    }
    $statVal = $newStat ?: null;
    $stmt->bind_param('iissi', $reqId, $uid, $comment, $statVal, $internal);
    if (!$stmt->execute()) {
      echo json_encode(['success' => false, 'message' => 'Failed to save update']);
      break;
    }

    // Update request status if changed
    if ($newStat && isAdmin()) {
      $resolved = in_array($newStat, ['resolved', 'closed']) ? date('Y-m-d H:i:s') : null;
      $upd = $conn->prepare("UPDATE service_requests SET status=?, resolved_at=?, updated_at=NOW() WHERE id=?");
      $upd->bind_param('ssi', $newStat, $resolved, $reqId);
      $upd->execute();

      // Notify submitter
      $req = $conn->query("SELECT user_id, ticket_id FROM service_requests WHERE id=$reqId")->fetch_assoc();
      if ($req && $req['user_id'] != $uid) {
        $msg = "Your request {$req['ticket_id']} status changed to " . str_replace('_', ' ', $newStat) . ".";
        $ins = $conn->prepare("INSERT INTO notifications (user_id,request_id,message,type) VALUES (?,?,?,'status_update')");
        $ins->bind_param('iis', $req['user_id'], $reqId, $msg);
        $ins->execute();
      }
    } else {
      // Notify admin about user comment
      $req = $conn->query("SELECT ticket_id FROM service_requests WHERE id=$reqId LIMIT 1")->fetch_assoc();
      $adm = $conn->query("SELECT id FROM users WHERE role='admin' LIMIT 1")->fetch_assoc();
      if ($req && $adm) {
        $msg = "New comment on {$req['ticket_id']}.";
        $ins = $conn->prepare("INSERT INTO notifications (user_id,request_id,message,type) VALUES (?,?,?,'comment')");
        $ins->bind_param('iis', $adm['id'], $reqId, $msg);
        $ins->execute();
      }
    }
    echo json_encode(['success' => true]);
    break;

  /* ── Update request status (admin) ──────────────────────────────── */
  case 'update_status':
    requireAdmin();
    $id = (int) ($_POST['id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    $note = trim($_POST['note'] ?? '');
    $assign = (int) ($_POST['assigned_to'] ?? 0);
    $allowed = ['pending', 'open', 'in_progress', 'resolved', 'closed', 'rejected'];
    if (!in_array($status, $allowed, true)) {
      echo json_encode(['success' => false, 'message' => 'Invalid status']);
      break;
    }
    if (!$note) {
      echo json_encode(['success' => false, 'message' => 'Note required']);
      break;
    }

    $resolved = in_array($status, ['resolved', 'closed']) ? date('Y-m-d H:i:s') : null;
    $assignVal = $assign ?: null;
    $upd = $conn->prepare("UPDATE service_requests SET status=?,assigned_to=?,resolved_at=?,updated_at=NOW() WHERE id=?");
    $upd->bind_param('sisi', $status, $assignVal, $resolved, $id);
    $upd->execute();

    $uid = (int) $_SESSION['user_id'];
    $ins = $conn->prepare("INSERT INTO request_updates (request_id,user_id,comment,status_changed_to) VALUES (?,?,?,?)");
    $ins->bind_param('iiss', $id, $uid, $note, $status);
    $ins->execute();

    // Notify owner
    $req = $conn->query("SELECT user_id, ticket_id FROM service_requests WHERE id=$id LIMIT 1")->fetch_assoc();
    if ($req) {
      $msg = "Your request {$req['ticket_id']} has been updated to " . str_replace('_', ' ', $status) . ".";
      $n = $conn->prepare("INSERT INTO notifications (user_id,request_id,message,type) VALUES (?,?,?,'status_update')");
      $n->bind_param('iis', $req['user_id'], $id, $msg);
      $n->execute();
    }
    echo json_encode(['success' => true]);
    break;

  /* ── Delete request ──────────────────────────────────────────────── */
  case 'delete_request':
    requireLogin();
    $id = (int) ($_POST['id'] ?? 0);
    $uid = (int) $_SESSION['user_id'];
    if (!isAdmin()) {
      $chk = $conn->prepare("SELECT id FROM service_requests WHERE id=? AND user_id=? AND status='pending'");
      $chk->bind_param('ii', $id, $uid);
      $chk->execute();
      if (!$chk->get_result()->fetch_assoc()) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete this request']);
        break;
      }
    }
    $del = $conn->prepare("DELETE FROM service_requests WHERE id=?");
    $del->bind_param('i', $id);
    $del->execute();
    echo json_encode(['success' => true]);
    break;

  /* ── Toggle user active/inactive ─────────────────────────────────── */
  case 'toggle_user':
    requireAdmin();
    $id = (int) ($_POST['id'] ?? 0);
    if ($id === (int) $_SESSION['user_id']) {
      echo json_encode(['success' => false, 'message' => 'Cannot deactivate yourself']);
      break;
    }
    $conn->query("UPDATE users SET is_active = 1 - is_active WHERE id=$id");
    echo json_encode(['success' => true]);
    break;

  /* ── Search requests (admin live search) ─────────────────────────── */
  case 'search_requests':
    requireAdmin();
    $q = '%' . $conn->real_escape_string(trim($_POST['q'] ?? '')) . '%';
    $status = trim($_POST['status'] ?? '');
    $priority = trim($_POST['priority'] ?? '');
    $catId = (int) ($_POST['category'] ?? 0);

    $where = ["(sr.title LIKE ? OR sr.ticket_id LIKE ? OR u.name LIKE ?)"];
    $params = [$q, $q, $q];
    $types = 'sss';
    if ($status) {
      $where[] = "sr.status=?";
      $params[] = $status;
      $types .= 's';
    }
    if ($priority) {
      $where[] = "sr.priority=?";
      $params[] = $priority;
      $types .= 's';
    }
    if ($catId) {
      $where[] = "sr.category_id=?";
      $params[] = $catId;
      $types .= 'i';
    }

    $sql = "SELECT sr.*,u.name AS user_name,c.name AS cat_name,a.name AS assigned_name FROM service_requests sr LEFT JOIN users u ON u.id=sr.user_id LEFT JOIN categories c ON c.id=sr.category_id LEFT JOIN users a ON a.id=sr.assigned_to WHERE " . implode(' AND ', $where) . " ORDER BY sr.created_at DESC LIMIT 50";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $html = '';
    foreach ($rows as $r) {
      $statBadge = statusBadgeHtml($r['status']);
      $priBadge = priorityBadgeHtml($r['priority']);
      $tid = htmlspecialchars($r['ticket_id'], ENT_QUOTES);
      $title = htmlspecialchars($r['title'], ENT_QUOTES);
      $uname = htmlspecialchars($r['user_name'], ENT_QUOTES);
      $cat = htmlspecialchars($r['cat_name'] ?? '—', ENT_QUOTES);
      $date = date('M j, Y', strtotime($r['created_at']));
      $html .= "<tr>
                <td><span class='ticket-id'>$tid</span></td>
                <td><a href='/ThinkFest/view-request.php?id={$r['id']}' style='color:var(--dark);font-weight:600;'>$title</a></td>
                <td>$uname</td><td>$cat</td>
                <td>$priBadge</td><td>$statBadge</td><td>$date</td>
                <td><a href='/ThinkFest/view-request.php?id={$r['id']}' class='btn btn-sm btn-secondary'><i class='fas fa-eye'></i></a>
                    <button onclick='openStatusModal({$r['id']},\"{$r['status']}\",\"{$r['assigned_to']}\")' class='btn btn-sm btn-primary'><i class='fas fa-edit'></i></button></td>
            </tr>";
    }
    echo json_encode(['success' => true, 'html' => $html ?: '<tr><td colspan="8" style="text-align:center;padding:30px;color:var(--gray-500);">No requests found</td></tr>']);
    break;

  /* ── Update profile ──────────────────────────────────────────────── */
  case 'update_profile':
    requireLogin();
    $uid = (int) $_SESSION['user_id'];
    $name = normalizeText($_POST['name'] ?? '');
    $dept = normalizeText($_POST['department'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    if (!isValidPersonName($name)) {
      echo json_encode(['success' => false, 'message' => 'Enter a valid full name']);
      break;
    }
    if (!isValidDepartment($dept)) {
      echo json_encode(['success' => false, 'message' => 'Enter a valid department']);
      break;
    }
    if (!isValidPhone($phone)) {
      echo json_encode(['success' => false, 'message' => 'Enter a valid phone number']);
      break;
    }
    $stmt = $conn->prepare("UPDATE users SET name=?,department=?,phone=? WHERE id=?");
    $stmt->bind_param('sssi', $name, $dept, $phone, $uid);
    $stmt->execute();
    $_SESSION['user_name'] = $name;
    echo json_encode(['success' => true, 'message' => 'Profile updated']);
    break;

  /* ── Change password ─────────────────────────────────────────────── */
  case 'change_password':
    requireLogin();
    $uid = (int) $_SESSION['user_id'];
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if (!isStrongPassword($new)) {
      echo json_encode(['success' => false, 'message' => 'Password must be 8-64 chars and include uppercase, lowercase, number and special character']);
      break;
    }
    if ($new !== $confirm) {
      echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
      break;
    }
    $stmt = $conn->prepare("SELECT password FROM users WHERE id=?");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row || !password_verify($current, $row['password'])) {
      echo json_encode(['success' => false, 'message' => 'Current password incorrect']);
      break;
    }
    $hash = password_hash($new, PASSWORD_DEFAULT);
    $upd = $conn->prepare("UPDATE users SET password=? WHERE id=?");
    $upd->bind_param('si', $hash, $uid);
    $upd->execute();
    echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
    break;

  /* ── Get stats (dashboard) ───────────────────────────────────────── */
  case 'get_stats':
    requireAdmin();
    $total = $conn->query("SELECT COUNT(*) FROM service_requests")->fetch_row()[0];
    $pending = $conn->query("SELECT COUNT(*) FROM service_requests WHERE status IN ('pending','open')")->fetch_row()[0];
    $progress = $conn->query("SELECT COUNT(*) FROM service_requests WHERE status='in_progress'")->fetch_row()[0];
    $resolved = $conn->query("SELECT COUNT(*) FROM service_requests WHERE status IN ('resolved','closed')")->fetch_row()[0];
    echo json_encode(compact('total', 'pending', 'progress', 'resolved'));
    break;

  default:
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
}

/* ── Local helpers ────────────────────────────────────────────────── */
function statusBadgeHtml(string $s): string
{
  $m = ['pending' => 'badge-warning', 'open' => 'badge-info', 'in_progress' => 'badge-primary', 'resolved' => 'badge-success', 'closed' => 'badge-secondary', 'rejected' => 'badge-danger'];
  $c = $m[$s] ?? 'badge-secondary';
  return "<span class='badge $c'>" . ucwords(str_replace('_', ' ', $s)) . "</span>";
}
function priorityBadgeHtml(string $p): string
{
  $m = ['low' => 'badge-secondary', 'medium' => 'badge-info', 'high' => 'badge-warning', 'urgent' => 'badge-danger'];
  $c = $m[$p] ?? 'badge-secondary';
  return "<span class='badge $c'>" . ucfirst($p) . "</span>";
}
