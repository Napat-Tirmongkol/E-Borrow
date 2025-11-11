<?php
// [แก้ไขไฟล์: napat-tirmongkol/e-borrow/E-Borrow-c4df732f98db10bf52a8e9d7299e212b6f2abd37/index.php]
// index.php (v3 - แสดง Pending + Fines)

// 1. "จ้างยาม" และ "เชื่อมต่อ DB"
@session_start(); 
include('includes/check_student_session.php');
require_once('includes/db_connect.php'); 

// 2. ดึง ID นักศึกษาจาก Session
$student_id = $_SESSION['student_id']; 

try {
    // 3. (Query ที่ 1 - แก้ไข) ดึงข้อมูลรายการ "ที่ยังไม่คืน" ทั้งหมด (ทั้ง Pending และ Approved)
    $sql_borrowed = "SELECT 
                        t.id as transaction_id, t.borrow_date, t.due_date,
                        t.approval_status, -- ✅ (1) เพิ่มคอลัมน์นี้
                        ei.name as equipment_name, 
                        et.image_url, 
                        et.name as type_name
                     FROM med_transactions t
                     JOIN med_equipment_items ei ON t.item_id = ei.id
                     JOIN med_equipment_types et ON t.type_id = et.id
                     WHERE t.borrower_student_id = ? 
                       AND t.status = 'borrowed' -- (status = 'borrowed' หมายถึงของยังไม่อยู่ในสต็อก)
                       AND t.approval_status IN ('approved', 'pending') -- ✅ (2) แก้ไขเงื่อนไขนี้
                     ORDER BY t.borrow_date DESC"; // (เรียงตามวันที่ขอล่าสุด)
    
    $stmt_borrowed = $pdo->prepare($sql_borrowed);
    $stmt_borrowed->execute([$student_id]);
    $borrowed_items = $stmt_borrowed->fetchAll(PDO::FETCH_ASSOC);

    // ✅ (3) (Query ที่ 2 - เพิ่มใหม่) ค้นหาค่าปรับที่ค้างชำระ (pending)
    $total_fine = 0;
    $stmt_fine = $pdo->prepare(
        "SELECT SUM(f.amount) as total 
         FROM med_fines f
         JOIN med_transactions t ON f.transaction_id = t.id
         WHERE t.borrower_student_id = ? AND f.status = 'pending'"
    );
    $stmt_fine->execute([$student_id]);
    $fine_data = $stmt_fine->fetch(PDO::FETCH_ASSOC);
    
    if ($fine_data && $fine_data['total'] > 0) {
        $total_fine = $fine_data['total'];
    }

} catch (PDOException $e) {
    $error_message = "เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage();
    $borrowed_items = [];
    $total_fine = 0; // (ตั้งค่าเริ่มต้นหาก Query ล้มเหลว)
}

// 4. ตั้งค่าตัวแปรสำหรับ Header
$page_title = "สถานะปัจจุบัน"; // ✅ (4) แก้ไขชื่อ Title
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
        <div class="section-card" style="background-color: var(--color-danger); color: white; margin-bottom: 1.5rem;">
            <p style="margin: 0; font-weight: bold;"><?php echo $error_message; ?></p>
        </div>
    <?php endif; ?>

    <?php if ($total_fine > 0): ?>
        <div class="section-card" style="background-color: var(--color-danger); color: white; margin-bottom: 0;">
            <h3 style="margin-top: 0; color: white;">
                <i class="fas fa-exclamation-triangle"></i> แจ้งเตือนค่าปรับ
            </h3>
            <p style="margin-bottom: 0;">
                คุณมีค่าปรับค้างชำระทั้งสิ้น <strong><?php echo number_format($total_fine, 2); ?> บาท</strong>
                กรุณาติดต่อเจ้าหน้าที่คลินิกเวชกรรมฯ เพื่อชำระค่าปรับ
            </p>
        </div>
    <?php endif; ?>


    <div class="header-row" style="margin-top: 1.5rem;">
        <h2><i class="fas fa-hand-holding-medical"></i> สถานะการยืมปัจจุบัน</h2>
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
                        // (ตรรกะ Icon: ถ้า Pending ให้ใช้ Hourglass, ถ้า Approved ให้ใช้รูป)
                        if ($item['approval_status'] == 'pending') {
                        ?>
                            <span class="status-badge yellow" title="รอดำเนินการ">
                                <i class="fas fa-hourglass-half"></i>
                            </span>
                        <?php 
                        } else {
                            // (โค้ดแสดงรูปภาพเหมือนเดิม)
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
                            <?php 
                            endif; // (จบ if $image_path)
                        } // (จบ if 'pending')
                        ?>
                    </div>

                    <div class="history-card-info">
                        <h4 class="truncate-text" title="<?php echo htmlspecialchars($item['equipment_name']); ?>">
                            <?php echo htmlspecialchars($item['equipment_name']); ?>
                        </h4>
                        <p class="text-muted" style="font-size: 0.9em;"><?php echo htmlspecialchars($item['type_name']); ?></p>
                        
                        <?php // (ตรรกะแสดงผล: Pending vs Approved)
                        if ($item['approval_status'] == 'pending') {
                        ?>
                            <p>
                                <strong>สถานะ:</strong> 
                                <span style="color: var(--color-warning); font-weight: bold;">
                                    รอดำเนินการ
                                </span>
                            </p>
                        <?php 
                        } else {
                        ?>
                            <p>
                                <strong>กำหนดคืน:</strong> 
                                <span style="color: <?php echo (strtotime($item['due_date']) < time()) ? 'var(--color-danger)' : 'var(--color-text-normal)'; ?>; font-weight: bold;">
                                    <?php echo date('d/m/Y', strtotime($item['due_date'])); ?>
                                </span>
                            </p>
                        <?php 
                        } // (จบ if 'pending')
                        ?>
                    </div>

                    <div class="pending-card-actions">
                        <?php if ($item['approval_status'] == 'pending'): ?>
                            <button type="button" 
                                    class="btn btn-danger btn-sm" 
                                    style="margin-top: 5px; width: 100%;"
                                    onclick="confirmCancelRequest(<?php echo $item['transaction_id']; ?>)">
                                <i class="fas fa-trash-alt"></i> ยกเลิก
                            </button>
                        <?php else: ?>
                            <?php endif; ?>
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