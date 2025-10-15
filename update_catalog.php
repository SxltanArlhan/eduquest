<?php
header('Content-Type: text/html; charset=utf-8');

$STATS_FILE = __DIR__ . '/stats.json';
$CATALOG_FILE = __DIR__ . '/catalog.json';

// --- Functions ---
function json_read_local($p) {
    $d = @file_get_contents($p);
    return $d ? (json_decode($d, true) ?: []) : [];
}

function json_write_local($p, $data) {
    file_put_contents($p, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// --- Main Logic ---
$stats_data = json_read_local($STATS_FILE);
$catalog_data = json_read_local($CATALOG_FILE);

$all_subject_names = [];
// วนลูปผู้ใช้ทุกคนใน stats.json
foreach ($stats_data as $username => $subjects) {
    if (is_array($subjects)) {
        foreach ($subjects as $subject) {
            if (!empty($subject['name'])) {
                // เก็บชื่อวิชาที่ไม่ซ้ำกัน
                $all_subject_names[$subject['name']] = true;
            }
        }
    }
}

$new_subjects_found = 0;
foreach (array_keys($all_subject_names) as $subject_name) {
    // ถ้าชื่อวิชานี้ยังไม่มีใน catalog
    if (!isset($catalog_data[$subject_name])) {
        // เพิ่มเข้าไปใหม่ พร้อมค่าเริ่มต้น
        $catalog_data[$subject_name] = [
            "type" => "uncategorized", // รอการจัดกลุ่ม
            "gid" => 0,
            "cr" => 3
        ];
        $new_subjects_found++;
    }
}

// ถ้ามีการเพิ่มวิชาใหม่ ให้บันทึกไฟล์
if ($new_subjects_found > 0) {
    json_write_local($CATALOG_FILE, $catalog_data);
}

// --- Display Result ---
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>อัปเดต Catalog</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; padding: 2em; background: #f4f4f4; }
        .container { max-width: 800px; margin: auto; background: #fff; padding: 2em; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .success { color: green; font-weight: bold; }
        .neutral { color: navy; }
    </style>
</head>
<body>
    <div class="container">
        <h1>ผลการอัปเดต Catalog</h1>
        <?php if ($new_subjects_found > 0): ?>
            <p class="success">พบและเพิ่มวิชาใหม่จำนวน <?= $new_subjects_found ?> วิชาลงใน catalog.json สำเร็จ!</p>
            <p><strong>ขั้นตอนต่อไป:</strong> กรุณาเปิดไฟล์ <code>catalog.json</code> และแก้ไขข้อมูลของวิชาใหม่ที่มีค่า <code>"type": "uncategorized"</code> ให้ถูกต้อง</p>
        <?php else: ?>
            <p class="neutral">ไม่พบวิชาใหม่ใน stats.json ไฟล์ catalog ของคุณเป็นปัจจุบันแล้ว</p>
        <?php endif; ?>
    </div>
</body>
</html>