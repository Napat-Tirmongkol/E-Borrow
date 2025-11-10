<?php
// process/request_borrow_process.php
// (ไฟล์ใหม่) รับคำขอยืมจากนักศึกษา

// 1. "จ้างยาม" และ "เชื่อมต่อ DB"
// (ใช้ "ยาม" ตัวใหม่ที่เราเพิ่งสร้าง)
require_once('../includes/check_student_session_ajax.php'); 

require_once('../includes/db_connect.php');
require_once('../includes/log_function.php');

header('Content-Type: application/json');
$response = ['status' => 'error', 'message' => 'เกิดข้อผิดพลาดไม่ทราบสาเหตุ'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 2. รับข้อมูลจากฟอร์ม
    $type_id = isset($_POST['type_id']) ? (int)$_POST['type_id'] : 0;
    $student_id = $_SESSION['student_id']; // (ดึงจาก Session)
    $reason = isset($_POST['reason_for_borrowing']) ? trim($_POST['reason_for_borrowing']) : '';
    $staff_id = isset($_POST['lending_staff_id']) ? (int)$_POST['lending_staff_id'] : 0;
    $due_date = isset($_POST['due_date']) ? $_POST['due_date'] : null;

    if ($type_id == 0 || $staff_id == 0 || empty($reason) || $due_date == null) {
        $response['message'] = 'ข้อมูลที่ส่งมาไม่ครบถ้วน (เหตุผล, ผู้ดูแล, หรือวันที่คืน)';
        echo json_encode($response);
        exit;
    }

    // 3. เริ่ม Transaction
    try {
        $pdo->beginTransaction();

        // 3.1 ค้นหา "ชิ้น" อุปกรณ์ (item) ที่ว่าง
        $stmt_find = $pdo->prepare("SELECT id FROM med_equipment_items WHERE type_id = ? AND status = 'available' LIMIT 1 FOR UPDATE");
        $stmt_find->execute([$type_id]);
        $item_id = $stmt_find->fetchColumn();

        if (!$item_id) {
            throw new Exception("อุปกรณ์ประเภทนี้ถูกยืมไปหมดแล้วในขณะนี้");
        }

        // 3.2 "จอง" อุปกรณ์ชิ้นนั้น (เปลี่ยนสถานะ item)
        $stmt_item = $pdo->prepare("UPDATE med_equipment_items SET status = 'borrowed' WHERE id = ?");
        $stmt_item->execute([$item_id]);

        // 3.3 "ลด" จำนวนของว่างในประเภท (type)
        $stmt_type = $pdo->prepare("UPDATE med_equipment_types SET available_quantity = available_quantity - 1 WHERE id = ? AND available_quantity > 0");
        $stmt_type->execute([$type_id]);
        
        if ($stmt_item->rowCount() == 0 || $stmt_type->rowCount() == 0) {
             throw new Exception("ไม่สามารถอัปเดตสต็อกอุปกรณ์ได้");
        }

        // 3.4 "สร้าง" คำขอยืม (transaction)
        // (เราต้องใส่ทั้ง type_id, item_id และ equipment_id (ซึ่งก็คือ item_id) ตาม Schema)
        $sql_trans = "INSERT INTO med_transactions 
                        (type_id, item_id, equipment_id, borrower_student_id, reason_for_borrowing, lending_staff_id, due_date, 
                         status, approval_status, quantity) 
                      VALUES 
                        (?, ?, ?, ?, ?, ?, ?, 
                         'borrowed', 'pending', 1)";
        
        $stmt_trans = $pdo->prepare($sql_trans);
        $stmt_trans->execute([
            $type_id, $item_id, $item_id, $student_id, $reason, $staff_id, $due_date
        ]);

        $pdo->commit();

        $response['status'] = 'success';
        $response['message'] = 'ส่งคำขอยืมสำเร็จ! กรุณารอ Admin อนุมัติ';

    } catch (Exception $e) {
        $pdo->rollBack();
        $response['message'] = $e->getMessage();
    }

} else {
    $response['message'] = 'ต้องใช้วิธี POST เท่านั้น';
}

// 4. ส่งคำตอบ
echo json_encode($response);
exit;
?>