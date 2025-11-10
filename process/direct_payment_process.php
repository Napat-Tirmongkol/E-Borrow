<?php
// direct_payment_process.php
// (เวอร์ชันอัปเกรด: 2.0 - รองรับการอัปโหลดสลิป)

include('../includes/check_session_ajax.php');
require_once('../includes/db_connect.php');
require_once('../includes/log_function.php');

$allowed_roles = ['admin', 'editor'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์ดำเนินการ']);
    exit;
}

header('Content-Type: application/json');
$response = ['status' => 'error', 'message' => 'เกิดข้อผิดพลาดไม่ทราบสาเหตุ'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 1. รับข้อมูลจาก Form (อัปเดต)
    $transaction_id = isset($_POST['transaction_id']) ? (int)$_POST['transaction_id'] : 0;
    $student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
    $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
    $amount_paid = isset($_POST['amount_paid']) ? (float)$_POST['amount_paid'] : 0;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : null;
    $staff_id = $_SESSION['user_id'];
    
    // [เพิ่ม] รับ fields ใหม่
    $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'cash';
    $payment_slip_url = null; // (ค่าเริ่มต้น)
    
    // [ลบ] receipt_number (ตั้งเป็น NULL)
    $receipt_number = null; 

    if ($transaction_id == 0 || $student_id == 0 || $amount <= 0 || $amount_paid <= 0) {
        $response['message'] = 'ข้อมูลที่ส่งมาไม่ครบถ้วน';
        echo json_encode($response);
        exit;
    }

   try {
        $pdo->beginTransaction();

        // 2. [เพิ่ม] ตรรกะการอัปโหลดไฟล์
        if ($payment_method == 'bank_transfer') {
            if (isset($_FILES['payment_slip']) && $_FILES['payment_slip']['error'] == 0) {
                
                // ✅ โค้ดใหม่: START
                $upload_dir_server = '../uploads/slips/'; // Path สำหรับ Server (PHP)
                $upload_dir_db = 'uploads/slips/';     // Path สำหรับ Database (HTML)
                // ✅ โค้ดใหม่: END

                // (สร้าง Folder ถ้ายังไม่มี)
                if (!is_dir($upload_dir_server)) {
                    mkdir($upload_dir_server, 0755, true);
                }
                
                $file_extension = pathinfo($_FILES['payment_slip']['name'], PATHINFO_EXTENSION);
                $new_filename = 'slip-' . $transaction_id . '-' . uniqid() . '.' . strtolower($file_extension);
                
                // ✅ โค้ดใหม่: START
                $target_file_server = $upload_dir_server . $new_filename; // (Path Server)
                $target_file_db = $upload_dir_db . $new_filename;     // (Path DB)
                // ✅ โค้ดใหม่: END

                $check = getimagesize($_FILES['payment_slip']['tmp_name']);
                if ($check !== false) {
                    // ✅ โค้ดใหม่: ใช้ $target_file_server
                    if (move_uploaded_file($_FILES['payment_slip']['tmp_name'], $target_file_server)) {
                        // ✅ โค้ดใหม่: ใช้ $target_file_db
                        $payment_slip_url = $target_file_db; // (ได้ URL ที่จะเก็บลง DB)
                    } else {
                        throw new Exception("ไม่สามารถย้ายไฟล์สลิปที่อัปโหลดได้");
                    }
                } else {
                    throw new Exception("ไฟล์ที่แนบมาไม่ใช่ไฟล์รูปภาพ");
                }
            } else {
                // (ถ้าเลือกโอนเงินแต่ไม่มีไฟล์แนบ)
                throw new Exception("เลือกวิธีโอนเงิน แต่ไม่พบไฟล์สลิป");
            }
        }

        // 3. สร้างรายการค่าปรับ (med_fines)
        $sql_fine = "INSERT INTO med_fines 
                        (transaction_id, student_id, amount, notes, created_by_staff_id, status) 
                     VALUES (?, ?, ?, ?, ?, 'paid')"; 
        $stmt_fine = $pdo->prepare($sql_fine);
        $stmt_fine->execute([$transaction_id, $student_id, $amount, $notes, $staff_id]);
        $new_fine_id = $pdo->lastInsertId();

        // 4. [แก้ไข] สร้างรายการชำระเงิน (med_payments) - เพิ่ม fields ใหม่
        $sql_pay = "INSERT INTO med_payments 
                        (fine_id, amount_paid, payment_method, payment_slip_url, received_by_staff_id, receipt_number) 
                    VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_pay = $pdo->prepare($sql_pay);
        $stmt_pay->execute([$new_fine_id, $amount_paid, $payment_method, $payment_slip_url, $staff_id, $receipt_number]);
        $new_payment_id = $pdo->lastInsertId();

        // 5. อัปเดตตาราง med_transactions ให้มีสถานะ 'paid'
        $sql_trans = "UPDATE med_transactions SET fine_status = 'paid' WHERE id = ?";
        $stmt_trans = $pdo->prepare($sql_trans);
        $stmt_trans->execute([$transaction_id]);

        // 6. บันทึก Log
        $admin_user_name = $_SESSION['full_name'] ?? 'System';
        $log_desc = "Admin '{$admin_user_name}' (ID: {$staff_id}) ได้รับชำระเงิน (แบบ Direct, {$payment_method}) 
                     สำหรับ (TID: {$transaction_id}) ยอด: {$amount_paid} บาท (FineID: {$new_fine_id}, PayID: {$new_payment_id})";
        log_action($pdo, $staff_id, 'direct_payment', $log_desc);

        $pdo->commit();

        $response['status'] = 'success';
        $response['message'] = 'บันทึกการชำระเงินสำเร็จ';
        $response['new_payment_id'] = $new_payment_id;

    } catch (Exception $e) {
        $pdo->rollBack();
        $response['message'] = $e->getMessage();
    }

} else {
    $response['message'] = 'ต้องใช้วิธี POST เท่านั้น';
}

echo json_encode($response);
exit;
?>