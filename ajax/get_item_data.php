<?php
// get_item_data.php
// (ไฟล์ใหม่สำหรับดึงข้อมูล Item ไปแก้ไข)

include('includes/check_session_ajax.php');
require_once('db_connect.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์ดำเนินการ']);
    exit;
}
header('Content-Type: application/json');

$response = [
    'status' => 'error',
    'message' => 'เกิดข้อผิดพลาดไม่ทราบสาเหตุ',
    'item' => null
];

$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($item_id == 0) {
    $response['message'] = 'ไม่ได้ระบุ ID อุปกรณ์';
    echo json_encode($response);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM med_equipment_items WHERE id = ?");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($item) {
        $response['status'] = 'success';
        $response['item'] = $item;
    } else {
        $response['message'] = 'ไม่พบข้อมูลอุปกรณ์';
    }

} catch (PDOException $e) {
    $response['message'] = 'เกิดข้อผิดพลาด DB: ' . $e->getMessage();
}

echo json_encode($response);
exit;
?>