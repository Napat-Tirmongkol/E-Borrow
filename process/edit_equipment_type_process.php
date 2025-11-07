<?php
// edit_equipment_type_process.php
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

// 4. ตรวจสอบว่าเป็นการส่งข้อมูลแบบ POST หรือไม่
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 5. รับข้อมูลจากฟอร์ม
    $type_id       = isset($_POST['type_id']) ? (int)$_POST['type_id'] : 0;
    $name          = isset($_POST['name']) ? trim($_POST['name']) : '';
    $description   = isset($_POST['description']) ? trim($_POST['description']) : null;
    
    if (empty($description)) $description = null;

    // 6. ตรวจสอบข้อมูล
    if ($type_id == 0 || empty($name)) {
        $response['message'] = 'ข้อมูลไม่ครบถ้วน (ID หรือ Name)';
        echo json_encode($response);
        exit;
    }
    
    try {
        // 7. ดึงข้อมูลรูปภาพเดิมก่อน
        $stmt_get_old = $pdo->prepare("SELECT image_url FROM med_equipment_types WHERE id = ?");
        $stmt_get_old->execute([$type_id]);
        $current_data = $stmt_get_old->fetch(PDO::FETCH_ASSOC);

        if (!$current_data) {
             throw new Exception("ไม่พบประเภทอุปกรณ์ที่ต้องการแก้ไข (ID: $type_id)");
        }
        
        $image_url_to_db = $current_data['image_url']; // (ใช้รูปเดิมเป็นค่าเริ่มต้น)

        // 8. ตรวจสอบว่ามีการอัปโหลดไฟล์ใหม่หรือไม่
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] == 0) {
            $upload_dir = 'images/';
            $file_extension = pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION);
            $new_filename = uniqid('equip-', true) . '.' . strtolower($file_extension);
            $target_file = $upload_dir . $new_filename;

            $check = getimagesize($_FILES['image_file']['tmp_name']);
            if ($check !== false) {
                if (move_uploaded_file($_FILES['image_file']['tmp_name'], $target_file)) {
                    if (!empty($image_url_to_db) && file_exists($image_url_to_db)) {
                        @unlink($image_url_to_db);
                    }
                    $image_url_to_db = $target_file;
                } else {
                    throw new Exception("อัปโหลดไฟล์ใหม่ล้มเหลว (ย้ายไฟล์ไม่สำเร็จ)");
                }
            } else {
                 throw new Exception("ไฟล์ที่แนบมาไม่ใช่ไฟล์รูปภาพ");
            }
        }

        // 9. ดำเนินการ UPDATE
        $sql = "UPDATE med_equipment_types 
                SET name = ?, description = ?, image_url = ?
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $description, $image_url_to_db, $type_id]);

        $response['status'] = 'success';
        $response['message'] = 'บันทึกการเปลี่ยนแปลงสำเร็จ';

    } catch (PDOException $e) {
        if ($e->getCode() == '23000') { // ◀️ (แก้ไข)
             $response['message'] = 'ชื่อประเภทอุปกรณ์นี้มีในระบบแล้ว';
        } else {
             $response['message'] = 'เกิดข้อผิดพลาด DB: ' . $e->getMessage();
        }
    } catch (Exception $e) {
         $response['message'] = $e->getMessage();
    }

} else {
    $response['message'] = 'ต้องใช้วิธี POST เท่านั้น';
}

// 10. ส่งคำตอบ (JSON) กลับไปให้ JavaScript
echo json_encode($response);
exit;
?>