<?php
// get_equipment_type_data.php
// (ไฟล์ใหม่)

// 1. "จ้างยาม" และ "เชื่อมต่อ DB"
include('includes/check_session_ajax.php');
require_once('db_connect.php');

// 2. ตรวจสอบสิทธิ์ Admin และตั้งค่า Header
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์ดำเนินการ']);
    exit;
}
header('Content-Type: application/json');

// 3. สร้างตัวแปรสำหรับเก็บคำตอบ
$response = [
    'status' => 'error', 
    'message' => 'เกิดข้อผิดพลาดไม่ทราบสาเหตุ',
    'equipment_type' => null
];

// 4. รับ ID ประเภทอุปกรณ์จาก URL
$type_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($type_id == 0) {
    $response['message'] = 'ไม่ได้ระบุ ID ประเภทอุปกรณ์';
    echo json_encode($response);
    exit;
}

try {
    // 5. ดึงข้อมูลประเภทอุปกรณ์
    $stmt = $pdo->prepare("SELECT * FROM med_equipment_types WHERE id = ?");
    $stmt->execute([$type_id]);
    $type = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($type) {
        $response['status'] = 'success';
        $response['equipment_type'] = $type;
        $response['message'] = 'ดึงข้อมูลสำเร็จ';
    } else {
        $response['message'] = 'ไม่พบข้อมูลประเภทอุปกรณ์';
    }

} catch (PDOException $e) {
    $response['message'] = 'เกิดข้อผิดพลาด DB: ' . $e->getMessage();
}

// 6. ส่งคำตอบ (JSON) กลับไปให้ JavaScript
echo json_encode($response);
exit;
?>