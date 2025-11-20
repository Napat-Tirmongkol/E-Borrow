<?php
// [แก้ไขไฟล์: index.php]
// index.php (v4 - เพิ่มปุ่ม QR Code + แจ้งเตือนค่าปรับ + รายการรออนุมัติ)

// 1. "จ้างยาม" และ "เชื่อมต่อ DB"
@session_start(); 
include('includes/check_student_session.php');
require_once('includes/db_connect.php'); 

// 2. ดึง ID นักศึกษาจาก Session
$student_id = $_SESSION['student_id']; 

try {
    // ✅ (1) เพิ่ม: ดึงข้อมูลนักศึกษา (เพื่อเอาไปสร้าง QR Code)
    $stmt_st = $pdo->prepare("SELECT student_personnel_id, full_name FROM med_students WHERE id = ?");
    $stmt_st->execute([$student_id]);
    $student_data = $stmt_st->fetch(PDO::FETCH_ASSOC);

    // ✅ (2) แก้ไข Query: ดึงทั้งรายการ "ยืมอยู่" และ "รออนุมัติ"
    // และแก้ไขให้ดึงรูปจาก med_equipment_types (et.image_url)
    $sql_borrowed = "SELECT 
                        t.id as transaction_id, t.borrow_date, t.due_date,
                        t.approval_status,
                        ei.name as equipment_name, 
                        et.image_url, 
                        et.name as type_name
                     FROM med_transactions t
                     JOIN med_equipment_items ei ON t.item_id = ei.id
                     JOIN med_equipment_types et ON t.type_id = et.id
                     WHERE t.borrower_student_id = ? 
                       AND t.status = 'borrowed' 
                       AND t.approval_status IN ('approved', 'pending')
                     ORDER BY t.borrow_date DESC";
    
    $stmt_borrowed = $pdo->prepare($sql_borrowed);
    $stmt_borrowed->execute([$student_id]);
    $borrowed_items = $stmt_borrowed->fetchAll(PDO::FETCH_ASSOC);

    // ✅ (3) เพิ่ม: ค้นหาค่าปรับที่ค้างชำระ
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
    $total_fine = 0;
}

$page_title = "หน้าแรก";
$active_page = 'home'; 
include('includes/student_header.php');
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<style>
    @media (min-width: 993px) {
        .student-card-list {
            display: flex !important; 
            flex-direction: column;
            gap: 1rem;
        }
    }
    /* สไตล์ปุ่ม QR Code */
    .qr-btn-container {
        background: linear-gradient(135deg, var(--color-primary), #084C1A);
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        color: white;
        margin-bottom: 1.5rem;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        cursor: pointer;
        transition: transform 0.2s;
    }
    .qr-btn-container:active {
        transform: scale(0.98);
    }
    .qr-btn-icon {
        font-size: 2.5rem;
        margin-bottom: 10px;
    }
</style>

<div class="main-container">

    <?php if (isset($error_message)): ?>
        <div class="section-card" style="background-color: var(--color-danger); color: white; margin-bottom: 1.5rem;">
            <p style="margin: 0; font-weight: bold;"><?php echo $error_message; ?></p>
        </div>
    <?php endif; ?>

    <?php if ($total_fine > 0): ?>
        <div class="section-card" style="background-color: var(--color-danger); color: white; margin-bottom: 1.5rem;">
            <h3 style="margin-top: 0; color: white;">
                <i class="fas fa-exclamation-triangle"></i> แจ้งเตือนค่าปรับ
            </h3>
            <p style="margin-bottom: 0;">
                คุณมีค่าปรับค้างชำระทั้งสิ้น <strong><?php echo number_format($total_fine, 2); ?> บาท</strong>
                กรุณาติดต่อเจ้าหน้าที่เพื่อชำระ
            </p>
        </div>
    <?php endif; ?>

    <div class="qr-btn-container" onclick="showHomeQRCode()">
        <div class="qr-btn-icon"><i class="fas fa-qrcode"></i></div>
        <h3 style="margin: 0; color: white;">แตะเพื่อแสดง QR Code</h3>
        <p style="margin: 5px 0 0 0; opacity: 0.8; font-size: 0.9em;">สำหรับให้เจ้าหน้าที่สแกนยืมอุปกรณ์</p>
    </div>

    <div class="header-row">
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
                        // ถ้าสถานะเป็น Pending (รออนุมัติ)
                        if ($item['approval_status'] == 'pending') {
                        ?>
                            <span class="status-badge yellow" title="รอดำเนินการ">
                                <i class="fas fa-hourglass-half"></i>
                            </span>
                        <?php 
                        } else {
                            // ถ้าสถานะเป็น Approved (ยืมอยู่) ให้แสดงรูป
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
                            endif; 
                        } 
                        ?>
                    </div>

                    <div class="history-card-info">
                        <h4 class="truncate-text" title="<?php echo htmlspecialchars($item['equipment_name']); ?>">
                            <?php echo htmlspecialchars($item['equipment_name']); ?>
                        </h4>
                        <p class="text-muted" style="font-size: 0.9em;"><?php echo htmlspecialchars($item['type_name']); ?></p>
                        
                        <?php 
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
                        } 
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
                        <?php endif; ?>
                    </div>

                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div> 

<script>
function showHomeQRCode() {
    const studentCode = "<?php echo htmlspecialchars($student_data['student_personnel_id']); ?>"; 
    const studentName = "<?php echo htmlspecialchars($student_data['full_name']); ?>";
    const qrData = "MEDLOAN_STUDENT:" + studentCode;

    Swal.fire({
        title: 'QR Code ประจำตัว',
        html: `
            <div style="display: flex; justify-content: center; margin: 20px 0; padding: 10px; background: white; border-radius: 8px;">
                <div id="qrcode-home-container"></div>
            </div>
            <h3 style="margin-bottom: 5px; color: var(--color-text-normal);">${studentCode}</h3>
            <p class="text-muted">${studentName}</p>
        `,
        didOpen: () => {
            new QRCode(document.getElementById("qrcode-home-container"), {
                text: qrData,
                width: 220,
                height: 220,
                correctLevel : QRCode.CorrectLevel.H
            });
        },
        confirmButtonText: 'ปิด',
        confirmButtonColor: '#6c757d'
    });
}
</script>

<?php
// 5. เรียกใช้ Footer
include('includes/student_footer.php');
?>