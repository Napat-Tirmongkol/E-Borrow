<?php
// delete_equipment_type_process.php
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

// 3. รับ ID ประเภทอุปกรณ์
$type_id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;

if ($type_id == 0) {
    $response['message'] = 'ไม่ได้ระบุ ID ประเภทอุปกรณ์';
    echo json_encode($response);
    exit;
}

// 4. ตรวจสอบและดำเนินการ
try {
    // (เช็คว่ามี "ชิ้น" อุปกรณ์ผูกอยู่หรือไม่)
    $sql_check = "SELECT COUNT(*) FROM med_equipment_items WHERE type_id = ?";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$type_id]);
    $item_count = $stmt_check->fetchColumn();

    if ($item_count > 0) {
        throw new Exception("ไม่สามารถลบได้ เนื่องจากยังมีอุปกรณ์รายชิ้นผูกอยู่กับประเภทนี้ ($item_count ชิ้น)");
    }

    // (ดึงข้อมูล "ก่อน" ลบ เพื่อใช้ใน Log)
    $stmt_get = $pdo->prepare("SELECT name FROM med_equipment_types WHERE id = ?");
    $stmt_get->execute([$type_id]);
    $type_name_for_log = $stmt_get->fetchColumn() ?: "ID: {$type_id}";

    // 5. ดำเนินการลบ
    $sql_delete = "DELETE FROM med_equipment_types WHERE id = ?";
    $stmt_delete = $pdo->prepare($sql_delete);
    $stmt_delete->execute([$type_id]);

    // 6. ตรวจสอบและบันทึก Log
    if ($stmt_delete->rowCount() > 0) {
        $admin_user_id = $_SESSION['user_id'] ?? null;
        $admin_user_name = $_SESSION['full_name'] ?? 'System';
        $log_desc = "Admin '{$admin_user_name}' (ID: {$admin_user_id}) ได้ลบประเภทอุปกรณ์: '{$type_name_for_log}'";
        log_action($pdo, $admin_user_id, 'delete_equipment_type', $log_desc);

        $response['status'] = 'success';
        $response['message'] = 'ลบประเภทอุปกรณ์สำเร็จ';
    } else {
        throw new Exception("ไม่พบประเภทอุปกรณ์ที่ต้องการลบ (ID: $type_id)");
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
?>