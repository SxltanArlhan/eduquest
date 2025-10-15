<?php
// ===== stats_live.php =====
// คืนค่าคะแนนปัจจุบันของผู้ใช้ที่ล็อกอินอยู่ เพื่อให้กราฟอัปเดตแบบ "เรียลไทม์" (ผ่าน polling)

session_start();
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error'=>'unauthorized']);
    exit;
}

require_once __DIR__ . '/stats_utils.php';

$STATS_FILE  = __DIR__ . '/stats.json';
if (!file_exists($STATS_FILE)) file_put_contents($STATS_FILE, '[]');

$username = $_SESSION['user']['username'];
$subjects = stats_get_for($username, $STATS_FILE);

// เตรียมโครงสร้างตอบกลับ
$labels = array_map(fn($s)=>$s['name'], $subjects);
$scores = array_map(fn($s)=>intval($s['score']), $subjects);

// สร้างแฮชง่ายๆ ใช้ตรวจว่าข้อมูลเปลี่ยนไหม เพื่อลดการ re-render
$hash = md5(json_encode([$labels,$scores], JSON_UNESCAPED_UNICODE));

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
  'labels' => $labels,
  'scores' => $scores,
  'hash'   => $hash,
], JSON_UNESCAPED_UNICODE);
