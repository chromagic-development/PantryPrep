<?php
require_once 'db.php';
$db = getDB();

$stmt = $db->prepare("SELECT value FROM settings WHERE key = 'allowed_ip'");
$stmt->execute();
$allowedIp = $stmt->fetchColumn() ?: $_SERVER['REMOTE_ADDR'];

$visitor_ip = $_SERVER['REMOTE_ADDR'];

if ($visitor_ip !== $allowedIp) {
    die("Access Denied: You are not authorized to view this page.");
}

$stmt = $db->query("SELECT * FROM config_items WHERE active = 1 ORDER BY category, sort_order, id");
$allItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

$categories = [];
foreach ($allItems as $item) {
    $categories[$item['category']][] = $item;
}

$success  = isset($_GET['success']);
$orderId  = (int)($_GET['order_id'] ?? 0);
$errName  = isset($_GET['error']) && $_GET['error'] === 'name';

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
    --blue:   #0056b3;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  html { font-size: 135%; } 

  body { font-family: Arial, sans-serif; background: var(--light); color: var(--text); min-height: 100vh; }

  /* ── Header ─────────────────────────────── */
  .site-header {
    background: #fff; border-bottom: 3px solid var(--green);
    padding: 14px 24px; display: flex; align-items: center; gap: 16px;
    box-shadow: 0 2px 6px rgba(0,0,0,.08);
  }
  .site-header img { height: 64px; }
  .header-text h1 { font-size: 1.05rem; color: var(--brown); font-weight: 700; text-transform: uppercase; }
  .header-text p  { font-size: .8rem; color: #777; }

  /* ── Banners ────────────────────────────── */
  .banner {
    max-width: 900px; margin: 20px auto 0; padding: 16px 22px;
    border-radius: 8px; display: flex; align-items: center; gap: 14px;
  }
  .banner.success { background:#D4EDDA; border:1px solid #A8D8B9; color:#276437; transition: opacity 1s ease; }
  .banner.error   { background:#F8D7DA; border:1px solid #F1AEB5; color:#8B1A1A; }
  .banner.validation { background:#E7F3FF; border:1px solid #BADCFF; color:var(--blue); display:none; }
  
  .banner h3      { font-size:.95rem; font-weight:700; margin-bottom:2px; }
  .banner p       { font-size:.85rem; }

  /* ── Translation Widget ─────────────────── */
  .translate-wrap { margin-left: auto; display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
  .translate-wrap label { font-size: .75rem; color: #999; }
  #google_translate_element { display: none !important; }
  #custom_lang_select {
    border: 1px solid var(--border); border-radius: 5px;
    padding: 5px 8px; font-size: .82rem; background: #fff;
    color: var(--brown); cursor: pointer;
  }

  /* ── Main Layout ────────────────────────── */
  .container { max-width: 900px; margin: 24px auto 40px; padding: 0 16px; }
  .card { background: #fff; border: 1px solid var(--border); border-radius: 10px; overflow: visible; box-shadow: 0 2px 10px rgba(0,0,0,.07); }

  .order-header { display: grid; grid-template-columns: 1fr 110px 110px 140px; border-bottom: 2px solid var(--border); }
  .name-cell { padding: 12px 16px; border-right: 1px solid var(--border); display: flex; flex-direction: column; justify-content: center; gap: 6px; }
  .name-cell label { font-weight: 700; font-size: .8rem; text-transform: uppercase; color: var(--brown); }
  .name-cell input { border: none; border-bottom: 2px solid var(--border); font-size: 1rem; padding: 2px 4px; outline: none; background: transparent; }
  
  .count-cell { padding: 10px 18px; display: flex; flex-direction: column; align-items: center; justify-content: center; border-right: 1px solid var(--border); }
  .count-cell label { font-size: .72rem; font-weight: 700; text-transform: uppercase; color: var(--brown); margin-bottom: 4px; }

  /* ── Custom Select Styles ──────────────── */
  .custom-select-wrap { position: relative; display: inline-block; }
  .custom-select-trigger {
    display: flex; align-items: center; justify-content: space-between; gap: 6px;
    border: 1px solid var(--border); border-radius: 4px; padding: 5px 10px;
    font-size: 0.9rem; background: #fafaf5; cursor: pointer; min-width: 130px;
  }
  .count-cell .custom-select-trigger { min-width: 75px; padding: 5px 8px; }
  .count-cell .custom-select-options { min-width: 75px; }

  .custom-select-trigger.placeholder { color: var(--blue); border-color: var(--blue); font-weight: bold; }
  
  .custom-select-options {
    display: none; position: absolute; left: 0; min-width: 100%;
    background: #fff; border: 1px solid var(--border); border-radius: 4px;
    box-shadow: 0 4px 14px rgba(0,0,0,.15); z-index: 9999; max-height: 250px; overflow-y: auto;
  }
  .custom-select-options.open { display: block; }
  .custom-select-option { padding: 10px 14px; font-size: 0.9rem; cursor: pointer; border-bottom: 1px solid #F0EBD8; }
  .custom-select-option:hover { background: #F5FAE8; }
  .custom-select-option.selected { background: #E8F5D0; font-weight: 700; }

  /* ── Item Grid & Rows ──────────────────── */
  .items-grid { display: grid; grid-template-columns: 1fr 1fr; }
  .cat-block { padding: 16px 20px; border-right: 1px solid var(--border); border-bottom: 1px solid var(--border); }
  .cat-block h3 { font-size: .85rem; font-weight: 800; text-transform: uppercase; color: var(--brown); border-bottom: 2px solid var(--cat-bg); margin-bottom: 10px; }
  
  .item-row { margin-bottom: 10px; position: relative; }
  .item-row input[type="checkbox"] { position: absolute; opacity: 0; }
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
    min-height: 50px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    /* Use nowrap to prevent the text from breaking into two lines */
    white-space: nowrap; 
  }
  .item-row input[type="checkbox"]:checked + label { background: var(--green); border-color: var(--green); color: #fff; }
  
  .unavail-badge {
    display: inline-block; /* Changed from block to inline-block */
    font-size: .65rem; 
    font-weight: 700; 
    color: #fff;
    background: #6B4C11; 
    border-radius: 4px;
    padding: 2px 6px; 
    margin-left: 8px; /* Added space between name and badge */
    vertical-align: middle;
  }

  .item-row.unavailable label { 
    background: #f0f0f0; 
    border-color: #e0e0e0; 
    color: #aaa; 
    cursor: not-allowed; 
    /* Prevent flex-direction: column if it was inherited */
    flex-direction: row !important; 
  }

  .item-row.children-only { display: none; }
  .detail-row { margin: 6px 0 12px 10px; display: none; align-items: center; gap: 8px; }
  .detail-row.visible { display: flex; }

  .submit-row { padding: 18px 20px; background: var(--cat-bg); border-top: 2px solid var(--border); }
  .btn-submit { background: #8BAF3A; color: #fff; border: none; border-radius: 7px; padding: 16px; font-size: 1.15rem; font-weight: 700; width: 100%; cursor: pointer; }

  @media (max-width: 600px) {
    .order-header { grid-template-columns: 1fr; }
    .items-grid { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>

<header class="site-header">
  <img src="Footprint_logo.jpg" alt="Logo">
  <div class="header-text">
    <h1>Food Pantry Order Form</h1>
    <p>Select items and tap DONE</p>
  </div>
  <div class="translate-wrap">
    <label for="custom_lang_select">🌐 Translate:</label>
    <select id="custom_lang_select" onchange="triggerGoogleTranslate(this.value)" translate="no">
      <option value="en">English</option>
      <option value="es">Español</option>
      <option value="pt">Português</option>
      <option value="ar">العربية</option>
      <option value="zh-TW">廣東話</option>
      <option value="fr">Français</option>
      <option value="ht">Kreyòl ayisyen</option>
      <option value="so">Soomaali</option>
      <option value="vi">Tiếng Việt</option>
      <option value="km">ភាសាខ្មែរ</option>
      <option value="ru">Русский</option>
    </select>
    <div id="google_translate_element"></div>
  </div>
</header>

<div class="container">

<?php if ($success): ?>
<div class="banner success" id="successBanner">
  <div style="font-size:1.6rem;">✅</div>
  <div>
    <h3>Order Submitted!</h3>
    <p>Your order #<strong><?= $orderId ?></strong> has been received. Thank you!</p>
  </div>
</div>
<?php endif; ?>

<?php if ($errName): ?>
<div class="banner error">
  <div style="font-size:1.6rem;">⚠️</div>
  <div>
    <h3>Missing Name</h3>
    <p>Please enter your first name before submitting.</p>
  </div>
</div>
<?php endif; ?>

<div class="banner validation" id="validationBanner">
  <div style="font-size:1.6rem;">ℹ️</div>
  <div>
    <h3 id="valTitle">Action Required</h3>
    <p id="valMessage">Tap all — Select — fields marked in blue below and make your selections</p>
  </div>
</div>

<form method="POST" action="submit_order.php" id="orderForm" novalidate>
<input type="hidden" name="week_date" value="<?= htmlspecialchars($weekLabel) ?>">

<div class="card" style="margin-top:20px;">
  <div style="padding: 18px 20px; background: var(--cat-bg); border-bottom: 2px solid var(--border);">
    <button type="submit" class="btn-submit">📋 DONE</button>
  </div>

  <div class="order-header">
    <div class="name-cell">
      <label for="cust_name">First Name</label>
      <input type="text" id="cust_name" name="name" placeholder="First Name Only" required 
             onfocus="this.placeholder='Use Keyboard Below'" onblur="this.placeholder='First Name Only'">
    </div>
    
    <div class="count-cell">
      <label>Adults</label>
      <input type="hidden" id="adults" name="adults" value="1">
      <div class="custom-select-wrap" id="csdrop_adults">
        <div class="custom-select-trigger" onclick="toggleCustomDropdown('adults')">
          <span class="cs-label">1</span><span class="arrow">▼</span>
        </div>
        <div class="custom-select-options" id="csopts_adults">
          <?php for ($n = 1; $n <= 6; $n++): ?>
            <div class="custom-select-option<?= $n===1?' selected':'' ?>" onclick="pickCustomOption('adults', '<?= $n ?>', '<?= $n ?>', this)"><?= $n ?></div>
          <?php endfor; ?>
        </div>
      </div>
    </div>

    <div class="count-cell">
      <label>Children</label>
      <input type="hidden" id="children" name="children" value="0">
      <div class="custom-select-wrap" id="csdrop_children">
        <div class="custom-select-trigger" onclick="toggleCustomDropdown('children')">
          <span class="cs-label">0</span><span class="arrow">▼</span>
        </div>
        <div class="custom-select-options" id="csopts_children">
          <?php for ($n = 0; $n <= 6; $n++): ?>
            <div class="custom-select-option<?= $n===0?' selected':'' ?>" onclick="pickCustomOption('children', '<?= $n ?>', '<?= $n ?>', this)"><?= $n ?></div>
          <?php endfor; ?>
        </div>
      </div>
    </div>

    <div style="display:flex; flex-direction:column; justify-content:center; align-items:center;">
      <div style="font-size:.65rem; color:#888;">Week</div>
      <div style="font-size:.85rem; font-weight:700; color:var(--brown);"><?= htmlspecialchars($weekLabel) ?></div>
    </div>
  </div>

  <div class="items-grid">
  <?php
    $orderedCats = ['DAIRY', 'FROZEN ITEMS', 'SPECIALS', 'DRY GOODS', 'OTHER ITEMS'];
    foreach ($orderedCats as $catName):
        if (!isset($categories[$catName])) continue;
  ?>
    <div class="cat-block">
      <h3><?= htmlspecialchars($catName) ?></h3>
      <?php foreach ($categories[$catName] as $item): ?>
      <div class="item-row<?= !empty($item['unavailable']) ? ' unavailable' : '' ?><?= !empty($item['use_children']) && empty($item['use_adults']) ? ' children-only' : '' ?>" 
           <?= !empty($item['use_children']) && empty($item['use_adults']) ? 'data-children-only="1"' : '' ?>>
        <input type="checkbox" id="item_<?= $item['id'] ?>" name="item_<?= $item['id'] ?>" value="1" 
               data-item-id="<?= $item['id'] ?>" <?= $item['has_detail'] ? 'data-has-detail="1"' : '' ?>
               <?= !empty($item['unavailable']) ? 'disabled' : '' ?>>
        
        <label for="item_<?= $item['id'] ?>">
            <?= htmlspecialchars($item['item_name']) ?>
            <?php if (!empty($item['unavailable'])): ?>
                <span class="unavail-badge">Unavailable</span>
            <?php endif; ?>
        </label>
      </div>

      <?php if ($item['has_detail']): ?>
      <div class="detail-row" id="detail_row_<?= $item['id'] ?>">
        <label><?= htmlspecialchars($item['detail_label']) ?>:</label>
        <input type="hidden" id="detail_<?= $item['id'] ?>" name="detail_<?= $item['id'] ?>" value="">
        <div class="custom-select-wrap" id="csdrop_<?= $item['id'] ?>">
          <div class="custom-select-trigger placeholder" onclick="toggleCustomDropdown('<?= $item['id'] ?>')">
            <span class="cs-label">— Select —</span><span class="arrow">▼</span>
          </div>
          <div class="custom-select-options" id="csopts_<?= $item['id'] ?>">
            <div class="custom-select-option" onclick="pickCustomOption('<?= $item['id'] ?>', '', '— Select —', this)">— Select —</div>
            <?php 
              $opts = array_filter(array_map('trim', explode(',', $item['size_options'] ?? '')));
              foreach ($opts as $o): 
            ?>
              <div class="custom-select-option" onclick="pickCustomOption('<?= $item['id'] ?>', '<?= htmlspecialchars($o) ?>', '<?= htmlspecialchars($o) ?>', this)"><?= htmlspecialchars($o) ?></div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
  </div>

  <div class="submit-row">
    <button type="submit" class="btn-submit">📋 DONE</button>
  </div>
</div>
</form>
</div>

<script>
function googleTranslateElementInit() {
  new google.translate.TranslateElement({pageLanguage: 'en'}, 'google_translate_element');
  var timer = setInterval(function() {
    var gtSel = document.querySelector('#google_translate_element select');
    if (!gtSel) return;
    clearInterval(timer);
    var saved = localStorage.getItem('fp_lang');
    if (saved && saved !== 'en') {
      document.getElementById('custom_lang_select').value = saved;
      gtSel.value = saved;
      gtSel.dispatchEvent(new Event('change'));
    }
  }, 500);
}

function triggerGoogleTranslate(lang) {
  localStorage.setItem('fp_lang', lang);
  var gtSel = document.querySelector('#google_translate_element select');
  if (gtSel) { gtSel.value = lang; gtSel.dispatchEvent(new Event('change')); }
}

function toggleCustomDropdown(itemId) {
  var opts = document.getElementById('csopts_' + itemId);
  var trigger = document.querySelector('#csdrop_' + itemId + ' .custom-select-trigger');
  if (!opts) return;
  var isOpen = opts.classList.contains('open');
  document.querySelectorAll('.custom-select-options.open').forEach(el => el.classList.remove('open'));
  if (!isOpen) {
    opts.classList.add('open');
    var rect = trigger.getBoundingClientRect();
    if (window.innerHeight - rect.bottom < 250) {
      opts.style.top = 'auto'; opts.style.bottom = '100%';
    } else {
      opts.style.top = '100%'; opts.style.bottom = 'auto';
    }
  }
}

function pickCustomOption(itemId, value, label, el) {
  var hidden = document.getElementById('detail_' + itemId) || document.getElementById(itemId);
  var trigger = document.querySelector('#csdrop_' + itemId + ' .custom-select-trigger');
  var lbl = trigger ? trigger.querySelector('.cs-label') : null;
  if (hidden) hidden.value = value;
  if (lbl) lbl.textContent = label;
  if (trigger && !isNaN(itemId)) trigger.classList.toggle('placeholder', !value);
  if (el) {
    el.parentNode.querySelectorAll('.custom-select-option').forEach(o => o.classList.remove('selected'));
    el.classList.add('selected');
    el.parentNode.classList.remove('open');
  }
  document.getElementById('validationBanner').style.display = 'none';
  if (itemId === 'children') updateChildrenOnly();
}

function updateChildrenOnly() {
  var count = parseInt(document.getElementById('children').value) || 0;
  document.querySelectorAll('[data-children-only]').forEach(row => {
    row.style.display = (count > 0) ? 'block' : 'none';
    if (count === 0) {
       var cb = row.querySelector('input');
       if(cb) cb.checked = false;
    }
  });
}

document.querySelectorAll('[data-has-detail]').forEach(cb => {
  cb.addEventListener('change', function() {
    document.getElementById('detail_row_' + this.dataset.itemId).classList.toggle('visible', this.checked);
  });
});

// 1. Hide the banner as soon as the user starts typing in the Name field
document.getElementById('cust_name').addEventListener('input', function() {
  var valBanner = document.getElementById('validationBanner');
  if (this.value.trim().length > 0) {
    valBanner.style.display = 'none';
    this.classList.remove('error');
  }
});

// 2. Updated Submission Logic for all 3 requirements
document.getElementById('orderForm').addEventListener('submit', function(e) {
  var nameField = document.getElementById('cust_name');
  var checked = document.querySelectorAll('.items-grid input[type="checkbox"]:checked');
  var valBanner = document.getElementById('validationBanner');
  var valMsg = document.getElementById('valMessage');
  
  // REQUIREMENT 1: Check for First Name
  if (!nameField.value.trim()) {
    e.preventDefault();
    valMsg.textContent = "Please enter your First Name to continue";
    valBanner.style.display = 'flex';
    nameField.classList.add('error'); 
    window.scrollTo({ top: 0, behavior: 'smooth' });
    return;
  }

  // REQUIREMENT 2: Check if at least one item is selected (REPLACED ALERT)
  if (checked.length === 0) { 
    e.preventDefault();
    valMsg.textContent = "Please select at least one item for your order";
    valBanner.style.display = 'flex';
    window.scrollTo({ top: 0, behavior: 'smooth' });
    return; 
  }
  
  // REQUIREMENT 3: Check for unselected dropdowns on checked items
  var missingDropdown = false;
  checked.forEach(cb => {
    var detail = document.getElementById('detail_' + cb.dataset.itemId);
    if (detail && !detail.value) missingDropdown = true;
  });

  if (missingDropdown) { 
    e.preventDefault(); 
    valMsg.textContent = "Tap — Select — fields marked in blue to complete selections";
    valBanner.style.display = 'flex';
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
});

document.addEventListener('click', e => {
  if (!e.target.closest('.custom-select-wrap')) document.querySelectorAll('.custom-select-options.open').forEach(el => el.classList.remove('open'));
});

var sBanner = document.getElementById('successBanner');
if (sBanner) {
  setTimeout(function() {
    sBanner.style.opacity = '0'; 
    setTimeout(function(){ sBanner.style.display='none'; }, 1000);
  }, 5000);
}
</script>
<script src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
</body>
</html>
```