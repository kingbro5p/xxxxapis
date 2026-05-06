<?php
session_start();
require_once 'env_loader.php';

$admin_user = $_ENV['ADMIN_USER'];
$admin_pass = $_ENV['ADMIN_PASS'];
$error = null;

// ══════════════════════════════════════════════
//  SETTINGS — DB থেকে load করো, না থাকলে default
// ══════════════════════════════════════════════
function getSettings($conn) {
    $cfg = ['main_api_key' => 'YOUR-API-KEY-HERE', 'api_prefix' => 'GH'];
    $res = $conn->query("SELECT `key`, `value` FROM settings");
    if ($res) {
        while ($r = $res->fetch_assoc()) $cfg[$r['key']] = $r['value'];
    }
    return $cfg;
}

function saveSetting($conn, $key, $value) {
    $k = mysqli_real_escape_string($conn, $key);
    $v = mysqli_real_escape_string($conn, $value);
    $conn->query("INSERT INTO settings (`key`, `value`) VALUES ('$k','$v')
                  ON DUPLICATE KEY UPDATE `value` = '$v'");
}

// ══════════════════════════════════════════════
//  LOGIN PAGE
// ══════════════════════════════════════════════
if (!isset($_SESSION['logged_in'])) {
    if (isset($_POST['login'])) {
        if ($_POST['user'] === $admin_user && $_POST['pass'] === $admin_pass) {
            $_SESSION['logged_in'] = true;
            header("Location: admin.php"); exit();
        } else { $error = "Incorrect username or password."; }
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — RxHoster</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  :root{--bg:#f7f8fc;--surface:#fff;--border:#e4e7ef;--text:#0f1117;--muted:#6b7280;--accent:#4f46e5;--accent-light:#eef2ff;--danger:#ef4444;--danger-light:#fef2f2}
  body{font-family:'DM Sans',sans-serif;background:var(--bg);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
  .card{background:var(--surface);border:1px solid var(--border);border-radius:24px;padding:48px 40px;width:100%;max-width:400px;box-shadow:0 1px 3px rgba(0,0,0,.06),0 8px 32px rgba(0,0,0,.06)}
  .brand{text-align:center;margin-bottom:36px}
  .bw{font-size:26px;font-weight:800;color:var(--text);letter-spacing:-1px}
  .bw span{color:var(--accent)}
  .bs{font-size:13px;color:var(--muted);margin-top:4px}
  .err{background:var(--danger-light);border:1px solid #fecaca;color:var(--danger);border-radius:10px;padding:11px 14px;font-size:13px;font-weight:500;margin-bottom:18px;display:flex;align-items:center;gap:8px}
  .fg{margin-bottom:14px}
  label{display:block;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
  input{width:100%;padding:12px 14px;border:1px solid var(--border);border-radius:12px;font-family:inherit;font-size:14px;color:var(--text);background:var(--bg);outline:none;transition:border-color .15s,box-shadow .15s}
  input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(79,70,229,.1);background:#fff}
  input::placeholder{color:#d1d5db}
  .btn{width:100%;background:var(--accent);color:#fff;border:none;border-radius:12px;padding:13px;font-family:inherit;font-size:14px;font-weight:600;cursor:pointer;transition:background .15s;margin-top:6px}
  .btn:hover{background:#4338ca}
</style>
</head>
<body>
  <div class="card">
    <div class="brand">
      <div class="bw">Rx<span>Hoster</span></div>
      <div class="bs">Admin Panel · Authorized Access Only</div>
    </div>
    <?php if ($error): ?>
    <div class="err">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>
    <form method="POST">
      <div class="fg"><label>Username</label><input type="text" name="user" placeholder="admin" required autocomplete="username"></div>
      <div class="fg"><label>Password</label><input type="password" name="pass" placeholder="••••••••" required autocomplete="current-password"></div>
      <button type="submit" name="login" class="btn">Sign In</button>
    </form>
  </div>
</body>
</html>
    <?php exit();
}

// ══════════════════════════════════════════════
//  SETTINGS TABLE তৈরি (না থাকলে)
// ══════════════════════════════════════════════
$conn->query("CREATE TABLE IF NOT EXISTS settings (
    `key`   VARCHAR(100) NOT NULL PRIMARY KEY,
    `value` TEXT NOT NULL
)");

// Settings load
$cfg        = getSettings($conn);
$MAIN_KEY   = $cfg['main_api_key'];
$API_PREFIX = strtoupper(trim($cfg['api_prefix']));

// ══════════════════════════════════════════════
//  ACTIONS
// ══════════════════════════════════════════════
if (isset($_GET['logout'])) { session_destroy(); header("Location: admin.php"); exit(); }

// ── Settings Update ──
if (isset($_POST['update_settings'])) {
    $new_key    = trim($_POST['main_api_key']);
    $new_prefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', trim($_POST['api_prefix'])));
    if ($new_key !== '') {
        saveSetting($conn, 'main_api_key', $new_key);
        // সব reseller-এর source_token একসাথে update
        $escaped = mysqli_real_escape_string($conn, $new_key);
        $conn->query("UPDATE api_users SET source_token = '$escaped'");
    }
    if ($new_prefix !== '') saveSetting($conn, 'api_prefix', $new_prefix);
    header("Location: admin.php?msg=Settings+Updated"); exit();
}

if (isset($_POST['add_user'])) {
    $name      = mysqli_real_escape_string($conn, trim($_POST['name']));
    $credits   = (int)$_POST['credits'];
    $api_token = $API_PREFIX . "-" . strtoupper(bin2hex(random_bytes(8)));
    $source    = $MAIN_KEY;
    $conn->query("INSERT INTO api_users 
                    (username, api_token, source_token, credits, used_requests, expiry_date, status)
                  VALUES 
                    ('$name', '$api_token', '$source', $credits, 0, DATE_ADD(NOW(), INTERVAL 1 MONTH), 1)");
    header("Location: admin.php?msg=Reseller+Created"); exit();
}

if (isset($_GET['del'])) {
    $id = (int)$_GET['del'];
    $conn->query("DELETE FROM api_users WHERE id = $id");
    header("Location: admin.php?msg=Reseller+Removed"); exit();
}

if (isset($_POST['update_credits'])) {
    $id     = (int)$_POST['reseller_id'];
    $type   = $_POST['credit_type'];
    $amount = (int)$_POST['credit_amount'];
    if ($type === 'add') {
        $conn->query("UPDATE api_users SET credits = credits + $amount WHERE id = $id");
    } else {
        $conn->query("UPDATE api_users SET credits = $amount WHERE id = $id");
    }
    header("Location: admin.php?msg=Credits+Updated"); exit();
}

if (isset($_POST['update_expiry'])) {
    $id   = (int)$_POST['reseller_id'];
    $type = $_POST['expiry_type'];
    $val  = mysqli_real_escape_string($conn, $_POST['expiry_value']);
    if ($type === 'extend') {
        $conn->query("UPDATE api_users SET expiry_date = DATE_ADD(expiry_date, INTERVAL $val DAY) WHERE id = $id");
    } else {
        $conn->query("UPDATE api_users SET expiry_date = '$val 00:00:00' WHERE id = $id");
    }
    header("Location: admin.php?msg=Expiry+Updated"); exit();
}

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $conn->query("UPDATE api_users SET status = IF(status=1, 0, 1) WHERE id = $id");
    header("Location: admin.php?msg=Status+Updated"); exit();
}

if (isset($_GET['regen'])) {
    $id        = (int)$_GET['regen'];
    $new_token = $API_PREFIX . "-" . strtoupper(bin2hex(random_bytes(8)));
    $conn->query("UPDATE api_users SET api_token = '$new_token' WHERE id = $id");
    header("Location: admin.php?msg=Key+Regenerated"); exit();
}

// Fetch resellers
$res   = $conn->query("SELECT * FROM api_users ORDER BY id DESC");
$users = [];
while ($u = $res->fetch_assoc()) $users[] = $u;
$total_allocated = array_sum(array_column($users, 'credits'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RxHoster — Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  :root{
    --bg:#f7f8fc;--surface:#fff;--border:#e4e7ef;
    --text:#0f1117;--muted:#6b7280;--muted2:#9ca3af;
    --accent:#4f46e5;--accent-light:#eef2ff;--accent-dark:#4338ca;
    --success:#10b981;--success-light:#ecfdf5;--success-border:#a7f3d0;
    --danger:#ef4444;--danger-light:#fef2f2;--danger-border:#fecaca;
    --warn:#f59e0b;--warn-light:#fffbeb;--warn-border:#fde68a;
    --indigo-light:#e0e7ff;
    --shadow-sm:0 1px 2px rgba(0,0,0,.04);
    --shadow:0 1px 3px rgba(0,0,0,.06),0 8px 24px rgba(0,0,0,.05);
    --shadow-lg:0 4px 6px rgba(0,0,0,.04),0 20px 48px rgba(0,0,0,.1);
  }
  body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh}
  .wrap{max-width:1200px;margin:0 auto;padding:0 20px}

  nav{background:var(--surface);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:100}
  .nav-inner{display:flex;align-items:center;justify-content:space-between;height:60px}
  .bw{font-size:18px;font-weight:800;color:var(--text);letter-spacing:-.6px;text-decoration:none}
  .bw span{color:var(--accent)}
  .nav-r{display:flex;align-items:center;gap:10px}
  .badge{background:var(--accent-light);color:var(--accent);font-size:11px;font-weight:600;padding:3px 10px;border-radius:100px}
  .btn-out{display:flex;align-items:center;gap:6px;background:none;border:1px solid var(--border);color:var(--muted);border-radius:100px;padding:6px 14px;font-family:inherit;font-size:13px;font-weight:500;cursor:pointer;text-decoration:none;transition:all .15s}
  .btn-out:hover{border-color:var(--danger);color:var(--danger);background:var(--danger-light)}
  .btn-out svg{width:13px;height:13px}

  .ph{padding:28px 0 22px}
  .ph h1{font-size:22px;font-weight:700;letter-spacing:-.4px;margin-bottom:3px}
  .ph p{font-size:13px;color:var(--muted)}

  .toast{display:flex;align-items:center;gap:10px;background:var(--success-light);border:1px solid var(--success-border);color:var(--success);border-radius:10px;padding:11px 14px;font-size:13px;font-weight:500;margin-bottom:20px}
  .toast svg{width:15px;height:15px;flex-shrink:0}

  /* ── Settings Card ── */
  .settings-card{background:var(--surface);border:1px solid var(--border);border-radius:20px;box-shadow:var(--shadow);padding:22px 24px;margin-bottom:24px}
  .settings-head{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:18px}
  .settings-title{display:flex;align-items:center;gap:10px}
  .settings-title span{font-size:14px;font-weight:700;color:var(--text)}
  .settings-badge{background:var(--warn-light);color:var(--warn);font-size:11px;font-weight:600;padding:3px 9px;border-radius:100px;border:1px solid var(--warn-border)}
  .settings-grid{display:grid;grid-template-columns:1fr 180px auto;gap:12px;align-items:end}
  @media(max-width:700px){.settings-grid{grid-template-columns:1fr}}
  .settings-group{display:flex;flex-direction:column;gap:5px}
  .settings-label{font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px}
  .settings-input{padding:10px 13px;border:1px solid var(--border);border-radius:10px;font-family:'DM Mono',monospace;font-size:13px;color:var(--text);background:var(--bg);outline:none;transition:border-color .15s,box-shadow .15s;width:100%}
  .settings-input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(79,70,229,.1);background:#fff}
  .prefix-wrap{position:relative}
  .prefix-hint{position:absolute;right:11px;top:50%;transform:translateY(-50%);font-size:10px;color:var(--muted2);pointer-events:none}
  .btn-settings-save{background:var(--accent);color:#fff;border:none;border-radius:10px;padding:11px 20px;font-family:inherit;font-size:13px;font-weight:600;cursor:pointer;transition:background .15s;white-space:nowrap;height:42px}
  .btn-settings-save:hover{background:var(--accent-dark)}
  .settings-info{display:flex;align-items:center;gap:8px;background:var(--accent-light);border:1px solid #c7d2fe;border-radius:8px;padding:9px 12px;font-size:12px;color:#4338ca;font-weight:500;margin-top:12px}
  .settings-info svg{width:14px;height:14px;flex-shrink:0}
  .preview-key{font-family:'DM Mono',monospace;font-weight:700}

  .main-card{background:var(--surface);border:1px solid var(--border);border-radius:20px;box-shadow:var(--shadow);padding:22px 24px;margin-bottom:24px}
  .mc-top{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px}
  .mc-left{display:flex;align-items:center;gap:10px}
  .mc-title{font-size:14px;font-weight:700;color:var(--text)}
  .mc-key{font-family:'DM Mono',monospace;font-size:11px;background:var(--accent-light);color:var(--accent);padding:3px 9px;border-radius:6px;letter-spacing:.3px;cursor:pointer;transition:background .15s}
  .mc-key:hover{background:var(--indigo-light)}
  .mc-pill{display:flex;align-items:center;gap:6px;border-radius:100px;padding:5px 13px;font-size:12px;font-weight:600;border:1px solid var(--border);background:var(--bg);color:var(--muted);transition:all .3s}
  .mc-pill.ok{background:var(--success-light);border-color:var(--success-border);color:var(--success)}
  .mc-pill.err{background:var(--danger-light);border-color:var(--danger-border);color:var(--danger)}
  .mc-dot{width:7px;height:7px;border-radius:50%;background:var(--muted2);flex-shrink:0}
  .mc-dot.ok{background:var(--success);animation:pulse 2s infinite}
  .mc-dot.err{background:var(--danger);animation:none}
  @keyframes pulse{0%,100%{opacity:1}50%{opacity:.35}}

  .mc-stats{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:18px}
  @media(max-width:860px){.mc-stats{grid-template-columns:repeat(3,1fr)}}
  @media(max-width:520px){.mc-stats{grid-template-columns:repeat(2,1fr)}}
  .mc-stat{background:var(--bg);border:1px solid var(--border);border-radius:12px;padding:14px 15px}
  .mc-stat.highlight{background:var(--accent-light);border-color:#c7d2fe}
  .mc-stat.highlight .mc-s-label{color:#6366f1}
  .mc-stat.highlight .mc-s-val{color:var(--accent)}
  .mc-stat.warn-stat{background:var(--warn-light);border-color:var(--warn-border)}
  .mc-stat.warn-stat .mc-s-label{color:#b45309}
  .mc-stat.warn-stat .mc-s-val{color:var(--warn)}
  .mc-stat.ok-stat{background:var(--success-light);border-color:var(--success-border)}
  .mc-stat.ok-stat .mc-s-label{color:#065f46}
  .mc-stat.ok-stat .mc-s-val{color:var(--success)}
  .mc-s-label{font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
  .mc-s-val{font-size:22px;font-weight:800;color:var(--text);line-height:1}
  .mc-s-val.sm{font-size:13px;font-family:'DM Mono',monospace;font-weight:600;margin-top:3px}
  .mc-s-val.dim{font-size:14px;color:var(--muted2);font-weight:400}
  .mc-s-sub{font-size:10px;color:var(--muted);margin-top:4px}
  .mc-prog-top{display:flex;justify-content:space-between;align-items:center;font-size:11px;color:var(--muted);margin-bottom:7px;font-weight:500}
  .mc-bar-bg{background:var(--border);border-radius:100px;height:7px;overflow:hidden}
  .mc-bar-fill{height:100%;border-radius:100px;background:var(--accent);transition:width .7s ease;width:0%}
  .mc-bar-fill.warn{background:var(--warn)}
  .mc-bar-fill.danger{background:var(--danger)}
  .mc-bar-fill.ok{background:var(--success)}

  .main-grid{display:grid;grid-template-columns:300px 1fr;gap:20px;align-items:start;padding-bottom:48px}
  @media(max-width:860px){.main-grid{grid-template-columns:1fr}}

  .add-card{background:var(--surface);border:1px solid var(--border);border-radius:18px;box-shadow:var(--shadow)}
  .add-card-head{padding:18px 20px 14px;border-bottom:1px solid var(--border)}
  .add-card-title{font-size:13px;font-weight:700;color:var(--text)}
  .add-card-body{padding:16px 20px 20px}
  .form-group{margin-bottom:13px}
  .form-label{display:block;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px}
  .form-input{width:100%;padding:10px 13px;border:1px solid var(--border);border-radius:10px;font-family:inherit;font-size:14px;color:var(--text);background:var(--bg);outline:none;transition:border-color .15s,box-shadow .15s}
  .form-input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(79,70,229,.1);background:#fff}
  .form-input::placeholder{color:#d1d5db}
  .input-locked{display:flex;align-items:center;gap:8px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:10px;padding:10px 13px}
  .input-locked-val{font-family:'DM Mono',monospace;font-size:12px;color:var(--accent);font-weight:600;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .input-locked svg{width:14px;height:14px;color:var(--muted2);flex-shrink:0}
  .credit-warn-box{background:var(--warn-light);border:1px solid var(--warn-border);border-radius:8px;padding:9px 12px;font-size:12px;color:#92400e;font-weight:500;margin-top:8px;display:none;align-items:center;gap:6px}
  .credit-warn-box svg{width:13px;height:13px;flex-shrink:0}
  .credit-avail-hint{font-size:11px;color:var(--muted);margin-top:5px}
  .key-preview{font-size:11px;color:var(--muted);margin-top:5px;font-family:'DM Mono',monospace}
  .key-preview span{color:var(--accent);font-weight:600}
  .btn-add{width:100%;background:var(--accent);color:#fff;border:none;border-radius:10px;padding:12px;font-family:inherit;font-size:14px;font-weight:600;cursor:pointer;transition:background .15s,box-shadow .15s;margin-top:6px}
  .btn-add:hover{background:var(--accent-dark);box-shadow:0 4px 12px rgba(79,70,229,.22)}
  .btn-add:disabled{background:#a5b4fc;cursor:not-allowed;box-shadow:none}

  .sec-label{font-size:12px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;display:flex;align-items:center;justify-content:space-between}
  .cnt-badge{background:var(--accent-light);color:var(--accent);font-size:11px;font-weight:700;padding:2px 8px;border-radius:100px}
  .r-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(265px,1fr));gap:14px}
  @media(max-width:480px){.r-grid{grid-template-columns:1fr}}

  .rc{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:18px;box-shadow:var(--shadow-sm);transition:box-shadow .2s,border-color .2s}
  .rc:hover{box-shadow:var(--shadow);border-color:#d1d5db}
  .rc-head{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:13px}
  .rc-av{width:36px;height:36px;border-radius:10px;background:var(--accent-light);color:var(--accent);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;flex-shrink:0}
  .rc-name{font-size:14px;font-weight:600;color:var(--text)}
  .rc-id{font-size:11px;color:var(--muted);margin-top:1px}
  .btn-del{background:none;border:none;cursor:pointer;color:var(--muted2);padding:5px;border-radius:7px;transition:color .15s,background .15s;display:flex}
  .btn-del:hover{color:var(--danger);background:var(--danger-light)}
  .btn-del svg{width:15px;height:15px}

  .tok-box{background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:7px 11px;display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:10px}
  .tok-val{font-family:'DM Mono',monospace;font-size:11px;color:var(--accent);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .btn-cp{background:none;border:none;cursor:pointer;color:var(--muted);padding:2px;border-radius:5px;transition:color .15s;flex-shrink:0;display:flex}
  .btn-cp:hover{color:var(--accent)}
  .btn-cp svg{width:13px;height:13px}

  .rc-stats{display:flex;gap:7px;margin-bottom:10px}
  .sp{flex:1;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:8px 10px;text-align:center}
  .sp-label{font-size:9px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px}
  .sp-val{font-size:16px;font-weight:700;color:var(--text)}

  .api-inf{background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:9px 11px}
  .ai-row{display:flex;align-items:center;justify-content:space-between;padding:2.5px 0}
  .ai-lbl{font-size:11px;color:var(--muted);font-weight:500;display:flex;align-items:center;gap:5px}
  .ai-val{font-size:12px;font-weight:600;color:var(--text);font-family:'DM Mono',monospace}
  .ai-val.loading{color:var(--muted2);font-style:italic;font-family:inherit;font-size:11px;font-weight:400}
  .ai-val.ok{color:var(--success);font-family:inherit}
  .ai-val.err{color:var(--danger);font-family:inherit;font-size:11px}
  .sdot{width:6px;height:6px;border-radius:50%;background:var(--muted2);display:inline-block;flex-shrink:0}
  .sdot.ok{background:var(--success);animation:pulse 2s infinite}
  .sdot.err{background:var(--danger);animation:none}
  .exp-badge{display:inline-flex;align-items:center;font-size:11px;font-weight:600;padding:2px 7px;border-radius:100px;font-family:'DM Sans',sans-serif}
  .exp-badge.ok{background:var(--success-light);color:var(--success)}
  .exp-badge.warn{background:var(--warn-light);color:var(--warn)}
  .exp-badge.exp{background:var(--danger-light);color:var(--danger)}

  .rc-actions{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px;padding-top:10px;border-top:1px solid var(--border)}
  .btn-action{flex:1;min-width:calc(50% - 3px);background:var(--bg);border:1px solid var(--border);color:var(--text);border-radius:8px;padding:7px 6px;font-family:inherit;font-size:11px;font-weight:600;cursor:pointer;transition:all .15s;text-align:center;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:4px}
  .btn-action:hover{border-color:var(--accent);color:var(--accent);background:var(--accent-light)}
  .btn-action.danger:hover{border-color:var(--danger);color:var(--danger);background:var(--danger-light)}
  .btn-action.success:hover{border-color:var(--success);color:var(--success);background:var(--success-light)}
  .btn-action.warn:hover{border-color:var(--warn);color:var(--warn);background:var(--warn-light)}
  .status-badge{display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:700;padding:2px 8px;border-radius:100px}
  .status-badge.active{background:var(--success-light);color:var(--success)}
  .status-badge.inactive{background:var(--danger-light);color:var(--danger)}
  .empty{text-align:center;padding:56px 24px;color:var(--muted);grid-column:1/-1}
  .empty svg{width:36px;height:36px;margin:0 auto 12px;opacity:.25;display:block}
  .empty p{font-size:14px}

  /* Delete Modal */
  .modal-ov{display:none;position:fixed;inset:0;z-index:500;background:rgba(15,17,23,.35);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:20px}
  .modal-ov.show{display:flex}
  .modal{background:var(--surface);border-radius:20px;padding:28px;max-width:360px;width:100%;box-shadow:var(--shadow-lg);animation:mIn .18s ease}
  @keyframes mIn{from{transform:scale(.96);opacity:0}to{transform:scale(1);opacity:1}}
  .modal-ico{width:44px;height:44px;border-radius:12px;background:var(--danger-light);color:var(--danger);display:flex;align-items:center;justify-content:center;margin-bottom:16px}
  .modal-ico svg{width:20px;height:20px}
  .modal-title{font-size:17px;font-weight:700;margin-bottom:8px}
  .modal-desc{font-size:13px;color:var(--muted);margin-bottom:22px;line-height:1.6}
  .modal-name{font-weight:600;color:var(--text)}
  .modal-btns{display:flex;gap:9px}
  .btn-cancel{flex:1;background:var(--bg);border:1px solid var(--border);color:var(--text);border-radius:10px;padding:11px;font-family:inherit;font-size:13px;font-weight:600;cursor:pointer;transition:background .15s}
  .btn-cancel:hover{background:var(--border)}
  .btn-del-confirm{flex:1;background:var(--danger);border:none;color:#fff;border-radius:10px;padding:11px;font-family:inherit;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;display:flex;align-items:center;justify-content:center;transition:background .15s}
  .btn-del-confirm:hover{background:#dc2626}

  /* Edit Modal */
  .edit-modal-ov{display:none;position:fixed;inset:0;z-index:500;background:rgba(15,17,23,.35);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:20px}
  .edit-modal-ov.show{display:flex}
  .edit-modal{background:var(--surface);border-radius:20px;padding:28px;max-width:380px;width:100%;box-shadow:var(--shadow-lg);animation:mIn .18s ease}
  .edit-modal-title{font-size:16px;font-weight:700;margin-bottom:16px;color:var(--text)}
  .edit-tabs{display:flex;gap:6px;margin-bottom:16px}
  .edit-tab{flex:1;padding:8px;border:1px solid var(--border);border-radius:8px;background:var(--bg);font-family:inherit;font-size:12px;font-weight:600;cursor:pointer;color:var(--muted);transition:all .15s;text-align:center}
  .edit-tab.active{background:var(--accent-light);border-color:var(--accent);color:var(--accent)}
  .edit-section{display:none}
  .edit-section.show{display:block}
  .edit-row{display:flex;gap:8px;margin-bottom:12px}
  .edit-row .form-input{flex:1}
  .edit-row select{flex:1;padding:10px 13px;border:1px solid var(--border);border-radius:10px;font-family:inherit;font-size:14px;color:var(--text);background:var(--bg);outline:none}
  .edit-row select:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(79,70,229,.1)}
  .btn-save{width:100%;background:var(--accent);color:#fff;border:none;border-radius:10px;padding:11px;font-family:inherit;font-size:14px;font-weight:600;cursor:pointer;transition:background .15s}
  .btn-save:hover{background:var(--accent-dark)}
  .edit-cancel{width:100%;background:none;border:1px solid var(--border);color:var(--muted);border-radius:10px;padding:10px;font-family:inherit;font-size:13px;font-weight:500;cursor:pointer;margin-top:8px;transition:all .15s}
  .edit-cancel:hover{background:var(--bg)}

  /* Settings Modal */
  .settings-modal-ov{display:none;position:fixed;inset:0;z-index:500;background:rgba(15,17,23,.35);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:20px}
  .settings-modal-ov.show{display:flex}
  .settings-modal{background:var(--surface);border-radius:20px;padding:28px;max-width:460px;width:100%;box-shadow:var(--shadow-lg);animation:mIn .18s ease}
  .sm-header{display:flex;align-items:center;gap:12px;margin-bottom:20px}
  .sm-ico{width:42px;height:42px;border-radius:12px;background:var(--warn-light);color:var(--warn);display:flex;align-items:center;justify-content:center;flex-shrink:0}
  .sm-ico svg{width:20px;height:20px}
  .sm-title{font-size:16px;font-weight:700;color:var(--text)}
  .sm-sub{font-size:12px;color:var(--muted);margin-top:2px}
  .sm-section{margin-bottom:16px}
  .sm-section-label{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
  .sm-input-wrap{position:relative}
  .sm-input{width:100%;padding:11px 13px;border:1px solid var(--border);border-radius:10px;font-family:'DM Mono',monospace;font-size:13px;color:var(--text);background:var(--bg);outline:none;transition:border-color .15s,box-shadow .15s}
  .sm-input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(79,70,229,.1);background:#fff}
  .sm-hint{font-size:11px;color:var(--muted);margin-top:5px}
  .sm-prefix-row{display:flex;gap:10px;align-items:center}
  .sm-prefix-row .sm-input{flex:1;max-width:120px;text-transform:uppercase;font-weight:700;letter-spacing:1px}
  .prefix-sample{font-family:'DM Mono',monospace;font-size:12px;color:var(--muted);background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:8px 12px;flex:1}
  .prefix-sample strong{color:var(--accent)}
  .sm-divider{border:none;border-top:1px solid var(--border);margin:16px 0}
  .sm-warn{display:flex;align-items:flex-start;gap:8px;background:var(--warn-light);border:1px solid var(--warn-border);border-radius:8px;padding:10px 12px;font-size:12px;color:#92400e;margin-bottom:16px;line-height:1.6}
.sm-warn svg{flex-shrink:0;margin-top:1px}
  .sm-warn svg{width:14px;height:14px;flex-shrink:0;margin-top:1px}
  .sm-btns{display:flex;gap:8px}
</style>
</head>
<body>

<!-- Delete Modal -->
<div class="modal-ov" id="delModal">
  <div class="modal">
    <div class="modal-ico">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/>
      </svg>
    </div>
    <p class="modal-title">Delete Reseller?</p>
    <p class="modal-desc">Permanently remove <span class="modal-name" id="delName"></span>? Their API key will stop working immediately and this cannot be undone.</p>
    <div class="modal-btns">
      <button class="btn-cancel" onclick="closeModal()">Cancel</button>
      <a href="#" id="delBtn" class="btn-del-confirm">Delete</a>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div class="edit-modal-ov" id="editModal">
  <div class="edit-modal">
    <p class="edit-modal-title" id="edit-title">Edit Reseller</p>
    <div class="edit-tabs">
      <div class="edit-tab active" onclick="switchTab('credits')">Credits</div>
      <div class="edit-tab" onclick="switchTab('expiry')">Expiry</div>
    </div>
    <div class="edit-section show" id="tab-credits">
      <form method="POST">
        <input type="hidden" name="reseller_id" id="edit-id-credits">
        <div class="edit-row">
          <select name="credit_type">
            <option value="add">Add</option>
            <option value="set">Set</option>
          </select>
          <input type="number" name="credit_amount" class="form-input" placeholder="Amount" min="1" required>
        </div>
        <button type="submit" name="update_credits" class="btn-save">Save Credits</button>
      </form>
    </div>
    <div class="edit-section" id="tab-expiry">
      <form method="POST">
        <input type="hidden" name="reseller_id" id="edit-id-expiry">
        <div class="edit-row">
          <select name="expiry_type" id="expiry-type-sel" onchange="toggleExpiryInput(this.value)">
            <option value="extend">Extend (days)</option>
            <option value="set">Set Date</option>
          </select>
          <input type="number" name="expiry_value" id="expiry-days-input" class="form-input" placeholder="Days" min="1">
          <input type="date" name="expiry_value" id="expiry-date-input" class="form-input" style="display:none">
        </div>
        <button type="submit" name="update_expiry" class="btn-save">Save Expiry</button>
      </form>
    </div>
    <button class="edit-cancel" onclick="closeEditModal()">Cancel</button>
  </div>
</div>

<!-- ── Settings Modal ── -->
<div class="settings-modal-ov" id="settingsModal">
  <div class="settings-modal">
    <div class="sm-header">
      <div class="sm-ico">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 010 14.14M4.93 4.93a10 10 0 000 14.14"/>
          <path d="M12 2v2M12 20v2M2 12h2M20 12h2"/>
        </svg>
      </div>
      <div>
        <div class="sm-title">API Settings</div>
        <div class="sm-sub">Change main provider key and reseller key prefix</div>
      </div>
    </div>

    <div class="sm-warn">
  <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
    <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
    <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
  </svg>
  <strong>Note:</strong> Old Reseller Key will still work. 
</div>

    <form method="POST" id="settingsForm">
      <div class="sm-section">
        <div class="sm-section-label">Main Provider API Key</div>
        <div class="sm-input-wrap">
          <input type="text" name="main_api_key" id="sm-api-key" class="sm-input"
                 value="<?= htmlspecialchars($MAIN_KEY) ?>"
                 placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" required>
        </div>
        <div class="sm-hint">This key is used to make calls to the main provider API.</div>
      </div>

      <hr class="sm-divider">

      <div class="sm-section">
        <div class="sm-section-label">Reseller Key Prefix</div>
        <div class="sm-prefix-row">
          <input type="text" name="api_prefix" id="sm-prefix" class="sm-input"
                 value="<?= htmlspecialchars($API_PREFIX) ?>"
                 placeholder="GH" maxlength="8"
                 oninput="updatePrefixPreview(this.value)"
                 style="max-width:120px;text-transform:uppercase">
          <div class="prefix-sample" id="prefix-sample">
            Preview: <strong id="prefix-preview"><?= htmlspecialchars($API_PREFIX) ?></strong>-A3F92BC1D4E78F20
          </div>
        </div>
        <div class="sm-hint">Only A-Z and 0-9 allowed. Max 8 characters.</div>
      </div>

      <div class="sm-btns">
        <button type="button" class="edit-cancel" onclick="closeSettingsModal()" style="flex:1;margin:0">Cancel</button>
        <button type="submit" name="update_settings" class="btn-save" style="flex:2">💾 Save Settings</button>
      </div>
    </form>
  </div>
</div>

<!-- Nav -->
<nav>
  <div class="wrap nav-inner">
    <a class="bw" href="admin.php">Rx<span>Hoster</span></a>
    <div class="nav-r">
      <span class="badge">Admin</span>
      <button class="btn-out" onclick="openSettingsModal()" style="border-color:var(--warn);color:var(--warn)">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="3"/>
          <path d="M19.07 4.93a10 10 0 010 14.14M4.93 4.93a10 10 0 000 14.14"/>
        </svg>
        API Settings
      </button>
      <a href="?logout=1" class="btn-out">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/>
        </svg>
        Logout
      </a>
    </div>
  </div>
</nav>

<div class="wrap">
  <div class="ph">
    <h1>Dashboard</h1>
    <p>Monitor your main API quota and manage reseller access keys</p>
  </div>

  <?php if (isset($_GET['msg'])): ?>
  <div class="toast">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
    </svg>
    <?php echo htmlspecialchars($_GET['msg']); ?>
  </div>
  <?php endif; ?>

  <!-- MAIN PROVIDER API CARD -->
  <div class="main-card">
    <div class="mc-top">
      <div class="mc-left">
        <span class="mc-title">Main Provider API</span>
        <span class="mc-key" onclick="openSettingsModal()" title="Click to change API key">
          <?= htmlspecialchars(substr($MAIN_KEY, 0, 8)) ?>…
        </span>
        <span style="font-size:11px;color:var(--muted);background:var(--bg);border:1px solid var(--border);padding:2px 8px;border-radius:100px;">
          Prefix: <strong style="color:var(--accent)"><?= htmlspecialchars($API_PREFIX) ?></strong>
        </span>
      </div>
      <div style="display:flex;align-items:center;gap:8px">
        <button onclick="openSettingsModal()" style="font-size:12px;padding:7px 14px;border:1px solid #f59e0b;background:#f59e0b;color:#fff;border-radius:8px;cursor:pointer;font-family:inherit;font-weight:700;transition:all .15s;display:flex;align-items:center;gap:5px" onmouseover="this.style.background='#d97706';this.style.borderColor='#d97706'" onmouseout="this.style.background='#f59e0b';this.style.borderColor='#f59e0b'">⚙️ Change API</button>
        <div class="mc-pill" id="mc-pill">
          <span class="mc-dot" id="mc-dot"></span>
          <span id="mc-status">Checking…</span>
        </div>
      </div>
    </div>

    <div class="mc-stats">
      <div class="mc-stat" id="stat-total">
        <div class="mc-s-label">Total Credits</div>
        <div class="mc-s-val dim" id="mc-total">—</div>
        <div class="mc-s-sub">from provider</div>
      </div>
      <div class="mc-stat" id="stat-pused">
        <div class="mc-s-label">Provider Used</div>
        <div class="mc-s-val dim" id="mc-pused">—</div>
        <div class="mc-s-sub">Consumed by API This Month</div>
      </div>
      <div class="mc-stat warn-stat" id="stat-alloc">
        <div class="mc-s-label">Allocated</div>
        <div class="mc-s-val" id="mc-alloc"><?= number_format($total_allocated) ?></div>
        <div class="mc-s-sub"><?= count($users) ?> reseller<?= count($users) != 1 ? 's' : '' ?></div>
      </div>
      <div class="mc-stat ok-stat" id="stat-remain">
        <div class="mc-s-label">Remaining</div>
        <div class="mc-s-val dim" id="mc-remain">—</div>
        <div class="mc-s-sub">available to give</div>
      </div>
      <div class="mc-stat" id="stat-exp">
        <div class="mc-s-label">Expires</div>
        <div class="mc-s-val sm dim" id="mc-exp">—</div>
        <div class="mc-s-sub" id="mc-exp-diff">—</div>
      </div>
    </div>

    <div class="mc-progress">
      <div class="mc-prog-top">
        <span>Allocation Usage <span style="color:var(--muted2);font-weight:400">(allocated ÷ total)</span></span>
        <span id="mc-pct" style="font-weight:700;color:var(--muted2)">—</span>
      </div>
      <div class="mc-bar-bg">
        <div class="mc-bar-fill" id="mc-bar"></div>
      </div>
    </div>
  </div>

  <!-- MAIN GRID -->
  <div class="main-grid">
    <!-- Add Reseller -->
    <div>
      <div class="add-card">
        <div class="add-card-head">
          <div class="add-card-title">Add New Reseller</div>
        </div>
        <div class="add-card-body">
          <form method="POST" id="addForm">
            <div class="form-group">
              <label class="form-label">Reseller Name</label>
              <input type="text" name="name" class="form-input" placeholder="Enter name" required>
            </div>
            <div class="form-group">
              <label class="form-label">Credits to Allocate</label>
              <input type="number" name="credits" id="credits-input" class="form-input" placeholder="e.g. 100" required min="1">
              <div class="credit-avail-hint">Available: <strong id="hint-remain">loading…</strong></div>
              <div class="credit-warn-box" id="credit-warn">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                Not enough remaining credits!
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Source API Key</label>
              <div class="input-locked">
                <span class="input-locked-val"><?= htmlspecialchars($MAIN_KEY) ?></span>
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                  <path d="M7 11V7a5 5 0 0110 0v4"/>
                </svg>
              </div>
              <div class="key-preview">Key format: <span id="prefix-live"><?= htmlspecialchars($API_PREFIX) ?></span>-XXXXXXXXXXXXXXXX</div>
            </div>
            <button type="submit" name="add_user" class="btn-add" id="addBtn">Generate Access Key</button>
          </form>
        </div>
      </div>
    </div>

    <!-- Resellers -->
    <div>
      <div class="sec-label">
        <span>Active Resellers</span>
        <span class="cnt-badge"><?= count($users) ?></span>
      </div>
      <div class="r-grid">
        <?php if (empty($users)): ?>
        <div class="empty">
          <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>
            <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
          </svg>
          <p>No resellers yet. Create one to get started.</p>
        </div>
        <?php else: ?>
        <?php foreach ($users as $u): ?>
        <div class="rc">
          <div class="rc-head">
            <div style="display:flex;align-items:center;gap:10px">
              <div class="rc-av"><?= strtoupper(substr($u['username'], 0, 1)) ?></div>
              <div>
                <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                  <span class="rc-name"><?= htmlspecialchars($u['username']) ?></span>
                  <span class="status-badge <?= ($u['status'] ?? 1) ? 'active' : 'inactive' ?>">
                    <?= ($u['status'] ?? 1) ? 'Active' : 'Inactive' ?>
                  </span>
                </div>
                <div class="rc-id">#<?= $u['id'] ?></div>
              </div>
            </div>
            <button class="btn-del"
              onclick="openModal(<?= $u['id'] ?>,'<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')"
              title="Delete">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/>
                <path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/>
              </svg>
            </button>
          </div>

          <div class="tok-box">
            <span class="tok-val"><?= htmlspecialchars($u['api_token']) ?></span>
            <button class="btn-cp" onclick="cpKey('<?= htmlspecialchars($u['api_token'], ENT_QUOTES) ?>',this)" title="Copy">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <rect x="9" y="9" width="13" height="13" rx="2"/>
                <path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/>
              </svg>
            </button>
          </div>

          <div class="rc-stats">
            <div class="sp"><div class="sp-label">Allocated</div><div class="sp-val" id="cr-<?= $u['id'] ?>"><?= number_format($u['credits']) ?></div></div>
            <div class="sp"><div class="sp-label">Used</div><div class="sp-val" id="us-<?= $u['id'] ?>"><?= number_format($u['used_requests']) ?></div></div>
          </div>

          <div class="api-inf">
            <div class="ai-row">
              <span class="ai-lbl"><span class="sdot" id="dot-<?= $u['id'] ?>"></span> Status</span>
              <span class="ai-val loading" id="rs-<?= $u['id'] ?>">Checking…</span>
            </div>
            <div class="ai-row">
              <span class="ai-lbl">Live Credits</span>
              <span class="ai-val" id="lc-<?= $u['id'] ?>">—</span>
            </div>
            <div class="ai-row">
              <span class="ai-lbl">Expiry</span>
              <span id="ex-<?= $u['id'] ?>">—</span>
            </div>
          </div>

          <div class="rc-actions">
            <button class="btn-action"
              onclick="openEditModal(<?= $u['id'] ?>,'<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')">
              ✏️ Edit
            </button>
            <a href="?toggle=<?= $u['id'] ?>" class="btn-action <?= ($u['status'] ?? 1) ? 'danger' : 'success' ?>">
              <?= ($u['status'] ?? 1) ? '🔴 Deactivate' : '🟢 Activate' ?>
            </a>
            <a href="?regen=<?= $u['id'] ?>"
              onclick="return confirm('API key regenerate করবেন? পুরনো key কাজ করবে না।')"
              class="btn-action warn">
              🔄 New Key
            </a>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
/* ── Delete Modal ── */
function openModal(id, name) {
  document.getElementById('delName').textContent = name;
  document.getElementById('delBtn').href = '?del=' + id;
  document.getElementById('delModal').classList.add('show');
}
function closeModal() { document.getElementById('delModal').classList.remove('show'); }
document.getElementById('delModal').addEventListener('click', function(e){ if(e.target===this) closeModal(); });

/* ── Edit Modal ── */
function openEditModal(id, name) {
  document.getElementById('edit-title').textContent = 'Edit — ' + name;
  document.getElementById('edit-id-credits').value  = id;
  document.getElementById('edit-id-expiry').value   = id;
  switchTab('credits');
  document.getElementById('editModal').classList.add('show');
}
function closeEditModal() { document.getElementById('editModal').classList.remove('show'); }
document.getElementById('editModal').addEventListener('click', function(e){ if(e.target===this) closeEditModal(); });

/* ── Settings Modal ── */
function openSettingsModal()  { document.getElementById('settingsModal').classList.add('show'); }
function closeSettingsModal() { document.getElementById('settingsModal').classList.remove('show'); }
document.getElementById('settingsModal').addEventListener('click', function(e){ if(e.target===this) closeSettingsModal(); });

/* ── Prefix preview live update ── */
function updatePrefixPreview(val) {
  const clean = val.toUpperCase().replace(/[^A-Z0-9]/g,'');
  document.getElementById('prefix-preview').textContent = clean || 'GH';
  document.getElementById('sm-prefix').value = clean;
  if (document.getElementById('prefix-live'))
    document.getElementById('prefix-live').textContent = clean || 'GH';
}

/* ── Tabs ── */
function switchTab(tab) {
  document.querySelectorAll('.edit-tab').forEach((t, i) => {
    t.classList.toggle('active', (i===0 && tab==='credits') || (i===1 && tab==='expiry'));
  });
  document.getElementById('tab-credits').classList.toggle('show', tab==='credits');
  document.getElementById('tab-expiry').classList.toggle('show',  tab==='expiry');
}

/* ── Expiry input toggle ── */
function toggleExpiryInput(type) {
  document.getElementById('expiry-days-input').style.display = type==='extend' ? '' : 'none';
  document.getElementById('expiry-date-input').style.display = type==='set'    ? '' : 'none';
}

/* ── Copy ── */
function cpKey(key, btn) {
  navigator.clipboard.writeText(key).then(() => {
    const orig = btn.innerHTML;
    btn.innerHTML = `<svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>`;
    btn.style.color = 'var(--success)';
    setTimeout(() => { btn.innerHTML = orig; btn.style.color = ''; }, 1600);
  });
}

/* ── Helpers ── */
function fmtDate(s) {
  if (!s) return '—';
  return new Date(s.replace(' ','T')).toLocaleDateString('en-GB', {day:'2-digit',month:'short',year:'numeric'});
}
function daysLeft(s) {
  if (!s) return null;
  return Math.round((new Date(s.replace(' ','T')) - new Date()) / 86400000);
}
function fmtExpiry(dateStr) {
  if (!dateStr) return '<span style="color:var(--muted2);font-size:11px">—</span>';
  const diff = daysLeft(dateStr);
  const fmt  = fmtDate(dateStr);
  let cls = 'ok', label = fmt;
  if (diff < 0)        { cls = 'exp';  label = 'Expired · ' + fmt; }
  else if (diff === 0) { cls = 'warn'; label = 'Expires today'; }
  else if (diff <= 7)  { cls = 'warn'; label = diff + 'd left · ' + fmt; }
  return `<span class="exp-badge ${cls}">${label}</span>`;
}

/* ── Credit tracking ── */
let mainTotalCredits = 0;
const totalAllocated = <?= (int)$total_allocated ?>;

/* ── Main API Poll ── */
async function fetchMain() {
  const pill    = document.getElementById('mc-pill');
  const dot     = document.getElementById('mc-dot');
  const stxt    = document.getElementById('mc-status');
  const totalEl = document.getElementById('mc-total');
  const pusedEl = document.getElementById('mc-pused');
  const allocEl = document.getElementById('mc-alloc');
  const remEl   = document.getElementById('mc-remain');
  const expEl   = document.getElementById('mc-exp');
  const diffEl  = document.getElementById('mc-exp-diff');
  const pctEl   = document.getElementById('mc-pct');
  const barEl   = document.getElementById('mc-bar');
  const hintEl  = document.getElementById('hint-remain');
  try {
    const resp = await fetch('api_proxy.php?type=main&key=<?= urlencode($MAIN_KEY) ?>');
    if (!resp.ok) throw new Error('Proxy error ' + resp.status);
    const data = await resp.json();
    if (data.status === 'success') {
      pill.className = 'mc-pill ok'; dot.className = 'mc-dot ok'; stxt.textContent = 'Online';
      const avail = Number(data.available_credits ?? 0);
      const pUsed = Number(data.Used_credits ?? 0);
      const total = Number(data.total_credits ?? (avail + pUsed));
      mainTotalCredits = avail;
      const remaining = avail - totalAllocated;
      totalEl.className = 'mc-s-val'; totalEl.textContent = total.toLocaleString();
      pusedEl.className = 'mc-s-val'; pusedEl.textContent = pUsed.toLocaleString();
      allocEl.textContent = totalAllocated.toLocaleString();
      remEl.className = 'mc-s-val';
      remEl.textContent = remaining >= 0 ? remaining.toLocaleString() : '0';
      if (remaining <= 0) {
        document.getElementById('stat-remain').style.background  = 'var(--danger-light)';
        document.getElementById('stat-remain').style.borderColor = 'var(--danger-border)';
        remEl.style.color = 'var(--danger)';
      } else {
        document.getElementById('stat-remain').style.background  = '';
        document.getElementById('stat-remain').style.borderColor = '';
        remEl.style.color = 'var(--success)';
      }
      expEl.className = 'mc-s-val sm'; expEl.textContent = fmtDate(data.expiry_date);
      const dl = daysLeft(data.expiry_date);
      diffEl.textContent = dl === null ? '—' : dl < 0 ? 'Expired!' : dl === 0 ? 'Expires today' : dl + ' days left';
      diffEl.style.color = dl !== null && dl <= 7 ? 'var(--warn)' : '';
      const pct = total > 0 ? Math.round((totalAllocated / total) * 100) : 0;
      pctEl.textContent = pct + '%';
      pctEl.style.color = pct >= 90 ? 'var(--danger)' : pct >= 70 ? 'var(--warn)' : 'var(--accent)';
      barEl.style.width = Math.min(pct, 100) + '%';
      barEl.className   = 'mc-bar-fill' + (pct >= 90 ? ' danger' : pct >= 70 ? ' warn' : '');
      if (hintEl) {
        hintEl.textContent = remaining >= 0 ? remaining.toLocaleString() + ' credits' : '0 credits';
        hintEl.style.color = remaining <= 0 ? 'var(--danger)' : 'var(--accent)';
      }
    } else { throw new Error(data.message || 'API error'); }
  } catch(e) {
    pill.className = 'mc-pill err'; dot.className = 'mc-dot err'; stxt.textContent = e.message;
    ['mc-total','mc-pused','mc-remain','mc-exp'].forEach(id => {
      const el = document.getElementById(id);
      if (el) { el.textContent = '—'; }
    });
    if (hintEl) hintEl.textContent = 'unavailable';
  }
}

/* ── Reseller Poll ── */
const resellers = <?= json_encode(array_map(fn($u) => ['id'=>$u['id'],'token'=>$u['api_token']], $users)) ?>;
async function fetchReseller(r) {
  const dot  = document.getElementById('dot-' + r.id);
  const stEl = document.getElementById('rs-'  + r.id);
  const lcEl = document.getElementById('lc-'  + r.id);
  const exEl = document.getElementById('ex-'  + r.id);
  const crEl = document.getElementById('cr-'  + r.id);
  const usEl = document.getElementById('us-'  + r.id);
  try {
    const resp = await fetch('api_proxy.php?type=reseller&key=' + encodeURIComponent(r.token));
    if (!resp.ok) throw new Error('proxy ' + resp.status);
    const d = await resp.json();
    if (d.status === 'success') {
      dot.className  = 'sdot ok';
      stEl.className = 'ai-val ok'; stEl.textContent = 'Active';
      if (lcEl) lcEl.textContent = d.credits !== undefined ? Number(d.credits).toLocaleString() : '—';
      if (exEl) exEl.innerHTML   = fmtExpiry(d.expiry);
      if (crEl && d.credits !== undefined) crEl.textContent = Number(d.credits).toLocaleString();
      if (usEl && d.used    !== undefined) usEl.textContent  = Number(d.used).toLocaleString();
    } else { throw new Error(d.message || d.status || 'invalid'); }
  } catch(e) {
    if (dot)  dot.className  = 'sdot err';
    if (stEl) { stEl.className = 'ai-val err'; stEl.textContent = e.message; }
  }
}

/* ── Credit warn ── */
const credInput = document.getElementById('credits-input');
const warnBox   = document.getElementById('credit-warn');
const addBtn    = document.getElementById('addBtn');
if (credInput) {
  credInput.addEventListener('input', function() {
    const val = parseInt(this.value) || 0;
    const rem = mainTotalCredits - totalAllocated;
    if (val > rem && rem >= 0 && mainTotalCredits > 0) {
      warnBox.style.display = 'flex'; addBtn.disabled = true;
    } else {
      warnBox.style.display = 'none'; addBtn.disabled = false;
    }
  });
}

function pollAll() { fetchMain(); resellers.forEach(r => fetchReseller(r)); }
pollAll();
setInterval(pollAll, 10000);
</script>
</body>
</html>