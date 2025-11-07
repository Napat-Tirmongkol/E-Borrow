<?php
// includes/check_session_ajax.php
// "ยาม" สำหรับไฟล์ AJAX ของ Admin/Staff
// จะตอบกลับเป็น JSON Error แทนการ Redirect

@session_start();

if (empty($_SESSION['user_id'])) {
    // ถ้า Session Admin/Staff ไม่มี
    header('Content-Type: application/json');
    http_response_code(401); // 401 Unauthorized
    echo json_encode(['status' => 'error', 'message' => 'Session หมดอายุ, กรุณา Log in ใหม่อีกครั้ง']);
    exit;
}

// ถ้ามี Session ให้ทำงานต่อไป
?>