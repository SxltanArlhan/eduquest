<?php
session_start();
require_once __DIR__ . '/users_lib.php';
require_once __DIR__ . '/config.php';

// บูทสแตร็ปแอดมินครั้งแรก (ถ้าอยากปิด ให้คอมเมนต์บรรทัดนี้)
$notice = ensure_bootstrap_admin('admin','admin123') ? 'สร้างแอดมินเริ่มต้น: admin / admin123' : '';

if (isset($_SESSION['user'])) { header('Location: index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $u = users_find_by_username($username);
    if ($u && password_verify($password, $u['password'])) {
        $_SESSION['user'] = [
            'username' => $u['username'],
            'fullname' => $u['fullname'] ?? $u['username'],
            'role'     => $u['role'] ?? 'user'
        ];
        // เคลียร์สถานะยืนยันแอดมินทุกครั้งที่ล็อกอินใหม่
        unset($_SESSION['admin_verified']);
        header('Location: index.php'); exit;
    } else {
        $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    }
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>เข้าสู่ระบบ</title>
<style>
  :root{
    --bg1:#0f1220; --bg2:#151935; --panel:#171a2b; --panel-2:#1b203d;
    --text:#e7e7f0; --muted:#a3a7c2; --accent:#8c4fff; --accent-2:#6f3ffb; --error:#592f2f; --info:#2b3e59;
    --border:#2a2f52;
  }
  *{box-sizing:border-box}
  html,body{height:100%}
  body{
    margin:0; color:var(--text); font-family:'Prompt',system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
    background: radial-gradient(1200px 500px at -10% -10%, rgba(140,79,255,.14), transparent 40%),
                radial-gradient(900px 400px at 120% -10%, rgba(79,180,255,.12), transparent 45%),
                linear-gradient(180deg,var(--bg1),var(--bg2));
    display:flex; align-items:center; justify-content:center; padding:24px;
  }

  .card{
    width: min(420px, 92vw);
    border-radius: 22px;
    background:
      radial-gradient(800px 200px at -20% -30%, rgba(140,79,255,.12), transparent 40%),
      radial-gradient(600px 240px at 120% -20%, rgba(79,180,255,.10), transparent 45%),
      rgba(23,26,43,.78);
    backdrop-filter: blur(10px);
    border: 1px solid var(--border);
    box-shadow: 0 30px 80px rgba(0,0,0,.45), inset 0 1px 0 rgba(255,255,255,.04);
    padding: 28px 26px;
    animation: pop .25s ease-out;
  }
  @keyframes pop { from{transform:translateY(6px); opacity:.0} to{transform:translateY(0); opacity:1} }

  .brand{ display:flex; align-items:center; gap:12px; margin-bottom:10px; }
  .logo{
    width:42px; height:42px; border-radius:50%;
    background: conic-gradient(from 210deg, var(--accent), var(--accent-2));
    box-shadow: 0 0 0 6px rgba(140,79,255,.15);
  }
  h2{margin:0; font-size:1.6rem; letter-spacing:.2px}
  .sub{color:var(--muted); font-size:.95rem; margin-bottom:14px}

  .alert{
    padding:.75rem 1rem; border-radius:12px; margin:.55rem 0;
    border:1px solid rgba(255,255,255,.06);
  }
  .alert.error{background:var(--error)}
  .alert.notice{background:var(--info)}

  .field{ position:relative; margin:.55rem 0; }
  .label{font-size:.95rem; color:var(--muted); margin:6px 2px}
  .input{
    width:100%; border:1px solid var(--border); border-radius:12px;
    background:#1a1f38; color:var(--text);
    padding:0.9rem 2.8rem 0.9rem 1rem; /* เผื่อที่ด้านขวาให้ไอคอน */
    outline:none; transition:.18s;
  }
  .input:focus{border-color:#7e86ff; box-shadow:0 0 0 4px rgba(126,134,255,.14)}

  /* ปุ่มไอคอน “ตา” ให้อยู่ในช่องพอดี */
  .toggle{
    position:absolute; right:10px; top:50%; transform:translateY(-50%);
    background:transparent; border:none; color:#c9cdf3; cursor:pointer;
    padding:4px; border-radius:8px; display:flex; align-items:center; justify-content:center;
    height:32px; width:32px;
  }
  .toggle:hover{background:rgba(255,255,255,.06)}
  .toggle svg{ width:20px; height:20px; display:block; }

  .caps{font-size:.85rem; color:#ffbf69; margin-top:4px; display:none}

  .btn{
    width:100%; margin-top:.6rem; border:none; cursor:pointer; font-weight:700;
    padding:0.9rem 1rem; border-radius:12px; color:#fff;
    background: linear-gradient(180deg,var(--accent),var(--accent-2));
    transition:filter .15s, transform .15s;
  }
  .btn:hover{filter:brightness(1.06); transform:translateY(-1px)}
  .btn:active{transform:translateY(0)}

  .row-actions{display:flex; justify-content:space-between; align-items:center; margin-top:6px}
  .remember{display:flex; align-items:center; gap:8px; color:var(--muted); font-size:.95rem}
  .checkbox{appearance:none; width:18px; height:18px; border-radius:5px; border:1px solid var(--border); background:#1a1f38; position:relative; cursor:pointer}
  .checkbox:checked{background:linear-gradient(180deg,var(--accent),var(--accent-2)); border-color:transparent}
  .checkbox:checked::after{content:""; position:absolute; inset:3px; background:#fff; border-radius:3px; opacity:.9}

  .link{color:#bfc8ff; text-decoration:none}
  .link:hover{text-decoration:underline}
  .help{margin-top:10px; font-size:.9rem; color:var(--muted)}
</style>
</head>
<body>
  <div class="card">
    <div class="brand">
      <div class="logo"></div>
      <div>
        <h2>เข้าสู่ระบบ</h2>
        <div class="sub">ยินดีต้อนรับกลับมา 👋</div>
      </div>
    </div>

    <?php if($notice): ?>
      <div class="alert notice"><?= htmlspecialchars($notice) ?></div>
    <?php endif; ?>
    <?php if($error): ?>
      <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off" id="loginForm">
      <div class="field">
        <div class="label">ชื่อผู้ใช้</div>
        <input class="input" name="username" placeholder="เช่น student01" required
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
      </div>

      <div class="field">
        <div class="label">รหัสผ่าน</div>
        <input class="input" id="password" name="password" type="password" placeholder="••••••••" required>
        <button type="button" class="toggle" aria-label="สลับการแสดงรหัสผ่าน" id="togglePwd">
          <!-- eye icon -->
          <svg id="eye" viewBox="0 0 24 24" fill="none">
            <path d="M12 5C7 5 2.73 8.11 1 12c1.73 3.89 6 7 11 7s9.27-3.11 11-7c-1.73-3.89-6-7-11-7Z" stroke="currentColor" stroke-width="1.6"/>
            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6"/>
          </svg>
        </button>
        <div class="caps" id="capsHint">Caps Lock เปิดอยู่</div>
      </div>

      <div class="row-actions">
        <label class="remember">
          <input type="checkbox" class="checkbox" id="remember">
          จำฉันไว้
        </label>
        <a class="link" href="register.php">สมัครสมาชิก</a>
      </div>

      <button class="btn" type="submit">เข้าสู่ระบบ</button>
      <div class="help">* หากลืมรหัส ให้ติดต่อผู้ดูแลระบบ</div>
    </form>
  </div>

<script>
  // toggle password visibility
  const pwd = document.getElementById('password');
  const toggle = document.getElementById('togglePwd');
  let shown = false;
  toggle.addEventListener('click', () => {
    shown = !shown;
    pwd.type = shown ? 'text' : 'password';
    toggle.setAttribute('aria-pressed', shown ? 'true' : 'false');
    // swap icon (eye / eye-off)
    toggle.innerHTML = shown
      ? `<svg viewBox="0 0 24 24" fill="none">
           <path d="M3 3l18 18" stroke="currentColor" stroke-width="1.8"/>
           <path d="M10.6 7.2A8.6 8.6 0 0 1 12 7c5 0 9.27 3.11 11 7a12.8 12.8 0 0 1-3.14 4.23" stroke="currentColor" stroke-width="1.6"/>
           <path d="M6.8 6.8A12.2 12.2 0 0 0 1 12c1.73 3.89 6 7 11 7 1.07 0 2.1-.14 3.07-.41" stroke="currentColor" stroke-width="1.6"/>
           <path d="M9.88 9.88A3 3 0 0 0 12 15a3 3 0 0 0 2.12-.88" stroke="currentColor" stroke-width="1.6"/>
         </svg>`
      : `<svg viewBox="0 0 24 24" fill="none">
           <path d="M12 5C7 5 2.73 8.11 1 12c1.73 3.89 6 7 11 7s9.27-3.11 11-7c-1.73-3.89-6-7-11-7Z" stroke="currentColor" stroke-width="1.6"/>
           <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6"/>
         </svg>`;
  });

  // caps lock detection
  const capsHint = document.getElementById('capsHint');
  function detectCaps(e){
    const caps = e.getModifierState && e.getModifierState('CapsLock');
    capsHint.style.display = caps ? 'block' : 'none';
  }
  pwd.addEventListener('keydown', detectCaps);
  pwd.addEventListener('keyup', detectCaps);

  // (optional) fake remember me for UI only
  const form = document.getElementById('loginForm');
  form.addEventListener('submit', ()=>{
    if (document.getElementById('remember').checked) {
      try { localStorage.setItem('remember_username', document.querySelector('[name="username"]').value); } catch(e){}
    } else {
      try { localStorage.removeItem('remember_username'); } catch(e){}
    }
  });
  // preload remembered username
  try {
    const remembered = localStorage.getItem('remember_username');
    if (remembered && !document.querySelector('[name="username"]').value) {
      document.querySelector('[name="username"]').value = remembered;
    }
  } catch(e){}
</script>
</body>
</html>
