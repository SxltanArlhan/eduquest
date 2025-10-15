<?php
require_once __DIR__ . '/config.php';

function users_file_path() {
    return __DIR__ . '/users.json';
}
function users_read() {
    $f = users_file_path();
    if (!file_exists($f)) return [];
    $raw = @file_get_contents($f);
    $data = $raw ? json_decode($raw, true) : [];
    return is_array($data) ? $data : [];
}
function users_write($arr) {
    $f = users_file_path();
    file_put_contents($f, json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
}
function users_find_by_username($username) {
    $users = users_read();
    foreach ($users as $u) {
        if (mb_strtolower($u['username']) === mb_strtolower($username)) return $u;
    }
    return null;
}
function users_upsert($user) {
    $users = users_read();
    $found = false;
    foreach ($users as &$u) {
        if (mb_strtolower($u['username']) === mb_strtolower($user['username'])) {
            $u = $user;
            $found = true;
            break;
        }
    }
    if (!$found) $users[] = $user;
    users_write($users);
}
// สร้าง admin อัตโนมัติเมื่อยังไม่มีผู้ใช้เลย (ตัวเลือก)
function ensure_bootstrap_admin($username='admin', $password='admin123') {
    $users = users_read();
    if (!$users || !users_find_by_username($username)) {
        $users[] = [
            'fullname'   => 'Administrator',
            'username'   => $username,
            'password'   => password_hash($password, PASSWORD_DEFAULT),
            'role'       => 'admin',
            'created_at' => date('c')
        ];
        users_write($users);
        return true;
    }
    return false;
}
