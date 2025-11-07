<?php
// 1. "จ้างยาม" และ "เชื่อมต่อ DB"
include('includes/check_session_ajax.php');
require_once('db_connect.php');
require_once('includes/log_function.php'); // ◀️ (เพิ่ม) เรียกใช้ Log

// 2. ตรวจสอบสิทธิ์ (อนุญาต Admin และ Employee)
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'employee'])) {
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
    $equipment_id   = isset($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : 0;
    $transaction_id = isset($_POST['transaction_id']) ? (int)$_POST['transaction_id'] : 0;

    if ($equipment_id == 0 || $transaction_id == 0) {
        $response['message'] = 'ข้อมูล Transaction ID หรือ Equipment ID ไม่ครบถ้วน';
        echo json_encode($response);
        exit;
    }

    // 6. เริ่ม Transaction (การคืน)
    try {
        $pdo->beginTransaction();

        // 6.1 UPDATE อุปกรณ์ (item) เป็น 'available'
        $sql_update_item = "UPDATE med_equipment_items SET status = 'available' WHERE id = ? AND status = 'borrowed'";
        $stmt_update_item = $pdo->prepare($sql_update_item);
        $stmt_update_item->execute([$equipment_id]);
        
        // 6.2 UPDATE ประวัติการยืม (transaction) เป็น 'returned'
        $sql_update_trans = "UPDATE med_transactions SET status = 'returned', return_date = NOW() WHERE id = ? AND status = 'borrowed'";
        $stmt_update_trans = $pdo->prepare($sql_update_trans);
        $stmt_update_trans->execute([$transaction_id]);
        
        // 6.3 (ใหม่) UPDATE จำนวนในประเภท (type)
        if ($stmt_update_item->rowCount() > 0) {
            $stmt_get_type = $pdo->prepare("SELECT type_id FROM med_equipment_items WHERE id = ?");
            $stmt_get_type->execute([$equipment_id]);
            $type_id = $stmt_get_type->fetchColumn();
            if ($type_id) {
                $stmt_type = $pdo->prepare("UPDATE med_equipment_types SET available_quantity = available_quantity + 1 WHERE id = ?");
                $stmt_type->execute([$type_id]);
            }
        }

        // 6.4 ตรวจสอบว่าสำเร็จ
        if ($stmt_update_item->rowCount() == 0 || $stmt_update_trans->rowCount() == 0) {
            throw new Exception("ไม่สามารถคืนอุปกรณ์ได้ (อาจถูกคืนไปแล้ว หรือข้อมูลผิดพลาด)");
        }

        // ◀️ --- (เพิ่มส่วน Log) --- ◀️
        $admin_user_id = $_SESSION['user_id'] ?? null;
        $admin_user_name = $_SESSION['full_name'] ?? 'System';
        $log_desc = "Admin '{$admin_user_name}' (ID: {$admin_user_id}) ได้รับคืนอุปกรณ์ (EID: {$equipment_id}) จากคำสั่งยืม (TID: {$transaction_id})";
        log_action($pdo, $admin_user_id, 'process_return', $log_desc);
        // ◀️ --- (จบส่วน Log) --- ◀️
        
        $pdo->commit();
        
        // 7. ถ้าสำเร็จ
        $response['status'] = 'success';
        $response['message'] = 'รับคืนอุปกรณ์เรียบร้อย';

    } catch (Exception $e) {
        $pdo->rollBack();
        $response['message'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    }

} else {
    $response['message'] = 'ต้องใช้วิธี POST เท่านั้น';
}

// 8. ส่งคำตอบ (JSON) กลับไปให้ JavaScript
echo json_encode($response);
exit;
?>