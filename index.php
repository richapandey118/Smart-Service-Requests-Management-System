<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

// Redirect logged-in users
if (isLoggedIn()) {
  header('Location: ' . BASE_URL . (isAdmin() ? '/admin/index.php' : '/dashboard.php'));
  exit;
}

// Fetch real stats
$totalReqs = $conn->query("SELECT COUNT(*) FROM service_requests")->fetch_row()[0] ?? 0;
$totalUsers = $conn->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetch_row()[0] ?? 0;
$resolvedReqs = $conn->query("SELECT COUNT(*) FROM service_requests WHERE status IN ('resolved','closed')")->fetch_row()[0] ?? 0;
$categories = $conn->query("SELECT COUNT(*) FROM categories WHERE is_active=1")->fetch_row()[0] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>ServiceHub — Smart Service Request & Management Platform</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="landing-body">

  <!-- Navigation -->
  <nav class="landing-nav">
    <div class="landing-logo">
      <div class="logo-icon"><i class="fas fa-headset"></i></div>
      ServiceHub
    </div>
    <div class="landing-nav-links">
      <a href="#features">Features</a>
      <a href="#how-it-works">How It Works</a>
      <a href="#categories">Categories</a>
    </div>
    <div class="nav-cta">
      <a href="login.php" class="btn btn-secondary btn-sm">Sign In</a>
      <a href="register.php" class="btn btn-primary btn-sm"><i class="fas fa-rocket"></i> Get Started</a>
    </div>
  </nav>

  <!-- Hero -->
  <section class="hero">
    <div class="hero-inner">
      <div class="hero-content fade-in">
        <p class="hero-tag"><i class="fas fa-bolt"></i> Smart Request Management</p>
        <h1>Manage Service<br>Requests <span>Effortlessly</span></h1>
        <p class="hero-desc">A centralized platform for submitting, tracking, and resolving service requests. Eliminate
          emails and manual processes — get real-time transparency for everyone.</p>
        <div class="hero-btns">
          <a href="register.php" class="btn btn-primary"><i class="fas fa-user-plus"></i> Create Free Account</a>
          <a href="login.php" class="btn btn-secondary"><i class="fas fa-sign-in-alt"></i> Sign In</a>
        </div>
        <div class="hero-stats">
          <div class="hero-stat">
            <div class="num" data-count="<?= $totalReqs ?>">0</div>
            <div class="lbl">Requests Submitted</div>
          </div>
          <div class="hero-stat">
            <div class="num" data-count="<?= $resolvedReqs ?>">0</div>
            <div class="lbl">Resolved</div>
          </div>
          <div class="hero-stat">
            <div class="num" data-count="<?= $totalUsers ?>">0</div>
            <div class="lbl">Active Users</div>
          </div>
        </div>
      </div>
      <div class="hero-visual">
        <div class="mock-topbar">
          <div class="mock-dot" style="background:#ef4444"></div>
          <div class="mock-dot" style="background:#f59e0b;margin-left:6px"></div>
          <div class="mock-dot" style="background:#10b981;margin-left:6px"></div>
          <span style="color:rgba(255,255,255,.4);font-size:.65rem;margin-left:16px">ServiceHub Dashboard</span>
        </div>
        <div class="mock-cards">
          <div class="mock-card">
            <div class="mock-num">48</div>
            <div class="mock-lbl">Total Requests</div>
          </div>
          <div class="mock-card">
            <div class="mock-num">12</div>
            <div class="mock-lbl">Resolved</div>
          </div>
          <div class="mock-card">
            <div class="mock-num">8</div>
            <div class="mock-lbl">In Progress</div>
          </div>
          <div class="mock-card">
            <div class="mock-num">5</div>
            <div class="mock-lbl">Urgent</div>
          </div>
        </div>
        <div class="mock-table">
          <?php for ($i = 0; $i < 4; $i++): ?>
            <div class="mock-row">
              <div class="mock-line" style="width:<?= rand(50, 90) ?>px;flex-shrink:0"></div>
              <div class="mock-line" style="flex:1"></div>
              <div class="mock-badge"></div>
            </div>
          <?php endfor; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- Stats Banner -->
  <div class="stats-banner">
    <div class="container">
      <div class="banner-stat">
        <div class="big" data-count="<?= $totalReqs + 200 ?>">0</div>
        <div class="name">Requests Handled</div>
      </div>
      <div class="banner-stat">
        <div class="big" data-count="<?= $resolvedReqs + 150 ?>">0</div>
        <div class="name">Issues Resolved</div>
      </div>
      <div class="banner-stat">
        <div class="big" data-count="<?= $totalUsers + 50 ?>">0</div>
        <div class="name">Registered Users</div>
      </div>
      <div class="banner-stat">
        <div class="big" data-count="<?= $categories ?>">0</div>
        <div class="name">Service Categories</div>
      </div>
    </div>
  </div>

  <!-- Features -->
  <section class="section" id="features">
    <div class="container">
      <div class="section-header">
        <span class="section-tag">Key Features</span>
        <h2>Everything You Need to Manage Requests</h2>
        <p>A comprehensive platform designed for efficiency, transparency, and seamless communication.</p>
      </div>
      <div class="features-grid">
        <div class="feature-card">
          <div class="feature-icon"><i class="fas fa-paper-plane"></i></div>
          <h3>Easy Request Submission</h3>
          <p>Submit service requests in seconds using a clean form with category selection, priority levels, and file
            attachments.</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon"><i class="fas fa-tasks"></i></div>
          <h3>Real-Time Tracking</h3>
          <p>Monitor the status of every request with live updates — from submission to resolution, always stay
            informed.</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon"><i class="fas fa-bell"></i></div>
          <h3>Smart Notifications</h3>
          <p>Get instant notifications when your request status changes, a comment is added, or it gets assigned to
            staff.</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon"><i class="fas fa-chart-bar"></i></div>
          <h3>Admin Analytics</h3>
          <p>Powerful dashboards with charts and reports — track trends, resolution times, and team performance at a
            glance.</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon"><i class="fas fa-users-cog"></i></div>
          <h3>Role-Based Access</h3>
          <p>Three roles — Admin, Staff, and User — each with appropriate permissions and tailored dashboards for their
            needs.</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon"><i class="fas fa-comments"></i></div>
          <h3>Threaded Communication</h3>
          <p>Comment threads on every request create a clear communication trail between users and support staff.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- How It Works -->
  <section class="section section-alt" id="how-it-works">
    <div class="container">
      <div class="section-header">
        <span class="section-tag">How It Works</span>
        <h2>Three Simple Steps</h2>
        <p>Getting your issue resolved has never been faster or more transparent.</p>
      </div>
      <div class="steps-grid">
        <div class="step-card">
          <div class="step-num">1</div>
          <h3>Submit Your Request</h3>
          <p>Choose a category, fill in the details, attach relevant files, and set priority. Done in under 2 minutes.
          </p>
        </div>
        <div class="step-card">
          <div class="step-num">2</div>
          <h3>Admin Reviews & Assigns</h3>
          <p>Admins or staff review your request, assign it to the right person, and begin working on a resolution.</p>
        </div>
        <div class="step-card">
          <div class="step-num">3</div>
          <h3>Track & Get Resolved</h3>
          <p>Follow the progress on your dashboard. Get notified every step of the way until your issue is fully
            resolved.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Categories -->
  <?php
  $cats = $conn->query("SELECT * FROM categories WHERE is_active=1 ORDER BY id");
  ?>
  <section class="section" id="categories">
    <div class="container">
      <div class="section-header">
        <span class="section-tag">Service Categories</span>
        <h2>What Can We Help You With?</h2>
        <p>Browse the available service categories and find the right support for your needs.</p>
      </div>
      <div class="category-grid" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr))">
        <?php while ($cat = $cats->fetch_assoc()): ?>
          <div class="category-card" style="cursor:default">
            <div class="cat-icon"
              style="background:<?= htmlspecialchars($cat['color']) ?>20;color:<?= htmlspecialchars($cat['color']) ?>">
              <i class="fas <?= htmlspecialchars($cat['icon']) ?>"></i>
            </div>
            <div class="cat-name"><?= htmlspecialchars($cat['name']) ?></div>
          </div>
        <?php endwhile; ?>
      </div>
    </div>
  </section>

  <!-- CTA -->
  <section class="cta-section">
    <div class="container">
      <h2>Ready to Streamline Your Service Requests?</h2>
      <p>Join ServiceHub today and experience a smarter way to manage requests and support.</p>
      <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap">
        <a href="register.php" class="btn btn-primary btn-lg"><i class="fas fa-user-plus"></i> Create Account</a>
        <a href="login.php" style="background:rgba(255,255,255,.15);color:#fff;border:2px solid rgba(255,255,255,.3)"
          class="btn btn-lg"><i class="fas fa-sign-in-alt"></i> Sign In</a>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="landing-footer">
    <div class="container">
      <p><strong>ServiceHub</strong> &copy; <?= date('Y') ?> — Smart Service Request &amp; Management Platform</p>
      <p style="margin-top:8px;font-size:.78rem">Built for ThinkFest Hackathon &middot; HTML &middot; CSS &middot;
        JavaScript &middot; PHP &middot; MySQL</p>
    </div>
  </footer>

  <script src="assets/js/main.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      // Smooth scroll
      document.querySelectorAll('a[href^="#"]').forEach(a => {
        a.addEventListener('click', e => {
          e.preventDefault();
          const t = document.querySelector(a.getAttribute('href'));
          t && t.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
      });
    });
  </script>
</body>

</html>