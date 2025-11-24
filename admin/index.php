<?php
include('../includes/check_session.php');
require_once('../includes/db_connect.php');

try {
    $stmt_borrowed = $pdo->query("SELECT COUNT(*) FROM med_equipment_items WHERE status = 'borrowed'");
    $count_borrowed = $stmt_borrowed->fetchColumn();
    $stmt_available = $pdo->query("SELECT COUNT(*) FROM med_equipment_items WHERE status = 'available'");
    $count_available = $stmt_available->fetchColumn();
    $stmt_maintenance = $pdo->query("SELECT COUNT(*) FROM med_equipment_items WHERE status = 'maintenance'");
    $count_maintenance = $stmt_maintenance->fetchColumn();
    $stmt_overdue = $pdo->query("SELECT COUNT(*) FROM med_transactions WHERE status = 'borrowed' AND approval_status IN ('approved', 'staff_added') AND due_date < CURDATE()");
    $count_overdue = $stmt_overdue->fetchColumn();
} catch (PDOException $e) {
    $count_borrowed = $count_available = $count_maintenance = $count_overdue = 0;
    $kpi_error = "เกิดข้อผิดพลาดในการดึงข้อมูล KPI: " . $e->getMessage(); 
}

// 4. ดึงข้อมูล "รายการรออนุมัติ" (Pending Requests) 
$pending_requests = [];
try {
   // [แก้ไข 1] เพิ่ม t.equipment_id และ t.item_id ใน SELECT
   $sql_pending = "SELECT 
                        t.id as transaction_id,
                        t.borrow_date, 
                        t.due_date,
                        t.reason_for_borrowing,
                        t.attachment_url,
                        t.equipment_id, -- (จำเป็นต้องมีค่านี้สำหรับปุ่มอนุมัติ)
                        t.item_id,
                        et.name as equipment_name,
                        ei.serial_number,  
                        s.full_name as student_name,
                        u.full_name as staff_name
                    FROM med_transactions t
                    JOIN med_equipment_types et ON t.type_id = et.id 
                    LEFT JOIN med_equipment_items ei ON t.equipment_id = ei.id 
                    LEFT JOIN med_students s ON t.borrower_student_id = s.id
                    LEFT JOIN med_users u ON t.lending_staff_id = u.id
                    WHERE t.approval_status = 'pending'
                    ORDER BY t.borrow_date ASC";
    
    $stmt_pending = $pdo->prepare($sql_pending);
    $stmt_pending->execute();
    $pending_requests = $stmt_pending->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $pending_error = "เกิดข้อผิดพลาดในการดึงข้อมูลคำขอ: " . $e->getMessage(); 
}

// 5. ดึงข้อมูล "รายการที่เกินกำหนดคืน"
$overdue_items = [];
try {
    $sql_overdue = "SELECT 
                        t.id as transaction_id, 
                        t.equipment_id, 
                        t.due_date, 
                        t.fine_status,
                        ei.name as equipment_name, 
                        s.id as student_id, 
                        s.full_name as student_name,
                        s.phone_number,
                        DATEDIFF(CURDATE(), t.due_date) AS days_overdue
                    FROM med_transactions t
                    JOIN med_equipment_items ei ON t.equipment_id = ei.id
                    LEFT JOIN med_students s ON t.borrower_student_id = s.id
                    WHERE t.status = 'borrowed' 
                      AND t.approval_status IN ('approved', 'staff_added') 
                      AND t.due_date < CURDATE()
                      AND t.fine_status = 'none'
                    ORDER BY t.due_date ASC";
    $stmt_overdue = $pdo->prepare($sql_overdue);
    $stmt_overdue->execute();
    $overdue_items = $stmt_overdue->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $overdue_error = "เกิดข้อผิดพลาดในการดึงข้อมูลเกินกำหนด: " . $e->getMessage(); 
}

// 6. ดึงข้อมูล "รายการเคลื่อนไหวล่าสุด" (5 รายการ)
$recent_activity = [];
try {
    $sql_activity = "SELECT 
                        t.approval_status, t.status, t.borrow_date, t.return_date,
                        et.name as equipment_name,
                        s.full_name as student_name
                    FROM med_transactions t
                    JOIN med_equipment_types et ON t.type_id = et.id
                    LEFT JOIN med_students s ON t.borrower_student_id = s.id
                    ORDER BY t.id DESC
                    LIMIT 5";
    $stmt_activity = $pdo->prepare($sql_activity);
    $stmt_activity->execute();
    $recent_activity = $stmt_activity->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $activity_error = "เกิดข้อผิดพลาดในการดึงข้อมูลเคลื่อนไหว: " . $e->getMessage(); 
}


// 7. ตั้งค่าตัวแปรสำหรับหน้านี้
$page_title = "Dashboard - ภาพรวม";
$current_page = "index";
// 8. เรียกใช้ไฟล์ Header
include('../includes/header.php'); 
?>

<?php if (isset($kpi_error)) echo "<p style='color: red;'>$kpi_error</p>"; ?>

<?php if ($count_overdue > 0): ?>
    <div class="stat-card kpi-overdue" style="margin-bottom: 1.5rem;">
        <div class="stat-card-info">
            <p class="title">รายการเกินกำหนดคืน (ที่ยังไม่คืน)</p>
            <p class="value"><?php echo $count_overdue; ?> รายการ</p>
        </div>
        <div class="stat-card-icon">
            <i class="fas fa-calendar-times"></i>
        </div>
    </div>
<?php endif; ?>

<div class="header-row">
<<<<<<< HEAD
    <h2><i class="fas fa-tachometer-alt"></i> ภาพรวมระบบ</h2>
    <a href="admin/walkin_borrow.php" class="btn btn-primary" style="font-size: 1.1rem; padding: 0.7rem 1.2rem;">
        <i class="fas fa-qrcode"></i> สแกนยืม
    </a>
</div>
=======
        <h2><i class="fas fa-tachometer-alt"></i> ภาพรวมระบบ</h2>
        
        <a href="admin/walkin_borrow.php" class="btn btn-primary" style="font-size: 1.1rem; padding: 0.7rem 1.2rem;">
            <i class="fas fa-qrcode"></i> สแกนยืม
        </a>
    </div>
>>>>>>> ef5cd04f7b526bd3851d14aa002e832d920fab40

<?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') : ?>
<div class="section-card" style="margin-bottom: 1.5rem;">
    <h2 class="section-title">สถานะอุปกรณ์ทั้งหมด</h2>
    <div style="width: 100%; max-width: 400px; margin: 0 auto;">
        <canvas id="equipmentStatusChart"></canvas>
    </div>
</div>
<?php endif; ?>

<div class="dashboard-grid">

    <div class="container">
        <h2><i class="fas fa-bell" style="color: var(--color-warning);"></i> รายการรออนุมัติ (<?php echo count($pending_requests); ?>)</h2>
        <div class="container-content">
            <?php if (isset($pending_error)) echo "<p style='color: red;'>$pending_error</p>"; ?>
            
            <div class="history-list-container">
            
                <?php if (empty($pending_requests)): ?>
                    <div class="history-card">
                        <p style="text-align: center; width: 100%;">ไม่มีคำขอยืมที่รอดำเนินการ</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($pending_requests as $request): ?>
                        <div class="history-card">
                            
                            <div class="history-card-icon">
                                <span class="status-badge yellow"> <i class="fas fa-hourglass-half"></i></span>
                            </div>
                            
                            <div class="history-card-info">
                                <h4><?php echo htmlspecialchars($request['equipment_name']); ?></h4>
                                <p>ผู้ขอ: <strong><?php echo htmlspecialchars($request['student_name'] ?? '[N/A]'); ?></strong></p>
                                <p>กำหนดคืน: <strong><?php echo date('d/m/Y', strtotime($request['due_date'])); ?></strong></p>

                                <div style="display: flex; gap: 0.75rem; align-items: center; margin-top: 5px;">
                                    
                                    <a href="javascript:void(0)" 
                                       onclick="openDetailModal(this)"
                                       data-item="<?php echo htmlspecialchars($request['equipment_name']); ?>"
                                       data-serial="<?php echo htmlspecialchars($request['serial_number'] ?? '-'); ?>"
                                       data-requester="<?php echo htmlspecialchars($request['student_name'] ?? '-'); ?>"
                                       data-borrow="<?php echo date('d/m/Y', strtotime($request['borrow_date'])); ?>"
                                       data-due="<?php echo date('d/m/Y', strtotime($request['due_date'])); ?>"
                                       data-reason="<?php echo htmlspecialchars($request['reason_for_borrowing']); ?>"
                                       data-attachment="<?php echo htmlspecialchars($request['attachment_url'] ?? ''); ?>" 
                                       style="font-size: 0.9em; text-decoration: underline; color: var(--color-primary);">
                                       <i class="fas fa-info-circle"></i> ดูรายละเอียด
                                    </a>
                                    
                                    <?php if (!empty($request['attachment_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($request['attachment_url']); ?>"  
                                           target="_blank"
                                           style="font-size: 0.9em; text-decoration: underline; color: var(--color-info);">
                                           <i class="fas fa-paperclip"></i> ไฟล์แนบ
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="pending-card-actions">
                                <button type="button" class="btn btn-borrow" 
    onclick="openApproveSelectionModal(
        <?php echo $request['transaction_id']; ?>, 
        <?php echo $request['item_id'] ?? 0; ?>,  /* ใช้ item_id จะแม่นยำกว่า */
        '<?php echo htmlspecialchars($request['equipment_name'], ENT_QUOTES); ?>'
    )">
                                    <i class="fas fa-check"></i> อนุมัติ
                                </button>
                                
                                <button type="button" class="btn btn-danger" 
                                        onclick="openRejectPopup(<?php echo $request['transaction_id']; ?>)">
                                    <i class="fas fa-times"></i> ปฏิเสธ
                                </button>
                            </div>

                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="container">
        <h2><i class="fas fa-calendar-times" style="color: var(--color-danger);"></i> รายการที่เกินกำหนดคืน</h2>
        <div class="container-content">
            <?php if (isset($overdue_error)) echo "<p style='color: red;'>$overdue_error</p>"; ?>
            <div class="history-list-container">
                <?php if (empty($overdue_items)): ?>
                    <div class="history-card">
                        <p style="text-align: center; width: 100%;">ไม่มีรายการที่เกินกำหนด</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($overdue_items as $item): ?>
                        <?php
                            $days_overdue = (int)$item['days_overdue'];
                            if ($days_overdue < 0) $days_overdue = 0;
                            $calculated_fine = $days_overdue * (defined('FINE_RATE_PER_DAY') ? FINE_RATE_PER_DAY : 0);
                        ?>
                        <div class="history-card">
                            <div class="history-card-icon">
                                <span class="status-badge red"> <i class="fas fa-calendar-times"></i></span>
                            </div>
                            <div class="history-card-info">
                                <h4><?php echo htmlspecialchars($item['equipment_name']); ?></h4>
                                <p>ผู้ยืม: <strong><?php echo htmlspecialchars($item['student_name'] ?? '[N/A]'); ?></strong></p>
                                <p>เบอร์โทร: <?php echo htmlspecialchars($item['phone_number'] ?? '[N/A]'); ?></p>
                                <p style="color: var(--color-danger); font-weight: bold;">
                                    เลยกำหนด: <?php echo date('d/m/Y', strtotime($item['due_date'])); ?>
                                </p>
                            </div>
                            <div class="pending-card-actions">
                                <button type="button" class="btn btn-danger" 
                                        onclick="openFineAndReturnPopup(
                                            <?php echo $item['transaction_id']; ?>,
                                            <?php echo $item['student_id'] ?? 0; ?>,
                                            '<?php echo htmlspecialchars(addslashes($item['student_name'] ?? '[N/A]')); ?>',
                                            '<?php echo htmlspecialchars(addslashes($item['equipment_name'])); ?>',
                                            <?php echo $days_overdue; ?>,
                                            <?php echo $calculated_fine; ?>,
                                            <?php echo $item['equipment_id']; ?> 
                                        )">
                                    <i class="fas fa-dollar-sign"></i> ชำระ/รับคืน
                                </button>
                            </div>
                        </div> 
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<div class="container activity-log">
    <h2><i class="fas fa-history" style="color: var(--color-primary);"></i> รายการเคลื่อนไหวล่าสุด</h2>
    <div class="container-content">
        <?php if (isset($activity_error)) echo "<p style='color: red;'>$activity_error</p>"; ?>
        <div class="activity-list">
            <?php if (empty($recent_activity)): ?>
                <div class="activity-item">
                    <p style="text-align: center; width: 100%;">ยังไม่มีความเคลื่อนไหว</p>
                </div>
            <?php else: ?>
                <?php foreach ($recent_activity as $act): ?>
                    <?php
                        $status_icon = '';
                        $status_text = '';
                        $student_name = htmlspecialchars($act['student_name'] ?? 'N/A');
                        $equip_name = htmlspecialchars($act['equipment_name']);

                        if ($act['approval_status'] == 'pending') {
                            $status_icon = '🟡'; 
                            $status_text = "<strong>{$student_name}</strong> ได้ส่งคำขอยืม <strong>{$equip_name}</strong>";
                        } elseif ($act['approval_status'] == 'rejected') {
                            $status_icon = '⚪'; 
                            $status_text = "<strong>คุณ</strong> ได้ปฏิเสธคำขอยืม <strong>{$equip_name}</strong> ของ <strong>{$student_name}</strong>";
                        } elseif ($act['status'] == 'returned') {
                            $status_icon = '🟢'; 
                            $status_text = "<strong>{$student_name}</strong> ได้คืน <strong>{$equip_name}</strong> (เมื่อ " . date('d/m/Y H:i', strtotime($act['return_date'])) . ")";
                        } elseif ($act['approval_status'] == 'approved') {
                            $status_icon = '🔵'; 
                            $status_text = "<strong>คุณ</strong> ได้อนุมัติคำขอยืม <strong>{$equip_name}</strong> ให้ <strong>{$student_name}</strong>";
                        } elseif ($act['approval_status'] == 'staff_added') {
                            $status_icon = '🟣'; 
                            $status_text = "<strong>คุณ</strong> ได้บันทึกการยืม <strong>{$equip_name}</strong> ให้ <strong>{$student_name}</strong>";
                        }
                    ?>
                    <div class="activity-item">
                        <span class="activity-icon" title="<?php echo $act['approval_status'] . '/' . $act['status']; ?>">
                            <?php echo $status_icon; ?>
                        </span>
                        <p><?php echo $status_text; ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailModalLabel"><i class="fas fa-info-circle"></i> รายละเอียดการยืม</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><strong>ชื่อของที่ยืม:</strong> <span id="modalItemName" class="text-primary">-</span></p>
                <p><strong>Serial Number:</strong> <span id="modalSerialNumber">-</span></p>
                <p><strong>ผู้ขอ:</strong> <span id="modalRequester">-</span></p>
                <p><strong>วันที่ยืม:</strong> <span id="modalBorrowDate">-</span></p>
                <p><strong>กำหนดคืน:</strong> <span id="modalDueDate" class="text-danger">-</span></p>
                <hr>
                <p><strong>เหตุผลการยืม:</strong></p>
                <div class="p-2 bg-light border rounded" id="modalReasonText" style="min-height: 50px;">-</div>
                <div id="modalAttachmentSection" class="mt-3" style="display: none; border-top: 1px solid #dee2e6; padding-top: 10px;">
                    <strong><i class="fas fa-paperclip"></i> เอกสารแนบ:</strong>
                    <br>
                    <a href="#" id="modalAttachmentLink" target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                        <i class="fas fa-external-link-alt"></i> เปิดดูไฟล์แนบ
                    </a>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="approveSelectModal" tabindex="-1" data-bs-backdrop="static" aria-labelledby="approveSelectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="approveSelectModalLabel"><i class="fas fa-hand-holding"></i> เลือกอุปกรณ์ที่จะมอบให้</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="approveSelectForm" action="process/approve_request_process.php" method="POST">
                    <input type="hidden" name="transaction_id" id="approve_transaction_id">
                    <input type="hidden" name="original_item_id" id="approve_original_item_id">
                    
                    <div class="mb-3">
                        <label class="form-label">อุปกรณ์ที่ขอ:</label>
                        <input type="text" class="form-control" id="approve_equipment_name" readonly>
                    </div>

                    <hr>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-primary"><i class="fas fa-barcode"></i> สแกนบาร์โค้ด (ถ้ามี):</label>
                        <input type="text" class="form-control" id="scan_barcode_input" placeholder="คลิกที่นี่แล้วยิงบาร์โค้ด..." autocomplete="off">
                        <small class="text-muted">*ระบบจะเลือก Serial Number ให้อัตโนมัติเมื่อยิงบาร์โค้ด</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">เลือกหมายเลขเครื่อง (Serial Number):</label>
                        <select class="form-select" name="selected_item_id" id="approve_item_select" required>
                            <option value="">กำลังโหลดรายการ...</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="button" class="btn btn-success" onclick="submitApproveForm()">
                    <i class="fas fa-check-circle"></i> ยืนยันอนุมัติ
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// ฟังก์ชันเปิด Modal รายละเอียด (แก้ไข Aria-hidden)
function openDetailModal(element) {
    const item = element.getAttribute('data-item');
    const serial = element.getAttribute('data-serial');
    const requester = element.getAttribute('data-requester');
    const borrowDate = element.getAttribute('data-borrow');
    const dueDate = element.getAttribute('data-due');
    const reason = element.getAttribute('data-reason');
    const attachment = element.getAttribute('data-attachment');

    document.getElementById('modalItemName').innerText = item;
    document.getElementById('modalSerialNumber').innerText = (serial && serial !== '') ? serial : '-';
    document.getElementById('modalRequester').innerText = requester;
    document.getElementById('modalBorrowDate').innerText = borrowDate;
    document.getElementById('modalDueDate').innerText = dueDate;
    document.getElementById('modalReasonText').innerText = reason;

    const attachSection = document.getElementById('modalAttachmentSection');
    const attachLink = document.getElementById('modalAttachmentLink');

    if (attachment && attachment.trim() !== "") {
        attachSection.style.display = 'block'; 
        attachLink.href = attachment;          
    } else {
        attachSection.style.display = 'none';  
        attachLink.href = '#';
    }

    const modalEl = document.getElementById('detailModal');
    // ลบ aria-hidden ออกก่อนเปิดเพื่อป้องกัน Error
    modalEl.removeAttribute('aria-hidden');
    
    const myModal = bootstrap.Modal.getOrCreateInstance(modalEl);
    myModal.show();
}

// ฟังก์ชันเปิด Modal อนุมัติ (เลือกของ)
function openApproveSelectionModal(transId, currentItemId, equipName) {
    document.getElementById('approve_transaction_id').value = transId;
    document.getElementById('approve_original_item_id').value = currentItemId;
    document.getElementById('approve_equipment_name').value = equipName;
    document.getElementById('scan_barcode_input').value = ''; 
    
    const selectBox = document.getElementById('approve_item_select');
    selectBox.innerHTML = '<option value="">กำลังโหลด...</option>';

    fetch('ajax/get_items_for_approve.php?transaction_id=' + transId)
        .then(response => response.json())
        .then(data => {
            selectBox.innerHTML = ''; 
            if (data.status === 'success') {
                data.items.forEach(item => {
                    let isSelected = (item.id == currentItemId) ? 'selected' : '';
                    let label = item.serial_number ? `${item.serial_number} (ID: ${item.id})` : `ID: ${item.id} (ไม่มี Serial)`;
                    let option = `<option value="${item.id}" data-barcode="${item.id}" ${isSelected}>${label}</option>`;
                    selectBox.innerHTML += option;
                });
            } else {
                selectBox.innerHTML = '<option value="">ไม่พบอุปกรณ์</option>';
            }
        });

    const modalEl = document.getElementById('approveSelectModal');
    modalEl.removeAttribute('aria-hidden'); // ป้องกัน Error แบบเดียวกัน
    const myModal = new bootstrap.Modal(modalEl);
    myModal.show();
    
    modalEl.addEventListener('shown.bs.modal', function () {
        document.getElementById('scan_barcode_input').focus();
    });
}

document.getElementById('scan_barcode_input').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        const barcode = this.value.trim();
        const selectBox = document.getElementById('approve_item_select');
        let found = false;
        for (let i = 0; i < selectBox.options.length; i++) {
            if (selectBox.options[i].value == barcode) {
                selectBox.selectedIndex = i;
                found = true;
                break;
            }
        }
        if(found){
             this.value = '';
             submitApproveForm(); 
        } else {
             alert('ไม่พบอุปกรณ์หมายเลขนี้ในรายการ');
             this.value = '';
        }
    }
});

function submitApproveForm() {
    document.getElementById('approveSelectForm').submit();
}

// Listener รวมสำหรับ Modal ทุกตัวเพื่อจัดการ aria-hidden
document.addEventListener('show.bs.modal', event => {
    event.target.removeAttribute('aria-hidden');
});
document.addEventListener('shown.bs.modal', event => {
    const closeBtn = event.target.querySelector('.btn-close');
    if(closeBtn) closeBtn.focus();
});

document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById('equipmentStatusChart').getContext('2d');
    const availableCount = <?php echo $count_available; ?>;
    const borrowedCount = <?php echo $count_borrowed; ?>;
    const maintenanceCount = <?php echo $count_maintenance; ?>;
    
    const equipmentChart = new Chart(ctx, {
       type: 'pie', 
       data: {
           labels: ['พร้อมใช้งาน', 'กำลังถูกยืม', 'ซ่อมบำรุง'],
           datasets: [{
               data: [availableCount, borrowedCount, maintenanceCount],
               backgroundColor: ['rgba(22, 163, 74, 0.7)', 'rgba(254, 249, 195, 0.9)', 'rgba(249, 98, 11, 0.7)'],
               borderColor: ['rgba(22, 163, 74, 1)', 'rgba(133, 77, 14, 1)', 'rgba(220, 53, 69, 1)'],
               borderWidth: 1
           }]
       },
       options: { responsive: true, plugins: { legend: { position: 'top' } } }
    });

    try {
        const themeToggleBtn = document.getElementById('theme-toggle-btn');
        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', function() {
                setTimeout(() => {
                    const isDarkMode = document.body.classList.contains('dark-mode');
                    const newColor = isDarkMode ? '#E5E7EB' : '#6C757D';
                    if (equipmentChart) {
                        equipmentChart.options.plugins.legend.labels.color = newColor;
                        equipmentChart.update(); 
                    }
                }, 10); 
            });
        }
    } catch (e) { console.error(e); }
});
</script>

<?php
include('../includes/footer.php');
?>