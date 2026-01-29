<?php
// includes/check_student_session_ajax.php
// "ยาม" สำหรับไฟล์ AJAX ของ Student
// จะตอบกลับเป็น JSON Error แทนการ Redirect

@session_start();

if (!isset($_SESSION['student_id']) || $_SESSION['student_id'] == 0) {
    // ถ้า Session Student ไม่มี
    header('Content-Type: application/json');
    http_response_code(401); // 401 Unauthorized
    echo json_encode(['status' => 'error', 'message' => 'Session หมดอายุ, กรุณา Log in ใหม่อีกครั้ง']);
    exit;
}

// --- ⏳ ตั้งค่า Timeout (วินาที) ---
$timeout_duration = 1800; // 1800 วินาที = 30 นาที

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

// ถ้ามี Session ให้ทำงานต่อไป
?>