<?php
// เริ่ม Session
session_start();

// 1. ล้างข้อมูล Session ทั้งหมด
session_unset();
session_destroy();

// 2. ส่งผู้ใช้กลับไปหน้า Login ของ LINE
header("Location: login.php");
exit;
?>