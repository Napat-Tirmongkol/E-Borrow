<?php
// [แก้ไขไฟล์: process/admin_direct_borrow_process.php]
// (เวอร์ชัน V2 - รองรับหลายรายการจาก Cart)

require_once('../includes/check_session_ajax.php');
require_once('../includes/db_connect.php');

$student_id = $_POST['student_id'] ?? 0;
$due_date = $_POST['due_date'] ?? null;
$lending_staff_id = $_POST['lending_staff_id'] ?? 0;
$cart_json = $_POST['cart_data'] ?? '[]';

$cart = json_decode($cart_json, true);

if (!$student_id || !$due_date || empty($cart)) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

try {
    $pdo->beginTransaction();
    $success_count = 0;

    foreach ($cart as $item) {
        $type_id = $item['type_id'];
        $qty_needed = $item['qty'];
        $specific_ids = $item['specific_ids'] ?? []; // ID ที่สแกนมา (ถ้ามี)

        // 1. จัดการรายการที่ระบุตัวตน (Specific IDs) ก่อน
        foreach ($specific_ids as $spec_id) {
            // เปลี่ยนสถานะ Item
            $stmt = $pdo->prepare("UPDATE med_equipment_items SET status = 'borrowed' WHERE id = ? AND status = 'available'");
            $stmt->execute([$spec_id]);
            
            if ($stmt->rowCount() > 0) {
                // ลดจำนวนว่างของ Type
                $pdo->prepare("UPDATE med_equipment_types SET available_quantity = available_quantity - 1 WHERE id = ?")->execute([$type_id]);
                
                // สร้าง Transaction
                createTransaction($pdo, $type_id, $spec_id, $student_id, $lending_staff_id, $due_date);
                $success_count++;
                $qty_needed--; // ลดจำนวนที่ต้องการลง
            }
        }

        // 2. จัดการรายการที่เหลือ (Auto-pick สุ่มหยิบ)
        if ($qty_needed > 0) {
            // หาของว่างเพิ่ม
            $stmt_find = $pdo->prepare("SELECT id FROM med_equipment_items WHERE type_id = ? AND status = 'available' LIMIT ? FOR UPDATE");
            // (Bind param แบบ Integer สำคัญมากสำหรับ LIMIT)
            $stmt_find->bindValue(1, $type_id, PDO::PARAM_INT);
            $stmt_find->bindValue(2, $qty_needed, PDO::PARAM_INT);
            $stmt_find->execute();
            $auto_items = $stmt_find->fetchAll(PDO::FETCH_COLUMN);

            if (count($auto_items) < $qty_needed) {
                throw new Exception("อุปกรณ์ '{$item['name']}' มีไม่พอ (ต้องการเพิ่ม {$qty_needed} แต่เหลือ " . count($auto_items) . ")");
            }

            foreach ($auto_items as $auto_id) {
                $pdo->prepare("UPDATE med_equipment_items SET status = 'borrowed' WHERE id = ?")->execute([$auto_id]);
                $pdo->prepare("UPDATE med_equipment_types SET available_quantity = available_quantity - 1 WHERE id = ?")->execute([$type_id]);
                createTransaction($pdo, $type_id, $auto_id, $student_id, $lending_staff_id, $due_date);
                $success_count++;
            }
        }
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'count' => $success_count]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

// Helper Function
function createTransaction($pdo, $type_id, $item_id, $student_id, $staff_id, $due_date) {
    $sql = "INSERT INTO med_transactions 
            (type_id, item_id, equipment_id, borrower_student_id, lending_staff_id, due_date, status, approval_status, borrow_date, quantity)
            VALUES (?, ?, ?, ?, ?, ?, 'borrowed', 'approved', NOW(), 1)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$type_id, $item_id, $item_id, $student_id, $staff_id, $due_date]);
}
?>