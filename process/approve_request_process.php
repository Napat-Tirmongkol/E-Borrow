<?php
// process/approve_request_process.php (ฉบับแก้ไข: Logic ตรวจสอบสถานะที่ถูกต้อง)
include('../includes/check_session.php');
require_once('../includes/db_connect.php');
require_once('../includes/log_function.php');

// ตรวจสอบว่าเป็น POST และมีค่า transaction_id ส่งมา
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['transaction_id'])) {
    
    $transaction_id = $_POST['transaction_id'];
    $selected_item_id = $_POST['selected_item_id']; // ไอเท็มที่ Admin เลือกจาก Dropdown
    
    // ดึง ID ของคนอนุมัติ (รองรับทั้ง user_id และ id กันพลาด)
    $admin_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0;

    if (empty($selected_item_id)) {
        $_SESSION['error'] = "กรุณาเลือกอุปกรณ์ (Serial Number)";
        header("Location: ../admin/index.php");
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. [สำคัญ] ดึงข้อมูลเดิมจาก Database โดยตรง (ไม่ใช้ค่าจาก Form เพื่อความแม่นยำ)
        // เพื่อดูว่า "จริงๆ แล้ว" รายการนี้จอง item_id ไหนไว้อยู่
        $stmt_chk = $pdo->prepare("SELECT item_id FROM med_transactions WHERE id = ?");
        $stmt_chk->execute([$transaction_id]);
        $current_item_id = $stmt_chk->fetchColumn(); 

        // 2. เปรียบเทียบของที่เลือกใหม่ (Selected) กับของเดิม (Current)
        if ($selected_item_id != $current_item_id) {
            
            // === กรณีมีการเปลี่ยนชิ้นอุปกรณ์ ===
            
            // 2.1 ปล่อยของเดิมให้ว่าง (ถ้ามีค่า และไม่ใช่ 0)
            if (!empty($current_item_id)) {
                $stmt_release = $pdo->prepare("UPDATE med_equipment_items SET status = 'available' WHERE id = ?");
                $stmt_release->execute([$current_item_id]);
            }

            // 2.2 เช็คของชิ้นใหม่ว่าว่างจริงไหม
            $stmt_status = $pdo->prepare("SELECT status FROM med_equipment_items WHERE id = ?");
            $stmt_status->execute([$selected_item_id]);
            $new_item_status = $stmt_status->fetchColumn();

            if ($new_item_status !== 'available') {
                // ถ้าสถานะไม่ใช่ available แสดงว่าถูกคนอื่นแย่งไปแล้ว (จริงๆ)
                throw new Exception("อุปกรณ์ชิ้นที่เลือก (ID: $selected_item_id) ไม่ว่าง (สถานะ: $new_item_status)");
            }

            // 2.3 จองของชิ้นใหม่
            $stmt_borrow = $pdo->prepare("UPDATE med_equipment_items SET status = 'borrowed' WHERE id = ?");
            $stmt_borrow->execute([$selected_item_id]);

        } else {
            // === กรณีเลือกชิ้นเดิม ===
            // ไม่ต้องทำอะไรกับสถานะ items เพราะมันถูกจอง (borrowed) โดยรายการนี้ถูกต้องอยู่แล้ว
            // (ข้ามไปขั้นตอน Update Transaction เลย)
        }
        
        // 3. อัปเดตสถานะคำขอเป็น 'approved' และบันทึกข้อมูลผู้อนุมัติ
        $sql = "UPDATE med_transactions 
                SET approval_status = 'approved', 
                    approver_id = ?, 
                    item_id = ?,      -- บันทึก ID ชิ้นที่เลือกจริง
                    equipment_id = ?  -- อัปเดต Foreign Key ให้ตรงกัน
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        // ใช้ $selected_item_id ใส่ทั้งช่อง item_id และ equipment_id
        $stmt->execute([$admin_id, $selected_item_id, $selected_item_id, $transaction_id]);

        $pdo->commit();
        
        // บันทึก Log (ถ้ามีฟังก์ชันนี้)
        if(function_exists('writeLog')){
            writeLog($pdo, $admin_id, "Approve request ID: $transaction_id (Selected Item: $selected_item_id)", "approve");
        }

        $_SESSION['success'] = "อนุมัติเรียบร้อยแล้ว (มอบอุปกรณ์ ID: $selected_item_id)";

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
