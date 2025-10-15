<?php
// ===== home.php =====
session_start();
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit; }

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/stats_utils.php';

$QUESTS_FILE = __DIR__ . '/quests.json';
$STATS_FILE  = __DIR__ . '/stats.json';
if (!file_exists($QUESTS_FILE)) file_put_contents($QUESTS_FILE, '[]');
if (!file_exists($STATS_FILE))  file_put_contents($STATS_FILE, '[]');

$user = $_SESSION['user'];
$username = $user['username'];
$fullname = $user['fullname'] ?? $username;

function json_read_local($p){ $d=@file_get_contents($p); return $d? (json_decode($d,true)?:[]) :[]; }
function json_write_local($p,$data){ file_put_contents($p, json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)); }

function is_allowed_upload($tmpPath, $origName, &$mimeOut) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $tmpPath);
    finfo_close($finfo);
    $mimeOut = $mime;
    $allowed = ['image/jpeg','image/png','image/gif','image/webp','application/pdf'];
    if (!in_array($mime, $allowed, true)) return false;
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $map = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp','pdf'=>'application/pdf'];
    return isset($map[$ext]) && $map[$ext]===$mime;
}

$errors=[]; $okmsg='';

// โหลด “สเตตัสปัจจุบันของฉัน” เพื่อเป็นรายการวิชาใน <select> (ใช้กรณีไม่มี localStorage)
$my_subjects = stats_get_for($username, $STATS_FILE);
$subject_names = array_map(fn($s)=>$s['name'], $my_subjects);

// ส่งเควส
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['create_quest'])) {
    $title   = trim($_POST['title']??'');
    $subject = count($subject_names) ? trim($_POST['subject_select'] ?? '') : trim($_POST['subject'] ?? '');
    $desc    = trim($_POST['description']??'');
    $delta   = null;

    if ($title==='')   $errors[]='กรุณากรอกชื่อเควส';
    if ($subject==='') $errors[]='กรุณาเลือก/กรอกวิชา';

    $savedFiles = [];
    if (!empty($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
        $n = count($_FILES['attachments']['name']);
        for($i=0;$i<$n;$i++){
            if ($_FILES['attachments']['error'][$i]===UPLOAD_ERR_NO_FILE) continue;
            if ($_FILES['attachments']['error'][$i]!==UPLOAD_ERR_OK) { $errors[]='อัปโหลดไฟล์ล้มเหลวบางไฟล์'; continue; }
            if ($_FILES['attachments']['size'][$i] > 8*1024*1024) { $errors[]='ไฟล์เกิน 8MB: '.htmlspecialchars($_FILES['attachments']['name'][$i]); continue; }
            $mime='';
            if (!is_allowed_upload($_FILES['attachments']['tmp_name'][$i], $_FILES['attachments']['name'][$i], $mime)) {
                $errors[]='ชนิดไฟล์ไม่รองรับ: '.htmlspecialchars($_FILES['attachments']['name'][$i]); continue;
            }
            $ext = strtolower(pathinfo($_FILES['attachments']['name'][$i], PATHINFO_EXTENSION));
            $safeBase = preg_replace('/[^a-zA-Z0-9_\-\.]+/','_', pathinfo($_FILES['attachments']['name'][$i], PATHINFO_FILENAME));
            $newName  = 'q_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'_'.$safeBase.'.'.$ext;
            $destPath = UPLOAD_DIR.'/'.$newName;
            if (move_uploaded_file($_FILES['attachments']['tmp_name'][$i], $destPath)) {
                $savedFiles[] = 'uploads/'.$newName;
            } else { $errors[]='ย้ายไฟล์ล้มเหลว: '.htmlspecialchars($_FILES['attachments']['name'][$i]); }
        }
    }

    if (!$errors) {
        $quests = json_read_local($QUESTS_FILE);
        $quests[] = [
            'id' => uniqid('q_', true),
            'username' => $username,
            'fullname' => $fullname,
            'title' => htmlspecialchars($title, ENT_QUOTES,'UTF-8'),
            'subject' => htmlspecialchars($subject, ENT_QUOTES,'UTF-8'),
            'description' => htmlspecialchars($desc, ENT_QUOTES,'UTF-8'),
            'delta' => $delta,
            'files' => $savedFiles,
            'status' => 'pending',
            'created_at' => date('c'),
            'reviewed_at' => null,
            'review_note' => ''
        ];
        json_write_local($QUESTS_FILE, $quests);
        $okmsg = 'ส่งเควสเรียบร้อย';
    }
}

// โหลดรายการเควสของฉัน
$my_quests = array_values(array_filter(json_read_local($QUESTS_FILE), fn($q)=>mb_strtolower($q['username'])===mb_strtolower($username)));
// เรียงลำดับใหม่ล่าสุดขึ้นก่อน
usort($my_quests, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));


// รวมแต้ม (delta) เฉพาะที่อนุมัติแล้ว → วิชา => แต้มรวม
$approved_quests = array_values(array_filter($my_quests, function($q){
  return ($q['status']??'')==='approved' && isset($q['delta']) && is_numeric($q['delta']);
}));
$DELTA_MAP = [];
foreach ($approved_quests as $q) {
  $name = $q['subject'];
  $d = (float)$q['delta'];
  if (!isset($DELTA_MAP[$name])) $DELTA_MAP[$name] = 0.0;
  $DELTA_MAP[$name] += $d;
}
$DELTA_MAP_JSON    = json_encode($DELTA_MAP, JSON_UNESCAPED_UNICODE);
$MY_SUBJECTS_JSON  = json_encode($my_subjects, JSON_UNESCAPED_UNICODE);

// Fallback กรณีไม่มี localStorage: ให้มี labels/scores เริ่มต้นอย่างน้อย 1 ชุด
$fallback_labels = json_encode(array_map(fn($s)=>$s['name'],$my_subjects), JSON_UNESCAPED_UNICODE);
$fallback_scores = json_encode(array_map(fn($s)=>intval($s['score']),$my_subjects));
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ส่งเควส - <?= htmlspecialchars($fullname) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<style>
:root{
  --bg:#0f1220; --panel:#171a2b; --panel-2:#1f2540; --accent:#3ba5b4; --accent-2:#2f8d9a;
  --text:#e7e7f0; --muted:#a3a7c2; --border:#2a2f52; --chip:#30354f;
  --std:#ffd84d; --std-fill:rgba(255,216,77,.14);
  --status-pending: #ffc107; --status-approved: #28a745; --status-rejected: #dc3545;
}
body{background:var(--bg);background-image:linear-gradient(180deg,#0f1220 0%,#141830 100%);color:var(--text);font-family:'Prompt',system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif}
.navbar{background:rgba(23,26,43,.75)!important;backdrop-filter:blur(10px);border-bottom:1px solid var(--border)}
.card{
  background: radial-gradient(1000px 350px at -10% -20%, rgba(59,165,180,.08), transparent 40%),
            radial-gradient(700px 260px at 120% -10%, rgba(79,180,255,.06), transparent 45%),
            var(--panel);
  border:1px solid var(--border); color:var(--text); box-shadow:0 20px 60px rgba(0,0,0,.35);
  transition: transform .3s ease, box-shadow .3s ease;
}
.form-control,.form-select,.btn{border-radius:12px;border:1px solid var(--border);background:#1a1f38;color:var(--text)}
.form-control:focus,.form-select:focus{border-color:#7e86ff;box-shadow:0 0 0 .25rem rgba(126,134,255,.15)}
.btn-primary{background:linear-gradient(180deg,var(--accent),var(--accent-2));border:none; transition: all .3s ease;}
.btn-primary:hover{transform: translateY(-2px); box-shadow: 0 8px 20px rgba(59, 165, 180, 0.25);}
.btn-outline-light { border-color: var(--border); transition: all .3s ease; }
.btn-outline-light:hover { background-color: rgba(255,255,255,0.1); border-color: #fff; transform: translateY(-2px); }

.badge.rounded-pill{border:1px solid var(--border);background:#30354f;font-weight:500; padding: .4em .8em;}
.badge.pending{background:rgba(255,193,7,.1); border-color:rgba(255,193,7,.5); color: #ffc107;}
.badge.approved{background:rgba(40,167,69,.1); border-color:rgba(40,167,69,.5); color: #28a745;}
.badge.rejected{background:rgba(220,53,69,.1); border-color:rgba(220,53,69,.5); color: #dc3545;}
.dropzone{
  margin-top:.25rem;border:2px dashed #3b3f64;background:#151a33;border-radius:14px;padding:2rem 1rem;text-align:center;color:var(--muted);cursor:pointer;
  transition: all .3s ease;
}
.dropzone.drag{border-color:#7e86ff;background:#151a33cc;transform: scale(1.02); box-shadow:0 0 0 3px rgba(126,134,255,.12) inset}
.chart-box{ position:relative; width:100%; height:340px; }
.chart-box canvas{ width:100% !important; height:100% !important; display:block; }
.smallmuted{color:var(--muted);font-size:.9rem}

/* NEW: File Preview */
#file-preview { margin-top: 1rem; display: flex; flex-wrap: wrap; gap: 1rem; }
.preview-item { position: relative; width: 100px; }
.preview-item img { width: 100%; height: 100px; object-fit: cover; border-radius: 10px; border: 1px solid var(--border); }
.preview-item .file-placeholder { width:100%; height: 100px; background: var(--panel-2); border-radius: 10px; border: 1px solid var(--border); display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 0.5rem; }
.preview-item .file-placeholder svg { width: 32px; height: 32px; margin-bottom: 0.5rem; }
.preview-item .file-name { font-size: 0.75rem; color: var(--muted); text-align: center; overflow-wrap: break-word; line-height: 1.2; margin-top: 0.3rem;}

/* NEW: Quest List Item */
.quest-item {
  background: var(--panel-2); border-left: 4px solid var(--border); padding: 1rem; border-radius: 8px;
  transition: transform .2s ease, box-shadow .2s ease;
}
.quest-item:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
.quest-item.pending  { border-left-color: var(--status-pending); }
.quest-item.approved { border-left-color: var(--status-approved); }
.quest-item.rejected { border-left-color: var(--status-rejected); }
.quest-item-header { display: flex; justify-content: space-between; align-items: center; gap: 1rem; }
.quest-item-title { font-weight: 600; font-size: 1.1rem; }
.quest-item-body { margin-top: .5rem; font-size: 0.95rem; color: var(--muted); }
.quest-item-files { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .75rem; }
.quest-item-files a.btn { font-size: .8rem; }

#character-card-container {
    transition: all .5s ease;
    opacity: 0;
    transform: translateY(20px);
}
#character-card-container.visible {
    opacity: 1;
    transform: translateY(0);
}
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark sticky-top">
  <div class="container-fluid">
    <span class="navbar-brand">📈 Quest & Status</span>
    <div class="ms-auto d-flex align-items-center gap-3">
        <span class="navbar-text d-none d-sm-block">สวัสดี, <strong><?= htmlspecialchars($fullname) ?></strong></span>
        <?php if (($user['role']??'user')==='admin'): ?>
          <a href="admin_quests.php" class="btn btn-sm btn-outline-light">👑 ผู้ดูแล</a>
        <?php endif; ?>
        <a href="index.php" class="btn btn-sm btn-outline-light">📊 หน้ากราฟ</a>
        <a href="logout.php" class="btn btn-sm btn-outline-danger">ออกจากระบบ</a>
    </div>
  </div>
</nav>

<div class="container my-4">
  <div class="row g-4">
    <div class="col-12 col-lg-6">
      <div class="card p-3 p-md-4 h-100">
        <h4 class="mb-3">📝 ส่งเควสเพื่อเพิ่มสเตตัส</h4>

        <?php foreach($errors as $e): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
        <?php if($okmsg): ?><div class="alert alert-success"><?= $okmsg ?></div><?php endif; ?>

        <form method="post" enctype="multipart/form-data" autocomplete="off" id="questForm" class="needs-validation" novalidate>
          <input type="hidden" name="create_quest" value="1">

          <div class="mb-3">
            <label class="form-label">ชื่อเควส</label>
            <input name="title" class="form-control" placeholder="เช่น ทำแบบฝึกหัดบทที่ 3 ครบทุกข้อ" required>
            <div class="invalid-feedback">กรอกชื่อเควส</div>
          </div>

          <div class="mb-3">
            <label class="form-label">วิชา/หมวด</label>
            <?php if (count($subject_names)): ?>
              <select name="subject_select" class="form-select" required>
                <option value="">— เลือกวิชา —</option>
                <?php foreach ($subject_names as $sn): ?>
                  <option value="<?= htmlspecialchars($sn, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($sn) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="form-text text-light">รายวิชานี้มาจาก “สเตตัสปัจจุบันของฉัน”</div>
              <div class="invalid-feedback">โปรดเลือกวิชา</div>
            <?php else: ?>
              <input name="subject" class="form-control" placeholder="ยังไม่มีวิชาในระบบ — กรอกชื่อวิชาได้เลย" required>
              <div class="form-text text-light">* ยังไม่มีข้อมูลวิชา จึงแสดงช่องกรอกแทน</div>
              <div class="invalid-feedback">กรอกชื่อวิชา</div>
            <?php endif; ?>
          </div>

          <div class="mb-3">
            <label class="form-label">รายละเอียด (ถ้ามี)</label>
            <textarea name="description" class="form-control" placeholder="แนบหลักฐาน/อธิบายสิ่งที่ทำ เช่น ลิงก์งาน ฯลฯ" rows="4"></textarea>
          </div>

          <div class="mb-3">
            <label class="form-label">ไฟล์/รูปแนบ (jpg/png/gif/webp/pdf, ≤ 8MB/ไฟล์)</label>
            <div class="dropzone" id="dz">
                <div>📤</div>
                <div>ลากไฟล์มาวางที่นี่ หรือคลิกเพื่อเลือก</div>
            </div>
            <input id="fileInput" type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,image/*,application/pdf" class="d-none">
            <div id="file-preview"></div>
          </div>

          <div class="d-grid mt-4">
            <button type="submit" class="btn btn-primary btn-lg">🚀 ส่งเควส</button>
          </div>
        </form>
      </div>
    </div>

    <div class="col-12 col-lg-6">
      <div class="card p-3 p-md-4 mb-4" id="character-card-container" style="display: none;">
        <h4 class="mb-3">🌟 ตัวละครของคุณ</h4>
        <div class="d-flex flex-column flex-sm-row align-items-center text-center text-sm-start gap-4">
            <img id="char-img" src="" alt="Character Image" style="width:120px; height:120px; border-radius:50%; border:3px solid var(--accent); object-fit:cover; background:var(--panel-2);">
            <div>
                <h3 id="char-name" class="mb-1" style="color:var(--accent);"></h3>
                <p id="char-title" class="lead fs-5 mb-0" style="opacity: 0.9;"></p>
                <div class="smallmuted mt-2">คำนวณจากกลุ่มวิชาที่มีคะแนนสูงสุดของคุณ</div>
            </div>
        </div>
    </div>
      <div class="card p-3 p-md-4 mb-4">
        <h4 class="mb-2">📊 STATUS OVERVIEW</h4>
        <div class="smallmuted mb-3">แสดงผลจากข้อมูลที่หน้า <code>กรอกข้อมูล</code> • โซนมาตรฐาน 60</div>
        

        <div class="mb-4">
          <div class="d-flex justify-content-between align-items-center">
            <div class="fw-semibold">หมวดวิชาทั่วไป</div>
            <span class="badge rounded-pill" style="background:var(--std-fill); color:var(--std); border-color:var(--std);">มาตรฐาน 60</span>
          </div>
          <div class="chart-box"><canvas id="radarGen"></canvas></div>
        </div>

        <div>
          <div class="d-flex justify-content-between align-items-center">
            <div class="fw-semibold">หมวดวิชาเฉพาะด้าน</div>
            <span class="badge rounded-pill" style="background:var(--std-fill); color:var(--std); border-color:var(--std);">มาตรฐาน 60</span>
          </div>
          <div class="chart-box"><canvas id="radarSpec"></canvas></div>
        </div>

        <div class="smallmuted mt-3 text-center" id="graphStamp"></div>
      </div>

      

        <?php if ($my_quests): ?>
          <div class="d-flex flex-column gap-3">
            <?php foreach($my_quests as $q): ?>
              <div class="quest-item <?= $q['status'] ?>">
                  <div class="quest-item-header">
                      <div class="quest-item-title"><?= htmlspecialchars($q['title']) ?></div>
                      <span class="badge <?= $q['status'] ?> rounded-pill text-uppercase"><?= $q['status'] ?></span>
                  </div>
                  <div class="quest-item-body">
                      <div>วิชา: <strong><?= htmlspecialchars($q['subject']) ?></strong> | คะแนนที่จะได้รับ: <strong><?= (isset($q['delta']) && $q['delta'] !== null) ? intval($q['delta']) : 'รอตรวจ' ?></strong></div>
                      <?php if(!empty($q['description'])): ?>
                        <div class="mt-2">รายละเอียด: <?= nl2br(htmlspecialchars($q['description'])) ?></div>
                      <?php endif; ?>

                      <?php if(!empty($q['files'])): ?>
                        <div class="quest-item-files">
                          <?php foreach($q['files'] as $fp): ?>
                            <a href="<?= htmlspecialchars($fp) ?>" target="_blank" class="btn btn-sm btn-outline-light"><?= basename($fp) ?></a>
                          <?php endforeach; ?>
                        </div>
                      <?php endif; ?>

                      <div class="small mt-2" style="opacity: 0.7;">
                        <span>ส่งเมื่อ: <?= date('d/m/Y H:i', strtotime($q['created_at'])) ?></span>
                        <?php if($q['status']!=='pending'): ?>
                          <span> | ตรวจเมื่อ: <?= $q['reviewed_at']?date('d/m/Y H:i', strtotime($q['reviewed_at'])):'-' ?></span>
                        <?php endif; ?>
                      </div>
                      <?php if(!empty($q['review_note'])): ?><div class="small mt-1 fst-italic">หมายเหตุแอดมิน: <?= htmlspecialchars($q['review_note']) ?></div><?php endif; ?>
                  </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="alert text-center" style="background:#151a33;border:1px dashed var(--border);color:#e7e7f0">ยังไม่มีเควสที่ส่ง</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
// Bootstrap validation
(() => {
  'use strict'
  const forms = document.querySelectorAll('.needs-validation');
  Array.from(forms).forEach(form => {
    form.addEventListener('submit', e => {
      if (!form.checkValidity()) { e.preventDefault(); e.stopPropagation(); }
      form.classList.add('was-validated');
    }, false);
  });
})();

// Dropzone and File Preview
const dz = document.getElementById('dz');
const fileInput = document.getElementById('fileInput');
const previewContainer = document.getElementById('file-preview');
dz.addEventListener('click', () => fileInput.click());
['dragenter', 'dragover'].forEach(evt => dz.addEventListener(evt, e => { e.preventDefault(); e.stopPropagation(); dz.classList.add('drag'); }));
['dragleave', 'drop'].forEach(evt => dz.addEventListener(evt, e => { e.preventDefault(); e.stopPropagation(); dz.classList.remove('drag'); }));
dz.addEventListener('drop', e => {
  const files = e.dataTransfer.files;
  fileInput.files = files;
  updateFilePreview();
});
fileInput.addEventListener('change', updateFilePreview);
function updateFilePreview() {
    previewContainer.innerHTML = '';
    const files = fileInput.files;
    if (files.length > 0) { dz.innerHTML = `<div>✅</div> <div>เลือกแล้ว ${files.length} ไฟล์</div>`; }
    else { dz.innerHTML = '<div>📤</div> <div>ลากไฟล์มาวางที่นี่ หรือคลิกเพื่อเลือก</div>'; return; }
    Array.from(files).forEach(file => {
        const item = document.createElement('div'); item.className = 'preview-item';
        if (file.type.startsWith('image/')) {
            const img = document.createElement('img'); img.src = URL.createObjectURL(file);
            img.onload = () => URL.revokeObjectURL(img.src); item.appendChild(img);
        } else {
            const p = document.createElement('div'); p.className = 'file-placeholder';
            p.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 16 16"><path d="M14 4.5V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h5.5L14 4.5zm-3 0A1.5 1.5 0 0 1 9.5 3V1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V4.5h-2z"/></svg>`;
            item.appendChild(p);
        }
        const nameDiv = document.createElement('div'); nameDiv.className = 'file-name';
        nameDiv.textContent = file.name; item.appendChild(nameDiv);
        previewContainer.appendChild(item);
    });
}

// ===== Utility Functions =====
function getLS(key, fallback = null) {
  try {
    const v = localStorage.getItem(key);
    return (v !== null && v !== 'undefined') ? JSON.parse(v) : fallback;
  } catch (e) { return fallback; }
}
const clamp01 = v => Math.max(0, Math.min(100, Number(v || 0)));
function clampArray(arr) { return (arr || []).map(clamp01); }

// ===== Data from PHP & LocalStorage =====
const CATALOG = getLS('graph_catalog', null);
const DELTAS = <?= $DELTA_MAP_JSON ?> || {};
const MY_SUBJECTS = <?= $MY_SUBJECTS_JSON ?> || [];
const genLabels = getLS('graph_gen_labels', []);
const specLabels = getLS('graph_spec_labels', []);
const savedAtLS = localStorage.getItem('graph_saved_at');
const stamp = document.getElementById('graphStamp');
if (savedAtLS) { stamp.textContent = 'อัปเดตล่าสุด : ' + new Date(parseInt(savedAtLS, 10)).toLocaleString('th-TH'); }
else { stamp.textContent = 'ยังไม่พบข้อมูลจากหน้า index'; }


// ===== Character Display Logic =====
function showCharacter() {
    const CHARACTER_MAP = {
        'ภาษาและการสื่อสาร':      { name: 'ออโรร่า',  title: 'นักพากย์แห่งถ้อยคำ',    img: 'character/Aurora.png' },
        'สังคมศาสตร์':            { name: 'คาลวิน',  title: 'นักวิเคราะห์แห่งโลก',      img: 'character/Calvin.png' },
        'มนุษยศาสตร์':                  { name: 'อีดริส',   title: 'ผู้รักษาสารัตถะ',        img: 'character/Idris.png' },
        'วิทยาศาสตร์/คณิตฯ/เทคโนโลยี': { name: 'อัลฟ่า',   title: 'จอมปราชญ์แห่งสสาร',   img: 'character/Alpha.png' },
        'วิชาแกน':                     { name: 'เซเลสเตีย', title: 'เทพแห่งการสร้าง',       img: 'character/Celestia.png' },
        'องค์การและระบบสารสนเทศ': { name: 'ออร์กัส',  title: 'ผู้จัดระเบียบแห่งโลกดิจิทัล', img: 'character/Organus.png' },
        'เทคโนโลยีเพื่องานประยุกต์':      { name: 'อาร์คาน่า',  title: 'ผู้ประดิษฐ์แห่งอนาคต',  img: 'character/Arcana.png' },
        'เทคโนโลยีและวิธีการทางซอฟต์แวร์': { name: 'ซีเอ็น',    title: 'สถาปิกแห่งโค้ด',     img: 'character/Xian.png' },
        'โครงสร้างพื้นฐาน':     { name: 'เน็ตเวิร์ค',  title: 'ผู้ดูแลแห่งสายใย',       img: 'character/Netwerk.png' }
    };
    const container = document.getElementById('character-card-container');
    const displayCard = (imgSrc, name, title, subtextMsg = null) => {
        const [img, n, t] = [document.getElementById('char-img'), document.getElementById('char-name'), document.getElementById('char-title')];
        img.src = imgSrc; n.textContent = name; t.textContent = title;
        if (subtextMsg) {
            const sub = container.querySelector('.smallmuted');
            if (sub) sub.textContent = subtextMsg;
        }
        container.style.display = 'block';
        setTimeout(() => container.classList.add('visible'), 50);
    };

    const scores = getLS('graph_gen_scores', []);
    if (scores.length === 0) {
        displayCard('character/Celestia.png', 'ยังไม่ระบุตัวตน', 'กรุณาไปที่ "หน้ากราฟ" เพื่อคำนวณข้อมูล', 'ไม่พบข้อมูลสเตตัสสำหรับแสดงผล');
        return;
    }
    const winnerLabel = findHighest(genLabels.concat(specLabels), getLS('graph_gen_scores').concat(getLS('graph_spec_scores'))).name;
    if (winnerLabel && CHARACTER_MAP[winnerLabel]) {
        const charData = CHARACTER_MAP[winnerLabel];
        displayCard(charData.img, charData.name, charData.title);
    } else {
        displayCard('character/Celestia.png', 'เกิดข้อผิดพลาด', 'ไม่พบตัวละครที่ตรงกับกลุ่มวิชาของคุณ', 'อาจเกิดจากชื่อกลุ่มวิชาไม่ตรงกัน');
    }
}

// ===== NEW: Calculation Logic =====
function calculateGroupScores(useDeltas = false) {
    if (!CATALOG) return { gen: [], spec: [] };
    const base = {};
    MY_SUBJECTS.forEach(it => { base[it.name] = Number(it.score || 0); });
    const names = new Set([...Object.keys(base), ...Object.keys(DELTAS)]);
    const sumByGid = {}, cntByGid = {};
    names.forEach(name => {
        const meta = CATALOG[name];
        if (!meta) return;
        const score = useDeltas ? clamp01(Number(base[name] || 0) + Number(DELTAS[name] || 0)) : clamp01(Number(base[name] || 0));
        const pt = (score / 100) * 4, cr = Number(meta.cr || 3), gid = Number(meta.gid);
        if (!sumByGid[meta.type]) { sumByGid[meta.type] = {}; cntByGid[meta.type] = {}; }
        sumByGid[meta.type][gid] = (sumByGid[meta.type][gid] || 0) + (pt * cr);
        cntByGid[meta.type][gid] = (cntByGid[meta.type][gid] || 0) + 1;
    });

    const results = { gen: [], spec: [] };
    ['gen', 'spec'].forEach(type => {
        const maxGid = (type === 'gen') ? 4 : 5;
        for (let gid = 1; gid <= maxGid; gid++) {
            const SUM = (sumByGid[type] && sumByGid[type][gid]) || 0;
            const N = Math.max(1, (cntByGid[type] && cntByGid[type][gid]) || 0);
            const gpa = SUM / (N * 3.0);
            results[type].push(clamp01(Math.round((gpa / 4) * 100)));
        }
    });
    return results;
}

// ===== Radar Graphs Logic (REVISED) =====
const commonRadarOptions = {
  responsive: true, maintainAspectRatio: false,
  scales: { r: { min:0, max:100, grid:{ color:'#39406b' }, angleLines:{ color:'#39406b' }, pointLabels:{ color:'#e7e7f0', font:{ size:14, family:'Prompt, sans-serif' } }, ticks:{ showLabelBackdrop:false, color:'#cfe2ff', stepSize:20 } }},
  plugins:{ legend:{ labels:{ color:'#e7e7f0' } }, tooltip:{ enabled:true, displayColors:false, callbacks:{ label:(ctx)=>'คะแนน: '+ctx.formattedValue } }},
  animation:{ duration:300 }
};

function stdDataset(len) { return { label:'มาตรฐาน (60)', data:Array(len).fill(60), fill:true, backgroundColor:'rgba(255,216,77,.14)', borderColor:'#ffd84d', borderWidth:2, borderDash:[6,4], pointRadius:0, order:1 }; }
function actualDataset(data) { return { label:'คะแนนพื้นฐาน', data, fill:true, backgroundColor:'rgba(59,165,180,.18)', borderColor:'rgba(59,165,180,1)', pointBackgroundColor:'#fff', pointBorderColor:'rgba(59,165,180,1)', pointBorderWidth:2, pointRadius:4, pointHoverRadius:6, order:2 }; }
function boostedDataset(data) { return { label:'หลังบวกแต้ม (เควส)', data, fill:false, borderColor:'rgba(46, 204, 113, 1)', backgroundColor:'rgba(46, 204, 113, .15)', borderWidth:3, pointBackgroundColor:'#fff', pointBorderColor:'rgba(46, 204, 113, 1)', pointBorderWidth:2, pointRadius:4, pointHoverRadius:6, order:3 }; }

function makeRadar(elId, labels, datasets) {
  const el = document.getElementById(elId);
  if (!el || !labels || labels.length === 0) return null;
  return new Chart(el.getContext('2d'), { type:'radar', data:{ labels, datasets }, options: commonRadarOptions });
}

// --- Chart Drawing ---
const baseScores = calculateGroupScores(false); // Calculate "Before" scores
const chGen  = makeRadar('radarGen', genLabels, [stdDataset(genLabels.length), actualDataset(baseScores.gen)]);
const chSpec = makeRadar('radarSpec', specLabels, [stdDataset(specLabels.length), actualDataset(baseScores.spec)]);

if (Object.keys(DELTAS).length > 0) {
  const boostedScores = calculateGroupScores(true); // Calculate "After" scores
  if (boostedScores.gen.length > 0 && chGen) {
    chGen.data.datasets.push(boostedDataset(boostedScores.gen));
    chGen.update();
  }
  if (boostedScores.spec.length > 0 && chSpec) {
    chSpec.data.datasets.push(boostedDataset(boostedScores.spec));
    chSpec.update();
  }
  stamp.textContent += (stamp.textContent ? ' • ' : '') + `รวมแต้มจากเควสที่อนุมัติแล้ว: ${Object.keys(DELTAS).length} วิชา`;
}

// Run character logic last
function findHighest(labels, scores) {
  if (!labels || labels.length === 0) return null;
  let maxScore = -1, bestIndex = -1;
  scores.forEach((score, i) => { if (score > maxScore) { maxScore = score; bestIndex = i; } });
  return { name: labels[bestIndex], score: maxScore };
};
showCharacter();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>