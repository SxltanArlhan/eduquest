<?php
session_start();

/* ============ ต้องล็อกอินก่อน ============ */
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit; }

require_once __DIR__ . '/stats_utils.php'; // ใช้ไฟล์ยูทิลิตี้เดิมสำหรับเซฟ/โหลด
$STATS_FILE = __DIR__ . '/stats.json';
if (!file_exists($STATS_FILE)) file_put_contents($STATS_FILE, '[]');

$user     = $_SESSION['user'];
$username = $user['username'];
$fullname = $user['fullname'] ?? $username;

/* =========================================================
   เกณฑ์เกรด → เกรดพอยต์ และ helper แปลงกลับ
   เกรดพอยต์: A=4, B+=3.5, B=3, C+=2.5, C=2, D+=1.5, D=1, E=0
   ค่าในกราฟ: (GPA/4)*100
========================================================= */
$GRADE_TO_POINT = [
  'A'  => 4.0,
  'B+' => 3.5,
  'B'  => 3.0,
  'C+' => 2.5,
  'C'  => 2.0,
  'D+' => 1.5,
  'D'  => 1.0,
  'E'  => 0.0,
];

function point_to_grade(float $p): string {
  if ($p >= 3.75) return 'A';
  if ($p >= 3.25) return 'B+';
  if ($p >= 2.75) return 'B';
  if ($p >= 2.25) return 'C+';
  if ($p >= 1.75) return 'C';
  if ($p >= 1.25) return 'D+';
  if ($p >= 0.50) return 'D';
  return 'E';
}

/* =========================================================
   คลังรายวิชา + หน่วยกิต (credits)
========================================================= */
/* --- หมวดวิชาทั่วไป (ทุกตัว 3 หน่วยกิต) --- */
$GEN_GROUPS = [
  1 => ['name' => 'ภาษาและการสื่อสาร', 'min_credits' => 12, 'subjects' => [
    '1500101 ภาษาไทยเพื่อการสื่อสารที่มีประสิทธิภาพ',
    '1500102 ทักษะการฟังและการพูดภาษาอังกฤษ',
    '1500103 การใช้ภาษาอังกฤษเพื่อการสื่อสาร',
    '1500201 ภาษาอังกฤษเพื่อการสื่อสารข้ามวัฒนธรรม',
    '1500202 ภาษาอังกฤษเพื่อการสื่อสารในบริบทสากล',
    '1500203 ภาษาอังกฤษการสื่อสารที่มีประสิทธิภาพ',
    '1500204 การสื่อสารอย่างผู้นํา',
    '1500104 ภาษาอังกฤษเพื่อการประกอบอาชีพ',
    '1500205 การพัฒนาบุคลิกภาพและศิลปะการพูดให้สัมฤทธิ์ผล',
    '1500206 ภาษาอังกฤษในชั้นเรียน',
    '1500207 ภาษาอังกฤษเชิงวิชาการ',
    '1500208 ภาษาอังกฤษเพื่อการสมัครงาน',
    '1500209 การนําเสนองานด้วยวาจาภาษาอังกฤษ',
    '1500210 ภาษาอังกฤษเพื่อการเตรียมสอบ',
    '1500211 ภาษาจีนเพื่อการสื่อสาร',
    '1500212 การสนทนาภาษาจีนเพื่อการทํางาน',
    '1500213 ภาษาญี่ปุ่นเบื้องต้น',
    '1500214 ภาษาเขมรเบื้องต้น',
    '1500215 ภาษาอินโดนีเซียเบื้องต้น',
    '1500216 ภาษาพม่าเบื้องต้น',
    '1500217 ภาษาเวียดนามเบื้องต้น',
  ]],
  2 => ['name' => 'สังคมศาสตร์', 'min_credits' => 9, 'subjects' => [
    '2000101 พลเมืองที่เข้มแข็ง',
    '2000201 ปรัชญาของเศรษฐกิจพอเพียง',
    '2000202 สีสันแห่งชีวิต',
    '2000203 การบริหารจัดการในศตวรรษที่ 21',
    '2000204 พลวัตสังคมไทยและสังคมโลก',
    '2000205 วัยใส ใจสะอาด',
    '2000206 สิ่งแวดล้อมกับการดําเนินชีวิต',
    '2000207 วิถีชีวิตเศรษฐกิจพอเพียง',
    '2000208 เศรษฐกิจสร้างสรรค์',
    '2000209 กฎหมายในชีวิตประจําวัน',
    '2000210 ท้องถิ่นศึกษากับภูมิปัญญาไทยในการพัฒนาท้องถิ่น',
  ]],
  3 => ['name' => 'มนุษยศาสตร์', 'min_credits' => 3, 'subjects' => [
    '2500101 ความซาบซึ้งในสุนทรียะ',
    '2500201 จิตวิญญาณราชภัฏนครปฐม',
    '2500202 ความสุขของชีวิต',
    '2500203 มนุษย์กับการพัฒนาจิตใจ',
    '2500204 ศาสตร์และศิลป์ในการดําเนินชีวิต',
    '2500205 จิตวิทยาในชีวิตประจําวัน',
  ]],
  4 => ['name' => 'วิทยาศาสตร์/คณิตฯ/เทคโนโลยี', 'min_credits' => 3, 'subjects' => [
    '4000101 การสร้างเสริมและดูแลสุขภาวะ',
    '4000102 ทักษะในศตวรรษที่ 21 เพื่อชีวิตและอาชีพ',
    '4000103 การคิดเชิงเหตุผล',
    '4000201 เทคโนโลยีดิจิทัลและนวัตกรรม',
    '4000202 การสร้างสรรค์นวัตกรรม',
    '4000203 ฟิต ฟอร์ เฟิร์ม',
    '4000204 มนุษย์กับการใช้เหตุผล',
    '4000205 ความรอบรู้ทางด้านสุขภาพ',
    '4000206 โลกกับการพัฒนาด้านวิทยาศาสตร์และเทคโนโลย',
    '4000207 วิทยาศาสตร์และเทคโนโลยีกับสิ่งแวดล้อม',
    '4000208 สารสนเทศเพื่อการศึกษาค้นคว้า',
    '4000209 คณิตศาสตร์ในชีวิตประจําวัน',
    '4000210 พื้นฐานงานช่างในชีวิตประจําวัน',
  ]],
];
$GEN_TOTAL_MIN = 30;
$GEN_CREDIT = 3; // ทุกวิชาในหมวดทั่วไป 3 นก.

/* --- หมวดเฉพาะด้าน 5 กลุ่ม (ตัดภาคสนาม/สหกิจออก) + เพดานหน่วยกิตต่อกลุ่ม --- */
$SPEC_GROUPS = [
  1 => ['name' => 'วิชาแกน', 'cap_credits'=>18, 'subjects' => [
    '7201101 คณิตศาสตร์และสถิติสําหรับเทคโนโลยีสารสนเทศ' => 3,
    '7201102 ระบบคอมพิวเตอร์' => 3,
    '7201103 หลักการเขียนโปรแกรมคอมพิวเตอร์' => 3,
    '7201104 ภาษาอังกฤษสําหรับเทคโนโลยีสารสนเทศ' => 3,
    '7202105 การประกันและความปลอดภัยด้านสารสนเทศ' => 3,
    '7202106 การวิเคราะห์และออกแบบระบบสารสนเทศ' => 3,
  ]],
  2 => ['name' => 'องค์การและระบบสารสนเทศ', 'cap_credits'=>12, 'subjects' => [
    '7203202 กฎหมาย จริยธรรม และความปลอดภัยในการใช้เทคโนโลยีสารสนเทศ' => 3,
    '7203802 ระบบสารสนเทศเชิงธุรกิจ' => 3,
    '7203803 การพาณิชย์อิเล็กทรอนิกส์' => 3,
    '7203804 การวางแผนทรัพยากรองค์กร' => 3,
    '7203812 ธุรกิจสตาร์ทอัพด้านเทคโนโลยีสารสนเทศ' => 3,
    '7203813 ระบบธุรกิจอัจฉริยะเบื้องต้น' => 3,
    '7203815 พื้นฐานวิทยาการข้อมูล' => 3,
    '7203816 พื้นฐานปัญญาประดิษฐ์' => 3,
    '7203817 การประมวลผลภาษาธรรมชาติเบื้องต้น' => 3,
    '7203819 ความเป็นผู้ประกอบการธุรกิจเทคโนโลยี' => 3,
    '7203901 สัมมนาด้านเทคโนโลยีสารสนเทศ' => 2,
    '7203902 โครงงานด้านเทคโนโลยีสารสนเทศ 1' => 2,
    '7204201 การบริหารโครงการเทคโนโลยีสารสนเทศ' => 3,
    '7204302 การวิจัยเบื้องต้นด้านเทคโนโลยีสารสนเทศ' => 3,
    '7204903 โครงงานด้านเทคโนโลยีสารสนเทศ 2' => 2,
  ]],
  3 => ['name' => 'เทคโนโลยีเพื่องานประยุกต์', 'cap_credits'=>18, 'subjects' => [
    '7201301 เครือข่ายคอมพิวเตอร์เบื้องต้น' => 3,
    '7202302 ระบบปฏิบัติการ' => 3,
    '7202303 โครงสร้างข้อมูลและระบบฐานข้อมูล' => 3,
    '7202304 ระบบสารสนเทศทางภูมิศาสตร์เบื้องต้น' => 3,
    '7202305 การออกแบบและพัฒนาเว็บเบื้องต้น' => 3,
    '7203306 การเขียนโปรแกรมอุปกรณ์เคลื่อนที่' => 3,
    '7203503 ดิจิทัลและไมโครคอนโทรลเลอร์' => 3,
    '7203607 ระบบเครือข่ายเฉพาะที่' => 3,
    '7203609 ระบบปฏิบัติการเครือข่าย' => 3,
    '7203805 การทำเหมืองข้อมูล' => 3,
    '7203808 เว็บเซอร์วิส' => 3,
  ]],
  4 => ['name' => 'เทคโนโลยีและวิธีการทางซอฟต์แวร์', 'cap_credits'=>15, 'subjects' => [
    '7201401 โปรแกรมสำเร็จรูปมัลติมีเดีย' => 3,
    '7201402 การเขียนโปรแกรมแบบจินตภาพ' => 3,
    '7202403 การออกแบบปฏิสัมพันธ์ระหว่างมนุษย์และคอมพิวเตอร์' => 3,
    '7203401 การพัฒนาซอฟต์แวร์เชิงคอมโพเนนต์' => 3,
    '7203404 การออกแบบและพัฒนาเว็บขั้นสูง' => 3,
    '7203405 การเขียนโปรแกรมเชิงวัตถุ' => 3,
    '7203602 หัวข้อพิเศษด้านเทคโนโลยีสารสนเทศ' => 3,
    '7203603 การบริหารและการจัดการฐานข้อมูล' => 3,
    '7203706 การนำเสนอข้อมูลด้วยภาพ' => 3,
    '7203712 การออกแบบและพัฒนามัลติมีเดีย' => 3,
    '7203806 การพัฒนาซอฟต์แวร์ระบบการจัดการสำนักงานอัตโนมัติ' => 3,
    '7204704 การออกแบบและพัฒนาเกม' => 3,
    '7204705 การออกแบบและสร้างต้นแบบโมเดลสามมิติ' => 3,
    '7204706 องค์ประกอบศิลปะสำหรับเทคโนโลยีสารสนเทศ' => 3,
  ]],
  5 => ['name' => 'โครงสร้างพื้นฐานของระบบ', 'cap_credits'=>6, 'subjects' => [
    '7202501 เทคโนโลยีอินเทอร์เน็ต' => 3,
    '7203502 อินเทอร์เน็ตสําหรับทุกสรรพสิ่ง' => 3,
  ]],
];
$SPEC_MIN_TOTAL = 91;

/* --- เพดานวิชาเลือก --- */
$SPEC_ELECTIVE_MIN = 15; // วิชาเลือก 1 (เฉพาะด้าน) ต้องรวม ≥ 15
$FREE_ELECTIVE_MIN = 6;  // วิชาเลือก 2 (เสรี) ต้องรวม ≥ 6

/* =========================================================
   แผนที่ชื่อวิชา -> meta
========================================================= */
$CATALOG = []; // subj => ['type'=>'gen|spec', 'gid'=>int, 'cr'=>int]
foreach ($GEN_GROUPS as $gid=>$g) {
  foreach ($g['subjects'] as $s) {
    $CATALOG[$s] = ['type'=>'gen','gid'=>$gid,'cr'=>$GEN_CREDIT];
  }
}
foreach ($SPEC_GROUPS as $gid=>$g) {
  foreach ($g['subjects'] as $s=>$cr) {
    $CATALOG[$s] = ['type'=>'spec','gid'=>$gid,'cr'=>$cr];
  }
}

/* =========================================================
   โหลดค่าที่เคยบันทึก (score เก็บเป็น 0..100) -> แปลงเป็นเกรดพรีฟิล
========================================================= */
$saved = stats_get_for($username, $STATS_FILE);
$prefill_grade = [];
foreach ($saved as $row) {
  $n = $row['name'];
  $sc = floatval($row['score']); // 0..100
  $p  = max(0.0,min(4.0, ($sc/100.0)*4.0));
  $prefill_grade[$n] = point_to_grade($p);
}

/* =========================================================
   รับฟอร์ม
========================================================= */
$okmsg = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {

  if (isset($_POST['reset'])) {
    stats_set_for($username, [], $STATS_FILE);
    $prefill_grade = [];
    $okmsg = 'ล้างข้อมูลแล้ว';
  } else {

    // *** จุดที่แก้ไข 1: โหลดข้อมูลที่บันทึกไว้เดิมขึ้นมาก่อน ***
    $existing_subjects = stats_get_for($username, $STATS_FILE);
    // แปลงเป็น map เพื่อให้ง่ายต่อการอัปเดต: [ 'ชื่อวิชา' => score ]
    $subjects_map = [];
    foreach ($existing_subjects as $subject) {
        $subjects_map[$subject['name']] = $subject['score'];
    }

    $picked = []; // ยังใช้ตัวแปรนี้เพื่อรวบรวมข้อมูลจากฟอร์มเหมือนเดิม

    // ทั่วไป
    if (!empty($_POST['gen']) && is_array($_POST['gen'])) {
      foreach ($_POST['gen'] as $subj => $grade) {
        $subj = trim($subj); $grade = trim($grade);
        if ($grade==='' || !isset($CATALOG[$subj]) || $CATALOG[$subj]['type']!=='gen' || !isset($GRADE_TO_POINT[$grade])) continue;
        $picked[$subj] = ['grade'=>$grade, 'cr'=>$CATALOG[$subj]['cr'], 'type'=>'gen', 'gid'=>$CATALOG[$subj]['gid']];
      }
    }

    // รายวิชาเฉพาะด้านหลัก
    if (!empty($_POST['spec']) && is_array($_POST['spec'])) {
      foreach ($_POST['spec'] as $subj=>$grade) {
        $subj=trim($subj); $grade=trim($grade);
        if ($grade==='' || !isset($CATALOG[$subj]) || $CATALOG[$subj]['type']!=='spec' || !isset($GRADE_TO_POINT[$grade])) continue;
        $picked[$subj] = ['grade'=>$grade, 'cr'=>$CATALOG[$subj]['cr'], 'type'=>'spec', 'gid'=>$CATALOG[$subj]['gid']];
      }
    }

    // วิชาเลือก 1
    if (!empty($_POST['e1_sel']) && is_array($_POST['e1_sel'])) {
      foreach (array_keys($_POST['e1_sel']) as $subj) {
        if (!isset($CATALOG[$subj])) continue;
        $meta = $CATALOG[$subj];
        if ($meta['type']!=='spec' || !in_array($meta['gid'], [2,3,4], true)) continue;
        if (isset($picked[$subj])) continue;
        $grade = trim($_POST['e1_g'][$subj] ?? '');
        if (!isset($GRADE_TO_POINT[$grade])) continue;
        $picked[$subj] = ['grade'=>$grade, 'cr'=>$meta['cr'], 'type'=>'spec','gid'=>$meta['gid']];
      }
    }

    // วิชาเลือก 2
    if (!empty($_POST['e2_sel']) && is_array($_POST['e2_sel'])) {
      foreach (array_keys($_POST['e2_sel']) as $subj) {
        if (!isset($CATALOG[$subj])) continue;
        if (isset($picked[$subj])) continue;
        $meta = $CATALOG[$subj];
        $grade = trim($_POST['e2_g'][$subj] ?? '');
        if (!isset($GRADE_TO_POINT[$grade])) continue;
        $picked[$subj] = ['grade'=>$grade, 'cr'=>$meta['cr'], 'type'=>$meta['type'], 'gid'=>$meta['gid']];
      }
    }

    // *** จุดที่แก้ไข 2: ผสานข้อมูลใหม่เข้ากับข้อมูลเดิม ***
    foreach ($picked as $subj => $it) {
        $pt = $GRADE_TO_POINT[$it['grade']];
        $scaled_score = ($pt / 4.0) * 100.0;
        // อัปเดตหรือเพิ่มค่าใน map ของเรา
        $subjects_map[$subj] = $scaled_score;
    }

    // แปลง map กลับไปเป็น array รูปแบบเดิมเพื่อบันทึก
    $to_save = [];
    foreach ($subjects_map as $subj_name => $score) {
        $to_save[] = ['name' => $subj_name, 'score' => $score];
    }
    
    // บันทึกข้อมูลที่ผสานแล้วทั้งหมดกลับไป
    stats_set_for($username, $to_save, $STATS_FILE);

    // อัปเดต prefill_grade เพื่อให้หน้าเว็บแสดงผลถูกต้องทันทีหลังบันทึก
    $prefill_grade = [];
    foreach ($to_save as $row) {
      $prefill_grade[$row['name']] = point_to_grade(($row['score']/100)*4.0);
    }

    $okmsg = 'บันทึกข้อมูลเรียบร้อย';
  }
}

/* =========================================================
   คำนวณคะแนนรายกลุ่ม (ตามสูตรกำหนด)
   คะแนนกลุ่ม = round( ( Σ(เกรดพอยต์×นก.) / (จำนวนวิชา×3) ) / 4 * 100 )
========================================================= */
function calc_group_scores($items, $GRADE_TO_POINT) {
  $sum_by_gid = [];
  $cnt_by_gid = [];
  foreach ($items as $it) {
    $gid = $it['gid'];
    $pt = $GRADE_TO_POINT[$it['grade']] ?? 0.0;
    $cr = $it['cr'];
    $sum_by_gid[$gid] = ($sum_by_gid[$gid] ?? 0) + ($pt * $cr);
    $cnt_by_gid[$gid] = ($cnt_by_gid[$gid] ?? 0) + 1;
  }
  $score = [];
  foreach ($sum_by_gid as $gid=>$SUM) {
    $n = max(1, $cnt_by_gid[$gid]);
    $gpa = $SUM / ($n * 3.0);
    if ($gpa < 0) $gpa = 0; if ($gpa > 4) $gpa = 4;
    $score[$gid] = round(($gpa/4.0)*100); // 0..100
  }
  return $score;
}

/* รวม choices จาก prefill ไปคำนวณ */
$chosen = [];
foreach ($prefill_grade as $subj=>$grade) {
  if (!isset($CATALOG[$subj])) continue;
  $meta = $CATALOG[$subj];
  $chosen[] = ['name'=>$subj, 'grade'=>$grade, 'cr'=>$meta['cr'], 'type'=>$meta['type'], 'gid'=>$meta['gid']];
}
$chosen_gen  = array_values(array_filter($chosen, fn($x)=>$x['type']==='gen'));
$chosen_spec = array_values(array_filter($chosen, fn($x)=>$x['type']==='spec'));

$gen_scores_raw  = calc_group_scores($chosen_gen,  $GRADE_TO_POINT);
$spec_scores_raw = calc_group_scores($chosen_spec, $GRADE_TO_POINT);

/* Labels + Data */
$gen_labels = [ 'ภาษาและการสื่อสาร', 'สังคมศาสตร์', 'มนุษยศาสตร์', 'วิทยาศาสตร์/คณิตฯ/เทคโนโลยี' ];
$gen_data   = [];
for ($gid=1; $gid<=4; $gid++) { $gen_data[] = $gen_scores_raw[$gid] ?? 0; }

$spec_labels = [ 'วิชาแกน', 'องค์การและระบบสารสนเทศ', 'เทคโนโลยีเพื่องานประยุกต์', 'เทคโนโลยีและวิธีการซอฟต์แวร์', 'โครงสร้างพื้นฐาน' ];
$spec_data   = [];
for ($gid=1; $gid<=5; $gid++) { $spec_data[] = $spec_scores_raw[$gid] ?? 0; }

$gen_labels_json  = json_encode($gen_labels, JSON_UNESCAPED_UNICODE);
$gen_scores_json  = json_encode($gen_data);
$spec_labels_json = json_encode($spec_labels, JSON_UNESCAPED_UNICODE);
$spec_scores_json = json_encode($spec_data);

/* option เกรด */
function render_grade_options($selected='') {
  $g = ['A','B+','B','C+','C','D+','D','E'];
  $out = '<option value="">— เกรด —</option>';
  foreach ($g as $x) {
    $sel = ($x===$selected) ? ' selected' : '';
    $out .= "<option value=\"$x\"$sel>$x</option>";
  }
  return $out;
}

/* ===== ค่าเพดานฝั่ง JS ===== */
$SPEC_CAPS = [];
foreach ($SPEC_GROUPS as $gid=>$g){ $SPEC_CAPS[$gid] = $g['cap_credits']; }
$SPEC_CAPS_JSON = json_encode($SPEC_CAPS, JSON_UNESCAPED_UNICODE);
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>แผนเกรด (UI เจาะลึกเป็นชั้น) + กราฟ 2 หมวด</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<style>
  :root{
    --bg: #12161d;
    --panel: #1c2230;
    --panel-2: #16202b;
    --text: #f4f7ff;
    --muted: #a8b2c9;
    --border: #2a3344;
    --accent-line: #3ba5b4;
    --accent-fill: rgba(59,165,180,.18);
    --std: #ffd84d;
    --std-fill: rgba(255,216,77,.14);
  }
  html,body{margin:0;background:var(--bg);color:var(--text);font-family:'Prompt',system-ui,Arial}
  .topbar{position:sticky;top:0;z-index:10;background:#0d1219cc;backdrop-filter:blur(8px);border-bottom:1px solid var(--border);padding:.7rem 1rem;display:flex;gap:8px;align-items:center;justify-content:space-between}
  .pill{background:#243046;border:1px solid var(--border);padding:.35rem .7rem;border-radius:10px;color:#fff;text-decoration:none}
  .container{display:grid;grid-template-columns:560px 1fr;gap:16px;align-items:start;padding:16px}
  .card{background:var(--panel);border:1px solid var(--border);border-radius:16px;box-shadow:0 10px 28px rgba(0,0,0,.35)}
  .panel{padding:16px}
  .muted{color:var(--muted);font-size:.92rem}
  .badge{display:inline-block;background:#1a2a37;border:1px solid var(--border);border-radius:999px;padding:.15rem .5rem;color:#dfe9ff}
  .ok{background:#133628;border:1px solid #1f5a45;border-radius:10px;padding:.55rem .7rem;margin-bottom:8px;color:#b9f0cd}
  .btn{background:var(--accent-line);border-color:transparent;color:#06252a;font-weight:700;cursor:pointer;border-radius:12px;padding:.55rem .8rem}
  .btn:hover{filter:brightness(1.06)}
  .btn-muted{background:#7b8596;color:#0a0f16}

  /* ===== Drilldown buttons ===== */
  .bigMenu{display:grid;grid-template-columns:1fr 1fr;gap:10px}
  .bigItem{background:var(--panel-2);border:1px solid var(--border);border-radius:14px;padding:14px;cursor:pointer;display:flex;justify-content:space-between;align-items:center}
  .bigItem:hover{outline:2px solid #274757}
  .bigItem h3{margin:0}
  .hidden{display:none}

  .groupList{display:grid;gap:8px;margin-top:10px}
  .groupBtn{display:flex;justify-content:space-between;align-items:center;background:#141c27;border:1px solid var(--border);border-radius:12px;padding:.6rem .75rem;cursor:pointer}
  .groupBtn:hover{border-color:#34677a}
  .groupInner{margin:8px 0 2px 0;border-left:3px solid #2f4353;padding-left:10px}
  .row{display:grid;grid-template-columns:1fr 86px 140px;gap:8px;align-items:center;margin:.35rem 0}
  .row .cr{color:var(--muted);text-align:center}
  select{background:#1b2636;color:#e8f2ff;border:1px solid var(--border);border-radius:10px;padding:.45rem .55rem}
  .inline{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
  .note{margin-top:8px}

  .two-graphs{display:grid;grid-template-columns:1fr;gap:16px}
  .graphWrap{padding:10px 10px 16px}
  .graphHead{display:flex;justify-content:space-between;align-items:center;padding:10px 12px 0 12px}
  canvas{width:100% !important; height:auto !important; background:transparent; aspect-ratio: 2 / 1}
  @media(max-width:1100px){ .container{grid-template-columns:1fr} .bigMenu{grid-template-columns:1fr} }
</style>
</head>
<body>
  <div class="topbar">
    <div>👤 <?= htmlspecialchars($fullname) ?></div>
    <div style="display:flex;gap:8px">
      <a class="pill" href="home.php">หน้าเควส</a>
      <a class="pill" href="logout.php">ออกจากระบบ</a>
    </div>
  </div>

  <div class="container">
    <!-- ====== ฟอร์ม + UI เจาะลึก ====== -->
    <div class="card panel">
      <h2 style="margin:0 0 6px 0">กรอก/อัปเดตเกรดรายวิชา (UI เจาะลึก)</h2>
      <div class="muted">สูตรกราฟ: <span class="badge">Σ(เกรดพอยต์×หน่วยกิต)/(จำนวนวิชา×3)</span> → 0–100 • โซนมาตรฐาน 60</div>
      <?php if ($okmsg): ?><div class="ok"><?= htmlspecialchars($okmsg) ?></div><?php endif; ?>

      <form method="post" style="margin:.4rem 0" id="resetForm">
        <input type="hidden" name="reset" value="1">
        <button class="btn btn-muted">ลบข้อมูลทั้งหมด</button>
      </form>

      <form method="post" autocomplete="off" id="mainForm">
        <!-- ชั้นที่ 1: เลือกหมวดใหญ่ -->
        <div class="section">
          <h3 style="margin:0 0 10px 0">เลือกหมวดใหญ่</h3>
          <div class="bigMenu">
            <div class="bigItem" data-open="#genRoot">
              <h3>หมวดวิชาทั่วไป</h3>
              <span class="badge">≥ <?= $GEN_TOTAL_MIN ?> นก. (ทุกวิชา 3)</span>
            </div>
            <div class="bigItem" data-open="#specRoot">
              <h3>หมวดวิชาเฉพาะด้าน</h3>
              <span class="badge">≥ <?= $SPEC_MIN_TOTAL ?> นก.</span>
            </div>
          </div>
        </div>

        <!-- ชั้นที่ 2 (หมวดทั่วไป) -->
        <div id="genRoot" class="section hidden">
          <div class="inline" style="justify-content:space-between">
            <h3 style="margin:0">หมวดวิชาทั่วไป</h3>
            <button type="button" class="btn" data-close="#genRoot">ย้อนกลับ</button>
          </div>
          <div class="muted">คลิก “ชื่อกลุ่ม” เพื่อกางรายวิชา</div>

          <div class="groupList">
            <?php foreach ($GEN_GROUPS as $gid=>$g): ?>
              <div class="groupCard">
                <div class="groupBtn" data-toggle="#genGroup<?= $gid ?>">
                  <div><?= htmlspecialchars($g['name']) ?> <span class="badge">ต้อง ≥ <?= $g['min_credits'] ?> นก.</span></div>
                  <div class="muted">คลิกเพื่อกาง</div>
                </div>
                <div id="genGroup<?= $gid ?>" class="groupInner hidden">
                  <?php foreach ($g['subjects'] as $s): $sel = $prefill_grade[$s] ?? ''; ?>
                    <div class="row">
                      <div><?= htmlspecialchars($s) ?></div>
                      <div class="cr"><?= $GEN_CREDIT ?> นก.</div>
                      <div><select name="gen[<?= htmlspecialchars($s) ?>]"><?= render_grade_options($sel) ?></select></div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- ชั้นที่ 2 (หมวดเฉพาะด้าน) -->
        <div id="specRoot" class="section hidden">
          <div class="inline" style="justify-content:space-between">
            <h3 style="margin:0">หมวดวิชาเฉพาะด้าน</h3>
            <button type="button" class="btn" data-close="#specRoot">ย้อนกลับ</button>
          </div>
          <div class="muted">คลิก “ชื่อกลุ่ม” เพื่อกางรายวิชา • ระบบล็อคหน่วยกิตต่อกลุ่มให้อัตโนมัติ</div>

          <div class="groupList">
            <?php foreach ($SPEC_GROUPS as $gid=>$g): ?>
              <div class="groupCard">
                <div class="groupBtn" data-toggle="#specGroup<?= $gid ?>">
                  <div><?= htmlspecialchars($g['name']) ?> <span class="badge">เพดาน <?= $g['cap_credits'] ?> นก.</span></div>
                  <div class="muted">คลิกเพื่อกาง</div>
                </div>
                <div id="specGroup<?= $gid ?>" class="groupInner hidden">
                  <?php foreach ($g['subjects'] as $s=>$cr): $sel = $prefill_grade[$s] ?? ''; ?>
                    <div class="row">
                      <div><?= htmlspecialchars($s) ?></div>
                      <div class="cr"><?= $cr ?> นก.</div>
                      <div>
                        <select
                          name="spec[<?= htmlspecialchars($s) ?>]"
                          class="specSel"
                          data-gid="<?= $gid ?>"
                          data-cr="<?= $cr ?>"
                        ><?= render_grade_options($sel) ?></select>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>

            <!-- วิชาเลือก 1 -->
            <div class="groupCard">
              <div class="groupBtn" data-toggle="#elective1">
                <div>วิชาเลือก 1 — เฉพาะด้าน (กลุ่ม 2–4)</div>
                <div class="muted">ต้อง ≥ <?= $SPEC_ELECTIVE_MIN ?> นก. (ล็อคไม่เกิน 15)</div>
              </div>
              <div id="elective1" class="groupInner hidden">
                <?php
                  $alreadyChosen = array_fill_keys(array_keys($prefill_grade), true);
                  $e1_list = [];
                  foreach ([2,3,4] as $gidx) {
                    foreach ($SPEC_GROUPS[$gidx]['subjects'] as $s=>$cr) {
                      if (!isset($alreadyChosen[$s])) {
                        $e1_list[] = ['s'=>$s,'cr'=>$cr,'gid'=>$gidx];
                      }
                    }
                  }
                  if (!$e1_list) {
                    echo '<div class="muted">ไม่มีรายวิชาที่เหลือจากกลุ่ม 2–4</div>';
                  } else {
                    foreach ($e1_list as $row):
                      $s=$row['s']; $cr=$row['cr']; $gid=$row['gid'];
                    ?>
                    <div class="row" style="grid-template-columns:1.2rem 1fr 86px 140px;">
                      <input type="checkbox" name="e1_sel[<?= htmlspecialchars($s) ?>]" value="1" class="e1cb" data-cr="<?= $cr ?>">
                      <div><?= htmlspecialchars($s) ?><div class="muted" style="font-size:.84rem">จากกลุ่ม: <?= htmlspecialchars($SPEC_GROUPS[$gid]['name']) ?></div></div>
                      <div class="cr"><?= $cr ?> นก.</div>
                      <div><select name="e1_g[<?= htmlspecialchars($s) ?>]" class="e1g"><?= render_grade_options('') ?></select></div>
                    </div>
                    <?php endforeach;
                  }
                ?>
                <div class="note"><span class="badge" id="e1_sum">0</span> หน่วยกิตที่เลือก (ต้อง ≥ <?= $SPEC_ELECTIVE_MIN ?>, สูงสุด 15)</div>
              </div>
            </div>

            <!-- วิชาเลือก 2 -->
            <div class="groupCard">
              <div class="groupBtn" data-toggle="#elective2">
                <div>วิชาเลือก 2 — เสรี</div>
                <div class="muted">ต้อง ≥ <?= $FREE_ELECTIVE_MIN ?> นก. (ล็อคไม่เกิน 6)</div>
              </div>
              <div id="elective2" class="groupInner hidden">
                <?php
                  $already = array_fill_keys(array_keys($prefill_grade), true);
                  $free_list = [];
                  foreach ($CATALOG as $s=>$meta) {
                    if (isset($already[$s])) continue;
                    $free_list[] = ['s'=>$s, 'meta'=>$meta];
                  }
                  if (!$free_list) {
                    echo '<div class="muted">ไม่มีรายวิชาที่เหลือให้เลือก</div>';
                  } else {
                    foreach ($free_list as $row):
                      $s   = $row['s']; $m = $row['meta']; $cr = $m['cr'];
                      $lab = ($m['type']==='gen' ? 'ทั่วไป: ' . $GEN_GROUPS[$m['gid']]['name'] : 'เฉพาะ: ' . $SPEC_GROUPS[$m['gid']]['name']);
                    ?>
                    <div class="row" style="grid-template-columns:1.2rem 1fr 86px 140px;">
                      <input type="checkbox" name="e2_sel[<?= htmlspecialchars($s) ?>]" value="1" class="e2cb" data-cr="<?= $cr ?>">
                      <div><?= htmlspecialchars($s) ?><div class="muted" style="font-size:.84rem"><?= htmlspecialchars($lab) ?></div></div>
                      <div class="cr"><?= $cr ?> นก.</div>
                      <div><select name="e2_g[<?= htmlspecialchars($s) ?>]" class="e2g"><?= render_grade_options('') ?></select></div>
                    </div>
                    <?php endforeach;
                  }
                ?>
                <div class="note"><span class="badge" id="e2_sum">0</span> หน่วยกิตที่เลือก (ต้อง ≥ <?= $FREE_ELECTIVE_MIN ?>, สูงสุด 6)</div>
              </div>
            </div>
          </div>
        </div>

        <div class="inline" style="justify-content:flex-end;margin-top:10px">
          <button class="btn">บันทึกทั้งหมด</button>
        </div>
      </form>
      <div class="muted" style="margin-top:8px">• ชี้เมาส์บน “จุด” ที่กราฟเพื่อดูคะแนนของกลุ่มนั้น • โซนเหลือง (โปร่ง) = คะแนนมาตรฐาน 60</div>
    </div>

    <!-- ====== กราฟ 2 ชุด ====== -->
    <div class="two-graphs">
      <div class="card graphWrap">
        <div class="graphHead">
          <h3 style="margin:0">กราฟ — หมวดวิชาทั่วไป</h3>
          <span class="badge">มาตรฐาน 60</span>
        </div>
        <div style="padding:10px 12px">
          <canvas id="radarGen"></canvas>
        </div>
      </div>

      <div class="card graphWrap">
        <div class="graphHead">
          <h3 style="margin:0">กราฟ — หมวดวิชาเฉพาะด้าน</h3>
          <span class="badge">มาตรฐาน 60</span>
        </div>
        <div style="padding:10px 12px">
          <canvas id="radarSpec"></canvas>
        </div>
      </div>
    </div>
  </div>

<script>
/* ===== เปิด/ปิด panel ชั้นต่าง ๆ ===== */
document.addEventListener('click', (e)=>{
  const open = e.target.closest('[data-open]');
  if (open) {
    const sel = open.getAttribute('data-open');
    document.querySelectorAll('#genRoot,#specRoot').forEach(el=>el.classList.add('hidden'));
    const el = document.querySelector(sel);
    if (el) el.classList.remove('hidden');
  }
  const close = e.target.closest('[data-close]');
  if (close) {
    const sel = close.getAttribute('data-close');
    const el = document.querySelector(sel);
    if (el) el.classList.add('hidden');
  }
  const tog = e.target.closest('[data-toggle]');
  if (tog) {
    const sel = tog.getAttribute('data-toggle');
    const el = document.querySelector(sel);
    if (el) el.classList.toggle('hidden');
  }
});

/* ===== เพดานหน่วยกิตของแต่ละกลุ่มเฉพาะด้าน (5 กลุ่ม) ===== */
const SPEC_CAPS = <?= $SPEC_CAPS_JSON ?>; // {gid: cap}

/* ===== ล็อคหน่วยกิต “รายกลุ่มเฉพาะด้าน” ===== */
function enforceSpecCaps() {
  const used = {};
  document.querySelectorAll('.specSel').forEach(sel=>{
    const gid = sel.dataset.gid;
    const cr  = parseFloat(sel.dataset.cr || '0');
    if (sel.value) used[gid] = (used[gid]||0) + cr;
  });
  document.querySelectorAll('.specSel').forEach(sel=>{
    const gid = sel.dataset.gid;
    const cr  = parseFloat(sel.dataset.cr || '0');
    const cap = SPEC_CAPS[gid] || Infinity;
    const current = used[gid] || 0;
    if (!sel.value) {
      sel.disabled = (current + cr) > cap;
    } else {
      sel.disabled = false;
    }
  });
}

/* ===== ล็อคหน่วยกิต Elective 1/2 ===== */
const E1_MAX = 15;
const E2_MAX = 6;

function sumAndLockElectives() {
  // E1
  let e1 = 0;
  const e1cbs = Array.from(document.querySelectorAll('.e1cb'));
  e1cbs.forEach(cb=>{ if (cb.checked) e1 += parseFloat(cb.dataset.cr || '0'); });
  document.getElementById('e1_sum').textContent = e1;
  if (e1 >= E1_MAX) {
    e1cbs.forEach(cb=>{ if (!cb.checked) cb.disabled = true; });
  } else {
    e1cbs.forEach(cb=> cb.disabled = false);
  }

  // E2
  let e2 = 0;
  const e2cbs = Array.from(document.querySelectorAll('.e2cb'));
  e2cbs.forEach(cb=>{ if (cb.checked) e2 += parseFloat(cb.dataset.cr || '0'); });
  document.getElementById('e2_sum').textContent = e2;
  if (e2 >= E2_MAX) {
    e2cbs.forEach(cb=>{ if (!cb.checked) cb.disabled = true; });
  } else {
    e2cbs.forEach(cb=> cb.disabled = false);
  }
}

document.addEventListener('change', (e)=>{
  if (e.target.classList.contains('specSel')) enforceSpecCaps();
  if (e.target.classList.contains('e1cb') || e.target.classList.contains('e2cb')) sumAndLockElectives();
  if (e.target.classList.contains('e1cb') || e.target.classList.contains('e2cb')) {
    const row = e.target.closest('.row');
    if (row) {
      const sel = row.querySelector('select');
      if (e.target.checked && sel && !sel.value) sel.focus();
    }
  }
});

// init
enforceSpecCaps();
sumAndLockElectives();

/* ===== Chart.js สไตล์โปร่ง เห็นเส้นกริด + โซนมาตรฐาน ===== */
const commonRadarOptions = {
  responsive: true,
  maintainAspectRatio: false,
  scales: {
    r: {
      min: 0, max: 100,
      grid: { color: '#60708a', lineWidth: 1.1, circular: true },
      angleLines: { color: '#6f809a', lineWidth: 1.2 },
      pointLabels: { color:'#e9ecff', font:{size:16, weight:'500'} },
      ticks: { color:'#cfe2ff', showLabelBackdrop:false, stepSize: 10 }
    }
  },
  plugins: {
    legend: { labels: { color:'#e9ecff', font:{size:14} } },
    tooltip: {
      enabled: true,
      displayColors: false,
      callbacks: { label: (ctx) => 'คะแนน: ' + ctx.formattedValue },
      backgroundColor: 'rgba(15,22,30,0.97)',
      titleColor: '#e9ecff', bodyColor:'#e9ecff',
      borderColor:getComputedStyle(document.documentElement).getPropertyValue('--accent-line').trim() || '#3ba5b4',
      borderWidth:2, borderRadius:12,
      bodyFont:{ size:16, weight:'bold' }, titleFont:{size:14, weight:'bold'},
      padding:12, caretSize:8, caretPadding:8
    }
  },
  animation: { duration: 450 }
};

function standardDataset(len) {
  const arr = Array(len).fill(60);
  return {
    label: 'มาตรฐาน (60)',
    data: arr,
    fill: true,
    backgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--std-fill').trim() || 'rgba(255,216,77,.14)',
    borderColor: getComputedStyle(document.documentElement).getPropertyValue('--std').trim() || '#ffd84d',
    borderWidth: 2,
    borderDash: [6, 4],
    pointRadius: 0,
    order: 1
  };
}

function actualDataset(data) {
  const cs = getComputedStyle(document.documentElement);
  return {
    label: 'คะแนน',
    data: data,
    fill: true,
    backgroundColor: cs.getPropertyValue('--accent-fill').trim() || 'rgba(59,165,180,.18)',
    borderColor: cs.getPropertyValue('--accent-line').trim() || '#3ba5b4',
    borderWidth: 3,
    pointBackgroundColor: '#fff',
    pointBorderColor: cs.getPropertyValue('--accent-line').trim() || '#3ba5b4',
    pointBorderWidth: 2,
    pointRadius: 4,
    pointHoverRadius: 6,
    tension: 0,
    order: 2
  };
}

function makeRadar(canvasId, labels, data) {
  const el = document.getElementById(canvasId);
  if (!el) return;
  const ctx = el.getContext('2d');
  new Chart(ctx, {
    type: 'radar',
    data: {
      labels: labels,
      datasets: [
        standardDataset(labels.length),
        actualDataset(data),
      ]
    },
    options: commonRadarOptions
  });
}

makeRadar('radarGen',  <?= $gen_labels_json  ?>, <?= $gen_scores_json  ?>);
makeRadar('radarSpec', <?= $spec_labels_json ?>, <?= $spec_scores_json ?>);

/* ====== บันทึกข้อมูลกราฟไว้ที่ localStorage เพื่อให้หน้า home.php ดึงไปใช้ ====== */
(function saveGraphsToLocalStorage(){
  try {
    localStorage.setItem('graph_gen_labels', JSON.stringify(<?= $gen_labels_json ?>));
    localStorage.setItem('graph_gen_scores', JSON.stringify(<?= $gen_scores_json ?>));
    localStorage.setItem('graph_spec_labels', JSON.stringify(<?= $spec_labels_json ?>));
    localStorage.setItem('graph_spec_scores', JSON.stringify(<?= $spec_scores_json ?>));
    localStorage.setItem('graph_saved_at', Date.now().toString());
  } catch(e) { /* เงียบไว้ */ }
})();

/* ===== เคลียร์ localStorage เมื่อกด "ลบข้อมูลทั้งหมด" ===== */
const resetForm = document.getElementById('resetForm');
if (resetForm) {
  resetForm.addEventListener('submit', ()=>{
    try {
      ['graph_gen_labels','graph_gen_scores','graph_spec_labels','graph_spec_scores','graph_saved_at']
        .forEach(k=>localStorage.removeItem(k));
    } catch(e){}
  });
}
</script>
</body>
</html>
