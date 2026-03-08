<?php
// settings.php - UPDATED VERSION
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

Auth::requireLogin();
$user_id = Auth::getCurrentUserId();

// Hole User + Settings
$user = Database::fetchOne("
    SELECT
        email,
        notify_new_deals,
        notify_price_drops,
        notify_price_increases,
        notify_listing_gone,
        notify_daily_summary,
        email_verified
    FROM users
    WHERE id = $1
", [$user_id]);

// Hole alte notification_settings (falls vorhanden)
$old_settings = Database::fetchOne("
    SELECT * FROM notification_settings WHERE user_id = $1
", [$user_id]);

// Page Config
define('PAGE_TITLE', '⚙️ Einstellungen');
define('SHOW_ADD_BUTTON', false);

include 'includes/header.php';
?>

<!-- Content Grid -->
<div class="content-grid">
  <div class="left-col">

    <!-- Email Settings -->
    <div class="glass-card">
      <div class="card-header">
        <span style="font-size:18px">📧</span>
        <div class="card-title">Email-Benachrichtigungen</div>
        <?php if ($user['email_verified']): ?>
        <div class="card-action" style="color:var(--green);font-size:11px">● Aktiv</div>
        <?php else: ?>
        <div class="card-action" style="color:var(--red);font-size:11px">⚠ Nicht verifiziert</div>
        <?php endif; ?>
      </div>

      <form id="settingsForm" style="padding:20px">

        <!-- Email-Adresse -->
        <div style="margin-bottom:24px;padding:16px;background:var(--input-bg);border-radius:10px;border:1px solid var(--input-border)">
          <div style="font-size:13px;font-weight:600;margin-bottom:8px;color:var(--text)">
            📬 Email-Adresse
          </div>
          <div style="font-size:14px;color:var(--text);font-family:monospace">
            <?= htmlspecialchars($user['email']) ?>
          </div>
          <?php if (!$user['email_verified']): ?>
          <div style="margin-top:10px">
            <button type="button" class="add-btn" onclick="sendVerificationEmail()" style="padding:6px 12px;font-size:12px">
              ✉️ Verifizierungs-Email senden
            </button>
          </div>
          <?php endif; ?>
        </div>

        <div style="font-size:13px;font-weight:700;color:var(--text);margin-bottom:12px;padding-top:8px;border-top:1px solid var(--card-border)">
          🔔 Benachrichtigungstypen
        </div>

        <!-- Neue Deals -->
        <div class="setting-item">
          <div style="flex:1">
            <div style="font-size:14px;font-weight:600;color:var(--text)">
              💰 Neue Deals
            </div>
            <div style="font-size:12px;color:var(--text2);margin-top:4px">
              Benachrichtigung wenn ein Angebot deinen Zielpreis erreicht
            </div>
          </div>
          <label class="switch">
            <input type="checkbox" name="notify_new_deals" <?= $user['notify_new_deals'] ? 'checked' : '' ?>>
            <span class="slider"></span>
          </label>
        </div>

        <!-- Preissenkungen -->
        <div class="setting-item">
          <div style="flex:1">
            <div style="font-size:14px;font-weight:600;color:var(--text)">
              📉 Preissenkungen
            </div>
            <div style="font-size:12px;color:var(--text2);margin-top:4px">
              Email wenn ein beobachteter Preis sinkt
            </div>
          </div>
          <label class="switch">
            <input type="checkbox" name="notify_price_drops" <?= $user['notify_price_drops'] ? 'checked' : '' ?>>
            <span class="slider"></span>
          </label>
        </div>

        <!-- Preiserhöhungen -->
        <div class="setting-item">
          <div style="flex:1">
            <div style="font-size:14px;font-weight:600;color:var(--text)">
              📈 Preiserhöhungen
            </div>
            <div style="font-size:12px;color:var(--text2);margin-top:4px">
              Auch bei steigenden Preisen benachrichtigen (optional)
            </div>
          </div>
          <label class="switch">
            <input type="checkbox" name="notify_price_increases" <?= $user['notify_price_increases'] ? 'checked' : '' ?>>
            <span class="slider"></span>
          </label>
        </div>

        <!-- Listing verschwunden -->
        <div class="setting-item">
          <div style="flex:1">
            <div style="font-size:14px;font-weight:600;color:var(--text)">
              ❌ Angebot verschwunden
            </div>
            <div style="font-size:12px;color:var(--text2);margin-top:4px">
              Warnung wenn ein interessantes Angebot gelöscht wurde
            </div>
          </div>
          <label class="switch">
            <input type="checkbox" name="notify_listing_gone" <?= $user['notify_listing_gone'] ? 'checked' : '' ?>>
            <span class="slider"></span>
          </label>
        </div>

        <!-- Tägliche Zusammenfassung -->
        <div class="setting-item">
          <div style="flex:1">
            <div style="font-size:14px;font-weight:600;color:var(--text)">
              📊 Tägliche Zusammenfassung
            </div>
            <div style="font-size:12px;color:var(--text2);margin-top:4px">
              Einmal täglich (morgens) eine Übersicht aller Änderungen
            </div>
          </div>
          <label class="switch">
            <input type="checkbox" name="notify_daily_summary" <?= $user['notify_daily_summary'] ? 'checked' : '' ?>>
            <span class="slider"></span>
          </label>
        </div>

        <!-- Legacy Settings (falls vorhanden) -->
        <?php if ($old_settings): ?>
        <div style="margin-top:24px;padding-top:24px;border-top:1px solid var(--card-border)">
          <div style="font-size:13px;font-weight:700;color:var(--text);margin-bottom:12px">
            ⚙️ Erweiterte Einstellungen
          </div>

          <div style="margin-bottom:16px">
            <label style="font-size:13px;font-weight:600;display:block;margin-bottom:8px;color:var(--text)">
              🎯 Preis-Trigger (€)
            </label>
            <input type="number" class="add-input" name="price_trigger"
                   value="<?= $old_settings['email_price_trigger'] ?? '' ?>"
                   placeholder="Leer = Alle Preise" step="0.01" min="0" style="width:100%">
            <div style="font-size:11px;color:var(--text2);margin-top:6px">
              Optional: Nur Emails bei Angeboten unter diesem Preis
            </div>
          </div>

          <div style="margin-bottom:16px">
            <label style="font-size:13px;font-weight:600;display:block;margin-bottom:8px;color:var(--text)">
              ⏰ Email-Cooldown
            </label>
            <select class="add-input" name="cooldown_minutes" style="width:100%;padding-left:12px">
              <option value="5" <?= ($old_settings['email_cooldown_minutes'] ?? 30) == 5 ? 'selected' : '' ?>>5 Minuten</option>
              <option value="15" <?= ($old_settings['email_cooldown_minutes'] ?? 30) == 15 ? 'selected' : '' ?>>15 Minuten</option>
              <option value="30" <?= ($old_settings['email_cooldown_minutes'] ?? 30) == 30 ? 'selected' : '' ?>>30 Minuten (Standard)</option>
              <option value="60" <?= ($old_settings['email_cooldown_minutes'] ?? 30) == 60 ? 'selected' : '' ?>>1 Stunde</option>
              <option value="120" <?= ($old_settings['email_cooldown_minutes'] ?? 30) == 120 ? 'selected' : '' ?>>2 Stunden</option>
              <option value="1440" <?= ($old_settings['email_cooldown_minutes'] ?? 30) == 1440 ? 'selected' : '' ?>>Einmal täglich</option>
            </select>
            <div style="font-size:11px;color:var(--text2);margin-top:6px">
              Verhindert Email-Spam bei vielen Treffern
            </div>
          </div>
        </div>
        <?php endif; ?>

        <button type="submit" class="add-btn" style="width:100%;margin-top:24px">
          💾 Einstellungen speichern
        </button>
      </form>
    </div>

    <!-- Test Email -->
    <div class="glass-card">
      <div class="card-header">
        <span style="font-size:18px">✉️</span>
        <div class="card-title">Test-Email</div>
      </div>
      <div style="padding:20px">
        <p style="font-size:13px;color:var(--text2);margin-bottom:16px">
          Sende eine Test-Email, um deine Einstellungen zu überprüfen
        </p>
        <button class="add-btn" onclick="sendTestEmail()" style="width:100%">
          📨 Test-Email senden
        </button>
      </div>
    </div>

  </div>

  <div class="right-col">

    <!-- Info Card -->
    <div class="glass-card info-card">
      <div style="font-size:15px;font-weight:700;margin-bottom:12px">💡 Info</div>
      <div style="font-size:12px;color:var(--text2);line-height:1.6">
        <strong>Email-Verifizierung:</strong> Bestätige deine Email-Adresse, um Benachrichtigungen zu erhalten.<br><br>
        <strong>Neue Deals:</strong> Die wichtigste Benachrichtigung - du erfährst sofort, wenn ein Schnäppchen verfügbar ist.<br><br>
        <strong>Preis-Trigger:</strong> Filtere Benachrichtigungen nach Preis (optional).<br><br>
        <strong>Cooldown:</strong> Verhindert zu viele Emails in kurzer Zeit.
      </div>
    </div>

    <!-- Stats -->
    <div class="glass-card">
      <div class="card-header">
        <span style="font-size:18px">📊</span>
        <div class="card-title">Statistiken</div>
      </div>
      <div style="padding:20px">
        <?php
        $stats = Database::fetchOne("
            SELECT
                COUNT(*) FILTER (WHERE created_at > NOW() - INTERVAL '24 hours') as today,
                COUNT(*) FILTER (WHERE created_at > NOW() - INTERVAL '7 days') as week,
                COUNT(*) as total
            FROM notifications
            WHERE user_id = $1
        ", [$user_id]);
        ?>
        <div style="display:flex;justify-content:space-between;margin-bottom:12px">
          <span style="font-size:12px;color:var(--text2)">Heute:</span>
          <span style="font-size:14px;font-weight:600"><?= $stats['today'] ?? 0 ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;margin-bottom:12px">
          <span style="font-size:12px;color:var(--text2)">Diese Woche:</span>
          <span style="font-size:14px;font-weight:600"><?= $stats['week'] ?? 0 ?></span>
        </div>
        <div style="display:flex;justify-content:space-between">
          <span style="font-size:12px;color:var(--text2)">Gesamt:</span>
          <span style="font-size:14px;font-weight:600"><?= $stats['total'] ?? 0 ?></span>
        </div>
      </div>
    </div>

    <!-- Recent Notifications -->
    <div class="glass-card">
      <div class="card-header">
        <span style="font-size:18px">📬</span>
        <div class="card-title">Letzte Benachrichtigungen</div>
      </div>
      <?php
      $recent = Database::fetchAll("
          SELECT type, title, created_at
          FROM notifications
          WHERE user_id = $1
          ORDER BY created_at DESC
          LIMIT 5
      ", [$user_id]);

      if (empty($recent)): ?>
        <div style="padding:40px 20px;text-align:center;color:var(--text2)">
          <div style="font-size:40px;margin-bottom:12px">📭</div>
          <div style="font-size:13px">Noch keine Benachrichtigungen</div>
        </div>
      <?php else: ?>
        <div style="padding:0">
          <?php foreach ($recent as $notif):
            $icons = [
              'new_deal' => '💰',
              'price_change' => '📉',
              'system' => '⚙️',
              'gone' => '❌'
            ];
            $icon = $icons[$notif['type']] ?? '🔔';
          ?>
          <div style="padding:12px 20px;border-bottom:1px solid var(--card-border);display:flex;gap:10px">
            <div style="font-size:16px"><?= $icon ?></div>
            <div style="flex:1;min-width:0">
              <div style="font-size:12px;font-weight:600;color:var(--text);margin-bottom:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                <?= htmlspecialchars($notif['title']) ?>
              </div>
              <div style="font-size:11px;color:var(--text2)">
                <?= DashboardHelper::timeAgo($notif['created_at']) ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<style>
.setting-item {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px;
  margin-bottom: 12px;
  background: var(--input-bg);
  border: 1px solid var(--input-border);
  border-radius: 10px;
  transition: all 0.2s ease;
}

.setting-item:hover {
  border-color: var(--accent);
}

.switch {
  position: relative;
  width: 48px;
  height: 26px;
  display: inline-block;
  flex-shrink: 0;
}

.switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.slider {
  position: absolute;
  cursor: pointer;
  inset: 0;
  background: var(--input-bg);
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
  background: white;
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
// Save Settings
document.getElementById('settingsForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '⏳ Speichere...';

    const formData = new FormData(e.target);
    const data = {};

    // Checkboxes
    data.notify_new_deals = formData.get('notify_new_deals') === 'on';
    data.notify_price_drops = formData.get('notify_price_drops') === 'on';
    data.notify_price_increases = formData.get('notify_price_increases') === 'on';
    data.notify_listing_gone = formData.get('notify_listing_gone') === 'on';
    data.notify_daily_summary = formData.get('notify_daily_summary') === 'on';

    // Optional fields
    if (formData.get('price_trigger')) {
        data.price_trigger = parseFloat(formData.get('price_trigger'));
    }
    if (formData.get('cooldown_minutes')) {
        data.cooldown_minutes = parseInt(formData.get('cooldown_minutes'));
    }

    try {
        const res = await fetch('api/update_notification_settings.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });

        const result = await res.json();

        if (result.success) {
            btn.innerHTML = '✅ Gespeichert!';
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }, 2000);
        } else {
            throw new Error(result.error || 'Fehler beim Speichern');
        }
    } catch (err) {
        alert('❌ ' + err.message);
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
});

// Send Test Email
async function sendTestEmail() {
    try {
        const res = await fetch('api/send_test_email.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'}
        });

        const result = await res.json();

        if (result.success) {
            alert('✅ Test-Email wurde versendet! Prüfe dein Postfach.');
        } else {
            alert('❌ Fehler: ' + result.error);
        }
    } catch (err) {
        alert('❌ Fehler: ' + err.message);
    }
}

// Send Verification Email
async function sendVerificationEmail() {
    try {
        const res = await fetch('api/send_verification_email.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'}
        });

        const result = await res.json();

        if (result.success) {
            alert('✅ Verifizierungs-Email wurde versendet! Prüfe dein Postfach und klicke auf den Link.');
        } else {
            alert('❌ Fehler: ' + result.error);
        }
    } catch (err) {
        alert('❌ Fehler: ' + err.message);
    }
}

function openSidebar() {
    document.getElementById('sidebar')?.classList.add('open');
    document.getElementById('overlay')?.classList.add('open');
}

function closeSidebar() {
    document.getElementById('sidebar')?.classList.remove('open');
    document.getElementById('overlay')?.classList.remove('open');
}

document.getElementById('overlay')?.addEventListener('click', closeSidebar);
</script>

<?php include 'includes/footer.php'; ?>