<?php
// dashboard.php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Login erforderlich
Auth::requireLogin();

$user_id = Auth::getCurrentUserId();

// Stats laden
$stats = DashboardHelper::getStats($user_id);

// Watchers laden
$watchers = DashboardHelper::getWatchers($user_id, 5);

// Notifications laden
$notifications = DashboardHelper::getNotifications($user_id, 5);

// User Info
$user = Database::fetchOne("SELECT display_name, email FROM users WHERE id = $1", [$user_id]);
$display_name = $user['display_name'] ?? 'User';
$user_email = $user['email'] ?? '';

// Initialen für Avatar
$initials = strtoupper(substr($display_name, 0, 1));
?>
<!DOCTYPE html>
<html lang="de" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="theme-color" content="#dde2ec">
<title>Kafka-Frame — Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:ital,opsz,wght@0,6..12,300;0,6..12,400;0,6..12,500;0,6..12,600;0,6..12,700;1,6..12,300&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>

<div class="bg-scene">
  <div class="orb orb-a"></div>
  <div class="orb orb-b"></div>
</div>

<?php include 'includes/sidebar.php'; ?>

<!-- MAIN -->
<main class="main">
  <div class="topbar">
    <button class="menu-btn" onclick="openSidebar()">☰</button>
    <h1 class="page-title" id="page-title">Dashboard</h1>
    <div class="search-bar">
      <span style="opacity:0.4;font-size:14px">🔍</span>
      <input type="text" placeholder="Suchen…">
    </div>
    <button class="topbar-btn" onclick="showModal()">+ Neuer Watch</button>
    <button class="theme-btn" onclick="toggleTheme()" id="theme-btn">🌙</button>
  </div>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-icon">👁</div>
      <div class="stat-val"><?= $stats['active_watchers'] ?></div>
      <div class="stat-label">Aktive Beobachtungen</div>
      <div class="stat-delta delta-up">↑ Läuft</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">🔔</div>
      <div class="stat-val"><?= $stats['alerts_count'] ?></div>
      <div class="stat-label">Offene Alerts</div>
      <div class="stat-delta <?= $stats['alerts_count'] > 0 ? 'delta-down' : 'delta-up' ?>">
        <?= $stats['alerts_count'] > 0 ? '↓ Zielpreis erreicht' : '✓ Keine Alerts' ?>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">💰</div>
      <div class="stat-val"><?= DashboardHelper::formatPrice($stats['potential_savings']) ?></div>
      <div class="stat-label">Gespartes Potenzial</div>
      <div class="stat-delta delta-up">↑ Beste Deals</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">🤖</div>
      <div class="stat-val"><?= $stats['avg_ai_score'] ?: '--' ?></div>
      <div class="stat-label">Ø KI-Score</div>
      <div class="stat-delta delta-up">
        <?php if ($stats['avg_ai_score'] > 80): ?>
          ↑ Sehr gut
        <?php elseif ($stats['avg_ai_score'] > 60): ?>
          → Gut
        <?php else: ?>
          -- Keine Daten
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Main content grid -->
  <div class="content-grid">
    <!-- Left: Watches + Chart -->
    <div class="left-col">

      <!-- Watchlist -->
      <div class="glass-card">
        <div class="card-header">
          <span style="font-size:18px">👁</span>
          <div class="card-title">Aktive Beobachtungen</div>
          <div class="card-action">Alle anzeigen</div>
        </div>
        <div class="add-form">
          <input class="add-input" id="quick-search" placeholder="Suchbegriff oder Link (z.B. iPhone 15 Pro …)">
          <input class="add-input" id="quick-price" placeholder="Max. Preis €" style="flex:0.6;min-width:90px">
          <button class="add-btn" onclick="showModal()">Beobachten</button>
        </div>
        <div class="watch-list">
          <?php if (empty($watchers)): ?>
            <div style="padding:40px 20px;text-align:center;color:var(--text2)">
              <div style="font-size:40px;margin-bottom:12px">🔍</div>
              <div style="font-size:14px;font-weight:600;margin-bottom:6px">Noch keine Beobachtungen</div>
              <div style="font-size:12px">Erstelle deine erste Watch, um Preise zu überwachen!</div>
            </div>
          <?php else: ?>
            <?php foreach ($watchers as $watcher):
              $icon_map = [
                'kleinanzeigen' => '📱',
                'ebay' => '🛒',
                'willhaben' => '🏠'
              ];
              $icon = $icon_map[$watcher['platform']] ?? '🔎';

              $is_below_target = $watcher['max_price'] && $watcher['current_min_price']
                                 && $watcher['current_min_price'] <= $watcher['max_price'];

              $status_class = $is_below_target ? 'status-alert' : ($watcher['active_listings'] > 0 ? 'status-watch' : 'status-ok');
            ?>
            <div class="watch-item" onclick="window.location.href='listings.php?watcher_id=<?= $watcher['id'] ?>'" style="cursor:pointer">
              <div class="wi-thumb" style="background:rgba(59,107,255,0.12)"><?= $icon ?></div>
              <div class="wi-info">
                <div class="wi-name"><?= htmlspecialchars($watcher['label']) ?></div>
                <div class="wi-sub">
                  Kleinanzeigen · <?= $watcher['active_listings'] ?> Angebote
                  <?php if ($watcher['max_price']): ?>
                    · Max: <?= DashboardHelper::formatPrice($watcher['max_price']) ?>
                  <?php endif; ?>
                </div>
              </div>
              <div class="wi-price">
                <?php if ($watcher['current_min_price']): ?>
                  <div class="wi-current" style="color:<?= $is_below_target ? 'var(--green)' : 'var(--text)' ?>">
                    <?= DashboardHelper::formatPrice($watcher['current_min_price']) ?>
                  </div>
                  <?php if ($watcher['max_price']): ?>
                    <div class="wi-target">Max: <?= DashboardHelper::formatPrice($watcher['max_price']) ?> <?= $is_below_target ? '✓' : '' ?></div>
                  <?php endif; ?>
                <?php else: ?>
                  <div class="wi-current" style="font-size:12px;color:var(--text2)">Keine Angebote</div>
                <?php endif; ?>
              </div>
              <div class="wi-status <?= $status_class ?>"></div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Chart Placeholder -->
      <div class="glass-card">
        <div class="card-header">
          <span style="font-size:18px">📈</span>
          <div class="card-title">Preisverlauf</div>
          <div style="display:flex;gap:6px;margin-left:auto">
            <span style="font-size:11px;color:var(--text2);cursor:pointer;padding:3px 8px;border-radius:6px;background:var(--input-bg);border:1px solid var(--input-border)">7T</span>
            <span style="font-size:11px;color:var(--accent);cursor:pointer;padding:3px 8px;border-radius:6px;background:var(--input-bg);border:1px solid var(--accent)">30T</span>
            <span style="font-size:11px;color:var(--text2);cursor:pointer;padding:3px 8px;border-radius:6px;background:var(--input-bg);border:1px solid var(--input-border)">90T</span>
          </div>
        </div>
        <div style="padding:40px 20px;text-align:center;color:var(--text2)">
          <div style="font-size:40px;margin-bottom:12px">📊</div>
          <div style="font-size:14px;font-weight:600;margin-bottom:6px">Preisverlauf-Charts</div>
          <div style="font-size:12px">Wähle eine Beobachtung, um den Preisverlauf zu sehen</div>
        </div>
      </div>

    </div>

    <!-- Right column -->
    <div class="right-col">

      <!-- Alert feed -->
      <div class="glass-card">
        <div class="card-header">
          <span style="font-size:18px">🔔</span>
          <div class="card-title">Alerts</div>
          <div class="card-action">Alle lesen</div>
        </div>
        <?php if (empty($notifications)): ?>
          <div style="padding:40px 20px;text-align:center;color:var(--text2)">
            <div style="font-size:40px;margin-bottom:12px">🔕</div>
            <div style="font-size:13px;font-weight:600;margin-bottom:4px">Keine neuen Alerts</div>
            <div style="font-size:11px">Du wirst benachrichtigt, sobald es Neuigkeiten gibt</div>
          </div>
        <?php else: ?>
          <?php foreach ($notifications as $notif):
            $type_map = [
              'new_deal' => 'type-deal',
              'price_change' => 'type-price',
              'trend' => 'type-price',
              'gone' => 'type-scam',
              'system' => 'type-price'
            ];
            $dot_class = $type_map[$notif['type']] ?? 'type-price';
          ?>
          <div class="alert-item">
            <div class="alert-dot <?= $dot_class ?>"></div>
            <div class="alert-content">
              <div class="alert-title"><?= htmlspecialchars($notif['title']) ?></div>
              <div class="alert-sub"><?= htmlspecialchars($notif['message']) ?></div>
            </div>
            <div class="alert-time"><?= DashboardHelper::timeAgo($notif['created_at']) ?></div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Info Card -->
      <div class="glass-card info-card">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
          <span style="font-size:24px">🚀</span>
          <div style="font-size:15px;font-weight:700">Willkommen bei Kafka-Frame!</div>
        </div>
        <div style="font-size:12px;color:var(--text2);line-height:1.6;margin-bottom:16px">
          Überwache Preise auf <strong style="color:var(--text)">eBay Kleinanzeigen</strong> in Echtzeit.
          Erstelle deine erste Beobachtung und werde automatisch benachrichtigt,
          wenn dein Zielpreis erreicht wird.
        </div>
        <div style="display:flex;flex-direction:column;gap:8px">
          <div style="display:flex;align-items:center;gap:8px;font-size:12px">
            <span style="color:var(--green)">✓</span>
            <span style="color:var(--text2)">Automatische Preisüberwachung</span>
          </div>
          <div style="display:flex;align-items:center;gap:8px;font-size:12px">
            <span style="color:var(--green)">✓</span>
            <span style="color:var(--text2)">E-Mail-Benachrichtigungen</span>
          </div>
          <div style="display:flex;align-items:center;gap:8px;font-size:12px">
            <span style="color:var(--green)">✓</span>
            <span style="color:var(--text2)">Alle 5-60 Minuten aktualisiert</span>
          </div>
        </div>
      </div>

    </div>
  </div>
</main>

<!-- Add Modal - VERBESSERT -->
<div id="modal" style="display:none;position:fixed;inset:0;z-index:200;align-items:center;justify-content:center;padding:20px;background:rgba(0,0,0,0.5);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px)">
  <div style="background:var(--bg);border:1px solid var(--card-border);border-radius:var(--radius);padding:28px;max-width:480px;width:100%;box-shadow:0 24px 80px rgba(0,0,0,0.5);position:relative;z-index:201;max-height:90vh;overflow-y:auto">
    <div style="font-size:18px;font-weight:700;margin-bottom:20px;color:var(--text)">
      + Neue Kleinanzeigen-Beobachtung
    </div>
    <form id="addWatcherForm">
      <div style="display:flex;flex-direction:column;gap:12px">

        <!-- Suchbegriff ODER Link -->
        <div>
          <label style="font-size:11px;font-weight:600;color:var(--text2);text-transform:uppercase;letter-spacing:0.6px;display:block;margin-bottom:6px">
            Suchbegriff
          </label>
          <input class="add-input" name="query" id="query-input" style="width:100%"
                 placeholder="🔍 z.B. iPhone 15 Pro 256GB">
        </div>

        <div style="text-align:center;color:var(--text2);font-size:12px;font-weight:600">
          — ODER —
        </div>

        <div>
          <label style="font-size:11px;font-weight:600;color:var(--text2);text-transform:uppercase;letter-spacing:0.6px;display:block;margin-bottom:6px">
            Direkter Link
          </label>
          <input class="add-input" name="url" id="url-input" style="width:100%"
                 placeholder="🔗 https://www.kleinanzeigen.de/...">
        </div>

        <!-- Preis-Range -->
        <div>
          <label style="font-size:11px;font-weight:600;color:var(--text2);text-transform:uppercase;letter-spacing:0.6px;display:block;margin-bottom:6px">
            Preisbereich
          </label>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <input class="add-input" name="min_price" type="number" step="0.01"
                   placeholder="💰 Min. €">
            <input class="add-input" name="max_price" type="number" step="0.01"
                   placeholder="💰 Max. €">
          </div>
        </div>

        <!-- Standort & Radius -->
        <div>
          <label style="font-size:11px;font-weight:600;color:var(--text2);text-transform:uppercase;letter-spacing:0.6px;display:block;margin-bottom:6px">
            Standort
          </label>
          <input class="add-input" name="location" style="width:100%;margin-bottom:8px"
                 placeholder="📍 z.B. München oder PLZ">
        </div>

        <div>
          <label style="font-size:11px;font-weight:600;color:var(--text2);text-transform:uppercase;letter-spacing:0.6px;display:block;margin-bottom:6px">
            Umkreis
          </label>
          <select class="add-input" name="radius_km" style="width:100%;padding-left:12px">
            <option value="">Ganzer Ort</option>
            <option value="5">+ 5 km</option>
            <option value="10">+ 10 km</option>
            <option value="20">+ 20 km</option>
            <option value="30">+ 30 km</option>
            <option value="50">+ 50 km</option>
            <option value="100">+ 100 km</option>
            <option value="150">+ 150 km</option>
            <option value="200">+ 200 km</option>
          </select>
        </div>

        <!-- Intervall -->
        <div>
          <label style="font-size:11px;font-weight:600;color:var(--text2);text-transform:uppercase;letter-spacing:0.6px;display:block;margin-bottom:6px">
            Aktualisierung
          </label>
          <select class="add-input" name="interval" style="width:100%;padding-left:12px">
            <option value="10" selected>⏱ Alle 10 Minuten</option>
            <option value="5">⏱ Alle 5 Minuten</option>
            <option value="15">⏱ Alle 15 Minuten</option>
            <option value="30">⏱ Alle 30 Minuten</option>
            <option value="60">⏱ Stündlich</option>
          </select>
        </div>

        <div style="background:var(--input-bg);border:1px solid var(--input-border);border-radius:10px;padding:10px;font-size:11px;color:var(--text2)">
          ℹ️ Nur <strong style="color:var(--text)">eBay Kleinanzeigen</strong> wird unterstützt
        </div>
      </div>

      <div style="display:flex;gap:10px;margin-top:18px">
        <button type="button" onclick="hideModal()"
                style="flex:1;padding:11px;background:var(--input-bg);border:1px solid var(--input-border);border-radius:10px;color:var(--text);font-family:inherit;cursor:pointer;font-size:14px">
          Abbrechen
        </button>
        <button type="submit"
                style="flex:2;padding:11px;background:linear-gradient(135deg,#3b6bff,#7c3aed);border:none;border-radius:10px;color:#fff;font-family:inherit;font-size:14px;font-weight:600;cursor:pointer">
          Beobachtung starten →
        </button>
      </div>
    </form>
  </div>
</div>

<script src="assets/js/theme.js"></script>
<script>
// Dashboard Functions
function showModal() {
    document.getElementById('modal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function hideModal() {
    document.getElementById('modal').style.display = 'none';
    document.body.style.overflow = '';
}

function openSidebar() {
    document.getElementById('sidebar')?.classList.add('open');
    document.getElementById('overlay')?.classList.add('open');
}

function closeSidebar() {
    document.getElementById('sidebar')?.classList.remove('open');
    document.getElementById('overlay')?.classList.remove('open');
}

function setPage(el, name) {
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('page-title').textContent = name;
    closeSidebar();
}

// Event Listeners
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('modal')?.addEventListener('click', function(e) {
        if (e.target === this) hideModal();
    });
    document.getElementById('overlay')?.addEventListener('click', closeSidebar);

    // Query/URL gegenseitig ausschließen
    const queryInput = document.getElementById('query-input');
    const urlInput = document.getElementById('url-input');

    queryInput?.addEventListener('input', function() {
        if (this.value) urlInput.value = '';
    });

    urlInput?.addEventListener('input', function() {
        if (this.value) queryInput.value = '';
    });
});

// Form Submit
document.getElementById('addWatcherForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const data = Object.fromEntries(formData.entries());

    if (!data.query && !data.url) {
        alert('❌ Bitte gib einen Suchbegriff ODER einen Link ein!');
        return;
    }

    data.platform = 'kleinanzeigen';

    try {
        const response = await fetch('api/add_watcher.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            alert('✅ Beobachtung erfolgreich erstellt!\n\nDer Scraper findet bald die ersten Angebote.');
            hideModal();
            this.reset();
            setTimeout(() => location.reload(), 1000);
        } else {
            alert('❌ Fehler: ' + result.error);
        }
    } catch (err) {
        alert('❌ Fehler: ' + err.message);
    }
});
</script>
</body>
</html>