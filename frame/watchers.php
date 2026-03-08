<?php
// watchers.php - Alle Beobachtungen mit Verwaltung
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

Auth::requireLogin();

$user_id = Auth::getCurrentUserId();

// Alle Watchers mit Details laden
$watchers = Database::fetchAll("
    SELECT
        w.*,
        COUNT(DISTINCT l.id) FILTER (WHERE l.status = 'active') as active_listings,
        MIN(l.price) FILTER (WHERE l.status = 'active') as current_min_price,
        MAX(l.price) FILTER (WHERE l.status = 'active') as current_max_price,
        (
            SELECT sl.finished_at
            FROM scrape_logs sl
            WHERE sl.watcher_id = w.id
            ORDER BY sl.finished_at DESC
            LIMIT 1
        ) as last_run_at_real,
        (
            SELECT sl.listings_found
            FROM scrape_logs sl
            WHERE sl.watcher_id = w.id
            ORDER BY sl.finished_at DESC
            LIMIT 1
        ) as last_found_count,
        (
            SELECT sl.success
            FROM scrape_logs sl
            WHERE sl.watcher_id = w.id
            ORDER BY sl.finished_at DESC
            LIMIT 1
        ) as last_run_success,
        (
            SELECT COUNT(*)
            FROM scrape_logs sl
            WHERE sl.watcher_id = w.id
              AND sl.success = false
              AND sl.finished_at > NOW() - INTERVAL '1 hour'
        ) as recent_errors
    FROM watchers w
    LEFT JOIN listings l ON l.watcher_id = w.id AND l.status = 'active'
    WHERE w.user_id = $1
    GROUP BY w.id
    ORDER BY w.created_at DESC
", [$user_id]);

// User Info für Sidebar
$user = Database::fetchOne("SELECT display_name, email FROM users WHERE id = $1", [$user_id]);
$display_name = $user['display_name'] ?? 'User';
$initials = strtoupper(substr($display_name, 0, 1));
$stats = DashboardHelper::getStats($user_id);
?>
<!DOCTYPE html>
<html lang="de" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="theme-color" content="#dde2ec">
<title>Beobachtungen — Kafka-Frame</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:ital,opsz,wght@0,6..12,300;0,6..12,400;0,6..12,500;0,6..12,600;0,6..12,700;1,6..12,300&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/dashboard.css">
<style>
.action-btn {
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    border: none;
    font-family: inherit;
}
.action-btn:hover {
    transform: translateY(-1px);
}
.action-btn-primary {
    background: linear-gradient(135deg, #3b6bff, #7c3aed);
    color: #fff;
}
.action-btn-secondary {
    background: var(--input-bg);
    border: 1px solid var(--input-border);
    color: var(--text);
}
.action-btn-danger {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #dc2626;
}
.action-btn-success {
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #16a34a;
}
.action-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>
</head>
<body>

<div class="bg-scene">
  <div class="orb orb-a"></div>
  <div class="orb orb-b"></div>
</div>

<?php include 'includes/sidebar.php'; ?>

<main class="main">
  <div class="topbar">
    <button class="menu-btn" onclick="openSidebar()">☰</button>
    <h1 class="page-title">Beobachtungen</h1>
    <button class="topbar-btn" onclick="window.location.href='dashboard.php#add'">+ Neuer Watch</button>
    <button class="theme-btn" onclick="toggleTheme()" id="theme-btn">🌙</button>
  </div>

  <?php if (empty($watchers)): ?>
    <div class="glass-card" style="padding:60px 20px;text-align:center">
      <div style="font-size:60px;margin-bottom:16px">👁</div>
      <div style="font-size:18px;font-weight:700;margin-bottom:8px;color:var(--text)">
        Noch keine Beobachtungen
      </div>
      <div style="font-size:13px;color:var(--text2);margin-bottom:20px">
        Erstelle deine erste Watch, um Kleinanzeigen-Angebote zu überwachen
      </div>
      <button onclick="window.location.href='dashboard.php#add'"
              style="padding:12px 24px;background:linear-gradient(135deg,#3b6bff,#7c3aed);border:none;border-radius:12px;color:#fff;font-size:14px;font-weight:600;cursor:pointer">
        + Erste Beobachtung erstellen
      </button>
    </div>
  <?php else: ?>

    <!-- Watchers Grid -->
    <div style="display:grid;gap:16px">
      <?php foreach ($watchers as $watcher):
        $is_running = $watcher['next_run_at'] && strtotime($watcher['next_run_at']) <= time();
        $is_paused = $watcher['status'] !== 'active';
        $has_errors = $watcher['recent_errors'] > 0;
        $last_run = $watcher['last_run_at_real'] ?? $watcher['last_run_at'];
      ?>

      <div class="glass-card" style="padding:20px">
        <div style="display:flex;gap:16px">

          <!-- Icon -->
          <div style="font-size:48px;flex-shrink:0">📱</div>

          <!-- Info -->
          <div style="flex:1;min-width:0">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;flex-wrap:wrap">
              <div style="font-size:16px;font-weight:700">
                <?= htmlspecialchars($watcher['label']) ?>
              </div>

              <?php if ($is_paused): ?>
                <span style="padding:2px 8px;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);border-radius:6px;font-size:10px;font-weight:600;color:#dc2626">
                  ⏸ PAUSIERT
                </span>
              <?php elseif ($is_running): ?>
                <span style="padding:2px 8px;background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.3);border-radius:6px;font-size:10px;font-weight:600;color:#16a34a">
                  ▶ LÄUFT
                </span>
              <?php endif; ?>

              <?php if ($has_errors): ?>
                <span style="padding:2px 8px;background:rgba(251,191,36,0.1);border:1px solid rgba(251,191,36,0.3);border-radius:6px;font-size:10px;font-weight:600;color:#d97706" title="Fehler in der letzten Stunde">
                  ⚠️ <?= $watcher['recent_errors'] ?> Fehler
                </span>
              <?php endif; ?>
            </div>

            <!-- Details -->
            <div style="display:flex;flex-wrap:wrap;gap:12px;font-size:12px;color:var(--text2);margin-bottom:12px">
              <div>🔍 <?= $watcher['type'] === 'url' ? 'Direkter Link' : htmlspecialchars($watcher['query_text']) ?></div>
              <?php if ($watcher['min_price'] || $watcher['max_price']): ?>
                <div>💰
                  <?php if ($watcher['min_price'] && $watcher['max_price']): ?>
                    <?= number_format($watcher['min_price'], 0, ',', '.') ?>€ - <?= number_format($watcher['max_price'], 0, ',', '.') ?>€
                  <?php elseif ($watcher['max_price']): ?>
                    bis <?= number_format($watcher['max_price'], 0, ',', '.') ?>€
                  <?php else: ?>
                    ab <?= number_format($watcher['min_price'], 0, ',', '.') ?>€
                  <?php endif; ?>
                </div>
              <?php endif; ?>
              <?php if ($watcher['location_text']): ?>
                <div>📍 <?= htmlspecialchars($watcher['location_text']) ?>
                  <?php if ($watcher['radius_km']): ?>
                    (+ <?= $watcher['radius_km'] ?> km)
                  <?php endif; ?>
                </div>
              <?php endif; ?>
              <div>⏱ Alle <?= $watcher['interval_minutes'] ?> Min</div>
            </div>

            <!-- Stats -->
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:12px;margin-bottom:12px">

              <!-- Angebote -->
              <div style="background:var(--input-bg);border:1px solid var(--input-border);border-radius:10px;padding:10px">
                <div style="font-size:20px;font-weight:700;color:var(--accent)">
                  <?= $watcher['active_listings'] ?>
                </div>
                <div style="font-size:10px;color:var(--text2)">Aktive Angebote</div>
              </div>

              <!-- Preisspanne -->
              <?php if ($watcher['current_min_price']): ?>
              <div style="background:var(--input-bg);border:1px solid var(--input-border);border-radius:10px;padding:10px">
                <div style="font-size:14px;font-weight:700;color:var(--green)">
                  <?= number_format($watcher['current_min_price'], 0, ',', '.') ?>€
                </div>
                <div style="font-size:10px;color:var(--text2)">
                  Günstigster
                  <?php if ($watcher['current_max_price'] > $watcher['current_min_price']): ?>
                    - <?= number_format($watcher['current_max_price'], 0, ',', '.') ?>€
                  <?php endif; ?>
                </div>
              </div>
              <?php endif; ?>

              <!-- Letzter Lauf -->
              <div style="background:var(--input-bg);border:1px solid var(--input-border);border-radius:10px;padding:10px">
                <div style="font-size:11px;font-weight:600;color:<?= $watcher['last_run_success'] ? 'var(--green)' : ($watcher['last_run_success'] === false ? 'var(--red)' : 'var(--text2)') ?>">
                  <?php if ($last_run): ?>
                    <?= DashboardHelper::timeAgo($last_run) ?>
                  <?php else: ?>
                    Noch nie
                  <?php endif; ?>
                </div>
                <div style="font-size:10px;color:var(--text2)">
                  <?php if ($watcher['last_found_count'] !== null): ?>
                    <?= $watcher['last_found_count'] ?> gefunden
                  <?php else: ?>
                    Letzter Lauf
                  <?php endif; ?>
                </div>
              </div>

              <!-- Runs -->
              <div style="background:var(--input-bg);border:1px solid var(--input-border);border-radius:10px;padding:10px">
                <div style="font-size:20px;font-weight:700">
                  <?= $watcher['run_count'] ?>
                </div>
                <div style="font-size:10px;color:var(--text2)">Durchläufe</div>
              </div>

            </div>

            <!-- Actions -->
            <div style="display:flex;gap:8px;flex-wrap:wrap">
              <!-- Angebote anzeigen -->
              <button onclick="window.location.href='listings.php?watcher_id=<?= $watcher['id'] ?>'"
                      class="action-btn action-btn-primary">
                📱 Angebote (<?= $watcher['active_listings'] ?>)
              </button>

              <!-- Manuell starten -->
              <button onclick="runWatcherNow('<?= $watcher['id'] ?>')"
                      id="run-btn-<?= $watcher['id'] ?>"
                      class="action-btn action-btn-success"
                      <?= $is_paused ? 'disabled' : '' ?>>
                ▶️ Jetzt starten
              </button>

              <!-- Bearbeiten -->
              <button onclick="editWatcher('<?= $watcher['id'] ?>')"
                      class="action-btn action-btn-secondary">
                ✏️ Bearbeiten
              </button>

              <!-- AI-Assistent -->
               <button onclick="showAISettings('<?= $watcher['id'] ?>')"
                    class="action-btn action-btn-secondary">
                🤖 AI
               </button>

              <!-- Pausieren/Fortsetzen -->
              <?php if ($is_paused): ?>
                <button onclick="resumeWatcher('<?= $watcher['id'] ?>')"
                        class="action-btn action-btn-success">
                  ▶️ Fortsetzen
                </button>
              <?php else: ?>
                <button onclick="pauseWatcher('<?= $watcher['id'] ?>')"
                        class="action-btn action-btn-secondary">
                  ⏸️ Pausieren
                </button>
              <?php endif; ?>

              <!-- Löschen -->
              <button onclick="deleteWatcher('<?= $watcher['id'] ?>')"
                      class="action-btn action-btn-danger">
                🗑️ Löschen
              </button>
            </div>

          </div>
        </div>
      </div>

      <?php endforeach; ?>
    </div>

  <?php endif; ?>
</main>

<!-- Edit Modal -->
<div id="editModal" style="display:none;position:fixed;inset:0;z-index:200;align-items:center;justify-content:center;padding:20px;background:rgba(0,0,0,0.5);backdrop-filter:blur(8px)">
  <div style="background:var(--bg);border:1px solid var(--card-border);border-radius:var(--radius);padding:28px;max-width:480px;width:100%;box-shadow:0 24px 80px rgba(0,0,0,0.5);max-height:90vh;overflow-y:auto">
    <div style="font-size:18px;font-weight:700;margin-bottom:20px">
      ✏️ Beobachtung bearbeiten
    </div>
    <form id="editWatcherForm">
      <input type="hidden" name="watcher_id" id="edit-watcher-id">

      <div style="display:flex;flex-direction:column;gap:12px">
        <div>
          <label style="font-size:11px;font-weight:600;color:var(--text2);display:block;margin-bottom:6px">Label</label>
          <input class="add-input" name="label" id="edit-label" style="width:100%">
        </div>

        <div>
          <label style="font-size:11px;font-weight:600;color:var(--text2);display:block;margin-bottom:6px">Preisbereich</label>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <input class="add-input" name="min_price" id="edit-min-price" type="number" step="0.01" placeholder="Min. €">
            <input class="add-input" name="max_price" id="edit-max-price" type="number" step="0.01" placeholder="Max. €">
          </div>
        </div>

        <div>
          <label style="font-size:11px;font-weight:600;color:var(--text2);display:block;margin-bottom:6px">Standort</label>
          <input class="add-input" name="location" id="edit-location" style="width:100%" placeholder="z.B. München">
        </div>

        <div>
          <label style="font-size:11px;font-weight:600;color:var(--text2);display:block;margin-bottom:6px">Umkreis</label>
          <select class="add-input" name="radius_km" id="edit-radius" style="width:100%;padding-left:12px">
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

        <div>
          <label style="font-size:11px;font-weight:600;color:var(--text2);display:block;margin-bottom:6px">Intervall</label>
          <select class="add-input" name="interval" id="edit-interval" style="width:100%;padding-left:12px">
            <option value="5">⏱ Alle 5 Minuten</option>
            <option value="10">⏱ Alle 10 Minuten</option>
            <option value="15">⏱ Alle 15 Minuten</option>
            <option value="30">⏱ Alle 30 Minuten</option>
            <option value="60">⏱ Stündlich</option>
          </select>
        </div>
      </div>

      <div style="display:flex;gap:10px;margin-top:18px">
        <button type="button" onclick="hideEditModal()" class="action-btn action-btn-secondary" style="flex:1">
          Abbrechen
        </button>
        <button type="submit" class="action-btn action-btn-primary" style="flex:2">
          💾 Speichern
        </button>
      </div>
    </form>
  </div>
</div>
<!-- AI Settings Modal -->
<div id="aiModal" style="display:none;position:fixed;inset:0;z-index:200;align-items:center;justify-content:center;padding:20px;background:rgba(0,0,0,0.5);backdrop-filter:blur(8px)">
  <div style="background:var(--bg);border:1px solid var(--card-border);border-radius:var(--radius);padding:28px;max-width:500px;width:100%;box-shadow:0 24px 80px rgba(0,0,0,0.5);max-height:90vh;overflow-y:auto">

    <div style="font-size:18px;font-weight:700;margin-bottom:20px">
      🤖 KI-Assistent Einstellungen
    </div>

    <form id="aiSettingsForm">
      <input type="hidden" id="ai_watcher_id" name="watcher_id">

      <!-- AI aktivieren -->
      <div style="margin-bottom:24px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
          <label for="ai_enabled" style="font-size:14px;font-weight:600">
            KI-Assistent aktivieren
          </label>
          <label class="switch">
            <input type="checkbox" id="ai_enabled" name="ai_enabled">
            <span class="slider"></span>
          </label>
        </div>
        <div style="font-size:12px;color:var(--text2)">
          Analysiert automatisch neue Angebote und erstellt Nachrichten
        </div>
      </div>

      <!-- Zielpreis -->
      <div style="margin-bottom:24px">
        <label style="font-size:13px;font-weight:600;display:block;margin-bottom:8px">
          🎯 Zielpreis (optional)
        </label>
        <div style="display:flex;align-items:center;gap:8px">
          <input type="number" class="add-input" id="ai_target_price" name="ai_target_price"
                 placeholder="z.B. 1200" step="0.01" min="0" style="flex:1">
          <span>€</span>
        </div>
        <div style="font-size:11px;color:var(--text2);margin-top:6px">
          KI versucht Angebote auf diesen Preis zu verhandeln. Leer = Watcher Max-Preis
        </div>
      </div>

      <!-- Auto-Verhandeln -->
      <div style="margin-bottom:24px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
          <label for="ai_auto_negotiate" style="font-size:14px;font-weight:600">
            Automatisch verhandeln
          </label>
          <label class="switch">
            <input type="checkbox" id="ai_auto_negotiate" name="ai_auto_negotiate">
            <span class="slider"></span>
          </label>
        </div>
        <div style="font-size:12px;color:var(--text2)">
          ⚠️ Nachrichten werden OHNE Freigabe versendet (benötigt Kleinanzeigen-Login)
        </div>
      </div>

      <!-- Max Abweichung -->
      <div style="margin-bottom:24px">
        <label style="font-size:13px;font-weight:600;display:block;margin-bottom:8px">
          Max. Preisabweichung
        </label>
        <select class="add-input" id="ai_max_deviation" name="ai_max_deviation" style="width:100%">
          <option value="10">10% über Marktwert</option>
          <option value="15">15% über Marktwert</option>
          <option value="20" selected>20% über Marktwert (empfohlen)</option>
          <option value="30">30% über Marktwert</option>
        </select>
        <div style="font-size:11px;color:var(--text2);margin-top:6px">
          Angebote über diesem Wert werden übersprungen
        </div>
      </div>

      <div style="background:var(--input-bg);border:1px solid var(--input-border);border-radius:10px;padding:12px;margin-bottom:20px">
        <div style="font-size:12px;font-weight:600;margin-bottom:6px">ℹ️ So funktioniert's:</div>
        <div style="font-size:11px;color:var(--text2);line-height:1.6">
          1. KI analysiert neue Angebote<br>
          2. Vergleicht Preis mit Marktwert<br>
          3. Erstellt Nachricht (höflich & authentisch)<br>
          4. Optional: Sendet automatisch (mit Login)
        </div>
      </div>

      <div style="display:flex;gap:10px">
        <button type="button" onclick="hideAIModal()" class="action-btn action-btn-secondary" style="flex:1">
          Abbrechen
        </button>
        <button type="submit" class="action-btn action-btn-primary" style="flex:2">
          💾 Speichern
        </button>
      </div>
    </form>

  </div>
</div>

<!-- Toggle Switch CSS -->
<style>
.switch {
  position: relative;
  display: inline-block;
  width: 48px;
  height: 26px;
}
.switch input {
  opacity: 0;
  width: 0;
  height: 0;
}
.slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: var(--input-bg);
  border: 1px solid var(--input-border);
  transition: .3s;
  border-radius: 26px;
}
.slider:before {
  position: absolute;
  content: "";
  height: 18px;
  width: 18px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: .3s;
  border-radius: 50%;
}
input:checked + .slider {
  background: linear-gradient(135deg, #3b6bff, #7c3aed);
  border-color: #3b6bff;
}
input:checked + .slider:before {
  transform: translateX(22px);
}
</style>
<script src="assets/js/theme.js"></script>
<script>
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
    closeSidebar();
}

// Manuell starten
async function runWatcherNow(watcherId) {
    const btn = document.getElementById('run-btn-' + watcherId);
    const originalText = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = '⏳ Läuft...';

    try {
        const response = await fetch('api/run_watcher.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ watcher_id: watcherId })
        });

        const result = await response.json();

        if (result.success) {
            btn.innerHTML = '✅ Fertig!';
            setTimeout(() => location.reload(), 2000);
        } else {
            alert('❌ Fehler: ' + result.error);
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    } catch (err) {
        alert('❌ Fehler: ' + err.message);
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

// Bearbeiten
async function editWatcher(watcherId) {
    try {
        const response = await fetch('api/get_watcher.php?id=' + watcherId);
        const watcher = await response.json();

        if (watcher.error) {
            alert('❌ Fehler: ' + watcher.error);
            return;
        }

        document.getElementById('edit-watcher-id').value = watcher.id;
        document.getElementById('edit-label').value = watcher.label || '';
        document.getElementById('edit-min-price').value = watcher.min_price || '';
        document.getElementById('edit-max-price').value = watcher.max_price || '';
        document.getElementById('edit-location').value = watcher.location_text || '';
        document.getElementById('edit-radius').value = watcher.radius_km || '';
        document.getElementById('edit-interval').value = watcher.interval_minutes || 10;

        document.getElementById('editModal').style.display = 'flex';
    } catch (err) {
        alert('❌ Fehler: ' + err.message);
    }
}

function hideEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// Edit Form Submit
document.getElementById('editWatcherForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const data = Object.fromEntries(formData.entries());

    try {
        const response = await fetch('api/update_watcher.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            alert('✅ Beobachtung aktualisiert!');
            location.reload();
        } else {
            alert('❌ Fehler: ' + result.error);
        }
    } catch (err) {
        alert('❌ Fehler: ' + err.message);
    }
});

// Pausieren
async function pauseWatcher(watcherId) {
    if (!confirm('Beobachtung pausieren?\n\nDer Scraper wird gestoppt.')) return;

    try {
        const response = await fetch('api/toggle_watcher.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ watcher_id: watcherId, status: 'paused' })
        });

        const result = await response.json();

        if (result.success) {
            location.reload();
        } else {
            alert('❌ Fehler: ' + result.error);
        }
    } catch (err) {
        alert('❌ Fehler: ' + err.message);
    }
}

// Fortsetzen
async function resumeWatcher(watcherId) {
    try {
        const response = await fetch('api/toggle_watcher.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ watcher_id: watcherId, status: 'active' })
        });

        const result = await response.json();

        if (result.success) {
            location.reload();
        } else {
            alert('❌ Fehler: ' + result.error);
        }
    } catch (err) {
        alert('❌ Fehler: ' + err.message);
    }
}

// Löschen
async function deleteWatcher(watcherId) {
    if (!confirm('Beobachtung wirklich löschen?\n\nAlle Angebote werden ebenfalls gelöscht.')) return;

    try {
        const response = await fetch('api/delete_watcher.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ watcher_id: watcherId })
        });

        const result = await response.json();

        if (result.success) {
            alert('✅ Beobachtung gelöscht!');
            location.reload();
        } else {
            alert('❌ Fehler: ' + result.error);
        }
    } catch (err) {
        alert('❌ Fehler: ' + err.message);
    }
}

document.getElementById('overlay')?.addEventListener('click', closeSidebar);
document.getElementById('editModal')?.addEventListener('click', function(e) {
    if (e.target === this) hideEditModal();
});

// AI Settings Modal Functions
let currentWatcherId = null;

function showAISettings(watcherId) {
    currentWatcherId = watcherId;
    document.getElementById('ai_watcher_id').value = watcherId;

    // Lade aktuelle Settings
    fetch(`/api/get_ai_settings.php?watcher_id=${watcherId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('ai_enabled').checked = data.settings.ai_assistant_enabled || false;
                document.getElementById('ai_target_price').value = data.settings.ai_target_price || '';
                document.getElementById('ai_auto_negotiate').checked = data.settings.ai_auto_negotiate || false;
                document.getElementById('ai_max_deviation').value = data.settings.ai_max_price_deviation || 20;
            }
        })
        .catch(err => console.error('Error loading AI settings:', err));

    document.getElementById('aiModal').style.display = 'flex';
}

function hideAIModal() {
    document.getElementById('aiModal').style.display = 'none';
}

// AI Settings Form Submit
document.getElementById('aiSettingsForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const btn = e.target.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '⏳ Speichere...';

    const data = {
        watcher_id: document.getElementById('ai_watcher_id').value,
        ai_enabled: document.getElementById('ai_enabled').checked,
        ai_target_price: document.getElementById('ai_target_price').value || null,
        ai_auto_negotiate: document.getElementById('ai_auto_negotiate').checked,
        ai_max_deviation: parseFloat(document.getElementById('ai_max_deviation').value)
    };

    try {
        const res = await fetch('/api/update_ai_settings.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });

        const result = await res.json();

        if (result.success) {
            btn.innerHTML = '✅ Gespeichert!';
            setTimeout(() => {
                hideAIModal();
                location.reload();
            }, 1000);
        } else {
            throw new Error(result.error);
        }
    } catch (err) {
        alert('❌ ' + err.message);
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
});

// Close AI modal on click outside
document.getElementById('aiModal')?.addEventListener('click', function(e) {
    if (e.target === this) hideAIModal();
});
</script>
</body>
</html>