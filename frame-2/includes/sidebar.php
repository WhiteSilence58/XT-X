<?php
// includes/sidebar.php
// User-Info laden (falls nicht bereits gesetzt)
if (!isset($user_id)) {
    $user_id = Auth::getCurrentUserId();
}

if (!isset($user)) {
    $user = Database::fetchOne("SELECT display_name, email FROM users WHERE id = $1", [$user_id]);
}

$display_name = $user['display_name'] ?? 'User';
$user_email = $user['email'] ?? '';
$initials = strtoupper(substr($display_name, 0, 1));

// Simple Stats laden (ohne DashboardHelper)
$active_watchers = Database::fetchOne("
    SELECT COUNT(*) as count FROM watchers
    WHERE user_id = $1 AND status = 'active'
", [$user_id])['count'] ?? 0;

$alerts_count = Database::fetchOne("
    SELECT COUNT(DISTINCT l.id) as count
    FROM listings l
    JOIN watchers w ON l.watcher_id = w.id
    WHERE w.user_id = $1
      AND l.status = 'active'
      AND l.price <= COALESCE(w.max_price, l.price)
      AND l.first_seen_at > NOW() - INTERVAL '24 hours'
", [$user_id])['count'] ?? 0;

$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar-overlay" id="overlay" onclick="closeSidebar()"></div>

<aside class="sidebar" id="sidebar">
  <div class="logo">
    <div class="logo-icon">📡</div>
    <div class="logo-text">Kafka<span>-Frame</span></div>
  </div>

  <div class="nav-section-label">Übersicht</div>

  <div class="nav-item <?= $current_page === 'dashboard.php' ? 'active' : '' ?>"
       onclick="location.href='dashboard.php'">
    <span class="ni">🏠</span> Dashboard
    <?php if ($alerts_count > 0): ?>
    <span class="badge"><?= $alerts_count ?></span>
    <?php endif; ?>
  </div>

  <div class="nav-item <?= $current_page === 'watchers.php' ? 'active' : '' ?>"
       onclick="location.href='watchers.php'">
    <span class="ni">👁</span> Beobachtungen
    <?php if ($active_watchers > 0): ?>
    <span class="badge-sm"><?= $active_watchers ?></span>
    <?php endif; ?>
  </div>

  <div class="nav-item <?= $current_page === 'listings.php' ? 'active' : '' ?>"
       onclick="location.href='listings.php'">
    <span class="ni">📦</span> Angebote
  </div>

  <div class="nav-item <?= $current_page === 'alerts.php' ? 'active' : '' ?>"
       onclick="location.href='alerts.php'">
    <span class="ni">🔔</span> Alerts
    <?php if ($alerts_count > 0): ?>
    <span class="badge"><?= $alerts_count ?></span>
    <?php endif; ?>
  </div>

  <div class="nav-item" onclick="alert('🚧 Coming soon!')">
    <span class="ni">📈</span> Preisverläufe
  </div>

  <div class="nav-section-label" style="margin-top:8px">Analyse</div>

  <div class="nav-item" onclick="alert('🚧 Coming soon!')">
    <span class="ni">🤖</span> KI-Analyse
  </div>

  <div class="nav-item" onclick="alert('🚧 Coming soon!')">
    <span class="ni">🛡️</span> Scam-Check
  </div>

  <div class="nav-item" onclick="alert('🚧 Coming soon!')">
    <span class="ni">📊</span> Berichte
  </div>

  <div class="nav-section-label" style="margin-top:8px">System</div>

  <div class="nav-item <?= $current_page === 'settings.php' ? 'active' : '' ?>"
       onclick="location.href='settings.php'">
    <span class="ni">⚙️</span> Einstellungen
  </div>

<div class="nav-item <?= $current_page === 'platform_login.php' ? 'active' : '' ?>"
     onclick="location.href='platform_login.php'">
  <span class="ni">🔐</span> Kleinanzeigen Login
</div>
  <div class="nav-item" onclick="alert('🚧 Coming soon!')">
    <span class="ni">✉️</span> E-Mail Regeln
  </div>

  <div class="sidebar-footer">
    <div class="avatar"><?= htmlspecialchars($initials) ?></div>
    <div class="user-info">
      <div class="user-name"><?= htmlspecialchars($display_name) ?></div>
      <div class="user-plan">Aktiv · <?= $active_watchers ?> Watches</div>
    </div>
    <a href="logout.php" style="font-size:14px;cursor:pointer;opacity:0.5;text-decoration:none" title="Abmelden">⎋</a>
  </div>
</aside>

<style>
.badge-sm {
  display: inline-block;
  background: var(--accent);
  color: white;
  font-size: 10px;
  font-weight: 600;
  padding: 2px 6px;
  border-radius: 10px;
  margin-left: auto;
}
</style>