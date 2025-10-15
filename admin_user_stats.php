<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';
// ไม่จำเป็นต้องใช้ stats_utils.php แล้ว เพราะเราจะอ่านข้อมูลโดยตรง
// require_once __DIR__ . '/stats_utils.php';

// --- Files ---
$STATS_FILE = __DIR__ . '/stats.json';
$CATALOG_FILE = __DIR__ . '/catalog.json';
if (!file_exists($STATS_FILE) || !file_exists($CATALOG_FILE)) {
    echo json_encode(['error' => 'Stats or catalog file not found.']);
    exit;
}

// --- Get Username ---
$username = strtolower(trim($_GET['u'] ?? ''));
if (empty($username)) {
    echo json_encode(['error' => 'Username is required.']);
    exit;
}

// --- Read Data ---
function json_read_local($p){ $d=@file_get_contents($p); return $d?(json_decode($d,true)?:[]):[]; }
$all_stats  = json_read_local($STATS_FILE);
$catalog    = json_read_local($CATALOG_FILE);

// ** REVISED LOGIC TO READ STATS.JSON BY KEY **
$user_stats = $all_stats[$username] ?? [];

if (empty($user_stats)) {
    echo json_encode(['gen' => ['labels'=>[],'scores'=>[]], 'spec' => ['labels'=>[],'scores'=>[]]]);
    exit;
}

// --- Grouping Logic (เหมือนเดิม) ---
$sumByGid = ['gen' => [], 'spec' => []];
$cntByGid = ['gen' => [], 'spec' => []];

foreach ($user_stats as $stat) {
    $name = $stat['name'];
    $score = intval($stat['score']);
    $meta = $catalog[$name] ?? null;

    if (!$meta) continue;

    $type = $meta['type']; // 'gen' or 'spec'
    $gid  = intval($meta['gid']);
    $cr   = intval($meta['cr'] ?? 3);
    $pt   = ($score / 100) * 4.0;

    if (!isset($sumByGid[$type][$gid])) { $sumByGid[$type][$gid] = 0; $cntByGid[$type][$gid] = 0; }
    $sumByGid[$type][$gid] += ($pt * $cr);
    $cntByGid[$type][$gid] += 1;
}

// --- Calculate Final Group Scores (เหมือนเดิม) ---
$GROUP_NAMES = [
    'gen' => ['ภาษาและการสื่อสาร', 'สังคมศาสตร์', 'มนุษยศาสตร์', 'วิทยาศาสตร์/คณิตฯ/เทคโนโลยี'],
    'spec' => ['องค์การและระบบสารสนเทศ', 'เทคโนโลยีเพื่องานประยุกต์', 'เทคโนโลยีและวิธีการทางซอฟต์แวร์', 'โครงสร้างพื้นฐาน', 'วิชาแกน']
];

$output = ['gen' => ['labels'=>[],'scores'=>[]], 'spec' => ['labels'=>[],'scores'=>[]]];

foreach (['gen', 'spec'] as $type) {
    $output[$type]['labels'] = $GROUP_NAMES[$type];
    $maxGid = ($type === 'gen') ? 4 : 5;
    for ($gid = 1; $gid <= $maxGid; $gid++) {
        $SUM = $sumByGid[$type][$gid] ?? 0;
        $N = $cntByGid[$type][$gid] ?? 1;
        $gpa = $SUM / ($N * 3.0);
        $percent = round(($gpa / 4.0) * 100);
        $output[$type]['scores'][] = max(0, min(100, $percent));
    }
}

echo json_encode($output);