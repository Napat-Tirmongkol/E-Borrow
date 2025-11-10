<?php
include('../includes/check_session.php'); 
require_once('../includes/db_connect.php');

$allowed_roles = ['admin', 'editor'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header("Location: index.php");
    exit;
}

// 3. (Query 1) ดึงรายการที่ "เกินกำหนด" และ "ยังไม่ถูกปรับ"
$overdue_unfined = [];
try {
    $sql1 = "SELECT 
                t.id as transaction_id, t.due_date, t.return_date,
                ei.name as equipment_name,
                s.id as student_id, s.full_name as student_name,
                DATEDIFF(
                    COALESCE(t.return_date, CURDATE()), -- ถัาคืนแล้ว, ใช้วันที่คืน. ถ้ายัง, ใช้วันนี้
                    t.due_date                        -- ลบด้วย วันที่กำหนดคืน
                ) AS days_overdue
             FROM med_transactions t
             JOIN med_equipment_items ei ON t.equipment_id = ei.id
             LEFT JOIN med_students s ON t.borrower_student_id = s.id
             WHERE t.fine_status = 'none'
               AND t.approval_status IN ('approved', 'staff_added')
               -- (เช็คว่า วันที่ครบกำหนด < วันที่คืนจริง(หรือวันนี้))
               AND t.due_date < COALESCE(t.return_date, CURDATE()) 
             ORDER BY t.due_date ASC";
             
    $stmt1 = $pdo->prepare($sql1);
    $stmt1->execute();
    $overdue_unfined = $stmt1->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error1 = "เกิดข้อผิดพลาดในการดึงข้อมูล (Query 1): " . $e->getMessage();
}

// 4. (Query 2) ดึงรายการ "ค่าปรับที่สร้างแล้ว" (ประวัติการชำระ)
$fines_list = [];
try {
    // (แก้ไข Query ให้ดึงเฉพาะที่จ่ายแล้ว ('paid') มาแสดงในประวัติ)
    $sql2 = "SELECT 
                f.id as fine_id, f.amount, f.status as fine_status, f.notes, f.created_at,
                t.id as transaction_id,
                ei.name as equipment_name,
                s.full_name as student_name,
                p.id as payment_id, p.payment_date, p.amount_paid,
                u_staff.full_name as staff_name
             FROM med_fines f
             LEFT JOIN med_transactions t ON f.transaction_id = t.id
             LEFT JOIN med_equipment_items ei ON t.equipment_id = ei.id
             LEFT JOIN med_students s ON f.student_id = s.id
             LEFT JOIN med_users u_staff ON f.created_by_staff_id = u_staff.id
             LEFT JOIN med_payments p ON f.id = p.fine_id
             WHERE f.status = 'paid' -- ◀️ แสดงเฉพาะที่จ่ายแล้ว
             ORDER BY f.created_at DESC";
    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute();
    $fines_list = $stmt2->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error2 = "เกิดข้อผิดพลาดในการดึงข้อมูล (Query 2): " . $e->getMessage();
}


// 5. ตั้งค่าตัวแปรสำหรับ Header
$page_title = "จัดการค่าปรับ";
$current_page = "manage_fines"; 
include('../includes/header.php');
?>

<?php if (isset($error1)) echo "<p style='color: red;'>$error1</p>"; ?>
<?php if (isset($error2)) echo "<p style='color: red;'>$error2</p>"; ?>


<div class="header-row" data-target="#overdueSectionContent">
    <h2><i class="fas fa-exclamation-triangle" style="color: var(--color-danger);"></i> 1. รายการเกินกำหนด (รอชำระ)</h2>
    <button type="button" class="collapse-toggle-btn">
        <i class="fas fa-chevron-down"></i>
        <i class="fas fa-chevron-up"></i>
    </button>
</div>

<div id="overdueSectionContent" class="collapsible-content">
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>ผู้ยืม</th>
                    <th>อุปกรณ์</th>
                    <th>กำหนดคืน</th>
                    <th>เกินกำหนด (วัน)</th>
                    <th>ค่าปรับ (บาท)</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($overdue_unfined)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">ไม่มีรายการเกินกำหนดที่ต้องจัดการ</td>
                    </tr>
                <?php else: ?>
                    <?php 
                    foreach ($overdue_unfined as $item): 
                        $days_overdue = (int)$item['days_overdue'];
                        // (ป้องกันค่าติดลบ หากมีการแก้ไขข้อมูลย้อนหลัง)
                        if ($days_overdue < 0) $days_overdue = 0; 
                        
                        $calculated_fine = $days_overdue * FINE_RATE_PER_DAY; 
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['student_name'] ?? '[ผู้ใช้ถูกลบ]'); ?></td>
                            <td><?php echo htmlspecialchars($item['equipment_name']); ?></td>
                            <td style="color: var(--color-danger); font-weight: bold;"><?php echo date('d/m/Y', strtotime($item['due_date'])); ?></td>
                            <td style="text-align: center; font-weight: bold; font-size: 1.1em;"><?php echo $days_overdue; ?></td>
                            <td style="text-align: right; font-weight: bold; font-size: 1.1em; color: var(--color-danger);">
                                <?php echo number_format($calculated_fine, 2); ?>
                            </td>
                            <td class="action-buttons">
                                
                                <button type="button" class="btn btn-success" 
                                    onclick="openDirectPaymentPopup(
                                        <?php echo $item['transaction_id']; ?>, 
                                        <?php echo $item['student_id'] ?? 0; ?>, 
                                        '<?php echo htmlspecialchars(addslashes($item['student_name'] ?? '[ผู้ใช้ถูกลบ]')); ?>', 
                                        '<?php echo htmlspecialchars(addslashes($item['equipment_name'])); ?>',
                                        <?php echo $days_overdue; ?>,
                                        <?php echo $calculated_fine; ?>
                                    )">
                                    <i class="fas fa-hand-holding-usd"></i> ชำระเงิน
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<div class="header-row" data-target="#finesSectionContent" style="margin-top: 2rem;">
    <h2><i class="fas fa-file-invoice-dollar" style="color: var(--color-primary);"></i> 2. ประวัติการชำระค่าปรับ</h2>
    <button type="button" class="collapse-toggle-btn">
        <i class="fas fa-chevron-down"></i>
        <i class="fas fa-chevron-up"></i>
    </button>
</div>

<div id="finesSectionContent" class="collapsible-content">
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>ผู้ยืม</th>
                    <th>อุปกรณ์</th>
                    <th>จำนวนเงิน (บาท)</th>
                    <th>สถานะ</th>
                    <th>ผู้รับชำระ/วันที่</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($fines_list)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">ยังไม่มีประวัติค่าปรับ</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($fines_list as $fine): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($fine['student_name'] ?? '[N/A]'); ?></td>
                            <td><?php echo htmlspecialchars($fine['equipment_name']); ?></td>
                            <td><strong><?php echo number_format($fine['amount'], 2); ?></strong></td>
                            <td>
                                <span class="status-badge returned">
                                    <i class="fas fa-check-circle"></i> ชำระแล้ว
                                </span>
                                <div style="font-size: 0.9em; margin-top: 5px; color: #555;">
                                    (<?php echo date('d/m/Y', strtotime($fine['payment_date'])); ?>)
                                </div>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($fine['staff_name'] ?? '[N/A]'); ?>
                                <div style="font-size: 0.9em; margin-top: 5px; color: #555;">
                                    (<?php echo date('d/m/Y', strtotime($fine['created_at'])); ?>)
                                </div>
                            </td>
                           <td class="action-buttons">
                                
                                <a href="admin/print_receipt.php?payment_id=<?php echo $fine['payment_id']; ?>" 
                                   target="_blank" 
                                   class="btn btn-secondary">
                                    <i class="fas fa-print"></i> พิมพ์ใบเสร็จ
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<script>
// (JS สำหรับหน้านี้)

// 1. Popup สำหรับ "ชำระเงินโดยตรง" (จากตารางที่ 1)
function openDirectPaymentPopup(transactionId, studentId, studentName, equipName, daysOverdue, calculatedFine) {
    
    // ✅ (1.1) สร้างฟังก์ชัน Helper นี้ไว้ข้างนอก
    const setupPaymentMethodToggle = () => {
        try {
            // ค้นหา element ภายใน Popup ที่กำลังเปิดอยู่
            const cashRadio = Swal.getPopup().querySelector('#swal_pm_cash_1');
            const bankRadio = Swal.getPopup().querySelector('#swal_pm_bank_1');
            const slipGroup = Swal.getPopup().querySelector('#slipUploadGroup');
            const slipInput = Swal.getPopup().querySelector('#swal_payment_slip');
            const slipRequired = Swal.getPopup().querySelector('#slipRequired');

            const toggleLogic = (method) => {
                if (method === 'bank_transfer') {
                    slipGroup.style.display = 'block';
                    slipInput.required = true;
                    slipRequired.style.display = 'inline';
                } else {
                    slipGroup.style.display = 'none';
                    slipInput.required = false;
                    slipRequired.style.display = 'none';
                }
            };

            // แนบ Listener
            cashRadio.addEventListener('change', () => toggleLogic('cash'));
            bankRadio.addEventListener('change', () => toggleLogic('bank_transfer'));
            
            // เรียกใช้ครั้งแรก
            toggleLogic('cash');
        } catch (e) {
            console.error('Swal Toggle Error:', e);
        }
    };

    Swal.fire({
        title: '💵 บันทึกการชำระเงิน (เกินกำหนด)',
        html: `
        <div class="swal-info-box">
            <p style="margin: 0;"><strong>ผู้ยืม:</strong> ${studentName}</p>
            <p style="margin: 5px 0 0 0;"><strong>อุปกรณ์:</strong> ${equipName}</p>
            <p style="margin: 5px 0 0 0;" class="swal-info-danger">
                <strong>เกินกำหนด:</strong> ${daysOverdue} วัน
            </p>
        </div>
        
        <form id="swalDirectPaymentForm" style="text-align: left; margin-top: 20px;" enctype="multipart/form-data">
            <input type="hidden" name="transaction_id" value="${transactionId}">
            <input type="hidden" name="student_id" value="${studentId}">
            <input type="hidden" name="amount" value="${calculatedFine.toFixed(2)}">
            <input type="hidden" name="notes" value="เกินกำหนด ${daysOverdue} วัน">

            <div style="margin-bottom: 15px;">
                <label for="swal_amount_paid" style="font-weight: bold; display: block; margin-bottom: 5px;">จำนวนเงินที่รับชำระ: <span style="color:red;">*</span></label>
                <input type="number" name="amount_paid" id="swal_amount_paid" value="${calculatedFine.toFixed(2)}" step="0.01" required 
                       style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ddd; font-size: 1.2em; color: var(--color-primary); font-weight: bold;">
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="font-weight: bold; display: block; margin-bottom: 5px;">วิธีการชำระเงิน: <span style="color:red;">*</span></label>
                <div style="display: flex; gap: 1rem;">
                    
                    <label style="font-weight: normal;">
                        <input type="radio" name="payment_method" id="swal_pm_cash_1" value="cash" checked> เงินสด
                    </label>
                    <label style="font-weight: normal;">
                        <input type="radio" name="payment_method" id="swal_pm_bank_1" value="bank_transfer"> บัญชีธนาคาร
                    </label>
                </div>
            </div>

            <div id="slipUploadGroup" style="display: none; margin-bottom: 15px;">
                <label for="swal_payment_slip" style="font-weight: bold; display: block; margin-bottom: 5px;">แนบสลิปการโอน: <span id="slipRequired" style="color:red; display: none;">*</span></label>
                <input type="file" name="payment_slip" id="swal_payment_slip" accept="image/*"
                       style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ddd;">
            </div>
            
        </form>
        `,
        
        // ✅ (1.4) เพิ่ม didOpen กลับมา
        didOpen: () => {
            setupPaymentMethodToggle();
        },

        showCancelButton: true,
        confirmButtonText: 'ยืนยันการชำระเงิน',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: 'var(--color-success)',
        focusConfirm: false,
        preConfirm: () => {
            const form = document.getElementById('swalDirectPaymentForm');
            const formData = new FormData(form); 
            
            const paymentMethod = formData.get('payment_method');
            const slipFile = formData.get('payment_slip');

            if (paymentMethod === 'bank_transfer' && (!slipFile || slipFile.size === 0)) {
                Swal.showValidationMessage('กรุณาแนบสลิปการโอน');
                return false;
            }
            
            if (!form.checkValidity()) {
                Swal.showValidationMessage('กรุณากรอกข้อมูล * ให้ครบถ้วน');
                return false;
            }
            
            return fetch('process/direct_payment_process.php', { method: 'POST', body: formData }) 
                .then(response => response.json())
                .then(data => {
                    if (data.status !== 'success') throw new Error(data.message);
                    return data; 
                })
                .catch(error => { Swal.showValidationMessage(`เกิดข้อผิดพลาด: ${error.message}`); });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'ชำระเงินสำเร็จ!',
                text: 'บันทึกการชำระเงินเรียบร้อย',
                icon: 'success',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-print"></i> พิมพ์ใบเสร็จ',
                cancelButtonText: 'ปิดหน้าต่าง',
            }).then((finalResult) => {
                if (finalResult.isConfirmed) {
                    const newPaymentId = result.value.new_payment_id;
                    window.open(`admin/print_receipt.php?payment_id=${newPaymentId}`, '_blank');
                    
                    location.reload();
                } else {
                    location.reload(); 
                }
            });
        }
    });
}


// 2. Popup สำหรับ "รับชำระเงิน" (จากตารางที่ 2 - สำหรับข้อมูลเก่า)
function openRecordPaymentPopup(fineId, studentName, amountDue) {
    
    // ✅ (2.1) สร้างฟังก์ชัน Helper นี้ไว้ข้างนอก
    const setupPaymentMethodToggle = () => {
        try {
            // ค้นหา element ภายใน Popup ที่กำลังเปิดอยู่
            const cashRadio = Swal.getPopup().querySelector('#swal_pm_cash_2');
            const bankRadio = Swal.getPopup().querySelector('#swal_pm_bank_2');
            const slipGroup = Swal.getPopup().querySelector('#slipUploadGroup');
            const slipInput = Swal.getPopup().querySelector('#swal_payment_slip');
            const slipRequired = Swal.getPopup().querySelector('#slipRequired');

            const toggleLogic = (method) => {
                if (method === 'bank_transfer') {
                    slipGroup.style.display = 'block';
                    slipInput.required = true;
                    slipRequired.style.display = 'inline';
                } else {
                    slipGroup.style.display = 'none';
                    slipInput.required = false;
                    slipRequired.style.display = 'none';
                }
            };

            // แนบ Listener
            cashRadio.addEventListener('change', () => toggleLogic('cash'));
            bankRadio.addEventListener('change', () => toggleLogic('bank_transfer'));
            
            // เรียกใช้ครั้งแรก
            toggleLogic('cash');
        } catch (e) {
            console.error('Swal Toggle Error:', e);
        }
    };

    Swal.fire({
        title: '💵 บันทึกการชำระเงิน',
        html: `
        <div class="swal-info-box">
            <p style="margin: 0;"><strong>ผู้ยืม:</strong> ${studentName}</p>
            <p style="margin: 5px 0 0 0;"><strong>ยอดค้างชำระ:</strong> ${amountDue.toFixed(2)} บาท</p>
        </div>
        <form id="swalPaymentForm" style="text-align: left; margin-top: 20px;" enctype="multipart/form-data">
            <input type="hidden" name="fine_id" value="${fineId}">
            
            <div style="margin-bottom: 15px;">
                <label for="swal_amount_paid" style="font-weight: bold; display: block; margin-bottom: 5px;">จำนวนเงินที่รับ: <span style="color:red;">*</span></label>
                <input type="number" name="amount_paid" id="swal_amount_paid" value="${amountDue.toFixed(2)}" step="0.01" required 
                       style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ddd;">
            </div>

            <div style="margin-bottom: 15px;">
                <label style="font-weight: bold; display: block; margin-bottom: 5px;">วิธีการชำระเงิน: <span style="color:red;">*</span></label>
                <div style="display: flex; gap: 1rem;">
                    
                    <label style="font-weight: normal;">
                        <input type="radio" name="payment_method" id="swal_pm_cash_2" value="cash" checked> เงินสด
                    </label>
                    <label style="font-weight: normal;">
                        <input type="radio" name="payment_method" id="swal_pm_bank_2" value="bank_transfer"> บัญชีธนาคาร
                    </label>
                </div>
            </div>

            <div id="slipUploadGroup" style="display: none; margin-bottom: 15px;">
                <label for="swal_payment_slip" style="font-weight: bold; display: block; margin-bottom: 5px;">แนบสลิปการโอน: <span id="slipRequired" style="color:red; display: none;">*</span></label>
                <input type="file" name="payment_slip" id="swal_payment_slip" accept="image/*"
                       style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ddd;">
            </div>
        </form>
        `,
        
        // ✅ (2.4) เพิ่ม didOpen กลับมา
        didOpen: () => {
            setupPaymentMethodToggle();
        },

        showCancelButton: true,
        confirmButtonText: 'ยืนยันการชำระเงิน',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: 'var(--color-success)',
        focusConfirm: false,
        preConfirm: () => {
            const form = document.getElementById('swalPaymentForm');
            const formData = new FormData(form);

            const paymentMethod = formData.get('payment_method');
            const slipFile = formData.get('payment_slip');

            if (paymentMethod === 'bank_transfer' && (!slipFile || slipFile.size === 0)) {
                Swal.showValidationMessage('กรุณาแนบสลิปการโอน');
                return false;
            }

            if (!form.checkValidity()) {
                Swal.showValidationMessage('กรุณากรอกจำนวนเงิน');
                return false;
            }
           return fetch('process/record_payment_process.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.status !== 'success') throw new Error(data.message);
                    return data; 
                })
                .catch(error => { Swal.showValidationMessage(`เกิดข้อผิดพลาด: ${error.message}`); });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'ชำระเงินสำเร็จ!',
                text: 'บันทึกการชำระเงินเรียบร้อย',
                icon: 'success',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-print"></i> พิมพ์ใบเสร็จ',
                cancelButtonText: 'ปิดหน้าต่าง',
            }).then((finalResult) => {
                if (finalResult.isConfirmed) {
                    const newPaymentId = result.value.new_payment_id;
                    window.open(`admin/print_receipt.php?payment_id=${newPaymentId}`, '_blank');
                    
                    location.reload();
                } else {
                    location.reload(); 
                }
            });
        }
    });
}
</script>

<?php
include('../includes/footer.php');
?>