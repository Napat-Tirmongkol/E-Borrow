<?php
// process/approve_request_process.php
include('../includes/check_session.php');
require_once('../includes/db_connect.php');
require_once('../includes/log_function.php');

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['transaction_id'])) {
    
    $transaction_id = $_POST['transaction_id'];
    $original_item_id = $_POST['original_item_id']; // ตัวที่ระบบจองให้ตอนแรก
    $selected_item_id = $_POST['selected_item_id']; // ตัวที่ Admin เลือกจริง (อาจเป็นตัวเดิมหรือตัวใหม่)
    $admin_id = $_SESSION['user_id'];

    try {
        $pdo->beginTransaction();

        // 1. ตรวจสอบว่ามีการเปลี่ยนชิ้นอุปกรณ์หรือไม่
        if ($original_item_id != $selected_item_id) {
            
            // 1.1 คืนสภาพตัวเดิมให้เป็น 'available' (ถ้าตัวเดิมไม่ใช่ 0 หรือค่าว่าง)
            if (!empty($original_item_id)) {
                $stmt_release = $pdo->prepare("UPDATE med_equipment_items SET status = 'available' WHERE id = ?");
                $stmt_release->execute([$original_item_id]);
            }

            // 1.2 ปรับสถานะตัวใหม่เป็น 'borrowed'
            // ตรวจสอบก่อนว่าตัวใหม่ว่างจริงไหม (กันพลาด)
            $stmt_check = $pdo->prepare("SELECT status FROM med_equipment_items WHERE id = ?");
            $stmt_check->execute([$selected_item_id]);
            $new_item_status = $stmt_check->fetchColumn();

            if ($new_item_status != 'available') {
                throw new Exception("อุปกรณ์ที่เลือก (ID: $selected_item_id) ไม่ว่างในขณะนี้");
            }

            $stmt_borrow = $pdo->prepare("UPDATE med_equipment_items SET status = 'borrowed' WHERE id = ?");
            $stmt_borrow->execute([$selected_item_id]);
        }
        
        // 2. อัปเดตข้อมูลใน Transaction (เปลี่ยนสถานะเป็น approved และอัปเดต item_id เป็นตัวที่เลือก)
        $sql = "UPDATE med_transactions 
                SET approval_status = 'approved', 
                    approver_id = ?, 
                    item_id = ?,      -- อัปเดต Item ID จริง
                    equipment_id = ?  -- อัปเดต Equipment ID (FK) ให้ตรงกัน
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$admin_id, $selected_item_id, $selected_item_id, $transaction_id]);

        $pdo->commit();
        writeLog($pdo, $admin_id, "Approve request ID: $transaction_id (Item: $selected_item_id)", "approve");

        $_SESSION['success'] = "อนุมัติคำขอเรียบร้อยแล้ว (มอบอุปกรณ์ ID: $selected_item_id)";

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
} else {
    $_SESSION['error'] = "ข้อมูลไม่ครบถ้วน";
}

header("Location: ../admin/index.php");
exit();
?>