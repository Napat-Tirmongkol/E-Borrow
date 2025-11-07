<?php
// add_equipment_type_process.php
// (ไฟล์ใหม่)

// 1. "จ้างยาม" และ "เชื่อมต่อ DB"
include('includes/check_session_ajax.php');
require_once('db_connect.php');
require_once('includes/log_function.php');

// 2. ตรวจสอบสิทธิ์ Admin และตั้งค่า Header
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์ดำเนินการ']);
    exit;
}
header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'เกิดข้อผิดพลาดไม่ทราบสาเหตุ'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 3. รับข้อมูลจากฟอร์ม
    $name        = isset($_POST['name']) ? trim($_POST['name']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : null;

    if (empty($name)) {
        $response['message'] = 'กรุณากรอกชื่อประเภทอุปกรณ์';
        echo json_encode($response);
        exit;
    }

    // 4. ดำเนินการ INSERT
    try {
        // (เช็คชื่อซ้ำ)
        $stmt_check = $pdo->prepare("SELECT id FROM med_equipment_types WHERE name = ?");
        $stmt_check->execute([$name]);
        if ($stmt_check->fetch()) {
            throw new Exception("ชื่อประเภทอุปกรณ์ '$name' นี้มีในระบบแล้ว");
        }

        $sql = "INSERT INTO med_equipment_types (name, description, total_quantity, available_quantity) VALUES (?, ?, 0, 0)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $description]);

        $response['status'] = 'success';
        $response['message'] = 'เพิ่มประเภทอุปกรณ์ใหม่สำเร็จ';

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }

} else {
    $response['message'] = 'ต้องใช้วิธี POST เท่านั้น';
}

echo json_encode($response);
exit;
?>