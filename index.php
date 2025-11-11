<?php
// [napat-tirmongkol/e-borrow/E-Borrow-c4df732f98db10bf52a8e9d7299e212b6f2abd37/index.php]
// index.php (student_dashboard.php เดิม)
// (หน้าแรกของผู้ใช้งาน, แสดงรายการที่กำลังยืม)

// 1. "จ้างยาม" และ "เชื่อมต่อ DB"
@session_start(); 
include('includes/check_student_session.php');
// ◀️ (แก้ไข) เพิ่ม includes/ ◀️
require_once('includes/db_connect.php'); 

// 2. ดึง ID นักศึกษาจาก Session
$student_id = $_SESSION['student_id']; 

// 3. (Query ข้อมูล)
try {
    // (Query รายการที่กำลังยืม)
    $sql_borrowed = "SELECT 
                        t.id as transaction_id, t.borrow_date, t.due_date,
                        ei.name as equipment_name, ei.image_url,
                        et.name as type_name
                     FROM med_transactions t
                     JOIN med_equipment_items ei ON t.item_id = ei.id
                     JOIN med_equipment_types et ON t.type_id = et.id
                     WHERE t.borrower_student_id = ? 
                       AND t.status = 'borrowed'
                       AND t.approval_status = 'approved'
                     ORDER BY t.due_date ASC";
    
    $stmt_borrowed = $pdo->prepare($sql_borrowed);
    $stmt_borrowed->execute([$student_id]);
    $borrowed_items = $stmt_borrowed->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage();
    $borrowed_items = [];
}

// 4. ตั้งค่าตัวแปรสำหรับ Header
$page_title = "อุปกรณ์ที่ยืมอยู่";
$active_page = 'home'; 
include('includes/student_header.php');
?>

<style>
    /* * บังคับให้ .history-list-container แสดงผล (display: flex)
     * ในทุกขนาดหน้าจอ (desktop) เพื่อ override style ที่อาจซ่อนอยู่
     * (CSS นี้อ้างอิงจาก style.css บรรทัด 485)
     */
    @media (min-width: 993px) { /* (ใช้ 993px เพื่อให้แน่ใจว่า override media query ที่ 992px) */
        .student-card-list {
            display: flex !important; 
            flex-direction: column;
            gap: 1rem;
        }
    }
</style>

<div class="main-container">

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="header-row">
        <h2><i class="fas fa-hand-holding-medical"></i> อุปกรณ์ที่กำลังยืม</h2>
    </div>

    <div class="student-card-list">
        <?php if (empty($borrowed_items)): ?>
            <div class="history-card">
                <p style="text-align: center; width: 100%;">คุณยังไม่มียืมอุปกรณ์ใดๆ ในขณะนี้</p>
                <a href="borrow.php" class="btn-loan" style="width: 100%; text-align: center; margin-top: 1rem;">
                    <i class="fas fa-boxes-stacked"></i> ไปที่หน้ายืมอุปกรณ์
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($borrowed_items as $item): ?>
                <div class="history-card">
                    
                    <div class="history-card-icon">
                        <?php 
                        $image_path = $item['image_url'] ?? null;
                        if ($image_path): 
                        ?>
                            <img src="<?php echo htmlspecialchars($image_path); ?>" 
                                 alt="รูป" 
                                 style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px;"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                            <div class="equipment-card-image-placeholder" style="display: none; width: 40px; height: 40px; font-size: 1.2rem;"><i class="fas fa-image"></i></div>
                        <?php else: ?>
                            <div class="equipment-card-image-placeholder" style="width: 40px; height: 40px; font-size: 1.2rem;">
                                <i class="fas fa-camera"></i>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="history-card-info">
                        <h4 class="truncate-text" title="<?php echo htmlspecialchars($item['equipment_name']); ?>">
                            <?php echo htmlspecialchars($item['equipment_name']); ?>
                        </h4>
                        <p class="text-muted" style="font-size: 0.9em;"><?php echo htmlspecialchars($item['type_name']); ?></p>
                        <p>
                            <strong>กำหนดคืน:</strong> 
                            <span style="color: <?php echo (strtotime($item['due_date']) < time()) ? 'var(--color-danger)' : 'var(--color-text-normal)'; ?>; font-weight: bold;">
                                <?php echo date('d/m/Y', strtotime($item['due_date'])); ?>
                            </span>
                        </p>
                    </div>

                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div> 

<?php
// 5. เรียกใช้ Footer
include('includes/student_footer.php');
?>