<?php
// add_staff_process.php
// (ไฟล์ใหม่)

// 1. "จ้างยาม" และ "เชื่อมต่อ DB"
include('../includes/check_session_ajax.php');
require_once('../includes/db_connect.php');
require_once('../includes/log_function.php'); // ◀️ (เพิ่ม) เรียกใช้ Log

// 2. ตรวจสอบสิทธิ์ Admin และตั้งค่า Header
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์ดำเนินการ']);
    exit;
}
header('Content-Type: application/json');

// 3. สร้างตัวแปรสำหรับเก็บคำตอบ
$response = ['status' => 'error', 'message' => 'เกิดข้อผิดพลาดไม่ทราบสาเหตุ'];

// 4. ตรวจสอบว่าเป็นการส่งข้อมูลแบบ POST หรือไม่
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 5. รับข้อมูลจากฟอร์ม AJAX
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
    $role     = isset($_POST['role']) ? trim($_POST['role']) : 'employee';

    if (empty($username) || empty($password) || empty($full_name)) {
        $response['message'] = 'ข้อมูลที่ส่งมาไม่ครบถ้วน (Username, Password, ชื่อ-สกุล)';
        echo json_encode($response);
        exit;
    }
    if ($role != 'admin' && $role != 'employee') {
        $response['message'] = 'สิทธิ์ (Role) ไม่ถูกต้อง';
        echo json_encode($response);
        exit;
    }
	if (!in_array($role, ['admin', 'employee', 'editor'])) {
        $response['message'] = 'สิทธิ์ (Role) ไม่ถูกต้อง';

    // 6. ดำเนินการ INSERT
    try {
        // 6.1 ตรวจสอบว่า Username นี้ถูกใช้ไปหรือยัง
        $stmt_check_user = $pdo->prepare("SELECT id FROM med_users WHERE username = ?");
        $stmt_check_user->execute([$username]);
        if ($stmt_check_user->fetch()) {
            throw new Exception("Username '$username' นี้ถูกใช้งานแล้ว");
        }
        
        // 6.2 เข้ารหัสรหัสผ่าน
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // 6.3 (SQL) INSERT ข้อมูลเข้า med_users
        $sql = "INSERT INTO med_users (username, password_hash, full_name, role) 
                VALUES (?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username, $password_hash, $full_name, $role]);

        $new_user_id = $pdo->lastInsertId();

        // ◀️ --- (เพิ่มส่วน Log) --- ◀️
        if ($stmt->rowCount() > 0) {
            $admin_user_id = $_SESSION['user_id'] ?? null;
            $admin_user_name = $_SESSION['full_name'] ?? 'System';
            $log_desc = "Admin '{$admin_user_name}' (ID: {$admin_user_id}) ได้เพิ่มบัญชีพนักงานใหม่: '{$full_name}' (Username: {$username}, Role: {$role}) (ID ใหม่: {$new_user_id})";
            log_action($pdo, $admin_user_id, 'create_staff', $log_desc);
        }
        // ◀️ --- (จบส่วน Log) --- ◀️

        // 7. ถ้าสำเร็จ ให้เปลี่ยนคำตอบ
        $response['status'] = 'success';
        $response['message'] = 'เพิ่มบัญชีพนักงานสำเร็จ';

    } catch (Exception $e) {
        $response['message'] = $e->getMessage(); // ◀️ (แก้ไข)
    }

} else {
    $response['message'] = 'ต้องใช้วิธี POST เท่านั้น';
}

// 8. ส่งคำตอบ (JSON) กลับไปให้ JavaScript
echo json_encode($response);
exit;
?>