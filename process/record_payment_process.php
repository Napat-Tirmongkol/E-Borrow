<?php
// record_payment_process.php
// (อัปเกรด 2.0: รองรับการอัปโหลดสลิป)

include('..includes/check_session_ajax.php');
require_once('..includes/db_connect.php');
require_once('..includes/log_function.php');

$allowed_roles = ['admin', 'editor'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์ดำเนินการ']);
    exit;
}

header('Content-Type: application/json');
$response = ['status' => 'error', 'message' => 'เกิดข้อผิดพลาดไม่ทราบสาเหตุ'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 1. [แก้ไข] รับค่าใหม่
    $fine_id = isset($_POST['fine_id']) ? (int)$_POST['fine_id'] : 0;
    $amount_paid = isset($_POST['amount_paid']) ? (float)$_POST['amount_paid'] : 0;
    $staff_id = $_SESSION['user_id'];
    
    $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'cash';
    $payment_slip_url = null;
    $receipt_number = null; // (เราเลิกใช้ช่องนี้ในฟอร์มนี้แล้ว)


    if ($fine_id == 0 || $amount_paid <= 0) {
        $response['message'] = 'ข้อมูลไม่ครบถ้วน (Fine ID หรือ Amount)';
        echo json_encode($response);
        exit;
    }

  try {
        $pdo->beginTransaction();

        // 2. [เพิ่ม] ตรรกะการอัปโหลดไฟล์ (เหมือนกับไฟล์ direct_payment)
        if ($payment_method == 'bank_transfer') {
            if (isset($_FILES['payment_slip']) && $_FILES['payment_slip']['error'] == 0) {
                
                // ✅ โค้ดใหม่: START
                $upload_dir_server = '../uploads/slips/'; // Path สำหรับ Server (PHP)
                $upload_dir_db = 'uploads/slips/';     // Path สำหรับ Database (HTML)
                // ✅ โค้ดใหม่: END
                
                if (!is_dir($upload_dir_server)) {
                    mkdir($upload_dir_server, 0755, true);
                }
                
                $file_extension = pathinfo($_FILES['payment_slip']['name'], PATHINFO_EXTENSION);
                $new_filename = 'slip-fine-' . $fine_id . '-' . uniqid() . '.' . strtolower($file_extension);

                // ✅ โค้ดใหม่: START
                $target_file_server = $upload_dir_server . $new_filename; // (Path Server)
                $target_file_db = $upload_dir_db . $new_filename;     // (Path DB)
                // ✅ โค้ดใหม่: END

                $check = getimagesize($_FILES['payment_slip']['tmp_name']);
                if ($check !== false) {
                    // ✅ โค้ดใหม่: ใช้ $target_file_server
                    if (move_uploaded_file($_FILES['payment_slip']['tmp_name'], $target_file_server)) {
                        // ✅ โค้ดใหม่: ใช้ $target_file_db
                        $payment_slip_url = $target_file_db;
                    } else {
                        throw new Exception("ไม่สามารถย้ายไฟล์สลิปที่อัปโหลดได้");
                    }
                } else {
                    throw new Exception("ไฟล์ที่แนบมาไม่ใช่ไฟล์รูปภาพ");
                }
            } else {
                throw new Exception("เลือกวิธีโอนเงิน แต่ไม่พบไฟล์สลิป");
            }
        }

        // 3. ดึง transaction_id
        $stmt_get_fine = $pdo->prepare("SELECT transaction_id FROM med_fines WHERE id = ?");
        $stmt_get_fine->execute([$fine_id]);
        $transaction_id = $stmt_get_fine->fetchColumn();

        if (!$transaction_id) {
            throw new Exception("ไม่พบรายการค่าปรับ (Fine ID: $fine_id)");
        }

        // 4. [แก้ไข] INSERT ลงตาราง med_payments
        $sql_pay = "INSERT INTO med_payments 
                        (fine_id, amount_paid, payment_method, payment_slip_url, received_by_staff_id, receipt_number) 
                    VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_pay = $pdo->prepare($sql_pay);
        $stmt_pay->execute([$fine_id, $amount_paid, $payment_method, $payment_slip_url, $staff_id, $receipt_number]);
        $new_payment_id = $pdo->lastInsertId();

        // 5. อัปเดตตาราง med_fines เป็น 'paid'
        $sql_fine = "UPDATE med_fines SET status = 'paid' WHERE id = ?";
        $stmt_fine = $pdo->prepare($sql_fine);
        $stmt_fine->execute([$fine_id]);

        // 6. อัปเดตตาราง med_transactions เป็น 'paid'
        $sql_trans = "UPDATE med_transactions SET fine_status = 'paid' WHERE id = ?";
        $stmt_trans = $pdo->prepare($sql_trans);
        $stmt_trans->execute([$transaction_id]);


        if ($stmt_pay->rowCount() > 0 && $stmt_fine->rowCount() > 0) {
            
            // 7. [แก้ไข] บันทึก Log
            $admin_user_name = $_SESSION['full_name'] ?? 'System';
            $log_desc = "Admin '{$admin_user_name}' (ID: {$staff_id}) ได้รับชำระค่าปรับ (จาก Pending, {$payment_method}) 
                         จำนวน: {$amount_paid} บาท (FineID: {$fine_id}, PayID: {$new_payment_id})";
            log_action($pdo, $staff_id, 'record_payment', $log_desc);

            $pdo->commit();
            $response['status'] = 'success';
            $response['message'] = 'บันทึกการชำระเงินสำเร็จ';
            $response['new_payment_id'] = $new_payment_id;
        } else {
            throw new Exception("ไม่สามารถอัปเดตสถานะค่าปรับได้");
        }

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