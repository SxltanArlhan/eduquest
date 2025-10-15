<?php
// ===== admin_quests.php =====
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/stats_utils.php';

// ... (ส่วน PHP ด้านบนเหมือนเดิมทั้งหมด ไม่มีการเปลี่ยนแปลง) ...
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit; }
$user = $_SESSION['user'];
if (($user['role'] ?? 'user') !== 'admin') { http_response_code(403); echo 'เฉพาะผู้ดูแลระบบ'; exit; }
if (!isset($_SESSION['admin_verified']) || $_SESSION['admin_verified'] !== true) {
    $err = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_admin_pin'])) {
        if (trim($_POST['pin'] ?? '') === ADMIN_ACTION_PIN) {
            $_SESSION['admin_verified'] = true;
            header('Location: admin_quests.php'); exit;
        } else { $err = 'PIN ไม่ถูกต้อง'; }
    }
    ?>
    <!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ยืนยัน PIN แอดมิน</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600&display=swap" rel="stylesheet"><style>body{min-height:100vh;background:#0f1220;color:#e7e7f0;display:flex;align-items:center;justify-content:center;font-family:'Prompt',system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif}.card{background:#171a2b;border:1px solid #2a2f52;color:#e7e7f0;box-shadow:0 16px 60px rgba(0,0,0,.35)}.form-control, .btn{border-radius:12px; border:1px solid #2a2f52; background:#1f2540; color:#e7e7f0;}.form-control:focus{border-color:#8c4fff; box-shadow:0 0 0 .2rem rgba(140,79,255,.15);}.btn-primary{background:linear-gradient(180deg,#8c4fff,#6f3ffb);border:none}</style></head><body><div class="card p-4" style="width:380px;max-width:92vw;"><h3 class="mb-3">ยืนยัน PIN แอดมิน</h3><?php if($err): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($err) ?></div><?php endif; ?><form method="post" autocomplete="off"><input type="password" name="pin" class="form-control mb-3" placeholder="กรอก PIN แอดมิน" required><button type="submit" name="verify_admin_pin" value="1" class="btn btn-primary w-100">ยืนยัน</button></form><div class="mt-3 text-center"><a href="index.php" class="link-light text-decoration-none small">← กลับหน้าแรก</a></div></div></body></html>
    <?php
    exit;
}
$QUESTS_FILE = __DIR__ . '/quests.json';
$STATS_FILE  = __DIR__ . '/stats.json';
if (!file_exists($QUESTS_FILE)) file_put_contents($QUESTS_FILE, '[]');
if (!file_exists($STATS_FILE))  file_put_contents($STATS_FILE,  '[]');
function json_read_local($p){ $d=@file_get_contents($p); return $d? (json_decode($d,true)?:[]) :[]; }
function json_write_local($p,$data){ file_put_contents($p, json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)); }
$okmsg = ''; $errmsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $action = $_POST['action']; $id = $_POST['id']; $note = trim($_POST['note'] ?? '');
    $score_admin = (isset($_POST['score_admin']) && $_POST['score_admin'] !== '') ? intval($_POST['score_admin']) : null;
    $quests = json_read_local($QUESTS_FILE); $found = false;
    foreach ($quests as &$q) {
        if ($q['id'] === $id) {
            $found = true;
            if (($q['status'] ?? 'pending') !== 'pending') { $errmsg = 'เควสนี้ถูกตรวจไปแล้ว'; break; }
            if ($action === 'approve') {
                $applyScore = $score_admin !== null ? $score_admin : intval($q['delta'] ?? 0);
                stats_increment_subject($q['username'], $q['subject'], $applyScore, $STATS_FILE);
                $q['delta'] = $applyScore; $q['status'] = 'approved'; $q['reviewed_at'] = date('c'); $q['review_note'] = $note;
                $okmsg = 'อนุมัติเควสสำเร็จ และบันทึกคะแนนเรียบร้อย';
            } elseif ($action === 'reject') {
                $q['status'] = 'rejected'; $q['reviewed_at'] = date('c'); $q['review_note'] = $note;
                $okmsg = 'ปฏิเสธเควสเรียบร้อย';
            }
            break;
        }
    }
    if ($found) json_write_local($QUESTS_FILE, $quests);
    else if (!$errmsg) $errmsg = 'ไม่พบเควสที่ระบุ';
}
$all = json_read_local($QUESTS_FILE);
usort($all,function($a,$b){
    $order=['pending'=>0,'approved'=>1,'rejected'=>2];
    $oa=$order[$a['status']]??9; $ob=$order[$b['status']]??9;
    if($oa===$ob) return strcmp($b['created_at'],$a['created_at']);
    return $oa<=>$ob;
});
$total = count($all); $pend = count(array_filter($all, fn($q)=>$q['status']==='pending'));
$appr = count(array_filter($all, fn($q)=>$q['status']==='approved')); $rej = count(array_filter($all, fn($q)=>$q['status']==='rejected'));
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ผู้ดูแล | ตรวจเควส</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<style>
/* ... (ส่วน CSS เหมือนเดิมทั้งหมด ไม่มีการเปลี่ยนแปลง) ... */
:root{--bg:#0f1220; --panel:#171a2b; --panel-2:#1f2540; --accent:#8c4fff; --accent-2:#6f3ffb;--text:#e7e7f0; --muted:#a3a7c2; --border:#2a2f52;--status-pending: #ffc107; --status-approved: #28a745; --status-rejected: #dc3545;}
body{background:var(--bg); background-image:linear-gradient(180deg,#0f1220 0%,#141830 100%);color:var(--text);font-family:'Prompt',system-ui,sans-serif}
.navbar{background:rgba(23,26,43,.75)!important;backdrop-filter:blur(8px);border-bottom:1px solid var(--border)}
.card{background: radial-gradient(1200px 400px at -10% -20%, rgba(140,79,255,.08), transparent 40%), var(--panel);border:1px solid var(--border); color:var(--text); box-shadow:0 20px 60px rgba(0,0,0,.35)}
.form-control, .btn, .form-select{border-radius:12px;border:1px solid var(--border);background:#1f2540;color:var(--text)}
.form-control:focus{border-color:var(--accent);box-shadow:0 0 0 .2rem rgba(140,79,255,.15)}
.btn-primary{background:linear-gradient(180deg,var(--accent),var(--accent-2));border:none; transition: all .3s ease;}
.btn-primary:hover{transform: translateY(-2px); box-shadow: 0 4px 15px rgba(140,79,255,.2);}
.btn-danger{background:#c83349;border:none; transition: all .3s ease;}
.btn-danger:hover{background:#e14444; transform: translateY(-2px); box-shadow: 0 4px 15px rgba(220,53,69,.2);}
.btn-outline-light { border-color: var(--border); } .btn-outline-light:hover { background-color: rgba(255,255,255,.05); }
.stat-badge { background:var(--panel-2); border:1px solid var(--border); display:inline-flex; align-items:center; gap:.5rem; padding: .5rem 1rem; border-radius:12px; font-size:.9rem}
.quest-card {border-left-width: 4px;transition: transform .2s ease, box-shadow .2s ease;}
.quest-card:hover { transform: translateY(-4px); box-shadow: 0 10px 40px rgba(0,0,0,.25); }
.quest-card.status-pending  { border-left-color: var(--status-pending); }
.quest-card.status-approved { border-left-color: var(--status-approved); }
.quest-card.status-rejected { border-left-color: var(--status-rejected); }
.badge.status { font-weight: 500; font-size: .8rem; padding: .4em .8em; }
.badge.status-pending  { background:rgba(255,193,7,.1); border:1px solid rgba(255,193,7,.4); color: var(--status-pending);}
.badge.status-approved { background:rgba(40,167,69,.1); border:1px solid rgba(40,167,69,.4); color: var(--status-approved); }
.badge.status-rejected { background:rgba(220,53,69,.1); border:1px solid rgba(220,53,69,.4); color: var(--status-rejected); }
.quest-info-grid { display:grid; grid-template-columns: auto 1fr; gap: .25rem .75rem; align-items:center; }
.quest-info-grid > svg { width:16px; height:16px; color: var(--accent); opacity: .7; }
.file-link { background:var(--panel-2); border:1px solid var(--border); padding:.4rem .8rem; border-radius:10px; text-decoration:none; color:#e7e7f0; display:inline-flex; align-items:center; gap:.5rem; transition: background .2s;}
.file-link:hover { background: #2a2f52; }
.admin-action-form { background: rgba(0,0,0,.15); padding: 1rem; border-radius: 12px; margin-top: 1rem; border: 1px solid var(--border); }
hr{border-color:var(--border)}
.modal-content{background:var(--panel); color:var(--text); border:1px solid var(--border)}
.modal-header, .modal-footer{border-color:var(--border)}
.chart-loader { position: absolute; top: 0; left: 0; right: 0; bottom: 0; display: flex; align-items: center; justify-content: center; background: rgba(23,26,43,.7); z-index: 10; color: var(--muted); }
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark sticky-top">
    <div class="container-fluid"><span class="navbar-brand">🛠️ ผู้ดูแล — ตรวจสอบเควส</span><div class="d-flex gap-2 ms-auto"><a class="btn btn-sm btn-outline-light" href="home.php">หน้าเควส</a><a class="btn btn-sm btn-outline-light" href="index.php">หน้ากราฟ</a><a class="btn btn-sm btn-outline-light" href="logout.php">ออกจากระบบ</a></div></div>
</nav>

<div class="container my-4">
    <?php if($okmsg): ?><div class="alert alert-success"><?= $okmsg ?></div><?php endif; ?>
    <?php if($errmsg): ?><div class="alert alert-danger"><?= $errmsg ?></div><?php endif; ?>
    <div class="card p-3 mb-4"><div class="d-flex flex-wrap gap-3"><div class="stat-badge"><span>ทั้งหมด:</span> <span class="fw-bold fs-5"><?= $total ?></span></div><div class="stat-badge"><span>รอตรวจ:</span> <span class="fw-bold fs-5 text-warning"><?= $pend ?></span></div><div class="stat-badge"><span>อนุมัติ:</span> <span class="fw-bold fs-5 text-success"><?= $appr ?></span></div><div class="stat-badge"><span>ปฏิเสธ:</span> <span class="fw-bold fs-5 text-danger"><?= $rej ?></span></div></div></div>
    <?php if ($all): ?><div class="row g-4"><?php foreach ($all as $q): ?><div class="col-12 col-lg-6"><div class="card quest-card status-<?= $q['status'] ?> h-100"><div class="card-body d-flex flex-column"><div class="d-flex justify-content-between align-items-start mb-2"><h5 class="card-title mb-0 me-3 fw-semibold"><?= htmlspecialchars($q['title']) ?></h5><span class="badge status status-<?= $q['status'] ?> rounded-pill text-uppercase flex-shrink-0"><?= $q['status'] ?></span></div><div class="quest-info-grid mb-3"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6m2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0m4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4m-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10s-3.516.68-4.168 1.332c-.678.678-.83 1.418-.832 1.664z"/></svg><div><?= htmlspecialchars($q['fullname'] ?? $q['username']) ?> <small class="text-secondary">(<?= htmlspecialchars($q['username']) ?>)</small></div><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M6.354 5.523c.229-2.277 1.9-4.243 3.9-4.381a.73.73 0 0 1 .246.025a.73.73 0 0 1 .637.73c-.027.981-.487 1.94-1.21 2.61a.73.73 0 0 1-.62.247a.73.73 0 0 1-.72-.731zm-.004 2.173.004-.004a.73.73 0 0 1 .722-.734c.264.03.658.11 1.012.365a.73.73 0 0 1 .228.824c-.11.232-.292.422-.51.547a.73.73 0 0 1-.828.004a.73.73 0 0 1-.202-.625z"/><path d="M4.5 1a.5.5 0 0 0-.5.5v13a.5.5 0 0 0 .5.5h7a.5.5 0 0 0 .5-.5v-13a.5.5 0 0 0-.5-.5zM5 1.5h6v13H5z"/></svg><div><strong><?= htmlspecialchars($q['subject']) ?></strong> | <small class="text-secondary">คะแนน:</small> <span class="fw-bold"><?= isset($q['delta']) ? intval($q['delta']) : 0 ?></span></div></div><?php if(!empty($q['description'])): ?><p class="small mb-2 fst-italic text-secondary">"<?= nl2br(htmlspecialchars($q['description'])) ?>"</p><?php endif; ?><?php if(!empty($q['files'])): ?><div class="mb-3"><div class="d-flex flex-wrap gap-2"><?php foreach($q['files'] as $fp): ?><a href="<?= htmlspecialchars($fp) ?>" target="_blank" class="file-link"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M10.854 7.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 9.793l2.646-2.647a.5.5 0 0 1 .708 0z"/><path d="M4 1.5H3a2 2 0 0 0-2 2V14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V3.5a2 2 0 0 0-2-2h-1v1h1a1 1 0 0 1 1 1V14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V3.5a1 1 0 0 1 1-1h1z"/><path d="M9.5 1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5zm-3-1A1.5 1.5 0 0 0 5 1.5v1A1.5 1.5 0 0 0 6.5 4h3A1.5 1.5 0 0 0 11 2.5v-1A1.5 1.5 0 0 0 9.5 0z"/></svg><span>เปิดไฟล์แนบ</span></a><?php endforeach; ?></div></div><?php endif; ?><button class="btn btn-outline-light btn-sm align-self-start" data-view-graph data-username="<?= htmlspecialchars($q['username']) ?>" data-fullname="<?= htmlspecialchars($q['fullname'] ?? $q['username']) ?>">ดูกราฟผู้ใช้</button><div class="mt-auto"><?php if (($q['status'] ?? '') === 'pending'): ?><form method="post" class="admin-action-form"><input type="hidden" name="id" value="<?= $q['id'] ?>"><div class="row g-2"><div class="col-12 col-sm-5"><input type="number" name="score_admin" class="form-control" placeholder="คะแนน" value="<?= isset($q['delta']) ? intval($q['delta']) : '' ?>"></div><div class="col-12 col-sm-7"><input type="text" name="note" class="form-control" placeholder="หมายเหตุ (ถ้ามี)"></div></div><div class="d-flex gap-2 mt-2"><button type="submit" name="action" value="approve" class="btn btn-primary w-100">อนุมัติ</button><button type="submit" name="action" value="reject" class="btn btn-danger w-100">ปฏิเสธ</button></div></form><?php else: ?><hr class="my-3"><div class="small text-secondary"><div>ตรวจเมื่อ: <?= $q['reviewed_at'] ? date('d/m/Y H:i', strtotime($q['reviewed_at'])) : '-' ?></div><?php if(!empty($q['review_note'])): ?><div class="fst-italic">หมายเหตุ: <?= htmlspecialchars($q['review_note']) ?></div><?php endif; ?></div><?php endif; ?></div></div></div></div><?php endforeach; ?></div><?php else: ?><div class="card p-4 text-center text-secondary">ยังไม่มีเควสในระบบ</div><?php endif; ?>
</div>

<div class="modal fade" id="userGraphModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">กราฟสถานะของ <span id="mgName"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-12 col-md-6 mb-4 mb-md-0">
                        <h6 class="text-center mb-3">หมวดวิชาทั่วไป</h6>
                        <div style="height:350px; position:relative;">
                            <div class="chart-loader d-none">กำลังโหลด...</div>
                            <canvas id="userRadarGen"></canvas>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                         <h6 class="text-center mb-3">หมวดวิชาเฉพาะด้าน</h6>
                         <div style="height:350px; position:relative;">
                            <div class="chart-loader d-none">กำลังโหลด...</div>
                            <canvas id="userRadarSpec"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ===== REVISED SCRIPT FOR STABLE CHART UPDATES =====

// --- 1. Initialize variables and instances ONCE ---
let radarGenChart = null;
let radarSpecChart = null;
const modalEl = document.getElementById('userGraphModal');
const bsModal = new bootstrap.Modal(modalEl); // Create modal instance once
const mgNameEl = document.getElementById('mgName');
const loaders = document.querySelectorAll('.chart-loader');

const commonRadarOptions = {
    responsive: true, maintainAspectRatio: false,
    scales: { r: { min: 0, max: 100, grid: { color: '#39406b' }, angleLines: { color: '#39406b' }, pointLabels: { color: '#e7e7f0', font: { size: 13 } } } },
    plugins: { legend: { display: false }, tooltip: { enabled: true, displayColors: false } },
    animation: { duration: 400 }
};

// --- 2. Create chart instances ONCE with empty data ---
document.addEventListener('DOMContentLoaded', () => {
    const emptyData = { labels: [], datasets: [{ data: [] }] };
    radarGenChart = new Chart(document.getElementById('userRadarGen'), { type: 'radar', data: emptyData, options: commonRadarOptions });
    radarSpecChart = new Chart(document.getElementById('userRadarSpec'), { type: 'radar', data: emptyData, options: commonRadarOptions });
});

// --- 3. Function to update chart data ---
function updateChart(chart, labels, scores) {
    chart.data.labels = labels;
    chart.data.datasets[0] = {
        label: 'คะแนน', data: scores, fill: true,
        backgroundColor: 'rgba(140,79,255,.22)',
        borderColor: 'rgba(140,79,255,.9)',
        pointBackgroundColor: '#fff', pointBorderColor: '#8c4fff',
        pointRadius: 4, pointHoverRadius: 6
    };
    chart.update();
}

// --- 4. Attach event listeners ---
document.querySelectorAll('[data-view-graph]').forEach(btn => {
    btn.addEventListener('click', async () => {
        const username = btn.getAttribute('data-username');
        const fullname = btn.getAttribute('data-fullname') || username;
        
        mgNameEl.textContent = fullname;
        bsModal.show();
        loaders.forEach(l => l.classList.remove('d-none')); // Show loaders

        try {
            const res = await fetch('admin_user_stats.php?u=' + encodeURIComponent(username), { cache: 'no-store' });
            if (!res.ok) throw new Error('Network response was not ok: ' + res.statusText);
            const json = await res.json();
            
            if (json.error) throw new Error(json.error);

            // Update existing chart instances with new data
            updateChart(radarGenChart, json.gen.labels, json.gen.scores);
            updateChart(radarSpecChart, json.spec.labels, json.spec.scores);

        } catch (e) {
            console.error("Chart loading failed:", e);
            alert('โหลดข้อมูลกราฟไม่สำเร็จ: ' + e.message);
            bsModal.hide();
        } finally {
            loaders.forEach(l => l.classList.add('d-none')); // Hide loaders
        }
    });
});
</script>
</body>
</html>