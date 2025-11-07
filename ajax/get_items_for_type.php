<?php
// get_items_for_type.php
// (ไฟล์ใหม่)

include('includes/check_session_ajax.php');
require_once('db_connect.php');

header('Content-Type: application/json');
$response = [
    'status' => 'error',
    'message' => 'Invalid request',
    'type' => null,
    'items' => []
];

$type_id = isset($_GET['type_id']) ? (int)$_GET['type_id'] : 0;
if ($type_id == 0) {
    $response['message'] = 'ไม่ได้ระบุ ID ประเภทอุปกรณ์';
    echo json_encode($response);
    exit;
}

try {
    // 1. ดึงข้อมูลประเภท
    $stmt_type = $pdo->prepare("SELECT id, name FROM med_equipment_types WHERE id = ?");
    $stmt_type->execute([$type_id]);
    $type_info = $stmt_type->fetch(PDO::FETCH_ASSOC);

    if (!$type_info) {
        throw new Exception('ไม่พบประเภทอุปกรณ์นี้');
    }

    // 2. ดึงข้อมูลอุปกรณ์รายชิ้นทั้งหมดในประเภทนั้น
    $stmt_items = $pdo->prepare("SELECT * FROM med_equipment_items WHERE type_id = ? ORDER BY id DESC");
    $stmt_items->execute([$type_id]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    $response['status'] = 'success';
    $response['type'] = $type_info;
    $response['items'] = $items;

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
?>