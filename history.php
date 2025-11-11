<?php
// [napat-tirmongkol/e-borrow/E-Borrow-c4df732f98db10bf52a8e9d7299e212b6f2abd37/history.php]
// request_history.php (หน้าประวัติคำขอ - Layout ใหม่)

// 1. "จ้างยาม" และ "เชื่อมต่อ DB"
@session_start(); 
include('includes/check_student_session.php');
require_once('includes/db_connect.php');

// 2. ดึง ID ของผู้ใช้งาน
$student_id = $_SESSION['student_id']; 

// 3. (Query เฉพาะประวัติ)
try {
    $sql_history = "SELECT t.*, et.name as equipment_name
                    FROM med_transactions t
                    -- ◀️ (แก้ไข) เปลี่ยน t.equipment_type_id เป็น t.type_id
                    JOIN med_equipment_types et ON t.type_id = et.id
                    WHERE t.borrower_student_id = ? 
                      AND (t.status = 'returned' OR t.approval_status IN ('pending', 'rejected'))
                    ORDER BY t.borrow_date DESC, t.id DESC";
    
    $stmt_history = $pdo->prepare($sql_history);
    $stmt_history->execute([$student_id]);
    $history = $stmt_history->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $history = [];
    $history_error = "เกิดข้อผิดพลาด: " . $e->getMessage();
}

// 4. ตั้งค่าตัวแปรสำหรับ Header
$page_title = "ประวัติคำขอ";
$active_page = 'history'; // ◀️ (สำคัญ) บอก Footer ว่าเมนูไหน Active
include('includes/student_header.php');
?>

<div class="main-container">

    <div class="section-card" style="background: none; box-shadow: none; padding: 0;">
        <h2 class="section-title">ประวัติคำขอที่ผ่านมา</h2>
        <p class="text-muted">รายการคำขอที่คุณเคยส่ง (รอดำเนินการ, คืนแล้ว, ถูกปฏิเสธ)</p>

        <?php if (isset($history_error)) echo "<p style='color: red;'>" . htmlspecialchars($history_error) . "</p>"; ?>
        
        <div class="history-list-container">
        
            <?php if (empty($history)): ?>
                <div class="history-card">
                    <p style="text-align: center; width: 100%;">คุณยังไม่มีประวัติการยืม</p>
                </div>
            <?php else: ?>
                <?php foreach ($history as $row): ?>
                    
                    <?php
                        // (ตรรกะสำหรับเลือกไอคอนและ Badge)
                        $badge_class = 'grey';
                        $badge_text = htmlspecialchars($row['approval_status']);
                        $icon_class = 'fas fa-question-circle'; // Default
                        
                        // (เราจะเช็ค status ที่ 'returned' ก่อน)
                        if ($row['status'] == 'returned') {
                            $badge_class = 'green';
                            $badge_text = 'คืนแล้ว';
                            $icon_class = 'fas fa-check-circle';
                        } 
                        // (ถ้าไม่ใช่ 'returned' ค่อยเช็ค approval_status)
                        elseif ($row['approval_status'] == 'pending') {
                            $badge_class = 'yellow';
                            $badge_text = 'รอดำเนินการ';
                            $icon_class = 'fas fa-hourglass-half';
                        } elseif ($row['approval_status'] == 'rejected') {
                            $badge_class = 'grey';
                            $badge_text = 'ถูกปฏิเสธ';
                            $icon_class = 'fas fa-times-circle';
                        }
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
                                ส่งคำขอ: <?php echo date('d/m/Y H:i', strtotime($row['borrow_date'])); ?>
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