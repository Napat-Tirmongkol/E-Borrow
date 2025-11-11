<?php
// [แก้ไขไฟล์: napat-tirmongkol/e-borrow/E-Borrow-c4df732f98db10bf52a8e9d7299e212b6f2abd37/process/request_borrow_process.php]

// 1. "จ้างยาม" และ "เชื่อมต่อ DB"
require_once('../includes/check_student_session_ajax.php'); 
require_once('../includes/db_connect.php');
require_once('../includes/log_function.php');

header('Content-Type: application/json');
$response = ['status' => 'error', 'message' => 'เกิดข้อผิดพลาดไม่ทราบสาเหตุ'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 2. รับข้อมูลจากฟอร์ม
    $type_id = isset($_POST['type_id']) ? (int)$_POST['type_id'] : 0;
    $student_id = $_SESSION['student_id']; 
    $reason = isset($_POST['reason_for_borrowing']) ? trim($_POST['reason_for_borrowing']) : '';
    $staff_id = isset($_POST['lending_staff_id']) ? (int)$_POST['lending_staff_id'] : 0;
    $due_date = isset($_POST['due_date']) ? $_POST['due_date'] : null;

    if ($type_id == 0 || $staff_id == 0 || empty($reason) || $due_date == null) {
        $response['message'] = 'ข้อมูลที่ส่งมาไม่ครบถ้วน (เหตุผล, ผู้ดูแล, หรือวันที่คืน)';
        echo json_encode($response);
        exit;
    }

    // ✅ (3) เพิ่ม: ส่วนจัดการไฟล์อัปโหลด
    $attachment_url_to_db = null;
    try {
        if (isset($_FILES['attachment_file']) && $_FILES['attachment_file']['error'] == 0) {
            
            $upload_dir_server = '../uploads/attachments/'; // Path สำหรับ Server (PHP)
            $upload_dir_db = 'uploads/attachments/';     // Path สำหรับ Database (HTML)
            
            if (!is_dir($upload_dir_server)) {
                mkdir($upload_dir_server, 0755, true);
            }
            
            // (ป้องกันชื่อไฟล์ภาษาไทย หรืออักขระแปลกๆ)
            $original_filename = basename($_FILES['attachment_file']['name']);
            $safe_filename = preg_replace('/[^A-Za-z0-9\._-]/', '', str_replace(' ', '_', $original_filename));
            $new_filename = 'req-' . uniqid() . '-' . $safe_filename;

            $target_file_server = $upload_dir_server . $new_filename;
            $target_file_db = $upload_dir_db . $new_filename;

            if (move_uploaded_file($_FILES['attachment_file']['tmp_name'], $target_file_server)) {
                $attachment_url_to_db = $target_file_db;
            } else {
                throw new Exception("ไม่สามารถย้ายไฟล์ที่อัปโหลดได้");
            }
        }
    } catch (Exception $e) {
        // (ถ้าอัปโหลดไฟล์ล้มเหลว ก็ไม่เป็นไร ให้ดำเนินการต่อโดยไม่มีไฟล์)
        error_log("File upload failed: " . $e->getMessage());
    }
    // ✅ (จบส่วนจัดการไฟล์)


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
        // ✅ (4) แก้ไข SQL INSERT (ใช้คอลัมน์ใหม่ `attachment_url` ที่เราเพิ่มไปแล้ว)
        $sql_trans = "INSERT INTO med_transactions 
                        (type_id, item_id, equipment_id, borrower_student_id, reason_for_borrowing, 
                         attachment_url, -- (เพิ่มคอลัมน์นี้)
                         lending_staff_id, due_date, 
                         status, approval_status, quantity) 
                      VALUES 
                        (?, ?, ?, ?, ?, 
                         ?, -- (เพิ่ม ? นี้)
                         ?, ?, 
                         'borrowed', 'pending', 1)";
        
        $stmt_trans = $pdo->prepare($sql_trans);
        // ✅ (5) แก้ไข execute
        $stmt_trans->execute([
            $type_id, $item_id, $item_id, $student_id, $reason, 
            $attachment_url_to_db, // (เพิ่มตัวแปรนี้)
            $staff_id, $due_date
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