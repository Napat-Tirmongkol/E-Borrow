<?php
// approve_request_process.php
// (แก้ไข: 1. แก้ไข SQL ให้ดึง type_id/item_id 2. ลบตรรกะการค้นหา item ใหม่)

include('../includes/check_session_ajax.php');
require_once('../includes/db_connect.php');
require_once('../includes/log_function.php');

header('Content-Type: application/json');
$response = ['status' => 'error', 'message' => 'Invalid request'];

$allowed_roles = ['admin', 'employee', 'editor']; // (อนุญาต employee และ editor ด้วย)
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    $response['message'] = 'Unauthorized';
    echo json_encode($response);
    exit;
}

// 2. รับ ID ของ Transaction (คำขอ)
$transaction_id = isset($_POST['transaction_id']) ? (int)$_POST['transaction_id'] : 0;
if ($transaction_id == 0) {
    $response['message'] = 'Invalid Transaction ID';
    echo json_encode($response);
    exit;
}

try {
    // 3. เริ่ม Database Transaction
    $pdo->beginTransaction();

    // 4. (แก้ไข) ดึง type_id, item_id และ approval_status
    //    (เราจะใช้ type_id และ item_id สำหรับการบันทึก Log)
    $stmt_get = $pdo->prepare("SELECT type_id, item_id, approval_status FROM med_transactions WHERE id = ? FOR UPDATE");
    $stmt_get->execute([$transaction_id]);
    $transaction = $stmt_get->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        throw new Exception("ไม่พบคำขอนี้");
    }
    if ($transaction['approval_status'] != 'pending') {
        throw new Exception("คำขอนี้ถูกดำเนินการไปแล้ว (ไม่ใช่ Pending)");
    }
    
    $type_id = $transaction['type_id'];
    $item_id = $transaction['item_id']; // (สำหรับ Log)

    // 5. (ลบ) ลบตรรกะการค้นหา item ใหม่ทั้งหมด (บรรทัด 37-56 เดิม)
    //    (เพราะ item ถูกจองไว้แล้วตอนส่งคำขอ)

    // 6. (ลบ) ลบการ UPDATE med_equipment_items (บรรทัด 59 เดิม)
    //    (เพราะ status ถูกตั้งเป็น 'borrowed' แล้วตอนส่งคำขอ)

    // 7. (แก้ไข) อัปเดต med_transactions
    //    (เราจะอัปเดตเฉพาะ approval_status และ borrow_date (ให้เป็นเวลาปัจจุบัน))
    $stmt_trans = $pdo->prepare("UPDATE med_transactions SET approval_status = 'approved', borrow_date = NOW() WHERE id = ?");
    $stmt_trans->execute([$transaction_id]);

    // 8. บันทึก Log
    if ($stmt_trans->rowCount() > 0) {
        $admin_user_id = $_SESSION['user_id'] ?? null;
        $admin_user_name = $_SESSION['full_name'] ?? 'System';
        // (ใช้ $type_id และ $item_id ที่ดึงมา)
        $log_desc = "Admin '{$admin_user_name}' (ID: {$admin_user_id}) ได้อนุมัติคำขอ (TID: {$transaction_id}) สำหรับอุปกรณ์ (Type ID: {$type_id}, Item ID: {$item_id})";
        log_action($pdo, $admin_user_id, 'approve_request', $log_desc);
    } else {
        throw new Exception("ไม่สามารถอัปเดตสถานะคำขอได้");
    }

    // 9. ยืนยัน
    $pdo->commit();
    $response = ['status' => 'success', 'message' => 'อนุมัติคำขอเรียบร้อย!'];

} catch (Exception $e) {
    // 10. ย้อนกลับ
    $pdo->rollBack();
    $response['message'] = $e->getMessage(); 
}

echo json_encode($response);
exit;
?>