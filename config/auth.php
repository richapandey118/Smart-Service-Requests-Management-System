<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

function isLoggedIn(): bool
{
  return !empty($_SESSION['user_id']);
}

function isAdmin(): bool
{
  return isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'staff'], true);
}

function isSuperAdmin(): bool
{
  return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireLogin(): void
{
  if (!isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
  }
}

function requireAdmin(): void
{
  requireLogin();
  if (!isAdmin()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
  }
}

function currentUser(): ?array
{
  if (!isLoggedIn())
    return null;
  global $conn;
  $id = (int) $_SESSION['user_id'];
  $stmt = $conn->prepare("SELECT id,name,email,role,department,phone,avatar,created_at FROM users WHERE id=? AND is_active=1");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  return $stmt->get_result()->fetch_assoc();
}

function sanitize(string $data): string
{
  return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function normalizeText(string $value): string
{
  return preg_replace('/\s+/', ' ', trim($value));
}

function normalizeMultilineText(string $value): string
{
  $value = str_replace(["\r\n", "\r"], "\n", $value);
  return trim($value);
}

function isValidEmailAddress(string $email): bool
{
  return strlen($email) <= 120 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function isValidPersonName(string $name): bool
{
  return preg_match("/^[A-Za-z][A-Za-z\s'.-]{1,79}$/", $name) === 1;
}

function isValidDepartment(string $department): bool
{
  if ($department === '') {
    return true;
  }
  return preg_match("/^[A-Za-z0-9][A-Za-z0-9&()\/,\.\s-]{1,79}$/", $department) === 1;
}

function isValidPhone(string $phone): bool
{
  if ($phone === '') {
    return true;
  }
  return preg_match('/^\+?[0-9][0-9\s\-()]{7,19}$/', $phone) === 1;
}

function isStrongPassword(string $password): bool
{
  return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9])\S{8,64}$/', $password) === 1;
}

function isValidRequestTitle(string $title): bool
{
  return preg_match("/^[A-Za-z0-9][A-Za-z0-9\s&()\/#.,:;'\"!?+\-]{4,254}$/", $title) === 1;
}

function isValidLocation(string $location): bool
{
  if ($location === '') {
    return true;
  }
  return preg_match("/^[A-Za-z0-9][A-Za-z0-9\s#.,()\/-]{1,119}$/", $location) === 1;
}

function isValidTextBlock(string $text, int $min = 2, int $max = 2000): bool
{
  $length = strlen($text);
  if ($length < $min || $length > $max) {
    return false;
  }
  return preg_match("/^[A-Za-z0-9\s.,:;!?()'\"\/#&@+\-]+$/", $text) === 1;
}

function generateTicketId(): string
{
  return 'TKT-' . date('Y') . '-' . strtoupper(substr(uniqid('', true), -6));
}

function timeAgo(string $datetime): string
{
  $diff = time() - strtotime($datetime);
  if ($diff < 60)
    return 'Just now';
  if ($diff < 3600)
    return floor($diff / 60) . ' min ago';
  if ($diff < 86400)
    return floor($diff / 3600) . ' hr ago';
  if ($diff < 604800)
    return floor($diff / 86400) . ' days ago';
  return date('M j, Y', strtotime($datetime));
}

function statusBadge(string $status): string
{
  $map = [
    'pending' => 'badge-warning',
    'open' => 'badge-info',
    'in_progress' => 'badge-primary',
    'resolved' => 'badge-success',
    'closed' => 'badge-secondary',
    'rejected' => 'badge-danger',
  ];
  $cls = $map[$status] ?? 'badge-secondary';
  $text = ucwords(str_replace('_', ' ', $status));
  return "<span class=\"badge $cls\">$text</span>";
}

function priorityBadge(string $priority): string
{
  $map = ['low' => 'badge-secondary', 'medium' => 'badge-info', 'high' => 'badge-warning', 'urgent' => 'badge-danger'];
  $cls = $map[$priority] ?? 'badge-secondary';
  return "<span class=\"badge $cls\">" . ucfirst($priority) . "</span>";
}

function unreadNotifications(): int
{
  if (!isLoggedIn())
    return 0;
  global $conn;
  $uid = (int) $_SESSION['user_id'];
  $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
  $stmt->bind_param('i', $uid);
  $stmt->execute();
  $stmt->bind_result($c);
  $stmt->fetch();
  return (int) $c;
}
