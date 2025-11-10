<?php
// get_return_form_data.php
// (อัปเดต: JOIN ตาราง med_students)
// ดึงข้อมูลสำหรับ Popup "ยืนยันการรับคืน"

// 1. "จ้างยาม" และ "เชื่อมต่อ DB"
include('../includes/check_session_ajax.php');
require_once('../includes/db_connect.php');

$allowed_roles = ['admin', 'employee', 'editor'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์ดำเนินการ']);
    exit;
}
header('Content-Type: application/json');

// 3. สร้างตัวแปรสำหรับเก็บคำตอบ
$response = [
    'status' => 'error', 
    'message' => 'เกิดข้อผิดพลาดไม่ทราบสาเหตุ',
    'transaction' => null
];

// 4. รับ ID อุปกรณ์จาก URL
$equipment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($equipment_id == 0) {
    $response['message'] = 'ไม่ได้ระบุ ID อุปกรณ์';
    echo json_encode($response);
    exit;
}

try {
    // 5. (SQL ใหม่) ดึงข้อมูลการยืม (Transaction) ที่ยัง "active"
    //    (JOIN ใหม่กับ med_students)
    $sql = "SELECT 
                t.id as transaction_id, 
                t.borrow_date, 
                t.due_date,
                ei.name as equipment_name, 
                ei.serial_number as equipment_serial,
                s.full_name as borrower_name, /* (มาจาก med_students) */
                s.phone_number as borrower_contact /* (มาจาก med_students) */
            FROM med_transactions t
            JOIN med_equipment_items ei ON t.equipment_id = ei.id
            /* (JOIN ใหม่) ใช้ borrower_student_id เชื่อมไปยัง med_students */
           /* (JOIN ... ) */
            LEFT JOIN med_students s ON t.borrower_student_id = s.id
            WHERE t.equipment_id = ? AND t.status = 'borrowed'
              AND t.approval_status IN ('approved', 'staff_added') /* <-- เพิ่มบรรทัดนี้ */
            ORDER BY t.borrow_date DESC
            LIMIT 1"; // เอาเฉพาะรายการล่าสุดที่ยังไม่คืน

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$equipment_id]);
    $transaction_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($transaction_data) {
        $response['status'] = 'success';
        $response['transaction'] = $transaction_data;
        $response['message'] = 'ดึงข้อมูลสำเร็จ';
    } else {
        $response['message'] = 'ไม่พบข้อมูลการยืม (อาจถูกคืนไปแล้ว)';
    }

} catch (PDOException $e) {
    $response['message'] = 'เกิดข้อผิดพลาด DB: ' . $e->getMessage(); // ◀️ (แก้ไข)
}

// 6. ส่งคำตอบ (JSON) กลับไปให้ JavaScript
echo json_encode($response);
exit;
?>