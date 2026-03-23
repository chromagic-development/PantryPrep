<?php
session_start();
require_once '../db.php';
$db = getDB();

// Ensure settings table exists
$db->exec("CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL
)");

// Seed defaults if missing
$db->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('admin_password', 'admin')");
$db->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('allowed_ip', '')");

// Helper to get a setting
function getSetting($db, $key) {
    $s = $db->prepare("SELECT value FROM settings WHERE key = ?");
    $s->execute([$key]);
    return $s->fetchColumn();
}

$adminPassword = getSetting($db, 'admin_password');
$allowedIp     = getSetting($db, 'allowed_ip');

// Handle login form submission
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    if ($_POST['password'] === $adminPassword) {
        $_SESSION['admin_auth'] = true;
    } else {
        $loginError = 'Incorrect password.';
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Handle settings saves (must be authenticated)
if ($_SESSION['admin_auth'] ?? false) {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'save_ip') {
            $ip = trim($_POST['allowed_ip'] ?? '');
            $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('allowed_ip', ?)")->execute([$ip]);
            $allowedIp = $ip;
            $settingsSaved = 'IP address updated.';
        } elseif ($_POST['action'] === 'save_password') {
            $newPw     = trim($_POST['new_password'] ?? '');
            $confirmPw = trim($_POST['confirm_password'] ?? '');
            if (strlen($newPw) < 1) {
                $settingsError = 'Password cannot be empty.';
            } elseif ($newPw !== $confirmPw) {
                $settingsError = 'Passwords do not match.';
            } else {
                $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('admin_password', ?)")->execute([$newPw]);
                $adminPassword = $newPw;
                $settingsSaved = 'Password updated.';
            }
        }
    }
}

// Show login wall if not authenticated
if (!($_SESSION['admin_auth'] ?? false)) {
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Footprints – Admin Login</title>
<style>
  :root { --brown:#6B4C11; --green:#8BAF3A; --light:#F5F0E8; --border:#D4C9A8; }
  * { box-sizing:border-box; margin:0; padding:0; }
  body { font-family:Arial,sans-serif; background:var(--light); display:flex; align-items:center; justify-content:center; min-height:100vh; }
  .login-card { background:#fff; border:1px solid var(--border); border-radius:12px; padding:36px 40px; width:100%; max-width:360px; box-shadow:0 4px 16px rgba(0,0,0,.1); }
  .login-card h1 { font-size:1.1rem; color:var(--brown); margin-bottom:6px; }
  .login-card p  { font-size:.82rem; color:#888; margin-bottom:24px; }
  label { display:block; font-size:.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:var(--brown); margin-bottom:6px; }
  input[type="password"] { width:100%; border:1px solid var(--border); border-radius:6px; padding:9px 12px; font-size:.95rem; margin-bottom:16px; background:#fafaf5; }
  input[type="password"]:focus { outline:none; border-color:var(--green); }
  .btn-login { width:100%; background:var(--brown); color:#fff; border:none; border-radius:7px; padding:11px; font-size:1rem; font-weight:700; cursor:pointer; }
  .btn-login:hover { background:#8B6420; }
  .error { background:#F8D7DA; border:1px solid #F1AEB5; color:#8B1A1A; border-radius:6px; padding:10px 14px; font-size:.85rem; margin-bottom:16px; }
</style>
</head>
<body>
<div class="login-card">
  <h1>⚙ Admin Login</h1>
  <p>Enter the administrator password to continue.</p>
  <?php if (!empty($loginError)): ?>
    <div class="error">⚠ <?= htmlspecialchars($loginError) ?></div>
  <?php endif; ?>
  <form method="POST">
    <input type="hidden" name="action" value="login">
    <label for="password">Password</label>
    <input type="password" id="password" name="password" autofocus placeholder="Enter password">
    <button type="submit" class="btn-login">Log In</button>
  </form>
</div>
</body>
</html>
<?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/x-icon" href="../favicon.ico">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Footprints – Admin: Manage Items</title>
<style>
  :root { --brown:#6B4C11; --green:#8BAF3A; --light:#F5F0E8; --border:#D4C9A8; }
  * { box-sizing:border-box; margin:0; padding:0; }
  body { font-family:Arial,sans-serif; background:var(--light); color:#333; }
  .topbar {
    background:var(--brown); color:#fff; padding:0 24px;
    display:flex; align-items:center; justify-content:space-between; height:58px;
    box-shadow:0 2px 8px rgba(0,0,0,.2);
  }
  .topbar .brand { display:flex; align-items:center; gap:12px; }
  .topbar .brand img { height:38px; display:block; }
  .topbar .brand span { font-size:1rem; font-weight:700; }
  .topbar a { color:#fff; text-decoration:none; background:rgba(255,255,255,.2); padding:7px 14px; border-radius:6px; font-size:.84rem; }
  .topbar a:hover { background:rgba(255,255,255,.35); }

  .container { max-width:1100px; margin:30px auto 60px; padding:0 16px; }
  h1 { font-size:1.3rem; color:var(--brown); margin-bottom:6px; }
  .subtitle { font-size:.84rem; color:#777; margin-bottom:20px; }

  .card { background:#fff; border:1px solid var(--border); border-radius:10px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.06); }
  .card-header { padding:14px 20px; background:#F0EBD8; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
  .card-header h2 { font-size:.9rem; font-weight:800; text-transform:uppercase; letter-spacing:.5px; color:var(--brown); }

  #itemsTable { width:100%; border-collapse:collapse; }
  #itemsTable th { text-align:left; padding:9px 12px; font-size:.75rem; text-transform:uppercase; letter-spacing:.5px; background:#F5F0E8; color:var(--brown); border-bottom:1px solid var(--border); }
  #itemsTable td { padding:8px 12px; border-bottom:1px solid #F0EBD8; vertical-align:middle; font-size:.88rem; }
  #itemsTable tr:hover td { background:#FAFAF5; }
  #itemsTable input[type="text"], #itemsTable select {
    border:1px solid var(--border); border-radius:4px; padding:5px 8px;
    font-size:.85rem; width:100%; background:#fafaf5;
  }
  #itemsTable input[type="text"]:focus, #itemsTable select:focus {
    outline:none; border-color:var(--green);
  }
  .toggle-active { cursor:pointer; font-size:1.1rem; }
  .drag-handle { cursor:grab; color:#bbb; font-size:1.1rem; padding:0 4px; }
  .drag-handle:active { cursor:grabbing; }
  .row-dragging { opacity:.4; background:#EEE0C0 !important; }

  .btn { border:none; border-radius:6px; padding:8px 18px; font-size:.85rem; font-weight:700; cursor:pointer; transition:background .2s; }
  .btn-green  { background:var(--green); color:#fff; }
  .btn-green:hover  { background:#6F9430; }
  .btn-brown  { background:var(--brown); color:#fff; }
  .btn-brown:hover  { background:#8B6420; }
  .btn-red    { background:transparent; color:#C62828; border:1px solid #C62828; }
  .btn-red:hover    { background:#C62828; color:#fff; }

  .btn-row { padding:16px 20px; display:flex; justify-content:space-between; flex-wrap:wrap; gap:10px; border-top:1px solid var(--border); background:#F5F0E8; }

  .toast { position:fixed; bottom:24px; right:24px; background:#222; color:#fff; padding:12px 20px; border-radius:8px; font-size:.88rem; font-weight:600; transform:translateY(80px); opacity:0; transition:all .3s; pointer-events:none; z-index:999; }
  .toast.show { transform:translateY(0); opacity:1; }
</style>
</head>
<body>

<div class="topbar">
  <div class="brand">
    <img src="../Footprint_logo.jpg" alt="Footprints">
    <span>⚙ Manage Order Items</span>
  </div>
  <div style="display:flex;gap:10px;">
    <a href="../orders">← Orders</a>
    <a href="../" target="_blank" rel="noopener noreferrer">📋 Order Form</a>
	<a href="../report/">📊 Report</a>
    <a href="admin.php?logout=1" style="background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.3);">🔒 Log Out</a>
  </div>
</div>

<div class="container">
  <h1>Configure Order Form Items</h1>
  <p class="subtitle">Add, remove, reorder, or toggle items that appear on the customer order form. Drag rows to reorder. Changes take effect immediately for new orders.</p>

  <div class="card">
    <div class="card-header">
      <h2>Active Items</h2>
      <button class="btn btn-green" onclick="addRow()">+ Add Item</button>
    </div>
    <table id="itemsTable">
      <thead>
        <tr>
          <th style="width:30px;"></th>
          <th style="width:40px;">On</th>
          <th style="width:120px;">Category</th>
          <th style="min-width:160px;">Item Name</th>
          <th style="width:80px;">Has Size?</th>
          <th style="width:100px;">Size Label</th>
          <th style="min-width:160px;">Sizes</th>
          <th style="width:90px;">Unavailable?</th>
          <th style="width:60px;">Remove</th>
        </tr>
      </thead>
      <tbody id="itemsTbody"></tbody>
    </table>
    <div class="btn-row">
      <button class="btn btn-brown" onclick="saveItems()">💾 Save All Changes</button>
      <span style="font-size:.78rem;color:#999;">Changes are saved to the database immediately</span>
    </div>
  </div>
</div>

<!-- ── System Configuration ───────────────────────────────────── -->
<div class="container" style="margin-top:0;">
  <h1>System Configuration</h1>
  <p class="subtitle">Manage access controls and network settings for this admin panel.</p>

  <?php if (!empty($settingsSaved)): ?>
    <div style="background:#D4EDDA;border:1px solid #A8D8B9;color:#276437;border-radius:8px;padding:12px 18px;margin-bottom:16px;font-size:.88rem;">✅ <?= htmlspecialchars($settingsSaved) ?></div>
  <?php elseif (!empty($settingsError)): ?>
    <div style="background:#F8D7DA;border:1px solid #F1AEB5;color:#8B1A1A;border-radius:8px;padding:12px 18px;margin-bottom:16px;font-size:.88rem;">⚠ <?= htmlspecialchars($settingsError) ?></div>
  <?php endif; ?>

  <div class="card" style="margin-bottom:20px;">
    <div class="card-header">
      <h2>🌐 Network Access</h2>
    </div>
    <div style="padding:22px 24px;">
      <p style="font-size:.82rem;color:#777;margin-bottom:16px;">
        Your current detected IP is <strong><?= htmlspecialchars($_SERVER['REMOTE_ADDR']) ?></strong>.
        You may override this with a static public IPv4 address below (for WiFi access-restriction purposes).
      </p>
      <form method="POST">
        <input type="hidden" name="action" value="save_ip">
        <label style="display:block;font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--brown);margin-bottom:6px;" for="allowed_ip">Public IPv4 Address</label>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
          <input type="text" id="allowed_ip" name="allowed_ip"
                 value="<?= htmlspecialchars($allowedIp ?: $_SERVER['REMOTE_ADDR']) ?>"
                 placeholder="<?= htmlspecialchars($_SERVER['REMOTE_ADDR']) ?>"
                 style="border:1px solid var(--border);border-radius:6px;padding:8px 12px;font-size:.92rem;width:220px;background:#fafaf5;font-family:monospace;">
          <button type="submit" class="btn btn-brown">Set IP Address</button>
          <button type="button" class="btn btn-green"
                  onclick="document.getElementById('allowed_ip').value='<?= htmlspecialchars($_SERVER['REMOTE_ADDR']) ?>'">
            Use My Current IP
          </button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h2>🔑 Administrator Password</h2>
    </div>
    <div style="padding:22px 24px;">
      <p style="font-size:.82rem;color:#777;margin-bottom:16px;">
        Set the password required to log in to this admin panel. The default password is <code style="background:#EEE8D5;padding:2px 6px;border-radius:4px;">admin</code> — change it after first setup.
      </p>
      <form method="POST">
        <input type="hidden" name="action" value="save_password">
        <label style="display:block;font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--brown);margin-bottom:6px;" for="new_password">New Password</label>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:12px;">
          <input type="password" id="new_password" name="new_password"
                 placeholder="Enter new password"
                 oninput="checkPwMatch()"
                 style="border:1px solid var(--border);border-radius:6px;padding:8px 12px;font-size:.92rem;width:220px;background:#fafaf5;">
        </div>
        <label style="display:block;font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--brown);margin-bottom:6px;" for="confirm_password">Retype Password</label>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
          <input type="password" id="confirm_password" name="confirm_password"
                 placeholder="Retype new password"
                 oninput="checkPwMatch()"
                 style="border:1px solid var(--border);border-radius:6px;padding:8px 12px;font-size:.92rem;width:220px;background:#fafaf5;">
          <button type="submit" id="pwSubmitBtn" class="btn btn-brown" disabled>Set Password</button>
          <span id="pwMatchMsg" style="font-size:.8rem;"></span>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
const CATS = ['DAIRY','DRY GOODS','FROZEN ITEMS','SPECIALS','OTHER ITEMS'];
let items = [];
let dragSrc = null;

async function loadItems() {
  const res  = await fetch('../api.php?action=get_config');
  const data = await res.json();
  items = data.items || [];
  renderTable();
}

function renderTable() {
  const tbody = document.getElementById('itemsTbody');
  tbody.innerHTML = items.map((item, i) => `
    <tr id="row_${i}" draggable="true"
        ondragstart="dragStart(event,${i})" ondragover="dragOver(event,${i})"
        ondrop="dragDrop(event,${i})" ondragend="dragEnd(event)">
      <td><span class="drag-handle" title="Drag to reorder">⠿</span></td>
      <td>
        <span class="toggle-active" onclick="toggleActive(${i})" title="Toggle active">
          ${item.active=='1'||item.active===1 ? '✅' : '⬜'}
        </span>
      </td>
      <td>
        <select onchange="items[${i}].category=this.value">
          ${CATS.map(c => `<option value="${c}" ${c===item.category?'selected':''}>${c}</option>`).join('')}
        </select>
      </td>
      <td><input type="text" value="${escHtml(item.item_name)}" oninput="items[${i}].item_name=this.value" placeholder="Item name"></td>
      <td style="text-align:center;">
        <input type="checkbox" ${item.has_detail==1?'checked':''}
               onchange="items[${i}].has_detail=this.checked?1:0; renderTable()">
      </td>
      <td>
        ${item.has_detail==1 ? `<input type="text" value="${escHtml(item.detail_label||'Size')}" oninput="items[${i}].detail_label=this.value" placeholder="Label">` : '<span style="color:#ccc">—</span>'}
      </td>
      <td>
        ${item.has_detail==1 ? `<input type="text" value="${escHtml(item.size_options||'')}" oninput="items[${i}].size_options=this.value" placeholder="e.g. Small,Medium,Large" title="Comma-separated list of size options">` : '<span style="color:#ccc">—</span>'}
      </td>
      <td style="text-align:center;">
        <input type="checkbox" title="Check if this item is currently unavailable"
               ${item.unavailable==1?'checked':''}
               onchange="items[${i}].unavailable=this.checked?1:0"
               style="accent-color:#C62828;width:16px;height:16px;">
      </td>
      <td>
        <button class="btn btn-red" style="padding:5px 10px;" onclick="removeRow(${i})">✕</button>
      </td>
    </tr>
  `).join('');
}

function addRow() {
  items.push({ category:'DAIRY', item_name:'', has_detail:0, detail_label:'', size_options:'', active:1, unavailable:0, sort_order:items.length });
  renderTable();
  // Focus the new row name input
  setTimeout(() => {
    const rows = document.querySelectorAll('#itemsTbody tr');
    const last = rows[rows.length-1];
    if (last) { const inp = last.querySelector('input[type="text"]'); if (inp) inp.focus(); }
  }, 50);
}

function removeRow(i) {
  if (!confirm('Remove "'+(items[i].item_name||'this item')+'"?')) return;
  items.splice(i, 1);
  renderTable();
}

function toggleActive(i) {
  items[i].active = (items[i].active==1||items[i].active===true) ? 0 : 1;
  renderTable();
}

// Drag-to-reorder
function dragStart(e, i) { dragSrc=i; e.currentTarget.classList.add('row-dragging'); }
function dragOver(e, i)  { e.preventDefault(); }
function dragDrop(e, i)  {
  e.preventDefault();
  if (dragSrc === null || dragSrc === i) return;
  const moved = items.splice(dragSrc, 1)[0];
  items.splice(i, 0, moved);
  renderTable();
}
function dragEnd(e) { e.currentTarget.classList.remove('row-dragging'); dragSrc = null; }

async function saveItems() {
  // Validate
  for (let i=0; i<items.length; i++) {
    if (!items[i].item_name.trim()) {
      alert('Item name cannot be empty (row '+(i+1)+')');
      return;
    }
  }
  try {
    const res  = await fetch('../api.php?action=save_config', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ items })
    });
    const data = await res.json();
    if (!data.success) throw new Error(data.error);
    showToast('✅ Items saved successfully!');
    await loadItems();
  } catch(e) {
    showToast('⚠ Save failed: ' + e.message);
  }
}

function escHtml(str) {
  return String(str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function checkPwMatch() {
  var pw1 = document.getElementById('new_password').value;
  var pw2 = document.getElementById('confirm_password').value;
  var btn = document.getElementById('pwSubmitBtn');
  var msg = document.getElementById('pwMatchMsg');
  if (!pw1 && !pw2) {
    msg.textContent = ''; btn.disabled = true; return;
  }
  if (pw2.length === 0) {
    msg.textContent = ''; btn.disabled = true; return;
  }
  if (pw1 === pw2) {
    msg.textContent = '✅ Passwords match'; msg.style.color = '#276437'; btn.disabled = false;
  } else {
    msg.textContent = '✗ Passwords do not match'; msg.style.color = '#C62828'; btn.disabled = true;
  }
}

let toastTimer;
function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => t.classList.remove('show'), 3500);
}

loadItems();
</script>

<footer style="text-align:center; padding:24px 16px; font-size:.78rem; color:#999; border-top:1px solid var(--border); margin-top:40px;">
  Web application created by <a href="mailto:chromagic@gmail.com" style="color:var(--brown); text-decoration:none; font-weight:600;">Bruce Alexander</a>
</footer>

</body>
</html>
