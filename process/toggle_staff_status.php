<?php
// toggle_staff_status.php
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

// 3. สร้างตัวแปรสำหรับเก็บคำตอบ
$response = ['status' => 'error', 'message' => 'เกิดข้อผิดพลาดไม่ทราบสาเหตุ'];

// 4. ตรวจสอบว่าเป็นการส่งข้อมูลแบบ POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 5. รับ ID พนักงาน และ สถานะใหม่
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $new_status = isset($_POST['new_status']) ? $_POST['new_status'] : '';

    if ($user_id == 0 || ($new_status != 'active' && $new_status != 'disabled')) {
        $response['message'] = 'ข้อมูลที่ส่งมาไม่ถูกต้อง';
        echo json_encode($response);
        exit;
    }
    
    // (สำคัญ) ป้องกัน Admin ระงับบัญชีตัวเอง
    if ($user_id == $_SESSION['user_id']) {
         $response['message'] = 'คุณไม่สามารถระงับบัญชีของตัวเองได้';
         echo json_encode($response);
         exit;
    }

    try {
        // 6. อัปเดตฐานข้อมูล
        $sql = "UPDATE med_users SET account_status = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$new_status, $user_id]);

        if ($stmt->rowCount() > 0) {
            
            // 7. บันทึก Log
            $admin_user_id = $_SESSION['user_id'] ?? null;
            $admin_user_name = $_SESSION['full_name'] ?? 'System';
            $action_text = ($new_status == 'disabled') ? 'ระงับบัญชี' : 'เปิดใช้งานบัญชี';
            
            $stmt_get = $pdo->prepare("SELECT username FROM med_users WHERE id = ?");
            $stmt_get->execute([$user_id]);
            $staff_username = $stmt_get->fetchColumn();

            $log_desc = "Admin '{$admin_user_name}' (ID: {$admin_user_id}) ได้{$action_text}พนักงาน: '{$staff_username}' (UID: {$user_id})";
            log_action($pdo, $admin_user_id, 'toggle_staff_status', $log_desc);

            $response['status'] = 'success';
            $response['message'] = "{$action_text} สำเร็จ";
        } else {
            throw new Exception("ไม่พบบัญชีพนักงาน หรือสถานะเหมือนเดิม");
        }

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }

} else {
    $response['message'] = 'ต้องใช้วิธี POST เท่านั้น';
}

// 9. ส่งคำตอบ (JSON) กลับไปให้ JavaScript
echo json_encode($response);
exit;
?>