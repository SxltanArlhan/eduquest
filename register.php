<?php
session_start();
require_once __DIR__ . '/users_lib.php';
require_once __DIR__ . '/config.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname   = trim($_POST['fullname'] ?? '');
    $username   = trim($_POST['username'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirm']  ?? '';
    $want_admin = isset($_POST['want_admin']);
    $invite     = trim($_POST['admin_invite'] ?? '');

    if ($fullname==='' || $username==='' || $password==='') {
        $errors[]='กรุณากรอกข้อมูลให้ครบ';
    }
    if ($password !== $confirm) {
        $errors[]='รหัสผ่านไม่ตรงกัน';
    }
    if (users_find_by_username($username)) {
        $errors[]='มีผู้ใช้นี้แล้ว';
    }

    $role = 'user';
    if ($want_admin) {
        if ($invite === ADMIN_INVITE_CODE) {
            $role = 'admin';
        } else {
            $errors[] = 'รหัสเชิญแอดมินไม่ถูกต้อง';
        }
    }

    if (!$errors) {
        $user = [
            'fullname'   => htmlspecialchars($fullname, ENT_QUOTES, 'UTF-8'),
            'username'   => htmlspecialchars($username, ENT_QUOTES, 'UTF-8'),
            'password'   => password_hash($password, PASSWORD_DEFAULT),
            'role'       => $role,
            'created_at' => date('c'),
        ];
        users_upsert($user);
        $_SESSION['user'] = ['username'=>$user['username'], 'fullname'=>$user['fullname'], 'role'=>$role];
        header('Location: index.php'); exit;
    }
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>สมัครสมาชิก</title>
<style>
  :root{
    --bg1:#0f1220; --bg2:#151935; --panel:#171a2b; --panel-2:#1b203d;
    --text:#e7e7f0; --muted:#a3a7c2; --accent:#8c4fff; --accent-2:#6f3ffb;
    --error:#592f2f; --border:#2a2f52; --chip:#30354f; --info:#2b3e59;
  }
  *{box-sizing:border-box}
  html,body{height:100%}
  body{
    margin:0; color:var(--text); font-family:'Prompt',system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
    background:
      radial-gradient(1200px 500px at -10% -10%, rgba(140,79,255,.14), transparent 40%),
      radial-gradient(900px 420px at 120% -10%, rgba(79,180,255,.12), transparent 45%),
      linear-gradient(180deg,var(--bg1),var(--bg2));
    display:flex; align-items:center; justify-content:center; padding:24px;
  }
  .card{
    width:min(460px,92vw);
    border-radius:22px;
    background:
      radial-gradient(800px 220px at -20% -30%, rgba(140,79,255,.12), transparent 40%),
      radial-gradient(600px 260px at 120% -20%, rgba(79,180,255,.10), transparent 45%),
      rgba(23,26,43,.78);
    backdrop-filter: blur(10px);
    border:1px solid var(--border);
    box-shadow:0 30px 80px rgba(0,0,0,.45), inset 0 1px 0 rgba(255,255,255,.04);
    padding:28px 26px;
    animation: pop .25s ease-out;
  }
  @keyframes pop{from{transform:translateY(6px);opacity:0}to{transform:translateY(0);opacity:1}}
  .brand{display:flex;align-items:center;gap:12px;margin-bottom:10px}
  .logo{width:44px;height:44px;border-radius:50%;
        background:conic-gradient(from 210deg,var(--accent),var(--accent-2));
        box-shadow:0 0 0 6px rgba(140,79,255,.15)}
  h2{margin:0;font-size:1.6rem}
  .sub{color:var(--muted);font-size:.95rem;margin-bottom:14px}

  .alert{padding:.75rem 1rem;border-radius:12px;margin:.55rem 0;border:1px solid rgba(255,255,255,.06)}
  .alert.error{background:var(--error)}
  .alert.info{background:var(--info)}

  .field{position:relative;margin:.55rem 0}
  .label{font-size:.95rem;color:var(--muted);margin:6px 2px}
  .input{
    width:100%;border:1px solid var(--border);border-radius:12px;background:#1a1f38;color:var(--text);
    padding:.9rem 1rem;outline:none;transition:.18s;
  }
  .input:focus{border-color:#7e86ff;box-shadow:0 0 0 4px rgba(126,134,255,.14)}
  /* ช่องรหัสผ่าน – เผื่อที่ให้ปุ่มตา */
  .input.has-toggle{padding-right:2.8rem}
  .toggle{
    position:absolute;right:10px;top:50%;transform:translateY(-50%);
    background:transparent;border:none;color:#c9cdf3;cursor:pointer;padding:4px;border-radius:8px;
    display:flex;align-items:center;justify-content:center;height:32px;width:32px;
  }
  .toggle:hover{background:rgba(255,255,255,.06)}
  .toggle svg{width:20px;height:20px;display:block}
  .caps{font-size:.85rem;color:#ffbf69;margin-top:4px;display:none}

  .row{display:grid;grid-template-columns:1fr 1fr;gap:.7rem}
  @media(max-width:520px){.row{grid-template-columns:1fr}}

  .inline{display:flex;gap:.6rem;align-items:center;margin-top:.25rem;color:var(--muted)}
  .checkbox{appearance:none;width:18px;height:18px;border-radius:5px;border:1px solid var(--border);
            background:#1a1f38;position:relative;cursor:pointer}
  .checkbox:checked{background:linear-gradient(180deg,var(--accent),var(--accent-2));border-color:transparent}
  .checkbox:checked::after{content:"";position:absolute;inset:3px;background:#fff;border-radius:3px;opacity:.9}

  .helper{color:var(--muted);font-size:.9rem}
  .btn{
    width:100%;margin-top:.6rem;border:none;cursor:pointer;font-weight:700;
    padding:.95rem 1rem;border-radius:12px;color:#fff;background:linear-gradient(180deg,var(--accent),var(--accent-2));
    transition:filter .15s, transform .15s;
  }
  .btn:hover{filter:brightness(1.06);transform:translateY(-1px)}
  .btn:active{transform:translateY(0)}

  .invite-wrap{display:none}
  .invite-wrap.show{display:block}
</style>
</head>
<body>
  <div class="card">
    <div class="brand">
      <div class="logo"></div>
      <div>
        <h2>สมัครสมาชิก</h2>
        <div class="sub">สร้างบัญชีใหม่เพื่อใช้งานระบบ</div>
      </div>
    </div>

    <?php foreach($errors as $e): ?>
      <div class="alert error"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <form method="post" autocomplete="off" id="regForm">
      <div class="field">
        <div class="label">ชื่อ–นามสกุล</div>
        <input class="input" name="fullname" placeholder="เช่น สมชาย ใจดี" required
               value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>">
      </div>

      <div class="field">
        <div class="label">ชื่อผู้ใช้</div>
        <input class="input" name="username" placeholder="เช่น student01" required
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
      </div>

      <div class="row">
        <div class="field">
          <div class="label">รหัสผ่าน</div>
          <input class="input has-toggle" id="pwd1" name="password" type="password" placeholder="••••••••" required>
          <button type="button" class="toggle" data-target="pwd1" aria-label="สลับการแสดงรหัสผ่าน">
            <svg viewBox="0 0 24 24" fill="none">
              <path d="M12 5C7 5 2.73 8.11 1 12c1.73 3.89 6 7 11 7s9.27-3.11 11-7c-1.73-3.89-6-7-11-7Z" stroke="currentColor" stroke-width="1.6"/>
              <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6"/>
            </svg>
          </button>
          <div class="caps" id="caps1">Caps Lock เปิดอยู่</div>
        </div>

        <div class="field">
          <div class="label">ยืนยันรหัสผ่าน</div>
          <input class="input has-toggle" id="pwd2" name="confirm" type="password" placeholder="••••••••" required>
          <button type="button" class="toggle" data-target="pwd2" aria-label="สลับการแสดงรหัสผ่าน">
            <svg viewBox="0 0 24 24" fill="none">
              <path d="M12 5C7 5 2.73 8.11 1 12c1.73 3.89 6 7 11 7s9.27-3.11 11-7c-1.73-3.89-6-7-11-7Z" stroke="currentColor" stroke-width="1.6"/>
              <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6"/>
            </svg>
          </button>
          <div class="caps" id="caps2">Caps Lock เปิดอยู่</div>
        </div>
      </div>

      <label class="inline">
        <input type="checkbox" class="checkbox" name="want_admin" id="wantAdmin" value="1" <?= isset($_POST['want_admin'])?'checked':'' ?>>
        สมัครเป็นแอดมิน
      </label>

      <div class="field invite-wrap <?= isset($_POST['want_admin']) ? 'show' : '' ?>" id="inviteWrap">
        <div class="label">รหัสเชิญแอดมิน</div>
        <input class="input" name="admin_invite" placeholder="ใส่รหัสเชิญจากผู้ดูแล"
               value="<?= htmlspecialchars($_POST['admin_invite'] ?? '') ?>">
        <div class="helper">* รับรหัสจากผู้ดูแลระบบเท่านั้น</div>
      </div>

      <button class="btn" type="submit">สร้างบัญชี</button>
    </form>
  </div>

<script>
  // toggle show/hide password (ทั้งสองช่อง)
  document.querySelectorAll('.toggle').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const id = btn.getAttribute('data-target');
      const input = document.getElementById(id);
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.setAttribute('aria-pressed', show ? 'true' : 'false');
      btn.innerHTML = show
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
  });

  // Caps Lock hint
  function capsHint(inputId, hintId){
    const input = document.getElementById(inputId);
    const hint  = document.getElementById(hintId);
    function detect(e){
      const caps = e.getModifierState && e.getModifierState('CapsLock');
      hint.style.display = caps ? 'block' : 'none';
    }
    input.addEventListener('keydown', detect);
    input.addEventListener('keyup', detect);
  }
  capsHint('pwd1','caps1'); capsHint('pwd2','caps2');

  // show/hide invite code
  const wantAdmin = document.getElementById('wantAdmin');
  const inviteWrap = document.getElementById('inviteWrap');
  function syncInvite(){ inviteWrap.classList.toggle('show', wantAdmin.checked); }
  wantAdmin.addEventListener('change', syncInvite);
  syncInvite();
</script>
</body>
</html>
