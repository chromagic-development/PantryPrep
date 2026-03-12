<?php
require_once 'db.php';
$db = getDB();

$stmt = $db->prepare("SELECT value FROM settings WHERE key = 'allowed_ip'");
$stmt->execute();
$allowedIp = $stmt->fetchColumn() ?: $_SERVER['REMOTE_ADDR'];

$visitor_ip = $_SERVER['REMOTE_ADDR'];

if ($visitor_ip !== $allowedIp) {
    // This appears if the device is NOT on the WiFi
    die("Access Denied: You are not authorized to view this page.");
}

// Load active config items grouped by category
$stmt = $db->query("SELECT * FROM config_items WHERE active = 1 ORDER BY category, sort_order, id");
$allItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

$categories = [];
$catOrder = ['DAIRY','FROZEN ITEMS','SPECIALS','DRY GOODS','OTHER ITEMS'];
foreach ($allItems as $item) {
    $categories[$item['category']][] = $item;
}

$success  = isset($_GET['success']);
$orderId  = (int)($_GET['order_id'] ?? 0);
$errName  = isset($_GET['error']) && $_GET['error'] === 'name';

// Get current week label
$weekStart = date('M j', strtotime('monday this week'));
$weekEnd   = date('j',   strtotime('friday this week'));
$weekLabel = $weekStart . ' - ' . $weekEnd;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/x-icon" href="favicon.ico">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Footprints – Order Form</title>
<style>
  :root {
    --brown:  #6B4C11;
    --green:  #8BAF3A;
    --light:  #F5F0E8;
    --border: #D4C9A8;
    --text:   #333;
    --cat-bg: #EEE8D5;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, sans-serif; background: var(--light); color: var(--text); min-height: 100vh; }

  /* ── Header ─────────────────────────────── */
  .site-header {
    background: #fff;
    border-bottom: 3px solid var(--green);
    padding: 14px 24px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 2px 6px rgba(0,0,0,.08);
  }
  .site-header img { height: 64px; }
  .header-text h1 { font-size: 1.05rem; color: var(--brown); font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
  .header-text p  { font-size: .8rem; color: #777; }

  /* ── Success banner ─────────────────────── */
  .banner {
    max-width: 860px; margin: 20px auto 0; padding: 16px 22px;
    border-radius: 8px; display: flex; align-items: center; gap: 14px;
  }
  .banner.success { background:#D4EDDA; border:1px solid #A8D8B9; color:#276437; transition: opacity 1s ease; }
  .banner.error   { background:#F8D7DA; border:1px solid #F1AEB5; color:#8B1A1A; }
  .banner .icon   { font-size:1.6rem; }
  .banner h3      { font-size:.95rem; font-weight:700; margin-bottom:2px; }
  .banner p       { font-size:.85rem; }

  /* ── Main container ─────────────────────── */
  .container { max-width: 860px; margin: 24px auto 40px; padding: 0 16px; }

  /* ── Form card ──────────────────────────── */
  .card {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,.07);
  }

  /* ── Customer info header ───────────────── */
  .order-header {
    display: grid;
    grid-template-columns: 1fr auto auto auto;
    border-bottom: 2px solid var(--border);
  }
  .order-header .logo-cell {
    padding: 12px 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-right: 1px solid var(--border);
  }
  .order-header .logo-cell img { height: 70px; }
  .order-header .name-cell {
    padding: 12px 16px;
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 6px;
  }
  .order-header .name-cell label { font-weight: 700; font-size: .8rem; text-transform: uppercase; letter-spacing: .4px; color: var(--brown); }
  .order-header .name-cell input {
    border: none; border-bottom: 2px solid var(--border);
    font-size: 1rem; padding: 2px 4px; width: 100%; outline: none; background: transparent;
  }
  .order-header .name-cell input:focus { border-bottom-color: var(--green); }
  .order-header .count-cell {
    padding: 10px 14px;
    display: flex; flex-direction: column; justify-content: center; gap: 6px;
    border-right: 1px solid var(--border);
    width: 90px;
  }
  .order-header .count-cell label { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: var(--brown); }
  .order-header .count-cell input {
    border: 1px solid var(--border); border-radius: 4px; padding: 4px 6px;
    width: 60px; font-size: .95rem; text-align: center; background: #fafaf5;
  }
  .week-cell {
    padding: 10px 14px;
    display: flex; flex-direction: column; justify-content: center; align-items: center;
    min-width: 90px;
  }
  .week-cell .wk-label { font-size: .65rem; text-transform: uppercase; letter-spacing: .5px; color: #888; margin-bottom: 4px; }
  .week-cell .wk-date  { font-size: .85rem; font-weight: 700; color: var(--brown); }

  /* ── Item grid ──────────────────────────── */
  .items-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0;
  }
  .cat-block {
    padding: 16px 20px;
    border-right: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
  }
  .cat-block:nth-child(even) { border-right: none; }
  .cat-block h3 {
    font-size: .85rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .6px;
    color: var(--brown);
    margin-bottom: 10px;
    padding-bottom: 5px;
    border-bottom: 2px solid var(--cat-bg);
  }
  .item-row {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    margin-bottom: 7px;
    padding: 4px 0;
  }
  .item-row input[type="checkbox"] {
    width: 17px; height: 17px;
    accent-color: var(--green);
    margin-top: 2px;
    cursor: pointer;
    flex-shrink: 0;
  }
  .item-row label {
    font-size: .88rem;
    cursor: pointer;
    line-height: 1.3;
    flex: 1;
  }
  .detail-row {
    margin: 3px 0 8px 25px;
    display: none;
  }
  .detail-row.visible { display: flex; align-items: center; gap: 8px; }
  .detail-row label   { font-size: .78rem; color: #666; white-space: nowrap; }
  .detail-row input   {
    border: 1px solid var(--border); border-radius: 4px; padding: 3px 8px;
    font-size: .82rem; width: 120px; background: #fafaf5;
  }

  /* ── Notes ──────────────────────────────── */
  .notes-row { padding: 14px 20px; border-top: 1px solid var(--border); }
  .notes-row label { font-size: .8rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: var(--brown); display: block; margin-bottom: 6px; }
  .notes-row textarea {
    width: 100%; border: 1px solid var(--border); border-radius: 6px;
    padding: 8px 10px; font-size: .88rem; resize: vertical; min-height: 56px;
    background: #fafaf5; font-family: inherit;
  }

  /* ── Submit row ─────────────────────────── */
  .submit-row {
    padding: 18px 20px;
    background: var(--cat-bg);
    border-top: 2px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
  }
  .submit-row .helper { font-size: .8rem; color: #666; }
  .btn-submit {
    background: var(--brown); color: #fff;
    border: none; border-radius: 7px; padding: 11px 32px;
    font-size: 1rem; font-weight: 700; cursor: pointer; letter-spacing: .3px;
    transition: background .2s;
  }
  .btn-submit:hover { background: #8B6420; }

  /* ── Error state ────────────────────────── */
  input.error { border-bottom-color: #c0392b !important; }
  .err-msg    { font-size: .75rem; color: #c0392b; margin-top: 2px; }

  /* ── Google Translate Widget ────────────── */
  .translate-wrap { margin-left: auto; display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
  .translate-wrap label { font-size: .75rem; color: #999; white-space: nowrap; }
  #google_translate_element .goog-te-gadget {
    margin: 0 !important;
    font-size: 0 !important;
    display: flex !important;
    align-items: center !important;
  }
  /* Hide the "Powered by Google" logo link and "Translate" text link */
  #google_translate_element .goog-te-gadget > a,
  #google_translate_element .goog-logo-link,
  #google_translate_element .goog-te-gadget img,
  #google_translate_element .goog-te-gadget > span > a {
    display: none !important;
  }
  /* Style the language select dropdown */
  #google_translate_element select {
    border: 1px solid var(--border) !important;
    border-radius: 5px !important;
    background: #fff !important;
    padding: 5px 8px !important;
    font-size: .82rem !important;
    color: var(--brown) !important;
    cursor: pointer !important;
    outline: none !important;
    font-family: Arial, sans-serif !important;
  }
  #google_translate_element select:focus {
    border-color: var(--green) !important;
  }

  /* ── Responsive ─────────────────────────── */
  @media (max-width: 600px) {
    .order-header { grid-template-columns: 1fr 1fr; }
    .order-header .logo-cell { display: none; }
    .items-grid { grid-template-columns: 1fr; }
    .cat-block { border-right: none; }
  }
</style>
</head>
<body>

<header class="site-header">
  <img src="Footprint_logo.jpg" alt="Footprints Logo">
  <div class="header-text">
    <h1>Food Pantry Order Form</h1>
    <p>Select items below and submit your request</p>
  </div>
  <div class="translate-wrap">
    <label>🌐 Translate:</label>
    <div id="google_translate_element"></div>
  </div>
</header>

<?php if ($success): ?>
<div class="container">
  <div class="banner success">
    <div class="icon">✅</div>
    <div>
      <h3>Order Submitted Successfully!</h3>
      <p>Your order #<?= $orderId ?> has been received and added to the pick queue. Thank you!</p>
    </div>
  </div>
</div>
<?php elseif ($errName): ?>
<div class="container">
  <div class="banner error">
    <div class="icon">⚠️</div>
    <div><h3>Please enter your name to submit an order.</h3></div>
  </div>
</div>
<?php endif; ?>

<div class="container">
<form method="POST" action="submit_order.php" id="orderForm">
<input type="hidden" name="week_date" value="<?= htmlspecialchars($weekLabel) ?>">

<div class="card">

  <!-- Customer Info Header -->
  <div class="order-header">
    <div class="name-cell">
      <label for="cust_name">Name</label>
      <input type="text" id="cust_name" name="name" placeholder="First Name Only" class="<?= $errName ? 'error' : '' ?>" required autocomplete="name">
      <?php if ($errName): ?><div class="err-msg">Name is required</div><?php endif; ?>
    </div>
    <div class="count-cell">
      <label for="adults">Adults</label>
      <input type="number" id="adults" name="adults" value="1" min="0" max="20">
    </div>
    <div class="count-cell">
      <label for="children">Children</label>
      <input type="number" id="children" name="children" value="0" min="0" max="20">
    </div>
    <div class="week-cell">
      <div class="wk-label">Week</div>
      <div class="wk-date"><?= htmlspecialchars($weekLabel) ?></div>
    </div>
  </div>

  <!-- Item Categories Grid -->
  <div class="items-grid">
  <?php
  // Render in desired column order: left col then right col
    $leftCats = ['DAIRY', 'FROZEN ITEMS', 'SPECIALS'];
   $rightCats = ['DRY GOODS', 'OTHER ITEMS'];
   $allCats = array_keys($categories);
  // Merge maintaining left/right order
   $orderedCats = array_merge($leftCats, $rightCats);
  // Add any extra categories not in our predefined list
  foreach ($allCats as $cat) {
      if (!in_array($cat, $orderedCats)) $orderedCats[] = $cat;
  }

  foreach ($orderedCats as $catName):
      if (!isset($categories[$catName])) continue;
       $items = $categories[$catName];
  ?>
  <div class="cat-block">
    <h3><?= htmlspecialchars($catName) ?></h3>
    <?php foreach ($items as $item): ?>
    <div class="item-row">
      <input type="checkbox"
             id="item_<?= $item['id'] ?>"
             name="item_<?= $item['id'] ?>"
             value="1"
             <?= $item['has_detail'] ? 'data-has-detail="1"' : '' ?>
             data-item-id="<?= $item['id'] ?>">
      <label for="item_<?= $item['id'] ?>"><?= htmlspecialchars($item['item_name']) ?></label>
    </div>
    <?php if ($item['has_detail']): ?>
    <div class="detail-row" id="detail_row_<?= $item['id'] ?>">
      <label for="detail_<?= $item['id'] ?>"><?= htmlspecialchars($item['detail_label']) ?>:</label>
      <input type="text" id="detail_<?= $item['id'] ?>" name="detail_<?= $item['id'] ?>" placeholder="e.g. Small, Medium, Large">
    </div>
    <?php endif; ?>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>
  </div>

  <!-- Notes -->
  <div class="notes-row">
    <label for="notes">Special Notes / Allergies</label>
    <textarea id="notes" name="notes" placeholder="Any special requests, dietary restrictions, or notes..."></textarea>
  </div>

  <!-- Submit -->
  <div class="submit-row">
    <span class="helper">All selections are optional. At least one item must be checked.</span>
    <button type="submit" class="btn-submit" id="submitBtn">📋 Submit Order</button>
  </div>

</div><!-- .card -->
</form>
</div><!-- .container -->

<script>
// Show/hide detail fields when checkbox is toggled
document.querySelectorAll('input[data-has-detail]').forEach(function(cb) {
  cb.addEventListener('change', function() {
    var row = document.getElementById('detail_row_' + this.dataset.itemId);
    if (row) row.classList.toggle('visible', this.checked);
  });
});

// Validate at least one item checked
document.getElementById('orderForm').addEventListener('submit', function(e) {
  var anyChecked = document.querySelectorAll('.items-grid input[type="checkbox"]:checked').length > 0;
  if (!anyChecked) {
    e.preventDefault();
    alert('Please select at least one item before submitting.');
  }
});

<?php if ($success): ?>
var successBanner = document.querySelector('.banner.success');
if (successBanner) {
  setTimeout(function() {
    successBanner.style.opacity = '0';
    setTimeout(function() { successBanner.style.display = 'none'; }, 1000);
  }, 5000);
}
<?php endif; ?>
</script>
<script type="text/javascript">
function googleTranslateElementInit() {
  new google.translate.TranslateElement({
    pageLanguage: 'en',
    includedLanguages: 'en,es,pt,ar,zh-TW,fr,ht,so,vi,km,ru',
    layout: google.translate.TranslateElement.InlineLayout.HORIZONTAL,
    autoDisplay: false,
    multilanguagePage: true
  }, 'google_translate_element');
}
</script>
<script type="text/javascript" src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
</body>
</html>
