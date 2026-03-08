<?php
// platform_login.php - Kleinanzeigen Account verbinden
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

Auth::requireLogin();
$user_id = Auth::getCurrentUserId();

// Check existing credentials
$credentials = Database::fetchOne("
    SELECT * FROM platform_credentials
    WHERE user_id = $1 AND platform = 'kleinanzeigen'
", [$user_id]);

// Page Config
define('PAGE_TITLE', '🔐 Kleinanzeigen Login');
define('SHOW_ADD_BUTTON', false);

include 'includes/header.php';
?>

<div class="content-grid">
  <div class="left-col">

    <div class="glass-card">
      <div class="card-header">
        <span style="font-size:18px">🔐</span>
        <div class="card-title">Kleinanzeigen Account verbinden</div>
        <?php if ($credentials && $credentials['status'] == 'active'): ?>
        <div class="card-action" style="color:var(--green)">● Verbunden</div>
        <?php endif; ?>
      </div>

      <form id="loginForm" style="padding:20px">
        <div style="margin-bottom:20px">
          <label style="font-size:13px;font-weight:600;display:block;margin-bottom:8px">
            Email / Benutzername
          </label>
          <input type="email" class="add-input" id="email" name="email"
                 value="<?= htmlspecialchars($credentials['email'] ?? '') ?>"
                 placeholder="deine@email.de" required style="width:100%">
        </div>

        <div style="margin-bottom:20px">
          <label style="font-size:13px;font-weight:600;display:block;margin-bottom:8px">
            Passwort
          </label>
          <input type="password" class="add-input" id="password" name="password"
                 placeholder="••••••••" required style="width:100%">
        </div>

        <div style="background:rgba(59,107,255,0.1);border:1px solid rgba(59,107,255,0.3);border-radius:10px;padding:12px;margin-bottom:20px">
          <div style="font-size:12px;font-weight:600;margin-bottom:6px;color:var(--accent)">
            🔒 Sicherheitshinweis
          </div>
          <div style="font-size:11px;color:var(--text2);line-height:1.6">
            • Dein Passwort wird <strong>verschlüsselt</strong> gespeichert<br>
            • Nur für automatisches Nachrichtenversenden<br>
            • Keine Weitergabe an Dritte<br>
            • Jederzeit löschbar
          </div>
        </div>

        <button type="submit" class="add-btn" style="width:100%">
          <?= $credentials ? '🔄 Zugangsdaten aktualisieren' : '✅ Account verbinden' ?>
        </button>

        <?php if ($credentials): ?>
        <button type="button" onclick="deleteCredentials()" class="action-btn action-btn-danger"
                style="width:100%;margin-top:10px">
          🗑️ Zugangsdaten löschen
        </button>
        <?php endif; ?>
      </form>
    </div>

    <?php if ($credentials): ?>
    <!-- Test Login Button -->
    <div class="glass-card">
      <div class="card-header">
        <span style="font-size:18px">🧪</span>
        <div class="card-title">Login testen</div>
      </div>
      <div style="padding:20px">
        <p style="font-size:12px;color:var(--text2);margin-bottom:12px">
          Teste ob die Anmeldung funktioniert (dauert ca. 10-20 Sekunden)
        </p>
        <button onclick="testLogin()" id="testBtn" class="add-btn" style="width:100%">
          🔍 Login testen
        </button>
        <div id="testResult" style="margin-top:12px;font-size:12px"></div>
      </div>
    </div>
    <?php endif; ?>

  </div>

  <div class="right-col">
    <div class="glass-card info-card" style="padding:20px">
      <div style="font-size:15px;font-weight:700;margin-bottom:12px">ℹ️ Wofür wird das benötigt?</div>
      <div style="font-size:12px;color:var(--text2);line-height:1.6;margin-bottom:16px">
        Mit deinem Kleinanzeigen-Login kann der KI-Assistent:<br><br>
        ✅ Automatisch Nachrichten senden<br>
        ✅ Preise verhandeln<br>
        ✅ Schneller als andere Käufer sein<br><br>
        <strong style="color:var(--text)">Ohne Login:</strong> Nachrichten werden nur vorbereitet und müssen manuell gesendet werden.
      </div>
    </div>

    <div class="glass-card" style="padding:20px">
      <div style="font-size:14px;font-weight:700;margin-bottom:12px">🛡️ Sicherheit</div>
      <div style="font-size:11px;color:var(--text2);line-height:1.6">
        <strong style="color:var(--text)">Verschlüsselung:</strong> AES-256<br>
        <strong style="color:var(--text)">Speicherung:</strong> Nur auf deinem Server<br>
        <strong style="color:var(--text)">Zugriff:</strong> Nur für dich<br><br>

        <div style="background:var(--input-bg);padding:8px;border-radius:6px;margin-top:8px">
          <strong style="color:var(--accent)">⚠️ Wichtig:</strong> Nutze ein <strong>einzigartiges Passwort</strong> nur für diesen Account!
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Login Form
document.getElementById('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const btn = e.target.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '⏳ Verbinde...';

    try {
        const res = await fetch('/api/save_platform_credentials.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                platform: 'kleinanzeigen',
                email: document.getElementById('email').value,
                password: document.getElementById('password').value
            })
        });

        const data = await res.json();

        if (data.success) {
            btn.innerHTML = '✅ Verbunden!';
            setTimeout(() => location.reload(), 1000);
        } else {
            throw new Error(data.error);
        }
    } catch (err) {
        alert('❌ ' + err.message);
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
});

// Delete Credentials
function deleteCredentials() {
    if (!confirm('Wirklich löschen?\n\nAutomatisches Nachrichtenversenden wird deaktiviert.')) return;

    fetch('/api/delete_platform_credentials.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({platform: 'kleinanzeigen'})
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('✅ Gelöscht');
            location.reload();
        } else {
            alert('❌ ' + data.error);
        }
    });
}

// Test Login
async function testLogin() {
    const btn = document.getElementById('testBtn');
    const result = document.getElementById('testResult');

    btn.disabled = true;
    btn.innerHTML = '⏳ Teste Login... (ca. 10-20s)';
    result.innerHTML = '<div style="color:var(--text2)">Browser wird gestartet...</div>';

    try {
        const res = await fetch('/api/test_kleinanzeigen_login.php', {
            method: 'POST'
        });

        const data = await res.json();

        if (data.success) {
            result.innerHTML = '<div style="color:var(--green);padding:10px;background:rgba(34,197,94,0.1);border-radius:8px">✅ Login erfolgreich!<br>Cookies gespeichert.</div>';
            btn.innerHTML = '✅ Erfolgreich';
        } else {
            result.innerHTML = '<div style="color:var(--red);padding:10px;background:rgba(239,68,68,0.1);border-radius:8px">❌ Login fehlgeschlagen<br>' + (data.error || 'Unbekannter Fehler') + '</div>';
            btn.innerHTML = '🔍 Login testen';
        }
    } catch (err) {
        result.innerHTML = '<div style="color:var(--red)">❌ Fehler: ' + err.message + '</div>';
        btn.innerHTML = '🔍 Login testen';
    }

    btn.disabled = false;
}
</script>

<?php include 'includes/footer.php'; ?>