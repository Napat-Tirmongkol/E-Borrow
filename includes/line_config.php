<?php
// line_config.php
// เก็บค่าลับสำหรับ LINE Login

// *** กรุณากรอกค่าจริงที่คุณได้จาก LINE Developers Console ***
define('LINE_LOGIN_CHANNEL_ID', '2008443668');
define('LINE_LOGIN_CHANNEL_SECRET', '8cfb63fcecc45c76fe49d0cab9d42e9c');

// 1. (แก้ไข) กำหนด Base URL ให้ถูกต้อง (ตามโฟลเดอร์ของคุณ)
$base_url = "https://healthycampus.rsu.ac.th/e_Borrow_test";

// 2. (แก้ไข) สร้าง Path ที่ถูกต้องโดยใช้ Base URL
define('LINE_LOGIN_CALLBACK_URL', $base_url . '/callback.php');
define('STAFF_LOGIN_URL', $base_url . '/admin/login.php');

?>