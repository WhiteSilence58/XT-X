<?php
// listings.php - Optimiert mit allen Features
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

Auth::requireLogin();

$user_id = Auth::getCurrentUserId();
$watcher_id = $_GET['watcher_id'] ?? null;
$sort = $_GET['sort'] ?? 'price_asc';

if (!$watcher_id) {
    header('Location: watchers.php');
    exit;
}

// Watcher laden
$watcher = Database::fetchOne("
    SELECT w.*,
        (SELECT sl.finished_at FROM scrape_logs sl WHERE sl.watcher_id = w.id ORDER BY sl.finished_at DESC LIMIT 1) as last_run_at_real,
        (SELECT sl.listings_found FROM scrape_logs sl WHERE sl.watcher_id = w.id ORDER BY sl.finished_at DESC LIMIT 1) as last_found_count
    FROM watchers w
    WHERE w.id = $1 AND w.user_id = $2
", [$watcher_id, $user_id]);

if (!$watcher) {
    header('Location: watchers.php');
    exit;
}

// Sortierung
$order_by = match($sort) {
    'price_asc' => 'price ASC',
    'price_desc' => 'price DESC',
    'newest' => 'posted_date DESC NULLS LAST, first_seen_at DESC',
    'oldest' => 'posted_date ASC NULLS LAST, first_seen_at ASC',
    default => 'price ASC'
};

// Listings laden (nur relevante Spalten)
$listings = Database::fetchAll("
    SELECT
        id, title, price, location_text, url, image_url,
        first_seen_at, posted_date,
        is_negotiable, shipping_available, pickup_only,
        condition
    FROM listings
    WHERE watcher_id = $1 AND status = 'active'
    ORDER BY $order_by
    LIMIT 100
", [$watcher_id]);

// User Info
$user = Database::fetchOne("SELECT display_name, email FROM users WHERE id = $1", [$user_id]);
$display_name = $user['display_name'] ?? 'User';
$initials = strtoupper(substr($display_name, 0, 1));
$stats = DashboardHelper::getStats($user_id);
?>
<!DOCTYPE html>
<html lang="de" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($watcher['label']) ?> — Kafka-Frame</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/dashboard.css">
<style>
.listing-card {
    transition: transform 0.3s, box-shadow 0.3s;
    position: relative;
}
.listing-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(0,0,0,0.15);
}
.listing-image {
    width: 100%;
    height: 200px;
    object-fit: cover;
    background: linear-gradient(135deg, #667eea20, #764ba220);
}
.listing-image-placeholder {
    width: 100%;
    height: 200px;
    background: linear-gradient(135deg, #667eea20, #764ba220);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 60px;
}
.sort-btn {
    padding: 8px 16px;
    background: var(--input-bg);
    border: 1px solid var(--input-border);
    border-radius: 8px;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.3s;
    font-family: inherit;
}
.sort-btn:hover {
    background: var(--accent);
    color: #fff;
    border-color: var(--accent);
}
.sort-btn.active {
    background: var(--accent);
    color: #fff;
    border-color: var(--accent);
}
.price-history-modal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 200;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(8px);
}
.price-history-content {
    background: var(--bg);
    border: 1px solid var(--card-border);
    border-radius: var(--radius);
    padding: 28px;
    max-width: 600px;
    width: 100%;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 24px 80px rgba(0,0,0,0.5);
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
    <h1 class="page-title">
      <a href="watchers.php" style="color:var(--text2);text-decoration:none;margin-right:8px">←</a>
      <?= htmlspecialchars($watcher['label']) ?>
    </h1>
    <button class="theme-btn" onclick="toggleTheme()" id="theme-btn">🌙</button>
  </div>

  <!-- Watcher Info + Sortierung -->
  <div class="glass-card" style="margin-bottom:20px;padding:20px">
    <div style="display:grid;grid-template-columns:1fr auto;gap:20px;align-items:center;margin-bottom:16px">
      <div>
        <div style="font-size:18px;font-weight:700;margin-bottom:8px">
          <?= htmlspecialchars($watcher['label']) ?>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:12px;font-size:12px;color:var(--text2)">
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
      </div>

      <div style="text-align:center;padding:12px;background:var(--input-bg);border:1px solid var(--input-border);border-radius:10px">
        <div style="font-size:24px;font-weight:700;color:var(--accent)"><?= count($listings) ?></div>
        <div style="font-size:10px;color:var(--text2)">Angebote</div>
      </div>
    </div>

    <!-- Sortierung -->
    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
      <span style="font-size:12px;font-weight:600;color:var(--text2);margin-right:8px">Sortieren:</span>
      <button class="sort-btn <?= $sort === 'price_asc' ? 'active' : '' ?>" onclick="location.href='?watcher_id=<?= $watcher_id ?>&sort=price_asc'">
        💰 Günstigste
      </button>
      <button class="sort-btn <?= $sort === 'price_desc' ? 'active' : '' ?>" onclick="location.href='?watcher_id=<?= $watcher_id ?>&sort=price_desc'">
        💎 Teuerste
      </button>
      <button class="sort-btn <?= $sort === 'newest' ? 'active' : '' ?>" onclick="location.href='?watcher_id=<?= $watcher_id ?>&sort=newest'">
        ✨ Neueste
      </button>
      <button class="sort-btn <?= $sort === 'oldest' ? 'active' : '' ?>" onclick="location.href='?watcher_id=<?= $watcher_id ?>&sort=oldest'">
        🕐 Älteste
      </button>
    </div>
  </div>

  <!-- Listings Grid -->
  <?php if (empty($listings)): ?>
    <div class="glass-card" style="padding:60px 20px;text-align:center">
      <div style="font-size:60px;margin-bottom:16px">⏳</div>
      <div style="font-size:16px;font-weight:600;margin-bottom:8px;color:var(--text)">
        Noch keine Angebote gefunden
      </div>
      <div style="font-size:13px;color:var(--text2)">
        Der Scraper läuft alle <?= $watcher['interval_minutes'] ?> Minuten.<br>
        Letzter Lauf: <?= $watcher['last_run_at_real'] ? DashboardHelper::timeAgo($watcher['last_run_at_real']) : 'Noch nie' ?>
      </div>
    </div>
  <?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px">
      <?php foreach ($listings as $listing):
        $is_deal = $watcher['max_price'] && $listing['price'] <= $watcher['max_price'];
        $is_new = $listing['posted_date'] && strtotime($listing['posted_date']) > (time() - 86400);

        // Boolean checks (PostgreSQL gibt 't'/'f' als String zurück)
        $has_vb = ($listing['is_negotiable'] === true || $listing['is_negotiable'] === 't');
        $has_shipping = ($listing['shipping_available'] === true || $listing['shipping_available'] === 't');
        $has_pickup = ($listing['pickup_only'] === true || $listing['pickup_only'] === 't');
      ?>
      <div class="glass-card listing-card" style="padding:0;overflow:hidden;">

        <!-- Bild (klickbar für Kleinanzeigen) -->
        <div style="cursor:pointer" onclick="window.open('<?= htmlspecialchars($listing['url']) ?>', '_blank')">
          <?php if ($listing['image_url']): ?>
            <div style="position:relative">
              <img src="<?= htmlspecialchars($listing['image_url']) ?>"
                   class="listing-image"
                   onerror="this.parentElement.innerHTML='<div class=\'listing-image-placeholder\'>📱</div>'">
              <?php if ($is_deal): ?>
                <div style="position:absolute;top:10px;right:10px;background:var(--green);color:#fff;padding:4px 8px;border-radius:6px;font-size:11px;font-weight:700;box-shadow:0 2px 8px rgba(0,0,0,0.3)">
                  🎯 Deal!
                </div>
              <?php endif; ?>
              <?php if ($is_new): ?>
                <div style="position:absolute;top:10px;left:10px;background:var(--accent);color:#fff;padding:4px 8px;border-radius:6px;font-size:11px;font-weight:700;box-shadow:0 2px 8px rgba(0,0,0,0.3)">
                  ✨ Neu
                </div>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <div class="listing-image-placeholder">📱</div>
          <?php endif; ?>
        </div>

        <!-- Content -->
        <div style="padding:14px">
          <!-- Titel -->
          <div style="font-size:13px;font-weight:600;margin-bottom:8px;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:34px;cursor:pointer"
               onclick="window.open('<?= htmlspecialchars($listing['url']) ?>', '_blank')">
            <?= htmlspecialchars($listing['title']) ?>
          </div>

          <!-- ⭐ PREIS + VB NEBENEINANDER -->
          <div style="display:flex;align-items:baseline;gap:6px;margin-bottom:8px">
            <div style="font-size:20px;font-weight:700;color:<?= $is_deal ? 'var(--green)' : 'var(--text)' ?>">
              <?= number_format($listing['price'], 0, ',', '.') ?>€
            </div>
            <?php if ($has_vb): ?>
              <span style="font-size:12px;font-weight:600;color:var(--text2)">VB</span>
            <?php endif; ?>
          </div>

          <!-- Datum -->
          <?php if ($listing['posted_date']): ?>
            <div style="font-size:10px;color:var(--text2);margin-bottom:8px">
              📅 <?= date('d.m.Y', strtotime($listing['posted_date'])) ?>
            </div>
          <?php endif; ?>

          <!-- ⭐ BADGES - NUR wenn gesetzt -->
          <?php if ($has_shipping || $has_pickup || !empty($listing['condition'])): ?>
          <div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:8px">
            <?php if ($has_shipping): ?>
              <span style="padding:2px 6px;background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.3);border-radius:4px;font-size:9px;font-weight:700;color:#22c55e">
                📦 Versand
              </span>
            <?php endif; ?>

            <?php if ($has_pickup): ?>
              <span style="padding:2px 6px;background:rgba(251,191,36,0.1);border:1px solid rgba(251,191,36,0.3);border-radius:4px;font-size:9px;font-weight:700;color:#f59e0b">
                🚗 Nur Abholung
              </span>
            <?php endif; ?>

            <?php if (!empty($listing['condition'])): ?>
              <span style="padding:2px 6px;background:rgba(139,92,246,0.1);border:1px solid rgba(139,92,246,0.3);border-radius:4px;font-size:9px;font-weight:700;color:#8b5cf6">
                <?= htmlspecialchars($listing['condition']) ?>
              </span>
            <?php endif; ?>
          </div>
          <?php endif; ?>

          <!-- Location -->
          <?php if ($listing['location_text']): ?>
            <div style="font-size:11px;color:var(--text2);margin-bottom:8px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
              📍 <?= htmlspecialchars($listing['location_text']) ?>
            </div>
          <?php endif; ?>

          <!-- ⭐ PREISVERLAUF BUTTON -->
          <button onclick="showPriceHistory('<?= $listing['id'] ?>'); event.stopPropagation();"
                  style="width:100%;padding:6px;background:var(--input-bg);border:1px solid var(--input-border);border-radius:6px;font-size:10px;color:var(--text);text-align:center;font-weight:600;cursor:pointer;margin-bottom:8px;font-family:inherit;transition:all 0.3s"
                  onmouseover="this.style.background='var(--accent)';this.style.color='#fff';this.style.borderColor='var(--accent)'"
                  onmouseout="this.style.background='var(--input-bg)';this.style.color='var(--text)';this.style.borderColor='var(--input-border)'">
            📈 Preisverlauf anzeigen
          </button>

          <div style="padding:6px;background:var(--input-bg);border-radius:6px;font-size:10px;color:var(--text2);text-align:center;font-weight:600;cursor:pointer"
               onclick="window.open('<?= htmlspecialchars($listing['url']) ?>', '_blank')">
            🔗 Auf Kleinanzeigen öffnen
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>

<!-- ⭐ PREISVERLAUF MODAL -->
<div id="priceHistoryModal" class="price-history-modal">
  <div class="price-history-content">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <div style="font-size:18px;font-weight:700">📈 Preisverlauf</div>
      <button onclick="closePriceHistory()" style="background:none;border:none;font-size:24px;cursor:pointer;color:var(--text2);padding:0;line-height:1">&times;</button>
    </div>

    <div id="priceHistoryContent" style="max-height:400px;overflow-y:auto">
      <div style="text-align:center;padding:40px;color:var(--text2)">
        Lade...
      </div>
    </div>
  </div>
</div>

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

document.getElementById('overlay')?.addEventListener('click', closeSidebar);

// ⭐ PREISVERLAUF FUNKTIONEN
async function showPriceHistory(listingId) {
    const modal = document.getElementById('priceHistoryModal');
    modal.style.display = 'flex';

    try {
        const response = await fetch(`/api/get_price_history.php?listing_id=${listingId}`);
        const data = await response.json();

        if (data.error) {
            document.getElementById('priceHistoryContent').innerHTML =
                `<div style="text-align:center;padding:40px;color:var(--red)">❌ ${data.error}</div>`;
            return;
        }

        if (!data.history || data.history.length === 0) {
            document.getElementById('priceHistoryContent').innerHTML =
                `<div style="text-align:center;padding:40px;color:var(--text2)">
                    <div style="font-size:48px;margin-bottom:12px">📊</div>
                    <div style="font-size:14px;font-weight:600;margin-bottom:8px">Noch keine Preisänderungen</div>
                    <div style="font-size:12px">Dieser Artikel wird beim nächsten Scraper-Durchlauf getrackt.</div>
                </div>`;
            return;
        }

        let html = `<div style="font-size:14px;font-weight:600;margin-bottom:16px;color:var(--text)">${data.title}</div>`;
        html += '<div style="display:flex;flex-direction:column;gap:12px">';

        let previousPrice = null;

        for (const entry of data.history) {
            const date = new Date(entry.recorded_at);
            const dateStr = date.toLocaleDateString('de-DE', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });

            let priceChange = '';
            let priceColor = 'var(--green)';

            if (previousPrice !== null) {
                const diff = parseFloat(entry.price) - previousPrice;
                if (diff > 0) {
                    priceChange = `<span style="color:var(--red);font-size:11px;margin-left:8px">↑ +${diff.toFixed(2)}€</span>`;
                    priceColor = 'var(--red)';
                } else if (diff < 0) {
                    priceChange = `<span style="color:var(--green);font-size:11px;margin-left:8px">↓ ${diff.toFixed(2)}€</span>`;
                    priceColor = 'var(--green)';
                }
            }

            previousPrice = parseFloat(entry.price);

            html += `
                <div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:var(--input-bg);border:1px solid var(--input-border);border-radius:8px">
                    <span style="font-size:12px;color:var(--text2)">${dateStr}</span>
                    <div style="display:flex;align-items:center">
                        <span style="font-size:16px;font-weight:700;color:${priceColor}">${parseFloat(entry.price).toFixed(2)}€</span>
                        ${priceChange}
                    </div>
                </div>
            `;
        }

        html += '</div>';

        // Statistik
        if (data.history.length > 1) {
            const prices = data.history.map(h => parseFloat(h.price));
            const minPrice = Math.min(...prices);
            const maxPrice = Math.max(...prices);
            const avgPrice = (prices.reduce((a, b) => a + b, 0) / prices.length).toFixed(2);

            html += `
                <div style="margin-top:20px;padding:16px;background:var(--input-bg);border-radius:8px">
                    <div style="font-size:12px;font-weight:600;margin-bottom:12px;color:var(--text2)">Statistik</div>
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;text-align:center">
                        <div>
                            <div style="font-size:11px;color:var(--text2);margin-bottom:4px">Min</div>
                            <div style="font-size:16px;font-weight:700;color:var(--green)">${minPrice.toFixed(2)}€</div>
                        </div>
                        <div>
                            <div style="font-size:11px;color:var(--text2);margin-bottom:4px">Ø</div>
                            <div style="font-size:16px;font-weight:700;color:var(--text)">${avgPrice}€</div>
                        </div>
                        <div>
                            <div style="font-size:11px;color:var(--text2);margin-bottom:4px">Max</div>
                            <div style="font-size:16px;font-weight:700;color:var(--red)">${maxPrice.toFixed(2)}€</div>
                        </div>
                    </div>
                </div>
            `;
        }

        document.getElementById('priceHistoryContent').innerHTML = html;

    } catch (err) {
        document.getElementById('priceHistoryContent').innerHTML =
            `<div style="text-align:center;padding:40px;color:var(--red)">❌ Fehler: ${err.message}</div>`;
    }
}

function closePriceHistory() {
    document.getElementById('priceHistoryModal').style.display = 'none';
}

// ESC-Taste zum Schließen
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closePriceHistory();
});

// Klick außerhalb schließt Modal
document.getElementById('priceHistoryModal')?.addEventListener('click', (e) => {
    if (e.target.id === 'priceHistoryModal') closePriceHistory();
});
</script>
</body>
</html>