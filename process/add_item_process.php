<?php
// add_item_process.php
// (แก้ไข: 1. แก้ไข .getMessage, 2. แก้ไขชื่อคอลัมน์ 'type_id')

include('includes/check_session_ajax.php');
require_once('db_connect.php');
require_once('includes/log_function.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์ดำเนินการ']);
    exit;
}
header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'เกิดข้อผิดพลาดไม่ทราบสาเหตุ'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $type_id       = isset($_POST['type_id']) ? (int)$_POST['type_id'] : 0;
    $name          = isset($_POST['name']) ? trim($_POST['name']) : '';
    $serial_number = isset($_POST['serial_number']) ? trim($_POST['serial_number']) : null;
    $description   = isset($_POST['description']) ? trim($_POST['description']) : null;

    if ($type_id == 0 || empty($name)) {
        $response['message'] = 'ข้อมูลไม่ครบถ้วน (Type ID หรือ Name)';
        echo json_encode($response);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. เช็ค Serial Number ซ้ำ (ถ้ามีการกรอก)
        if (!empty($serial_number)) {
            $stmt_check = $pdo->prepare("SELECT id FROM med_equipment_items WHERE serial_number = ?");
            $stmt_check->execute([$serial_number]);
            if ($stmt_check->fetch()) {
                throw new Exception("เลขซีเรียล '$serial_number' นี้มีในระบบแล้ว");
            }
        }

        // 2. INSERT ข้อมูลลง med_equipment_items
        // ◀️ (จุดที่แก้ไข) ต้องเป็น 'type_id' ตามฐานข้อมูลของคุณ
        $sql_item = "INSERT INTO med_equipment_items (type_id, name, serial_number, description, status) VALUES (?, ?, ?, ?, 'available')";
        $stmt_item = $pdo->prepare($sql_item);
        $stmt_item->execute([$type_id, $name, $serial_number, $description]);
        $new_item_id = $pdo->lastInsertId();

        // 3. อัปเดตจำนวนใน med_equipment_types
        $sql_type = "UPDATE med_equipment_types SET total_quantity = total_quantity + 1, available_quantity = available_quantity + 1 WHERE id = ?";
        $stmt_type = $pdo->prepare($sql_type);
        $stmt_type->execute([$type_id]);

        // 4. บันทึก Log
        $admin_user_id = $_SESSION['user_id'] ?? null;
        $admin_user_name = $_SESSION['full_name'] ?? 'System';
        $log_desc = "Admin '{$admin_user_name}' ได้เพิ่มอุปกรณ์ชิ้นใหม่ (ID: {$new_item_id}) ชื่อ '{$name}' (SN: {$serial_number}) เข้าไปในประเภท ID: {$type_id}";
        log_action($pdo, $admin_user_id, 'create_equipment_item', $log_desc);

        $pdo->commit();

        $response['status'] = 'success';
        $response['message'] = 'เพิ่มอุปกรณ์ชิ้นใหม่สำเร็จ';

    } catch (Exception $e) {
        $pdo->rollBack();
        $response['message'] = $e->getMessage(); // ◀️ (แก้ไข .getMessage)
    }
}

echo json_encode($response);
exit;
?>