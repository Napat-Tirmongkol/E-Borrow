<?php
// includes/check_student_session.php
// (เวอร์ชันแก้ไขที่ใช้ empty() เพื่อดักจับ '0')

// 1. เริ่ม Session ก่อนเสมอ
@session_start();

// 2. ตรวจสอบว่า 'student_id' มีอยู่จริง และ "ไม่ใช่ค่าว่าง" (0, null, "")
if (empty($_SESSION['student_id'])) {
    
    // 3. (แก้ไข) ถ้าไม่มีค่า ให้ส่งกลับไปหน้า Login เท่านั้น
    //    (ห้ามทำลาย Session เพราะอาจจะแค่กำลังโหลดช้า)
    
    // 4. ส่งกลับไปหน้า Login
    header("Location: line_login.php");
    exit; // จบการทำงาน
}

// 5. ถ้ามี Session 'student_id' ที่ "ไม่ใช่ 0" (Log in แล้ว)
//    สคริปต์ก็จะทำงานต่อไปได้
?>