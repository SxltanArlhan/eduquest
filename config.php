<?php
// ---- CONFIG (ตั้งค่าได้) ----
const ADMIN_INVITE_CODE = 'JOIN-ADMIN-2025'; // รหัสเชิญสมัครเป็นแอดมิน
const ADMIN_ACTION_PIN  = '123456';          // PIN ยืนยันก่อนเข้าหน้าแอดมิน/กดอนุมัติ
const UPLOAD_DIR        = __DIR__ . '/uploads';

// สร้างโฟลเดอร์อัปโหลดหากยังไม่มี
if (!is_dir(UPLOAD_DIR)) { @mkdir(UPLOAD_DIR, 0775, true); }
