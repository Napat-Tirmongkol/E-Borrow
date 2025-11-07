<?php
// student_dashboard.php (หน้าหลัก - รายการที่ยืมอยู่ - Layout ใหม่)

// 1. "จ้างยาม" และ "เชื่อมต่อ DB"
@session_start(); 
include('includes/check_student_session.php'); // (◀️ เปิดยาม)
// (⚠️ ลบส่วน Development Mode ออกแล้ว ⚠️)

require_once('db_connect.php'); //

// 2. ดึง ID ของผู้ใช้งาน
$student_id = $_SESSION['student_id']; 

// 3. (Query เฉพาะส่วนที่ยืมอยู่)
try {
    $sql_borrowed = "SELECT t.*, ei.name as equipment_name, ei.serial_number 
                     FROM med_transactions t
                     JOIN med_equipment_items ei ON t.equipment_id = ei.id
                     WHERE t.borrower_student_id = ? 
                       AND t.status = 'borrowed'
                       AND t.approval_status IN ('approved', 'staff_added')
                     ORDER BY t.due_date ASC";
    
    $stmt_borrowed = $pdo->prepare($sql_borrowed);
    $stmt_borrowed->execute([$student_id]);
    $currently_borrowed = $stmt_borrowed->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $currently_borrowed = [];
    $borrowed_error = "เกิดข้อผิดพลาด: " . $e->getMessage();
}

// 4. ตั้งค่าตัวแปรสำหรับ Header
$page_title = "อุปกรณ์ที่ยืมอยู่";
$active_page = 'home'; // ◀️ (สำคัญ) บอก Footer ว่าเมนูไหน Active
include('includes/student_header.php');
?>

<div class="main-container">

    <div class="section-card" style="background: none; box-shadow: none; padding: 0;">
        <h2 class="section-title">อุปกรณ์ที่ฉันกำลังยืม (รายการที่ต้องคืน)</h2>
        <p class="text-muted">รายการอุปกรณ์ที่ยืมอยู่และสถานะการคืน</p>
        
        <?php if (isset($borrowed_error)) echo "<p style='color: red;'>$borrowed_error</p>"; ?>
        
        <div class="history-list-container">
        
            <?php if (empty($currently_borrowed)): ?>
                <div class="history-card">
                    <p style="text-align: center; width: 100%;">คุณไม่มีอุปกรณ์ที่กำลังยืมอยู่</p>
                </div>
            <?php else: ?>
                <?php foreach ($currently_borrowed as $row): ?>
                    
                    <?php
                        // (ตรรกะสำหรับเช็คว่า "เกินกำหนด" หรือ "กำลังยืม")
                        $is_overdue = (strtotime($row['due_date']) < time());
                        
                        // (ใช้ Badge สีแดงสำหรับ "เกินกำหนด" และสีฟ้าสำหรับ "กำลังยืม")
                        $badge_class = $is_overdue ? 'red' : 'blue';
                        $badge_text = $is_overdue ? 'เกินกำหนด' : 'กำลังยืม';
                        $icon_class = $is_overdue ? 'fas fa-calendar-times' : 'fas fa-hand-holding-medical';
                    ?>

                    <div class="history-card">
                        
                        <div class="history-card-icon">
                            <span class="status-badge <?php echo $badge_class; ?>">
                                <i class="<?php echo $icon_class; ?>"></i>
                            </span>
                        </div>
                        
                        <div class="history-card-info">
                            <h4><?php echo htmlspecialchars($row['equipment_name']); ?></h4>
                            <p>
                                ยืมเมื่อ: <?php echo date('d/m/Y', strtotime($row['borrow_date'])); ?> |
                                คืน: <strong><?php echo date('d/m/Y', strtotime($row['due_date'])); ?></strong>
                            </p>
                        </div>
                        
                        <div class="history-card-status">
                            <span class="status-badge <?php echo $badge_class; ?>"><?php echo $badge_text; ?></span>
                        </div>
                    </div>
                
                <?php endforeach; ?>
            <?php endif; ?>
        
        </div>
        </div> 

</div> 

<?php
// 5. เรียกใช้ Footer
include('includes/student_footer.php'); 
?>