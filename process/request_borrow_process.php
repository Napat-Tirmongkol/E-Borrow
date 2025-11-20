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
$attachment_url = NULL;

if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    
    $file_tmp = $_FILES['attachment']['tmp_name'];
    $file_name = $_FILES['attachment']['name'];
    $file_size = $_FILES['attachment']['size'];
    
    // 1. กำหนดนามสกุลที่อนุญาต (Whitelist) - ห้าม .php, .exe เด็ดขาด
    $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
    
    // 2. กำหนด MIME Types ที่อนุญาต (ตรวจสอบไส้ในไฟล์)
    $allowed_mimes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint', 
        'application/vnd.openxmlformats-officedocument.presentationml.presentation'
    ];

    // แยกนามสกุลไฟล์
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    // ตรวจสอบ 1: นามสกุลต้องตรงกับที่อนุญาต
    if (!in_array($file_ext, $allowed_extensions)) {
        $_SESSION['error'] = "อนุญาตเฉพาะไฟล์เอกสาร (PDF, Word, Excel) เท่านั้น";
        header("Location: ../borrow.php"); 
        exit;
    }

    // ตรวจสอบ 2: ตรวจ MIME Type จริงของไฟล์ (กันการปลอมนามสกุล)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file_tmp);
    finfo_close($finfo);

    if (!in_array($mime_type, $allowed_mimes)) {
        $_SESSION['error'] = "ไฟล์ไม่ถูกต้อง หรืออาจเป็นไฟล์อันตราย";
        header("Location: ../borrow.php");
        exit;
    }

    // ตรวจสอบ 3: ขนาดไฟล์ (เช่น ไม่เกิน 5MB)
    if ($file_size > 5 * 1024 * 1024) {
        $_SESSION['error'] = "ไฟล์มีขนาดใหญ่เกินไป (ห้ามเกิน 5MB)";
        header("Location: ../borrow.php");
        exit;
    }

    // 3. ตั้งชื่อไฟล์ใหม่ (Random Name) เพื่อป้องกันไฟล์ทับกันและป้องกันชื่อไฟล์อันตราย
    // เช่น เปลี่ยน "hack.php.pdf" เป็น "req-65123ab123.pdf"
    $new_filename = "doc-" . uniqid() . "." . $file_ext;
    $upload_dir = '../uploads/attachments/';
    
    // สร้างโฟลเดอร์ถ้ายังไม่มี
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
        // สร้างไฟล์ .htaccess เพื่อป้องกันการรันสคริปต์ในโฟลเดอร์นี้ (ความปลอดภัยสูงสุด)
        file_put_contents($upload_dir . '.htaccess', "Order Deny,Allow\nDeny from all\n<FilesMatch '\.(pdf|doc|docx|xls|xlsx|ppt|pptx)$'>\nAllow from all\n</FilesMatch>");
    }

    $destination = $upload_dir . $new_filename;

    if (move_uploaded_file($file_tmp, $destination)) {
        // เก็บ Path ลงฐานข้อมูล (ตัด ../ ออก)
        $attachment_url = 'uploads/attachments/' . $new_filename;
    } else {
        $_SESSION['error'] = "เกิดข้อผิดพลาดในการอัปโหลดไฟล์";
        header("Location: ../borrow.php");
        exit;
    }
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