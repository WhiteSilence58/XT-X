<?php
// alerts.php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/notifications.php';

// Login erforderlich
Auth::requireLogin();

$user_id = Auth::getCurrentUserId();

// Benachrichtigungen aus neuen Listings / Preisänderungen generieren
NotificationHelper::generateForAllWatchers($user_id);

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Notifications laden mit Filter
$where_clause = "n.user_id = $1";
$params = [$user_id];

switch ($filter) {
    case 'unread':
        $where_clause .= " AND n.is_read = FALSE";
        break;
    case 'new_deal':
        $where_clause .= " AND n.type = 'new_deal'";
        break;
    case 'price_change':
        $where_clause .= " AND n.type = 'price_change'";
        break;
    case 'system':
        $where_clause .= " AND n.type = 'system'";
        break;
}

$notifications = Database::fetchAll("
    SELECT
        n.*,
        w.label as watcher_label,
        l.title as listing_title,
        l.price as listing_price,
        l.url as listing_url
    FROM notifications n
    LEFT JOIN watchers w ON n.watcher_id = w.id
    LEFT JOIN listings l ON n.listing_id = l.id
    WHERE {$where_clause}
    ORDER BY n.created_at DESC
    LIMIT {$per_page} OFFSET {$offset}
", $params);

// Total count für Pagination
$total_count = Database::fetchOne("
    SELECT COUNT(*) as count
    FROM notifications n
    WHERE {$where_clause}
", $params)['count'] ?? 0;

$total_pages = ceil($total_count / $per_page);

// Unread count
$unread_count = Database::fetchOne("
    SELECT COUNT(*) as count
    FROM notifications
    WHERE user_id = $1 AND is_read = FALSE
", [$user_id])['count'] ?? 0;

// Page-Konstanten für Header
define('PAGE_TITLE', 'Alerts & Benachrichtigungen');
define('SHOW_ADD_BUTTON', false);

// User Info
$user = Database::fetchOne("SELECT display_name, email FROM users WHERE id = $1", [$user_id]);
?>

<?php include 'includes/header.php'; ?>

<!-- Alerts Content -->
<div class="content-wrapper" style="padding:20px;max-width:1200px;margin:0 auto">

  <!-- Header with actions -->
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px">
    <div>
      <h2 style="font-size:24px;font-weight:700;color:var(--text);margin:0 0 4px 0">
        🔔 Alerts & Benachrichtigungen
      </h2>
      <p style="font-size:13px;color:var(--text2);margin:0">
        <?= $unread_count > 0 ? "{$unread_count} ungelesene Benachrichtigungen" : "Alle Benachrichtigungen gelesen" ?>
      </p>
    </div>
    <div style="display:flex;gap:10px">
      <?php if ($unread_count > 0): ?>
      <button class="topbar-btn" onclick="markAllAsRead()" style="background:var(--accent);color:white">
        ✓ Alle als gelesen markieren
      </button>
      <?php endif; ?>
      <button class="topbar-btn" onclick="if(confirm('Wirklich alle Alerts löschen?')) deleteAllAlerts()">
        🗑️ Alle löschen
      </button>
    </div>
  </div>

  <!-- Filter Tabs -->
  <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
    <a href="?filter=all" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">
      📋 Alle (<?= $total_count ?>)
    </a>
    <a href="?filter=unread" class="filter-tab <?= $filter === 'unread' ? 'active' : '' ?>">
      🔴 Ungelesen (<?= $unread_count ?>)
    </a>
    <a href="?filter=new_deal" class="filter-tab <?= $filter === 'new_deal' ? 'active' : '' ?>">
      💰 Neue Deals
    </a>
    <a href="?filter=price_change" class="filter-tab <?= $filter === 'price_change' ? 'active' : '' ?>">
      📉 Preisänderungen
    </a>
    <a href="?filter=system" class="filter-tab <?= $filter === 'system' ? 'active' : '' ?>">
      ⚙️ System
    </a>
  </div>

  <!-- Notifications List -->
  <div class="glass-card" style="padding:0">
    <?php if (empty($notifications)): ?>
      <div style="padding:60px 20px;text-align:center;color:var(--text2)">
        <div style="font-size:60px;margin-bottom:16px">🔕</div>
        <div style="font-size:16px;font-weight:600;margin-bottom:8px;color:var(--text)">Keine Alerts gefunden</div>
        <div style="font-size:13px">
          <?php if ($filter !== 'all'): ?>
            Versuche einen anderen Filter oder erstelle neue Beobachtungen.
          <?php else: ?>
            Du wirst benachrichtigt, sobald es Neuigkeiten zu deinen Beobachtungen gibt.
          <?php endif; ?>
        </div>
        <a href="watchers.php" style="display:inline-block;margin-top:20px;padding:10px 20px;background:var(--accent);color:white;text-decoration:none;border-radius:10px;font-size:13px;font-weight:600">
          → Zu den Beobachtungen
        </a>
      </div>
    <?php else: ?>
      <?php foreach ($notifications as $notif):
        $type_map = [
          'new_deal' => ['icon' => '💰', 'color' => 'var(--green)', 'label' => 'Neuer Deal'],
          'price_change' => ['icon' => '📉', 'color' => 'var(--accent)', 'label' => 'Preisänderung'],
          'trend' => ['icon' => '📈', 'color' => 'var(--accent)', 'label' => 'Trend'],
          'gone' => ['icon' => '❌', 'color' => 'var(--red)', 'label' => 'Nicht verfügbar'],
          'system' => ['icon' => '⚙️', 'color' => 'var(--text2)', 'label' => 'System']
        ];
        $type_info = $type_map[$notif['type']] ?? ['icon' => '🔔', 'color' => 'var(--text2)', 'label' => 'Info'];

        $is_read = $notif['is_read'];
      ?>
      <div class="notification-item <?= !$is_read ? 'unread' : '' ?>" data-id="<?= $notif['id'] ?>">
        <div class="notif-dot" style="background:<?= $type_info['color'] ?>"></div>

        <div class="notif-icon" style="background:<?= $type_info['color'] ?>15">
          <?= $type_info['icon'] ?>
        </div>

        <div class="notif-content">
          <div class="notif-header">
            <div class="notif-title">
              <?= htmlspecialchars($notif['title']) ?>
            </div>
            <div class="notif-badge" style="background:<?= $type_info['color'] ?>">
              <?= $type_info['label'] ?>
            </div>
          </div>

          <div class="notif-message">
            <?= htmlspecialchars($notif['message']) ?>
          </div>

          <?php if ($notif['watcher_label']): ?>
          <div class="notif-meta">
            <span>👁 <?= htmlspecialchars($notif['watcher_label']) ?></span>
            <?php if ($notif['listing_price']): ?>
            <span>💰 <?= DashboardHelper::formatPrice($notif['listing_price']) ?></span>
            <?php endif; ?>
          </div>
          <?php endif; ?>

          <div class="notif-footer">
            <span class="notif-time">🕐 <?= DashboardHelper::timeAgo($notif['created_at']) ?></span>

            <div class="notif-actions">
              <?php if ($notif['listing_url']): ?>
              <a href="<?= htmlspecialchars($notif['listing_url']) ?>" target="_blank" class="notif-action">
                🔗 Angebot öffnen
              </a>
              <?php endif; ?>

              <?php if ($notif['watcher_id']): ?>
              <a href="listings.php?watcher_id=<?= $notif['watcher_id'] ?>" class="notif-action">
                👁 Beobachtung anzeigen
              </a>
              <?php endif; ?>

              <?php if (!$is_read): ?>
              <button class="notif-action" onclick="markAsRead(<?= $notif['id'] ?>)">
                ✓ Als gelesen markieren
              </button>
              <?php endif; ?>

              <button class="notif-action delete" onclick="deleteNotification(<?= $notif['id'] ?>)">
                🗑️ Löschen
              </button>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Pagination -->
  <?php if ($total_pages > 1): ?>
  <div style="display:flex;justify-content:center;align-items:center;gap:10px;margin-top:24px">
    <?php if ($page > 1): ?>
    <a href="?filter=<?= $filter ?>&page=<?= $page - 1 ?>" class="pagination-btn">← Zurück</a>
    <?php endif; ?>

    <div style="font-size:13px;color:var(--text2)">
      Seite <?= $page ?> von <?= $total_pages ?>
    </div>

    <?php if ($page < $total_pages): ?>
    <a href="?filter=<?= $filter ?>&page=<?= $page + 1 ?>" class="pagination-btn">Weiter →</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div>

<!-- Styles -->
<style>
.filter-tab {
  padding: 8px 16px;
  background: var(--input-bg);
  border: 1px solid var(--input-border);
  border-radius: 10px;
  font-size: 13px;
  font-weight: 600;
  color: var(--text2);
  text-decoration: none;
  transition: all 0.2s ease;
  cursor: pointer;
}

.filter-tab:hover {
  border-color: var(--accent);
  color: var(--accent);
}

.filter-tab.active {
  background: var(--accent);
  border-color: var(--accent);
  color: white;
}

.notification-item {
  display: flex;
  gap: 14px;
  padding: 18px;
  border-bottom: 1px solid var(--card-border);
  transition: all 0.2s ease;
  position: relative;
}

.notification-item:last-child {
  border-bottom: none;
}

.notification-item:hover {
  background: var(--input-bg);
}

.notification-item.unread {
  background: rgba(59, 107, 255, 0.05);
}

.notification-item.unread::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 3px;
  background: var(--accent);
}

.notif-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  margin-top: 6px;
  flex-shrink: 0;
}

.notif-icon {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  flex-shrink: 0;
}

.notif-content {
  flex: 1;
  min-width: 0;
}

.notif-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
  margin-bottom: 6px;
}

.notif-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--text);
  line-height: 1.4;
}

.notif-badge {
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 10px;
  font-weight: 600;
  color: white;
  white-space: nowrap;
}

.notif-message {
  font-size: 13px;
  color: var(--text2);
  line-height: 1.5;
  margin-bottom: 8px;
}

.notif-meta {
  display: flex;
  gap: 12px;
  font-size: 12px;
  color: var(--text2);
  margin-bottom: 8px;
}

.notif-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.notif-time {
  font-size: 11px;
  color: var(--text2);
}

.notif-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.notif-action {
  padding: 4px 10px;
  background: var(--input-bg);
  border: 1px solid var(--input-border);
  border-radius: 6px;
  font-size: 11px;
  font-weight: 600;
  color: var(--text);
  text-decoration: none;
  cursor: pointer;
  transition: all 0.2s ease;
}

.notif-action:hover {
  border-color: var(--accent);
  color: var(--accent);
}

.notif-action.delete:hover {
  border-color: var(--red);
  color: var(--red);
}

.pagination-btn {
  padding: 8px 16px;
  background: var(--input-bg);
  border: 1px solid var(--input-border);
  border-radius: 10px;
  font-size: 13px;
  font-weight: 600;
  color: var(--text);
  text-decoration: none;
  transition: all 0.2s ease;
}

.pagination-btn:hover {
  border-color: var(--accent);
  color: var(--accent);
}

@media (max-width: 768px) {
  .notification-item {
    flex-direction: column;
    gap: 10px;
  }

  .notif-icon {
    width: 36px;
    height: 36px;
    font-size: 18px;
  }

  .notif-actions {
    width: 100%;
  }

  .notif-action {
    flex: 1;
    text-align: center;
  }
}
</style>

<script src="assets/js/theme.js"></script>
<script>
// Mark single notification as read
async function markAsRead(notificationId) {
    try {
        const response = await fetch('api/mark_notification_read.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ notification_id: notificationId })
        });

        const result = await response.json();

        if (result.success) {
            const item = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
            if (item) {
                item.classList.remove('unread');
                const markBtn = item.querySelector('button[onclick*="markAsRead"]');
                if (markBtn) markBtn.remove();
            }

            // Reload if on unread filter
            if (new URLSearchParams(window.location.search).get('filter') === 'unread') {
                setTimeout(() => location.reload(), 500);
            }
        } else {
            alert('❌ Fehler: ' + result.error);
        }
    } catch (err) {
        alert('❌ Fehler: ' + err.message);
    }
}

// Mark all as read
async function markAllAsRead() {
    try {
        const response = await fetch('api/mark_all_notifications_read.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'}
        });

        const result = await response.json();

        if (result.success) {
            alert('✅ Alle Benachrichtigungen als gelesen markiert!');
            location.reload();
        } else {
            alert('❌ Fehler: ' + result.error);
        }
    } catch (err) {
        alert('❌ Fehler: ' + err.message);
    }
}

// Delete single notification
async function deleteNotification(notificationId) {
    if (!confirm('Diese Benachrichtigung wirklich löschen?')) return;

    try {
        const response = await fetch('api/delete_notification.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ notification_id: notificationId })
        });

        const result = await response.json();

        if (result.success) {
            const item = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
            if (item) {
                item.style.opacity = '0';
                item.style.transform = 'translateX(-20px)';
                setTimeout(() => item.remove(), 300);
            }
        } else {
            alert('❌ Fehler: ' + result.error);
        }
    } catch (err) {
        alert('❌ Fehler: ' + err.message);
    }
}

// Delete all alerts
async function deleteAllAlerts() {
    try {
        const response = await fetch('api/delete_all_notifications.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'}
        });

        const result = await response.json();

        if (result.success) {
            alert('✅ Alle Benachrichtigungen gelöscht!');
            location.reload();
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
</body>
</html>