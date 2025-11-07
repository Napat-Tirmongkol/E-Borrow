<?php
// 1. "จ้างยาม" และ "เชื่อมต่อ DB"
include('includes/check_session_ajax.php');
require_once('db_connect.php');

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
    $equipment_id  = isset($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : 0;
    $name          = isset($_POST['name']) ? trim($_POST['name']) : '';
    $serial_number = isset($_POST['serial_number']) ? trim($_POST['serial_number']) : null;
    $status        = isset($_POST['status']) ? $_POST['status'] : '';
    $description   = isset($_POST['description']) ? trim($_POST['description']) : null;
    
    // (เราจะจัดการ $image_url_to_db ด้านล่าง)

    if (empty($serial_number)) $serial_number = null;
    if (empty($description)) $description = null;

    // 6. ตรวจสอบข้อมูล
    if ($equipment_id == 0 || empty($name) || empty($status)) {
        $response['message'] = 'ข้อมูลไม่ครบถ้วน (ID, Name, Status)';
        echo json_encode($response);
        exit;
    }

    // 7. ตรวจสอบว่า status ที่ส่งมาถูกต้อง
    if ($status != 'available' && $status != 'maintenance' && $status != 'borrowed') {
         $response['message'] = 'สถานะที่เลือกไม่ถูกต้อง';
         echo json_encode($response);
         exit;
    }
    
    try {
        // 8. (ใหม่!) ดึงข้อมูลรูปภาพเดิมก่อน
        $stmt_get_old = $pdo->prepare("SELECT status, image_url FROM med_equipment WHERE id = ?");
        $stmt_get_old->execute([$equipment_id]);
        $current_data = $stmt_get_old->fetch(PDO::FETCH_ASSOC);

        if (!$current_data) {
             throw new Exception("ไม่พบอุปกรณ์ที่ต้องการแก้ไข (ID: $equipment_id)");
        }
        
        $current_status = $current_data['status'];
        $image_url_to_db = $current_data['image_url']; // (ใช้รูปเดิมเป็นค่าเริ่มต้น)

        // 8.1 (เช็ค Status เหมือนเดิม)
        if ($current_status != 'borrowed' && $status == 'borrowed') {
            throw new Exception("ไม่สามารถเปลี่ยนสถานะเป็น 'ถูกยืม' จากหน้านี้ได้");
        }
        
        // 8.2 (ใหม่!) ตรวจสอบว่ามีการอัปโหลดไฟล์ใหม่หรือไม่
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] == 0) {
            
            $upload_dir = 'images/';
            $file_extension = pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION);
            $new_filename = uniqid('equip-', true) . '.' . strtolower($file_extension);
            $target_file = $upload_dir . $new_filename;

            // (ตรวจสอบว่าเป็นไฟล์รูปภาพจริงหรือไม่)
            $check = getimagesize($_FILES['image_file']['tmp_name']);
            if ($check !== false) {
                // (พยายามย้ายไฟล์)
                if (move_uploaded_file($_FILES['image_file']['tmp_name'], $target_file)) {
                    
                    // (ลบไฟล์เก่า ถ้ามี)
                    if (!empty($image_url_to_db) && file_exists($image_url_to_db)) {
                        @unlink($image_url_to_db); // (ใช้ @ เพื่อเมิน Error ถ้าลบไฟล์เก่าไม่สำเร็จ)
                    }
                    $image_url_to_db = $target_file; // (เปลี่ยน Path ใน DB เป็นไฟล์ใหม่)
                    
                } else {
                    throw new Exception("อัปโหลดไฟล์ใหม่ล้มเหลว (ย้ายไฟล์ไม่สำเร็จ)");
                }
            } else {
                 throw new Exception("ไฟล์ที่แนบมาไม่ใช่ไฟล์รูปภาพ");
            }
        }

        // 9. ดำเนินการ UPDATE (เพิ่ม description และ image_url)
        $sql = "UPDATE med_equipment 
                SET name = ?, serial_number = ?, status = ?, description = ?, image_url = ?
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $serial_number, $status, $description, $image_url_to_db, $equipment_id]);

        $response['status'] = 'success';
        $response['message'] = 'บันทึกการเปลี่ยนแปลงสำเร็จ';

    } catch (PDOException $e) {
        if ($e->getCode() == '23000') { // ◀️ (แก้ไข)
             $response['message'] = 'เลขซีเรียล (Serial Number) นี้มีในระบบแล้ว';
        } else {
             $response['message'] = 'เกิดข้อผิดพลาด DB: ' . $e->getMessage(); // ◀️ (แก้ไข)
        }
    } catch (Exception $e) {
         $response['message'] = $e->getMessage(); // ◀️ (แก้ไข)
    }

} else {
    $response['message'] = 'ต้องใช้วิธี POST เท่านั้น';
}

// 9. ส่งคำตอบ (JSON) กลับไปให้ JavaScript
echo json_encode($response);
exit;
?>