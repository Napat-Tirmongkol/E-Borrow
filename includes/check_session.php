<?php
@session_start(); // ◀️ (ย้ายมาไว้บนสุด)

// 1. ตรวจสอบว่า "บัตรพนักงาน" (Session 'user_id') ถูกสร้างหรือยัง
if (!isset($_SESSION['user_id'])) {
    
    // 3. ถ้ายังไม่มี (ยังไม่ Log in)
    //    ให้ส่งกลับไปหน้า Log in ทันที
    header("Location: ../admin/login.php");
    exit; // จบการทำงานของสคริปต์ทันที
}

// --- ⏳ ตั้งค่า Timeout (วินาที) ---
$timeout_duration = 60; // 1800 วินาที = 30 นาที

// ตรวจสอบว่ามีการใช้งานล่าสุดเมื่อไหร่
if (isset($_SESSION['LAST_ACTIVITY'])) {
    // ถ้าเวลาปัจจุบัน - เวลาล่าสุด มากกว่า Timeout
    if ((time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
        // หมดเวลา: เคลียร์ค่าและดีดออก
        session_unset();     // ลบตัวแปร Session
        session_destroy();   // ทำลาย Session บน Server
        
        // เคลียร์ Session Cookie ที่ฝั่ง User
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        // ส่งกลับไปหน้า Login พร้อมแจ้งเตือน
        // (ปรับ Path ตามความเหมาะสมของไฟล์นั้นๆ ว่าต้องถอย ../ หรือไม่)
        header("Location: ../index.php?timeout=1"); 
        exit;
    }
}

// อัปเดตเวลาล่าสุดเสมอเมื่อมีการโหลดหน้าใหม่
$_SESSION['LAST_ACTIVITY'] = time();

// 4. ถ้ามี Session 'user_id' อยู่แล้ว (Log in แล้ว)
//    สคริปต์ก็จะทำงานข้ามไป และอนุญาตให้แสดงเนื้อหาของหน้าต่อไปได้
?>