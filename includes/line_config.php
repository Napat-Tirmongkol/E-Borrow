<?php
// line_config.php
// เก็บค่าลับสำหรับ LINE Login

// *** กรุณากรอกค่าจริงที่คุณได้จาก LINE Developers Console ***
define('LINE_LOGIN_CHANNEL_ID', '2008476166');
define('LINE_LOGIN_CHANNEL_SECRET', '991a612c343dc2ed766cf399a4913cf5');

define('LINE_MESSAGING_API_TOKEN', 'dO49wXbHvt22YCTmZFqyVWtBXLgG+HzsvptogPUU7V79hAIbHZ7ik0onvRCbkmhvLsBoEnKV5HxhbhHqpx5L/IE1zc8vA3WsgWYOYSQFLXvcFCgHwEy99DJf4LZeBcyzEYFXjgDENVvj65bH8Nhw4QdB04t89/1O/w1cDnyilFU=');

// 1. (แก้ไข) กำหนด Base URL ให้ถูกต้อง (ตามโฟลเดอร์ของคุณ)
$base_url = "https://healthycampus.rsu.ac.th/e_Borrow_test";

// 2. (แก้ไข) สร้าง Path ที่ถูกต้องโดยใช้ Base URL
define('LINE_LOGIN_CALLBACK_URL', $base_url . '/callback.php');
define('STAFF_LOGIN_URL', $base_url . '/admin/login.php');

?>