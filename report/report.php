<?php
require_once '../db.php';
$db = getDB();

// ── Auth gate: same persistent cookie as admin.php ───────────────────────────
function makeAuthToken($password) {
    return hash('sha256', 'fp_admin_' . $password);
}
function isAuthenticated($db) {
    $s = $db->prepare("SELECT value FROM settings WHERE key = 'admin_password'");
    $s->execute();
    $pw = $s->fetchColumn();
    $cookie = $_COOKIE['fp_admin_auth'] ?? '';
    return $cookie !== '' && hash_equals(makeAuthToken($pw), $cookie);
}
if (!isAuthenticated($db)) {
    header('Location: ../admin/admin.php');
    exit;
}

// ── Date range defaults (current week Mon–Fri) ───────────────────────────────
$defaultStart = date('Y-m-d', strtotime('monday this week'));
$defaultEnd   = date('Y-m-d', strtotime('friday this week'));

$dateStart    = $_GET['date_start'] ?? $defaultStart;
$dateEnd      = $_GET['date_end']   ?? $defaultEnd;

// ── Load all distinct filter options from DB ─────────────────────────────────
$allNames      = $db->query("SELECT DISTINCT name FROM orders ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
$allCategories = $db->query("SELECT DISTINCT category FROM order_items ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
$allItems      = $db->query("SELECT DISTINCT item_name FROM config_items WHERE active = 1 ORDER BY item_name")->fetchAll(PDO::FETCH_COLUMN);

// ── Build anonymized name map: real name => "Client N" ───────────────────────
$nameMap = [];
foreach (array_values($allNames) as $idx => $realName) {
    $nameMap[$realName] = 'Client ' . ($idx + 1);
}

// ── Selected filters (arrays from multi-select) ───────────────────────────────
$selNames  = $_GET['names']      ?? [];
$selCats   = $_GET['categories'] ?? [];
$selItems  = $_GET['items']      ?? [];

// ── Build query ──────────────────────────────────────────────────────────────
$conditions = ["DATE(o.created_at) BETWEEN :ds AND :de"];
$params     = [':ds' => $dateStart, ':de' => $dateEnd];

if (!empty($selNames)) {
    $placeholders = implode(',', array_map(function($i) { return ":n$i"; }, array_keys($selNames)));
    $conditions[] = "o.name IN ($placeholders)";
    foreach ($selNames as $i => $v) $params[":n$i"] = $v;
}
if (!empty($selCats)) {
    $placeholders = implode(',', array_map(function($i) { return ":c$i"; }, array_keys($selCats)));
    $conditions[] = "oi.category IN ($placeholders)";
    foreach ($selCats as $i => $v) $params[":c$i"] = $v;
}
if (!empty($selItems)) {
    $placeholders = implode(',', array_map(function($i) { return ":it$i"; }, array_keys($selItems)));
    // Match via current config name (joined) OR legacy stored name (old rows without config_item_id)
    $conditions[] = "(COALESCE(ci.item_name, oi.item_name) IN ($placeholders))";
    foreach ($selItems as $i => $v) $params[":it$i"] = $v;
}

$where = implode(' AND ', $conditions);

$sql = "
    SELECT
        COALESCE(ci.category, oi.category)   AS category,
        COALESCE(ci.item_name, oi.item_name) AS item_name,
        COUNT(*) AS quantity
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    LEFT JOIN config_items ci ON ci.id = oi.config_item_id
    WHERE $where
    GROUP BY COALESCE(ci.id, oi.item_name)
    ORDER BY COALESCE(ci.category, oi.category), quantity DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Totals per category for summary row ──────────────────────────────────────
$totalByCategory = [];
foreach ($results as $row) {
    $totalByCategory[$row['category']] = ($totalByCategory[$row['category']] ?? 0) + $row['quantity'];
}
$grandTotal = array_sum(array_column($results, 'quantity'));

// ── Also fetch order count for header stat ───────────────────────────────────
$oSql = "SELECT COUNT(DISTINCT o.id) FROM orders o
         JOIN order_items oi ON oi.order_id = o.id
         LEFT JOIN config_items ci ON ci.id = oi.config_item_id
         WHERE $where";
$oStmt = $db->prepare($oSql);
$oStmt->execute($params);
$orderCount = (int)$oStmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/x-icon" href="../favicon.ico">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Footprints – Item Report</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
  :root { --brown:#6B4C11; --green:#8BAF3A; --light:#F5F0E8; --border:#D4C9A8; }
  * { box-sizing:border-box; margin:0; padding:0; }
  body { font-family:Arial,sans-serif; background:var(--light); color:#333; }

  /* ── Topbar ─────────────────────────────── */
  .topbar {
    background:var(--brown); color:#fff; padding:0 24px;
    display:flex; align-items:center; justify-content:space-between; height:58px;
    box-shadow:0 2px 8px rgba(0,0,0,.2);
  }
  .topbar .brand { display:flex; align-items:center; gap:12px; }
  .topbar .brand img { height:38px; display:block; }
  .topbar .brand span { font-size:1rem; font-weight:700; }
  .topbar nav { display:flex; gap:10px; }
  .topbar a { color:#fff; text-decoration:none; background:rgba(255,255,255,.2); padding:7px 14px; border-radius:6px; font-size:.84rem; }
  .topbar a:hover { background:rgba(255,255,255,.35); }

  /* ── Layout ─────────────────────────────── */
  .page { max-width:1100px; margin:30px auto 60px; padding:0 16px; }
  h1 { font-size:1.3rem; color:var(--brown); margin-bottom:4px; }
  .subtitle { font-size:.84rem; color:#777; margin-bottom:22px; }

  /* ── Filter card ─────────────────────────── */
  .filter-card {
    background:#fff; border:1px solid var(--border); border-radius:10px;
    overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.06); margin-bottom:24px;
  }
  .filter-card .card-header {
    padding:12px 20px; background:#F0EBD8; border-bottom:1px solid var(--border);
    font-size:.9rem; font-weight:800; text-transform:uppercase; letter-spacing:.5px; color:var(--brown);
  }
  .filter-body { padding:20px; display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:16px; align-items:start; }
  .filter-group label { display:block; font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:var(--brown); margin-bottom:6px; }
  .filter-group input[type="date"] {
    width:100%; border:1px solid var(--border); border-radius:6px;
    padding:7px 10px; font-size:.88rem; background:#fafaf5;
  }
  .filter-group select[multiple] {
    width:100%; border:1px solid var(--border); border-radius:6px;
    padding:4px; font-size:.83rem; background:#fafaf5; height:130px;
  }
  .filter-group select[multiple]:focus,
  .filter-group input[type="date"]:focus { outline:none; border-color:var(--green); }
  .filter-hint { font-size:.72rem; color:#999; margin-top:4px; }
  .filter-footer {
    padding:14px 20px; background:#F5F0E8; border-top:1px solid var(--border);
    display:flex; gap:10px; align-items:center;
  }
  .btn { border:none; border-radius:6px; padding:9px 22px; font-size:.88rem; font-weight:700; cursor:pointer; transition:background .2s; }
  .btn-brown { background:var(--brown); color:#fff; }
  .btn-brown:hover { background:#8B6420; }
  .btn-outline { background:transparent; color:var(--brown); border:1px solid var(--brown); }
  .btn-outline:hover { background:var(--brown); color:#fff; }

  /* ── Stats row ───────────────────────────── */
  .stats { display:flex; gap:16px; margin-bottom:22px; flex-wrap:wrap; }
  .stat-box {
    background:#fff; border:1px solid var(--border); border-radius:8px;
    padding:14px 22px; text-align:center; min-width:130px;
    box-shadow:0 1px 4px rgba(0,0,0,.05);
  }
  .stat-box .num { font-size:1.8rem; font-weight:800; color:var(--brown); }
  .stat-box .lbl { font-size:.73rem; text-transform:uppercase; letter-spacing:.4px; color:#888; margin-top:2px; }

  /* ── Results grid ────────────────────────── */
  .results-grid { display:grid; grid-template-columns:1fr 1fr; gap:22px; }
  @media (max-width:800px) { .results-grid { grid-template-columns:1fr; } .filter-body { grid-template-columns:1fr 1fr; } }
  @media (max-width:520px) { .filter-body { grid-template-columns:1fr; } }

  /* ── Table card ──────────────────────────── */
  .card { background:#fff; border:1px solid var(--border); border-radius:10px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.06); }
  .card .card-header { padding:12px 20px; background:#F0EBD8; border-bottom:1px solid var(--border); font-size:.9rem; font-weight:800; text-transform:uppercase; letter-spacing:.5px; color:var(--brown); }
  table { width:100%; border-collapse:collapse; }
  th { text-align:left; padding:9px 14px; font-size:.74rem; text-transform:uppercase; letter-spacing:.5px; background:#F5F0E8; color:var(--brown); border-bottom:1px solid var(--border); }
  td { padding:8px 14px; border-bottom:1px solid #F0EBD8; font-size:.86rem; vertical-align:middle; }
  tr:last-child td { border-bottom:none; }
  tr:hover td { background:#FAFAF5; }
  .cat-label { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#fff; background:var(--brown); border-radius:4px; padding:2px 7px; }
  .qty-bar-wrap { display:flex; align-items:center; gap:8px; }
  .qty-bar { height:10px; background:var(--green); border-radius:5px; min-width:4px; }
  .qty-num { font-weight:700; color:var(--brown); white-space:nowrap; }
  .total-row td { font-weight:700; background:#F0EBD8; border-top:2px solid var(--border); font-size:.88rem; }
  .no-data { padding:30px; text-align:center; color:#999; font-size:.9rem; }

  /* ── Chart card ──────────────────────────── */
  .chart-wrap { padding:18px; }
  canvas { max-width:100%; }

  /* ── Print ───────────────────────────────── */
  @media print {
    .topbar, .filter-card, .btn { display:none; }
    .results-grid { grid-template-columns:1fr; }
    .page { margin:0; }
  }
</style>
</head>
<body>

<div class="topbar">
  <div class="brand">
    <img src="../Footprint_logo.jpg" alt="Footprints">
    <span>📊 Item Usage Report</span>
  </div>
  <nav>
	<a href="../orders/">← Orders</a>
    <a href="../admin/">⚙ Manage Items</a>
    <a href="../admin/?logout=1">🔒 Log Out</a>
  </nav>
</div>

<div class="page">
  <h1>Item Usage Report</h1>
  <p class="subtitle">Filter by date range, customer name, category, or item to see pick quantities.</p>

  <!-- ── Filter Form ── -->
  <form method="GET" id="reportForm">
  <div class="filter-card">
    <div class="card-header">🔍 Filter Options</div>
    <div class="filter-body">

      <div class="filter-group">
        <label for="date_start">Start Date</label>
        <input type="date" id="date_start" name="date_start" value="<?= htmlspecialchars($dateStart) ?>">
      </div>

      <div class="filter-group">
        <label for="date_end">End Date</label>
        <input type="date" id="date_end" name="date_end" value="<?= htmlspecialchars($dateEnd) ?>">
      </div>

      <div class="filter-group">
        <label>Client</label>
        <select name="names[]" multiple id="sel_names">
          <?php foreach ($allNames as $n): ?>
            <option value="<?= htmlspecialchars($n) ?>" <?= in_array($n, $selNames) ? 'selected' : '' ?>>
              <?= htmlspecialchars($nameMap[$n] ?? $n) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="filter-hint">Ctrl/Cmd+click to select multiple. Leave blank for all.</div>
      </div>

      <div class="filter-group" style="display:grid;grid-template-rows:auto 1fr auto 1fr;gap:8px;">
        <label>Category</label>
        <select name="categories[]" multiple id="sel_cats">
          <?php foreach ($allCategories as $c): ?>
            <option value="<?= htmlspecialchars($c) ?>" <?= in_array($c, $selCats) ? 'selected' : '' ?>>
              <?= htmlspecialchars($c) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <label>Item Name</label>
        <select name="items[]" multiple id="sel_items">
          <?php foreach ($allItems as $it): ?>
            <option value="<?= htmlspecialchars($it) ?>" <?= in_array($it, $selItems) ? 'selected' : '' ?>>
              <?= htmlspecialchars($it) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

    </div>
    <div class="filter-footer">
      <button type="submit" class="btn btn-brown">📊 Run Report</button>
      <a href="../report/" class="btn btn-outline">↺ Reset</a>
      <?php if (!empty($results)): ?>
        <button type="button" class="btn btn-outline" onclick="window.print()" style="margin-left:auto;">🖨 Print</button>
      <?php endif; ?>
    </div>
  </div>
  </form>

  <?php if (isset($_GET['date_start'])): ?>

  <!-- ── Stats ── -->
  <div class="stats">
    <div class="stat-box">
      <div class="num"><?= $orderCount ?></div>
      <div class="lbl">Orders</div>
    </div>
    <div class="stat-box">
      <div class="num"><?= $grandTotal ?></div>
      <div class="lbl">Items Picked</div>
    </div>
    <div class="stat-box">
      <div class="num"><?= count($results) ?></div>
      <div class="lbl">Item Types</div>
    </div>
    <div class="stat-box">
      <div class="num"><?= htmlspecialchars(date('M j', strtotime($dateStart))) ?></div>
      <div class="lbl">Start Date</div>
    </div>
    <div class="stat-box">
      <div class="num"><?= htmlspecialchars(date('M j', strtotime($dateEnd))) ?></div>
      <div class="lbl">End Date</div>
    </div>
  </div>

  <?php if (empty($results)): ?>
    <div class="card"><div class="no-data">⚠ No data found for the selected filters and date range.</div></div>
  <?php else: ?>

  <!-- ── Results Grid ── -->
  <div class="results-grid">

    <!-- Table -->
    <div class="card">
      <div class="card-header">📋 Item Quantities</div>
      <table>
        <thead>
          <tr>
            <th>Category</th>
            <th>Item</th>
            <th>Quantity</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $maxQty = max(array_column($results, 'quantity'));
          $prevCat = null;
          foreach ($results as $row):
            $pct = $maxQty > 0 ? round(($row['quantity'] / $maxQty) * 100) : 0;
          ?>
          <tr>
            <td>
              <?php if ($row['category'] !== $prevCat): $prevCat = $row['category']; ?>
                <span class="cat-label"><?= htmlspecialchars($row['category']) ?></span>
              <?php else: ?>
                <span style="color:#ccc;font-size:.7rem;">↳</span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($row['item_name']) ?></td>
            <td>
              <div class="qty-bar-wrap">
                <div class="qty-bar" style="width:<?= $pct ?>px;max-width:80px;"></div>
                <span class="qty-num"><?= $row['quantity'] ?></span>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr class="total-row">
            <td colspan="2">Grand Total</td>
            <td><?= $grandTotal ?></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- Chart -->
    <div class="card">
      <div class="card-header">📈 Quantity Chart</div>
      <div class="chart-wrap">
        <canvas id="reportChart"></canvas>
      </div>
    </div>

  </div><!-- .results-grid -->

  <script>
  (function() {
    var labels   = <?= json_encode(array_map(function($r) { return $r['item_name']; }, $results)) ?>;
    var values   = <?= json_encode(array_map(function($r) { return (int)$r['quantity']; }, $results)) ?>;
    var cats     = <?= json_encode(array_map(function($r) { return $r['category']; }, $results)) ?>;

    // Assign a consistent color per category
    var catColors = {};
    var palette = ['#8BAF3A','#6B4C11','#4A90D9','#E07B39','#9B59B6','#27AE60','#E74C3C','#F39C12'];
    var ci = 0;
    cats.forEach(function(c) {
      if (!catColors[c]) catColors[c] = palette[ci++ % palette.length];
    });
    var colors = cats.map(function(c) { return catColors[c]; });

    new Chart(document.getElementById('reportChart'), {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{
          label: 'Quantity',
          data: values,
          backgroundColor: colors,
          borderRadius: 5,
          borderSkipped: false,
        }]
      },
      options: {
        indexAxis: <?= count($results) > 8 ? "'y'" : "'x'" ?>,
        responsive: true,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              title: function(ctx) { return cats[ctx[0].dataIndex] + ': ' + ctx[0].label; },
              label: function(ctx) { return 'Picked: ' + ctx.raw; }
            }
          }
        },
        scales: {
          x: { grid: { color:'#F0EBD8' }, ticks: { font: { size: 11 }, callback: function(v) {
            // When indexAxis='y', x is the value axis — show integers only
            // When indexAxis='x', x is the label axis — show the label string
            if (<?= count($results) > 8 ? 'true' : 'false' ?>) {
              return Number.isInteger(v) ? v : null;
            }
            return this.getLabelForValue(v);
          }}},
          y: { grid: { color:'#F0EBD8' }, ticks: { font: { size: 11 }, callback: function(v) {
            // When indexAxis='y', y is the label axis — show the label string
            // When indexAxis='x', y is the value axis — show integers only
            if (<?= count($results) > 8 ? 'true' : 'false' ?>) {
              return this.getLabelForValue(v);
            }
            return Number.isInteger(v) ? v : null;
          }}, beginAtZero: true }
        }
      }
    });
  })();
  </script>

  <?php endif; ?>
  <?php endif; ?>

</div><!-- .page -->

<footer style="text-align:center; padding:24px 16px; font-size:.78rem; color:#999; border-top:1px solid var(--border); margin-top:40px;">
  Web application created by <a href="mailto:chromagic@gmail.com" style="color:var(--brown); text-decoration:none; font-weight:600;">Bruce Alexander</a>
</footer>

</body>
</html>
