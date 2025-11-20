<?php
// [สร้างไฟล์ใหม่: ajax/get_student_by_code.php]
// ไฟล์นี้ใช้ค้นหานักศึกษาจาก "รหัสนักศึกษา" (สำหรับระบบสแกน QR)

// 1. เชื่อมต่อ DB และตรวจสอบสิทธิ์
include('../includes/check_session_ajax.php');
require_once('../includes/db_connect.php');

// 2. ตรวจสอบสิทธิ์ (ให้ทั้ง Admin และ Employee ใช้ได้)
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'employee'])) {
    echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์เข้าถึง']);
    exit;
}

// 3. รับรหัสนักศึกษา (จาก QR Code)
$student_code = $_GET['id'] ?? '';

if (empty($student_code)) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบรหัส']);
    exit;
}

try {
    // 4. ค้นหาด้วย student_personnel_id (รหัสนักศึกษา)
    $stmt = $pdo->prepare("SELECT id, full_name, student_personnel_id, department, status FROM med_students WHERE student_personnel_id = ? LIMIT 1");
    $stmt->execute([$student_code]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($student) {
        // เจอ! ส่งข้อมูลกลับไป
        echo json_encode(['status' => 'success', 'student' => $student]);
    } else {
        // ไม่เจอ
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลนักศึกษารหัสนี้']);
    }

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>