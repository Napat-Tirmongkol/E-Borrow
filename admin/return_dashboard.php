<?php
// return_dashboard.php (อัปเดต V3)

// 1. "จ้างยาม" และ "เชื่อมต่อ DB"
include('includes/check_session.php'); //
require_once('db_connect.php'); //

// 2. ตรวจสอบสิทธิ์ (อนุญาต Admin และ Employee)
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'employee'])) {
    header("Location: index.php");
    exit;
}

// 3. (SQL) ดึงข้อมูลอุปกรณ์ที่ถูกยืม
$borrowed_items = [];
try {
    $sql = "SELECT 
                t.equipment_id, 
                ei.name as equipment_name, 
                ei.serial_number as equipment_serial,
                s.full_name as borrower_name, 
                s.phone_number as borrower_contact,
                t.borrow_date, 
                t.due_date
            FROM med_transactions t
            JOIN med_equipment_items ei ON t.equipment_id = ei.id
            LEFT JOIN med_students s ON t.borrower_student_id = s.id
            WHERE t.status = 'borrowed'
              AND t.approval_status IN ('approved', 'staff_added') 
            ORDER BY t.due_date ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $borrowed_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage();
}

// 4. ตั้งค่าตัวแปรสำหรับ Header
$page_title = "คืนอุปกรณ์";
$current_page = "return"; 

// 5. เรียกใช้ Header
include('includes/header.php'); //
?>

<div class="header-row">
    <h2><i class="fas fa-undo-alt"></i> 📦 รายการอุปกรณ์ที่ต้องรับคืน</h2>
    </div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>อุปกรณ์</th>
                <th>เลขซีเรียล</th>
                <th>ผู้ยืม (User)</th>
                <th>ข้อมูลติดต่อ (ผู้ยืม)</th>
                <th>วันที่ยืม</th>
                <th>วันที่กำหนดคืน</th>
                <th>จัดการ</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($borrowed_items)): ?>
                <tr>
                    <td colspan="7" style="text-align: center;">ไม่มีอุปกรณ์ที่กำลังถูกยืมในขณะนี้</td>
                </tr>
            <?php else: ?>
                <?php foreach ($borrowed_items as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['equipment_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['equipment_serial'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($row['borrower_name'] ?? '[ผู้ใช้ถูกลบ]'); ?></td>
                        <td><?php echo htmlspecialchars($row['borrower_contact'] ?? '-'); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($row['borrow_date'])); ?></td>
                        <td style="color: var(--color-danger); font-weight: bold;">
                            <?php echo date('d/m/Y', strtotime($row['due_date'])); ?>
                        </td>
                        <td class="action-buttons">
                            <button type="button" 
                                    class="btn btn-return" 
                                    onclick="openReturnPopup(<?php echo $row['equipment_id']; ?>)">รับคืน</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
// 7. เรียกใช้ไฟล์ Footer (ซึ่งมี JavaScript popups อยู่)
include('includes/footer.php'); 
?>