<?php
// ยูทิลิตี้สำหรับอ่าน/เขียนไฟล์ JSON และจัดการสเตตัสรายผู้ใช้

function json_read($path) {
    if (!file_exists($path)) return [];
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function json_write($path, $data) {
    $tmp = $path . '.tmp';
    file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
    rename($tmp, $path);
}

// ----- จัดการสเตตัส (คะแนนรายวิชา) ต่อผู้ใช้ -----
// โครงสร้างใน stats.json:
// { "<username>": [ {"name":"คณิต", "score":80}, {"name":"อังกฤษ","score":75} ] }

function stats_get_for($username, $statsFile) {
    $all = json_read($statsFile);
    return $all[$username] ?? [];
}

function stats_set_for($username, $subjects, $statsFile) {
    $all = json_read($statsFile);
    $all[$username] = array_values($subjects);
    json_write($statsFile, $all);
}

// เพิ่ม/อัปเดตคะแนนวิชา (ถ้ามีวิชานั้นอยู่แล้วจะอัปเดต, ถ้าไม่มีจะเพิ่ม)
function stats_upsert_subject($username, $subjectName, $score, $statsFile) {
    $subjects = stats_get_for($username, $statsFile);
    $found = false;
    foreach ($subjects as &$s) {
        if (mb_strtolower($s['name']) === mb_strtolower($subjectName)) {
            $s['score'] = max(0, min(100, intval($score)));
            $found = true;
            break;
        }
    }
    if (!$found) {
        $subjects[] = ['name' => $subjectName, 'score' => max(0, min(100, intval($score)))];
    }
    stats_set_for($username, $subjects, $statsFile);
}

// เพิ่มค่าคะแนนแบบ “สะสม” (เช่น อนุมัติเควสให้ +5)
function stats_increment_subject($username, $subjectName, $delta, $statsFile) {
    $subjects = stats_get_for($username, $statsFile);
    $found = false;
    foreach ($subjects as &$s) {
        if (mb_strtolower($s['name']) === mb_strtolower($subjectName)) {
            $s['score'] = max(0, min(100, intval($s['score'] + $delta)));
            $found = true;
            break;
        }
    }
    if (!$found) {
        // ถ้ายังไม่มีวิชานี้ ให้เพิ่มใหม่โดยตั้งต้นที่ delta (ครอบ 0..100)
        $subjects[] = ['name' => $subjectName, 'score' => max(0, min(100, intval($delta)))];
    }
    stats_set_for($username, $subjects, $statsFile);
}
