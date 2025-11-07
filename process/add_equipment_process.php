<?php
// 1. "จ้างยาม" และ "เชื่อมต่อ DB"
include('includes/check_session_ajax.php');
require_once('db_connect.php');
require_once('includes/log_function.php'); // ◀️ (เพิ่ม) เรียกใช้ Log

// 2. ตรวจสอบสิทธิ์ Admin และตั้งค่า Header
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์ดำเนินการ']);
    exit;
}
header('Content-Type: application/json');

// 3. สร้างตัวแปรสำหรับเก็บคำตอบ
$response = ['status' => 'error', 'message' => 'เกิดข้อผิดพลาดไม่ทราบสาเหตุ'];

// 4. ตรวจสอบว่าเป็นการส่งข้อมูลแบบ POST หรือไม่
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 5. รับข้อมูลจากฟอร์ม
    $name          = isset($_POST['name']) ? trim($_POST['name']) : '';
    $serial_number = isset($_POST['serial_number']) ? trim($_POST['serial_number']) : null;
    $description   = isset($_POST['description']) ? trim($_POST['description']) : null;
    
    // (รับไฟล์)
    $image_url_to_db = null;
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] == 0) {
        $upload_dir = 'images/';
        $file_extension = pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION);
        $new_filename = uniqid('equip-', true) . '.' . strtolower($file_extension);
        $target_file = $upload_dir . $new_filename;
        $check = getimagesize($_FILES['image_file']['tmp_name']);
        if ($check !== false) {
            if (move_uploaded_file($_FILES['image_file']['tmp_name'], $target_file)) {
                $image_url_to_db = $target_file;
            }
        }
    }

    if (empty($name)) {
        $response['message'] = 'กรุณากรอกชื่ออุปกรณ์';
        echo json_encode($response);
        exit;
    }

    if (empty($serial_number)) $serial_number = null;
    if (empty($description)) $description = null;

    // 6. ดำเนินการ INSERT
    try {
        $sql = "INSERT INTO med_equipment (name, serial_number, description, image_url)
                VALUES (?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $serial_number, $description, $image_url_to_db]);
        
        $new_equipment_id = $pdo->lastInsertId();

        // ◀️ --- (เพิ่มส่วน Log) --- ◀️
        if ($stmt->rowCount() > 0) {
            $admin_user_id = $_SESSION['user_id'] ?? null;
            $admin_user_name = $_SESSION['full_name'] ?? 'System';
            $log_desc = "Admin '{$admin_user_name}' (ID: {$admin_user_id}) ได้เพิ่มอุปกรณ์ใหม่: '{$name}' (Serial: {$serial_number}) (ID ใหม่: {$new_equipment_id})";
            log_action($pdo, $admin_user_id, 'create_equipment', $log_desc);
        }
        // ◀️ --- (จบส่วน Log) --- ◀️

        // 7. ถ้าสำเร็จ ให้เปลี่ยนคำตอบ
        $response['status'] = 'success';
        $response['message'] = 'เพิ่มอุปกรณ์ใหม่สำเร็จ';

    } catch (PDOException $e) {
        if ($e->getCode() == '23000') { // ◀️ (แก้ไข)
             $response['message'] = 'เลขซีเรียล (Serial Number) นี้มีในระบบแล้ว';
        } else {
             $response['message'] = 'เกิดข้อผิดพลาด DB: ' . $e->getMessage(); // ◀️ (แก้ไข)
        }
    }

} else {
    $response['message'] = 'ต้องใช้วิธี POST เท่านั้น';
}

// 8. ส่งคำตอบ (JSON) กลับไปให้ JavaScript
echo json_encode($response);
exit;
?>