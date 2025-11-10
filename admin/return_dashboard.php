<?php
// return_dashboard.php (อัปเดต V3.1 - เพิ่ม Workflow ค่าปรับก่อนคืน)

// 1. "จ้างยาม" และ "เชื่อมต่อ DB"
include('../includes/check_session.php'); 
require_once('../includes/db_connect.php'); 

// 2. ตรวจสอบสิทธิ์ (อนุญาต Admin, Employee และ Editor)
$allowed_roles = ['admin', 'employee', 'editor'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header("Location: index.php");
    exit;
}

// 3. (SQL) ดึงข้อมูลอุปกรณ์ที่ถูกยืม
$borrowed_items = [];
try {
    
    // ✅ (แก้ไข) Query ใหม่: ดึง t.id (transaction_id), s.id (student_id), 
    //    t.fine_status, และคำนวณ DATEDIFF
    $sql = "SELECT 
                t.id as transaction_id, 
                t.equipment_id, 
                t.due_date, 
                t.fine_status,
                ei.name as equipment_name, 
                ei.serial_number as equipment_serial,
                s.id as student_id, 
                s.full_name as borrower_name, 
                s.phone_number as borrower_contact,
                t.borrow_date, 
                DATEDIFF(CURDATE(), t.due_date) AS days_overdue
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
include('../includes/header.php'); 
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
                    
                    <?php
                        // ✅ (ใหม่) ตรรกะสำหรับตรวจสอบค่าปรับ
                        $days_overdue = (int)$row['days_overdue'];
                        if ($days_overdue < 0) $days_overdue = 0;
                        
                        $is_overdue = ($days_overdue > 0);
                        $is_fine_paid = ($row['fine_status'] == 'paid');
                        $calculated_fine = $days_overdue * FINE_RATE_PER_DAY;
                    ?>

                    <tr>
                        <td><?php echo htmlspecialchars($row['equipment_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['equipment_serial'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($row['borrower_name'] ?? '[ผู้ใช้ถูกลบ]'); ?></td>
                        <td><?php echo htmlspecialchars($row['borrower_contact'] ?? '-'); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($row['borrow_date'])); ?></td>
                        
                        <?php // (เปลี่ยนสีวันที่ ถ้าเกินกำหนด) ?>
                        <td style="color: <?php echo $is_overdue ? 'var(--color-danger)' : 'inherit'; ?>; font-weight: <?php echo $is_overdue ? 'bold' : 'normal'; ?>;">
                            <?php echo date('d/m/Y', strtotime($row['due_date'])); ?>
                        </td>

                        <td class="action-buttons">
                            
                            <?php // ✅ (ใหม่) ตรรกะการเปลี่ยนปุ่ม ?>
                            <?php if ($is_overdue && !$is_fine_paid): ?>
                                <button type="button" class="btn btn-danger" 
                                        onclick="openFineAndReturnPopup(
                                            <?php echo $row['transaction_id']; ?>,
                                            <?php echo $row['student_id'] ?? 0; ?>,
                                            '<?php echo htmlspecialchars(addslashes($row['borrower_name'] ?? '[N/A]')); ?>',
                                            '<?php echo htmlspecialchars(addslashes($row['equipment_name'])); ?>',
                                            <?php echo $days_overdue; ?>,
                                            <?php echo $calculated_fine; ?>,
                                            <?php echo $row['equipment_id']; ?> 
                                        )">
                                    <i class="fas fa-dollar-sign"></i> ชำระค่าปรับ
                                </button>
                            <?php else: ?>
                                <button type="button" 
                                        class="btn btn-return" 
                                        onclick="openReturnPopup(<?php echo $row['equipment_id']; ?>)">รับคืน</button>
                            <?php endif; ?>

                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
// 7. เรียกใช้ไฟล์ Footer (ซึ่งมี JavaScript popups ที่เราย้ายไป)
include('../includes/footer.php'); 
?>