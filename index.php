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
  
  /* Increased base font size for better readability */
  html { font-size: 135%; } 

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
  .container { max-width: 900px; margin: 24px auto 40px; padding: 0 16px; }

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
    grid-template-columns: 1fr 110px 110px 140px;
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
    padding: 10px 18px;
    display: flex; flex-direction: column; justify-content: center; gap: 6px;
    border-right: 1px solid var(--border);
    min-width: 100px;
  }
  .order-header .count-cell label { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: var(--brown); }
  .order-header .count-cell select {
    border: 1px solid var(--border); border-radius: 4px; padding: 4px 6px;
    width: 65px; font-size: .95rem; text-align: center; background: #fafaf5;
    cursor: pointer;
  }
  .week-cell {
    padding: 10px 14px;
    display: flex; flex-direction: column; justify-content: center; align-items: center;
    min-width: 120px;
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

  /* ── Button-style Checkboxes ──────────────────────────── */
  .item-row {
    display: block;
    margin-bottom: 10px;
  }
  
  /* Visually hide the checkbox but keep it keyboard accessible */
  .item-row input[type="checkbox"] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
    overflow: hidden;
  }

  /* Style the label as a large, touch-friendly button */
  .item-row label {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 12px 14px;
    background-color: #fff;
    border: 2px solid var(--border);
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--text);
    transition: all 0.2s ease;
    text-align: center;
    min-height: 50px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  }

  .item-row label:hover {
    border-color: var(--green);
    background-color: #fafaf5;
  }

  /* When checkbox is checked, style label as active */
  .item-row input[type="checkbox"]:checked + label {
    background-color: var(--green);
    border-color: var(--green);
    color: #fff;
    box-shadow: 0 2px 5px rgba(139, 175, 58, 0.3);
  }

  /* Accessibility focus ring */
  .item-row input[type="checkbox"]:focus + label {
    outline: 2px solid var(--brown);
    outline-offset: 2px;
  }

  /* Styling for disabled/unavailable items */
  .item-row.unavailable label {
    background-color: #f0f0f0;
    border-color: #e0e0e0;
    color: #aaa;
    cursor: not-allowed;
    box-shadow: none;
  }

  .item-row.unavailable label:hover {
    border-color: #e0e0e0;
    background-color: #f0f0f0;
  }

  .detail-row {
    margin: 6px 0 12px 10px;
    display: none;
  }
  .detail-row.visible { display: flex; align-items: center; gap: 8px; }
  .detail-row label   { font-size: .78rem; color: #666; white-space: nowrap; }
  .detail-row input, .detail-row select {
    border: 1px solid var(--border); border-radius: 4px; padding: 3px 8px;
    font-size: .82rem; width: 130px; background: #fafaf5;
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
    background: #8BAF3A; color: #fff;
    border: none; border-radius: 7px; padding: 16px 32px;
    font-size: 1.15rem; font-weight: 700; cursor: pointer; letter-spacing: .3px;
    transition: background .2s; width: 100%;
  }
  .btn-submit:hover { background: #6F9430; }

  /* ── Error state ────────────────────────── */
  input.error { border-bottom-color: #c0392b !important; }
  .err-msg    { font-size: .75rem; color: #c0392b; margin-top: 2px; }

  /* ── Unavailable item ───────────────────── */
  .unavail-badge {
    font-size: .7rem; font-weight: 700; color: #fff;
    background: #6B4C11; border-radius: 4px;
    padding: 1px 6px; white-space: nowrap; margin-left: 4px;
  }

  /* ── Google Translate Widget ────────────── */
  .translate-wrap { margin-left: auto; display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
  .translate-wrap label { font-size: .75rem; color: #999; white-space: nowrap; }
  #google_translate_element { display: none !important; }
  #custom_lang_select {
    border: 1px solid var(--border); border-radius: 5px;
    padding: 5px 8px; font-size: .82rem; background: #fff;
    color: var(--brown); cursor: pointer; outline: none;
    font-family: Arial, sans-serif;
  }
  #custom_lang_select:focus { border-color: var(--green); }

  /* ── Responsive ─────────────────────────── */
  @media (max-width: 600px) {
    .order-header { 
      grid-template-columns: 1fr; /* Stacks header cells on mobile to ensure space */
    }
    .order-header .logo-cell { display: none; }
    .order-header .name-cell, .order-header .count-cell, .order-header .week-cell {
      border-right: none;
      border-bottom: 1px solid var(--border);
    }
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
    <p>Select items and tap DONE</p>
  </div>
  <div class="translate-wrap">
    <label for="custom_lang_select">🌐 Translate:</label>
    <select id="custom_lang_select" onchange="triggerGoogleTranslate(this.value)" translate="no">
      <option value="en" translate="no">English</option>
      <option value="es" translate="no">Español</option>
      <option value="pt" translate="no">Português</option>
      <option value="ar" translate="no">العربية</option>
      <option value="zh-TW" translate="no">廣東話</option>
      <option value="fr" translate="no">Français</option>
      <option value="ht" translate="no">Kreyòl ayisyen</option>
      <option value="so" translate="no">Soomaali</option>
      <option value="vi" translate="no">Tiếng Việt</option>
      <option value="km" translate="no">ភាសាខ្មែរ</option>
      <option value="ru" translate="no">Русский</option>
    </select>
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

  <div style="padding: 18px 20px; background: var(--cat-bg); border-bottom: 2px solid var(--border);">
    <button type="submit" class="btn-submit">📋 DONE</button>
  </div>

  <div class="order-header">
    <div class="name-cell">
      <label for="cust_name">First Name</label>
      <input type="text" id="cust_name" name="name" placeholder="First Name Only" class="<?= $errName ? 'error' : '' ?>" required autocomplete="name" onfocus="this.placeholder='Use Keyboard Below'" onblur="this.placeholder='First Name Only'">
      <?php if ($errName): ?><div class="err-msg">Name is required</div><?php endif; ?>
    </div>
    <div class="count-cell">
      <label for="adults">Adults</label>
      <select id="adults" name="adults">
        <?php for ($n = 1; $n <= 6; $n++): ?>
          <option value="<?= $n ?>"<?= $n === 1 ? ' selected' : '' ?>><?= $n ?></option>
        <?php endfor; ?>
      </select>
    </div>
    <div class="count-cell">
      <label for="children">Children</label>
      <select id="children" name="children">
        <?php for ($n = 0; $n <= 6; $n++): ?>
          <option value="<?= $n ?>"<?= $n === 0 ? ' selected' : '' ?>><?= $n ?></option>
        <?php endfor; ?>
      </select>
    </div>
    <div class="week-cell">
      <div class="wk-label">Week</div>
      <div class="wk-date"><?= htmlspecialchars($weekLabel) ?></div>
    </div>
  </div>

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
    <div class="item-row<?= !empty($item['unavailable']) ? ' unavailable' : '' ?>">
      <input type="checkbox"
             id="item_<?= $item['id'] ?>"
             name="item_<?= $item['id'] ?>"
             value="1"
             <?= $item['has_detail'] ? 'data-has-detail="1"' : '' ?>
             data-item-id="<?= $item['id'] ?>"
             <?= !empty($item['unavailable']) ? 'disabled' : '' ?>>
      <label for="item_<?= $item['id'] ?>"><?= htmlspecialchars($item['item_name']) ?><?= !empty($item['unavailable']) ? ' <span class="unavail-badge">Unavailable</span>' : '' ?></label>
    </div>
    <?php if ($item['has_detail']): ?>
    <div class="detail-row" id="detail_row_<?= $item['id'] ?>">
      <label for="detail_<?= $item['id'] ?>"><?= htmlspecialchars($item['detail_label']) ?>:</label>
      <?php
        $sizeOpts = array_filter(array_map('trim', explode(',', $item['size_options'] ?? '')));
        if (!empty($sizeOpts)):
      ?>
      <select id="detail_<?= $item['id'] ?>" name="detail_<?= $item['id'] ?>">
        <option value="">— Select —</option>
        <?php foreach ($sizeOpts as $opt): ?>
          <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
        <?php endforeach; ?>
      </select>
      <?php else: ?>
      <input type="text" id="detail_<?= $item['id'] ?>" name="detail_<?= $item['id'] ?>" placeholder="e.g. Small, Medium, Large">
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>
  </div>

  <div class="submit-row" style="flex-direction:column; align-items:stretch;">
    <button type="submit" class="btn-submit" id="submitBtn">📋 DONE</button>
  </div>

</div></form>
</div><script>
// Show/hide detail fields when checkbox is toggled; make size field required
document.querySelectorAll('input[data-has-detail]').forEach(function(cb) {
  cb.addEventListener('change', function() {
    var row = document.getElementById('detail_row_' + this.dataset.itemId);
    if (!row) return;
    row.classList.toggle('visible', this.checked);
    // Make the select or input inside required when checkbox is checked
    var field = row.querySelector('select, input[type="text"]');
    if (field) {
      if (this.checked) {
        field.setAttribute('required', 'required');
      } else {
        field.removeAttribute('required');
        field.value = field.tagName === 'SELECT' ? '' : '';
      }
    }
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

  // Once the widget renders, restore saved language and keep custom select in sync
  var initTimer = setInterval(function() {
    var gtSel = document.querySelector('#google_translate_element select');
    if (!gtSel) return;
    clearInterval(initTimer);

    // Restore previously selected language from localStorage
    var saved = localStorage.getItem('fp_lang');
    if (saved && saved !== 'en') {
      document.getElementById('custom_lang_select').value = saved;
      gtSel.value = saved;
      gtSel.dispatchEvent(new Event('change'));
    }

    // Keep custom select in sync when Google widget changes
    gtSel.addEventListener('change', function() {
      document.getElementById('custom_lang_select').value = this.value || 'en';
    });
  }, 200);
}

function triggerGoogleTranslate(lang) {
  // Save selection to localStorage so it survives page reloads/redirects
  if (lang) {
    localStorage.setItem('fp_lang', lang);
  }
  if (lang === 'en') {
    localStorage.removeItem('fp_lang');
  }
  var gtSel = document.querySelector('#google_translate_element select');
  if (gtSel) {
    gtSel.value = lang;
    gtSel.dispatchEvent(new Event('change'));
  }
}
</script>
<script type="text/javascript" src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
</body>
</html>