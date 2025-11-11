// [สร้างไฟล์ใหม่: assets/js/admin_app.js]

// (JS ทั้งหมดนี้จะทำงานสัมพันธ์กับ <base href="/e_Borrow_test/"> ที่เราตั้งใน header.php)

// ✅ =========================================
// (ใหม่) ฟังก์ชันสำหรับ "ชำระค่าปรับ" (ย้ายมาจาก manage_fines.php)
// (เพิ่ม onSuccessCallback ที่ท้ายสุด)
// =========================================

// 1. Popup สำหรับ "ชำระเงินโดยตรง" (จากตารางที่ 1)
function openDirectPaymentPopup(transactionId, studentId, studentName, equipName, daysOverdue, calculatedFine, onSuccessCallback = null) {
    
    // (Helper function)
    const setupPaymentMethodToggle_Direct = () => {
        try {
            const cashRadio = Swal.getPopup().querySelector('#swal_pm_cash_1');
            const bankRadio = Swal.getPopup().querySelector('#swal_pm_bank_1');
            const slipGroup = Swal.getPopup().querySelector('#slipUploadGroup');
            const slipInput = Swal.getPopup().querySelector('#swal_payment_slip');
            const slipRequired = Swal.getPopup().querySelector('#slipRequired');

            const toggleLogic = (method) => {
                if (method === 'bank_transfer') {
                    slipGroup.style.display = 'block'; slipInput.required = true; slipRequired.style.display = 'inline';
                } else {
                    slipGroup.style.display = 'none'; slipInput.required = false; slipRequired.style.display = 'none';
                }
            };
            cashRadio.addEventListener('change', () => toggleLogic('cash'));
            bankRadio.addEventListener('change', () => toggleLogic('bank_transfer'));
            toggleLogic('cash');
        } catch (e) { console.error('Swal Toggle Error:', e); }
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
        </form>`,
        didOpen: () => {
            setupPaymentMethodToggle_Direct();
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
                }
                
                // (เรียก Callback ถ้ามี)
                if (onSuccessCallback) {
                    onSuccessCallback(); 
                } else {
                    location.reload(); 
                }
            });
        }
    });
}

// 2. Popup สำหรับ "รับชำระเงิน" (จากตารางที่ 2 - สำหรับข้อมูลเก่า)
function openRecordPaymentPopup(fineId, studentName, amountDue, onSuccessCallback = null) {
    
    // (Helper function)
    const setupPaymentMethodToggle_Record = () => {
        try {
            const cashRadio = Swal.getPopup().querySelector('#swal_pm_cash_2');
            const bankRadio = Swal.getPopup().querySelector('#swal_pm_bank_2');
            const slipGroup = Swal.getPopup().querySelector('#slipUploadGroup');
            const slipInput = Swal.getPopup().querySelector('#swal_payment_slip');
            const slipRequired = Swal.getPopup().querySelector('#slipRequired');

            const toggleLogic = (method) => {
                if (method === 'bank_transfer') {
                    slipGroup.style.display = 'block'; slipInput.required = true; slipRequired.style.display = 'inline';
                } else {
                    slipGroup.style.display = 'none'; slipInput.required = false; slipRequired.style.display = 'none';
                }
            };
            cashRadio.addEventListener('change', () => toggleLogic('cash'));
            bankRadio.addEventListener('change', () => toggleLogic('bank_transfer'));
            toggleLogic('cash');
        } catch (e) { console.error('Swal Toggle Error:', e); }
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
        </form>`,
        didOpen: () => {
            setupPaymentMethodToggle_Record();
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
                }
                
                // (เรียก Callback ถ้ามี)
                if (onSuccessCallback) {
                    onSuccessCallback(); 
                } else {
                    location.reload(); 
                }
            });
        }
    });
}

// 3. ✅ (ใหม่) ฟังก์ชัน Wrapper สำหรับ Workflow ใหม่
function openFineAndReturnPopup(transactionId, studentId, studentName, equipName, daysOverdue, calculatedFine, equipmentId) {
    
    // (1) สร้างฟังก์ชัน Callback ที่จะทำงาน "หลังจาก" จ่ายเงินสำเร็จ
    const returnCallback = () => {
        // (เมื่อจ่ายเงินเสร็จ ให้เปิด Popup "รับคืน" ทันที)
        openReturnPopup(equipmentId);
    };

    // (2) เรียกฟังก์ชันชำระเงิน (ที่เพิ่งย้ายมา)
    // (และส่งฟังก์ชัน Callback (ข้อ 1) เข้าไปด้วย)
    openDirectPaymentPopup(
        transactionId, 
        studentId, 
        studentName, 
        equipName, 
        daysOverdue, 
        calculatedFine, 
        returnCallback 
    );
}

// =========================================
// (จบ) ฟังก์ชันสำหรับ "ชำระค่าปรับ"
// =========================================


// (ฟังก์ชัน "ยืม")
function openBorrowPopup(typeId) {
    Swal.fire({ title: 'กำลังโหลดข้อมูล...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
    
    fetch(`ajax/get_borrow_form_data.php?type_id=${typeId}`) 
        .then(response => response.json())
        .then(data => {
            if (data.status !== 'success') throw new Error(data.message);
            
            let borrowerOptions = '<option value="">--- กรุณาเลือกผู้ยืม ---</option>';
            if (data.borrowers.length > 0) {
                data.borrowers.forEach(b => { 
                    borrowerOptions += `<option value="${b.id}">${b.full_name} (${b.contact_info || 'N/A'})</option>`;
                });
            } else {
                borrowerOptions = '<option value="" disabled>ยังไม่มีข้อมูลผู้ใช้งานในระบบ</option>';
            }
            
            Swal.fire({
                title: '📝 ฟอร์มยืมอุปกรณ์',
                html: `
                <div class="swal-info-box">
                    <p style="margin: 0;"><strong>ประเภทอุปกรณ์:</strong> ${data.equipment_type.name}</p>
                </div>
                <form id="swalBorrowForm" style="text-align: left; margin-top: 20px;">
                    <input type="hidden" name="type_id" value="${data.equipment_type.id}">
                    <div style="margin-bottom: 15px;">
                        <label for="swal_borrower_id" style="font-weight: bold; display: block; margin-bottom: 5px;">ผู้ยืม:</label>
                        <select name="borrower_id" id="swal_borrower_id" required style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ddd;">
                            ${borrowerOptions}
                        </select>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label for="swal_due_date" style="font-weight: bold; display: block; margin-bottom: 5px;">วันที่กำหนดคืน:</label>
                        <input type="date" name="due_date" id="swal_due_date" required style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ddd;">
                    </div>
                </form>`,
                width: '600px',
                showCancelButton: true,
                confirmButtonText: 'ยืนยันการยืม',
                cancelButtonText: 'ยกเลิก',
                confirmButtonColor: 'var(--color-success, #28a745)',
                focusConfirm: false,
                preConfirm: () => {
                    const form = document.getElementById('swalBorrowForm');
                    const borrowerId = form.querySelector('#swal_borrower_id').value;
                    const dueDate = form.querySelector('#swal_due_date').value;
                    if (!borrowerId || !dueDate) {
                         Swal.showValidationMessage('กรุณากรอกข้อมูลให้ครบถ้วน');
                         return false;
                    }
                    return fetch('process/borrow_process.php', { method: 'POST', body: new FormData(form) })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status !== 'success') throw new Error(data.message);
                            return data;
                        })
                        .catch(error => { Swal.showValidationMessage(`เกิดข้อผิดพลาด: ${error.message}`); });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire('ยืมสำเร็จ!', 'บันทึกข้อมูลการยืมเรียบร้อย', 'success').then(() => location.reload());
                }
            });
        })
        .catch(error => {
            Swal.fire('เกิดข้อผิดพลาด', error.message, 'error');
        });
}

function openAddEquipmentTypePopup() { 
    Swal.fire({
        title: '➕ เพิ่มประเภทอุปกรณ์ใหม่',
        html: `
            <form id="swalAddForm" style="text-align: left; margin-top: 20px;" enctype="multipart/form-data">
                <div style="margin-bottom: 15px;">
                    <label for="swal_eq_name" style="font-weight: bold; display: block; margin-bottom: 5px;">ชื่อประเภทอุปกรณ์:</label>
                    <input type="text" name="name" id="swal_eq_name" required style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ddd;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label for="swal_eq_desc" style="font-weight: bold; display: block; margin-bottom: 5px;">รายละเอียด:</label>
                    <textarea name="description" id="swal_eq_desc" rows="3" style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ddd;"></textarea>
                </div>
                <div style="margin-bottom: 15px;">
                    <label for="swal_type_image_file" style="font-weight: bold; display: block; margin-bottom: 5px;">แนบรูปภาพ (ถ้ามี):</label>
                    <input type="file" name="image_file" id="swal_type_image_file" accept="image/*" style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ddd;">
                </div>
            </form>`,
        width: '600px',
        showCancelButton: true,
        confirmButtonText: 'บันทึก',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: 'var(--color-success, #28a745)',
        focusConfirm: false,
        preConfirm: () => {
            const form = document.getElementById('swalAddForm');
            const name = form.querySelector('#swal_eq_name').value;
            if (!name) {
                Swal.showValidationMessage('กรุณากรอกชื่อประเภทอุปกรณ์');
                return false;
            }
            return fetch('process/add_equipment_type_process.php', { method: 'POST', body: new FormData(form) }) 
                .then(response => response.json())
                .then(data => {
                    if (data.status !== 'success') throw new Error(data.message);
                    return data;
                })
                .catch(error => { Swal.showValidationMessage(`เกิดข้อผิดพลาด: ${error.message}`); });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire('เพิ่มสำเร็จ!', 'เพิ่มประเภทอุปกรณ์ใหม่เรียบร้อย', 'success').then(() => location.reload());
        }
    });
}
// 2. ฟังก์ชัน "แก้ไข" (อัปเดตสำหรับ File Upload)
function openEditEquipmentTypePopup(typeId) { 
    Swal.fire({ title: 'กำลังโหลดข้อมูล...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
    
    fetch(`ajax/get_equipment_type_data.php?id=${typeId}`) 
        .then(response => response.json())
        .then(data => {
            if (data.status !== 'success') throw new Error(data.message);
            const type = data.equipment_type;
            
            let imagePreviewHtml = `
                <div class="equipment-card-image-placeholder" style="width: 100%; height: 150px; font-size: 3rem; margin-bottom: 15px; display: flex; justify-content: center; align-items: center; background-color: #f0f0f0; color: #ccc; border-radius: 6px;">
                    <i class="fas fa-camera"></i>
                </div>`;
            if (type.image_url) {
                imagePreviewHtml = `
                    <img src="${type.image_url}?t=${new Date().getTime()}" 
                         alt="รูปตัวอย่าง" 
                         style="width: 100%; height: 150px; object-fit: cover; border-radius: 6px; margin-bottom: 15px;"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                    <div class="equipment-card-image-placeholder" style="display: none; width: 100%; height: 150px; font-size: 3rem; margin-bottom: 15px; justify-content: center; align-items: center; background-color: #f0f0f0; color: #ccc; border-radius: 6px;"><i class="fas fa-image"></i></div>`;
            }

            Swal.fire({
                title: '🔧 แก้ไขประเภทอุปกรณ์',
                html: `
                <form id="swalEditForm" style="text-align: left; margin-top: 20px;" enctype="multipart/form-data">
                    
                    ${imagePreviewHtml} <input type="hidden" name="type_id" value="${type.id}">
                    
                    <div style="margin-bottom: 15px;">
                        <label for="swal_eq_image_file" style="font-weight: bold; display: block; margin-bottom: 5px;">แนบรูปภาพใหม่ (เพื่อแทนที่):</label>
                        <input type="file" name="image_file" id="swal_eq_image_file" accept="image/*" style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ddd;">
                        <small style="color: #6c757d;">(หากไม่ต้องการเปลี่ยนรูป ให้เว้นว่างไว้)</small>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label for="swal_name" style="font-weight: bold; display: block; margin-bottom: 5px;">ชื่อประเภทอุปกรณ์:</label>
                        <input type="text" name="name" id="swal_name" value="${type.name}" required style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ddd;">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label for="swal_desc" style="font-weight: bold; display: block; margin-bottom: 5px;">รายละเอียด:</label>
                        <textarea name="description" id="swal_desc" rows="3" style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ddd;">${type.description || ''}</textarea>
                    </div>
                </form>`,
                width: '600px',
                showCancelButton: true,
                confirmButtonText: 'บันทึกการเปลี่ยนแปลง',
                showDenyButton: true, 
                denyButtonText: `<i class="fas fa-trash"></i> ลบประเภทนี้`,
                denyButtonColor: 'var(--color-danger)',

                cancelButtonText: 'ยกเลิก',
                confirmButtonColor: 'var(--color-primary, #0B6623)',
                focusConfirm: false,
                preConfirm: () => {
                    const form = document.getElementById('swalEditForm');
                    const name = form.querySelector('#swal_name').value;
                    if (!name) {
                        Swal.showValidationMessage('กรุณากรอกชื่อประเภทอุปกรณ์');
                        return false;
                    }
                    return fetch('process/edit_equipment_type_process.php', { method: 'POST', body: new FormData(form) }) 
                        .then(response => response.json())
                        .then(data => {
                            if (data.status !== 'success') throw new Error(data.message);
                            return data;
                        })
                        .catch(error => { Swal.showValidationMessage(`เกิดข้อผิดพลาด: ${error.message}`); });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire('บันทึกสำเร็จ!', 'แก้ไขข้อมูลประเภทอุปกรณ์เรียบร้อย', 'success').then(() => location.reload());
                }
                if (result.isDenied) {
                    confirmDeleteType(typeId, type.name); 
                }
            });
        })
        .catch(error => {
            Swal.fire('เกิดข้อผิดพลาด', error.message, 'error');
        });
}

// (ฟังก์ชันลบประเภท)
function confirmDeleteType(typeId, typeName) {
    Swal.fire({
        title: "คุณแน่ใจหรือไม่?",
        text: `คุณกำลังจะลบประเภท "${typeName}" (จะลบได้ต่อเมื่อไม่มีอุปกรณ์รายชิ้นในประเภทนี้)`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#3085d6",
        confirmButtonText: "ใช่, ลบเลย",
        cancelButtonText: "ยกเลิก"
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('id', typeId);

            fetch('process/delete_equipment_type_process.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire('ลบสำเร็จ!', data.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('เกิดข้อผิดพลาด!', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.fire('เกิดข้อผิดพลาด AJAX', error.message, 'error');
            });
        }
    });
}


// 3. ฟังก์ชัน "รับคืน"
function openReturnPopup(equipmentId) {
    Swal.fire({ title: 'กำลังโหลดข้อมูลการยืม...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
    
    fetch(`ajax/get_return_form_data.php?id=${equipmentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.status !== 'success') throw new Error(data.message);
            const trans = data.transaction;
            const formatDate = (dateString) => {
                if (!dateString) return 'N/A';
                const date = new Date(dateString);
                return date.toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: 'numeric' });
            };
            
            Swal.fire({
                title: '📦 ยืนยันการรับคืน?',
                html: `
                <div class="swal-info-box">
                    <p style="margin: 0;"><strong>อุปกรณ์:</strong> ${trans.equipment_name} (${trans.equipment_serial || 'N/A'})</p>
                    <p style="margin: 5px 0 0 0;"><strong>ผู้ยืม:</strong> ${trans.borrower_name} (${trans.borrower_contact || 'N/A'})</p>
                    <p style="margin: 5px 0 0 0;"><strong>วันที่ยืม:</strong> ${formatDate(trans.borrow_date)}</p>
                    <p style="margin: 5px 0 0 0;"><strong>กำหนดคืน:</strong> ${formatDate(trans.due_date)}</p>
                </div>
                <p style="font-weight: bold; color: #dc3545;">กรุณาตรวจสอบอุปกรณ์ก่อนกดยืนยัน</p>
                <form id="swalReturnForm">
                    <input type="hidden" name="equipment_id" value="${equipmentId}">
                    <input type="hidden" name="transaction_id" value="${trans.transaction_id}">
                </form>`,
                icon: 'warning',
                width: '600px',
                showCancelButton: true,
                confirmButtonText: 'ใช่, ยืนยันการรับคืน',
                cancelButtonText: 'ยกเลิก',
                confirmButtonColor: 'var(--color-primary, #0B6623)',
                cancelButtonColor: '#d33',
                preConfirm: () => {
                    const form = document.getElementById('swalReturnForm');
                    return fetch('process/return_process.php', { method: 'POST', body: new FormData(form) })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status !== 'success') throw new Error(data.message);
                            return data;
                        })
                        .catch(error => { Swal.showValidationMessage(`เกิดข้อผิดพลาด: ${error.message}`); });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire('รับคืนสำเร็จ!', 'อุปกรณ์กลับเข้าสู่สถานะ "ว่าง"', 'success')
                    .then(() => location.reload());
                }
            });
        })
        .catch(error => {
            Swal.fire('เกิดข้อผิดพลาด', error.message, 'error');
        });
}

// 4. ฟังก์ชัน "อนุมัติ" (Popup)
function openApprovePopup(transactionId) {
    Swal.fire({
        title: "ยืนยันการอนุมัติ?",
        text: "ระบบจะเปลี่ยนสถานะอุปกรณ์เป็น 'ถูกยืม'",
        icon: "info",
        showCancelButton: true,
        confirmButtonColor: "var(--color-success, #28a745)", // สีเขียว
        cancelButtonColor: "#d33",
        confirmButtonText: "ใช่, อนุมัติ",
        cancelButtonText: "ยกเลิก"
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('transaction_id', transactionId);

            fetch('process/approve_request_process.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire('อนุมัติสำเร็จ!', data.message, 'success')
                    .then(() => location.reload());
                } else {
                    Swal.fire('เกิดข้อผิดพลาด!', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.fire('เกิดข้อผิดพลาด AJAX', error.message, 'error');
            });
        }
    });
}

// 5. ฟังก์ชัน "ปฏิเสธ" (Popup)
function openRejectPopup(transactionId) {
    Swal.fire({
        title: "คุณแน่ใจหรือไม่?",
        text: "คุณกำลังจะปฏิเสธคำขอนี้",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33", // สีแดง
        cancelButtonColor: "#3085d6",
        confirmButtonText: "ใช่, ปฏิเสธ",
        cancelButtonText: "ยกเลิก"
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('transaction_id', transactionId);

            fetch('process/reject_request_process.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire('ปฏิเสธสำเร็จ', data.message, 'success')
                    .then(() => location.reload());
                } else {
                    Swal.fire('เกิดข้อผิดพลาด!', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.fire('เกิดข้อผิดพลาด AJAX', error.message, 'error');
            });
        }
    });
}

// ( ... โค้ดสำหรับ Hamburger ... )
const hamburgerBtn = document.getElementById('hamburgerBtn');
const sidebar = document.querySelector('.sidebar');
const body = document.body;

if (hamburgerBtn && sidebar) {
    hamburgerBtn.addEventListener('click', () => {
        sidebar.classList.toggle('sidebar-visible');
        body.classList.toggle('sidebar-open-overlay');
    });
    
    body.addEventListener('click', (event) => {
        if (body.classList.contains('sidebar-open-overlay') && 
            !sidebar.contains(event.target) && 
            !hamburgerBtn.contains(event.target)) {
            
            sidebar.classList.remove('sidebar-visible');
            body.classList.remove('sidebar-open-overlay');
        }
    });
}

function showReasonPopup(reason) {
    Swal.fire({
        title: 'เหตุผลการยืม',
        text: reason,
        icon: 'info',
        confirmButtonText: 'ปิด',
        confirmButtonColor: 'var(--color-primary, #0B6623)',
    });
}


// =========================================
// (ใหม่) Logic สำหรับปุ่ม Collapse (ซ่อน/แสดง)
// =========================================
document.addEventListener('DOMContentLoaded', function() {
    const toggles = document.querySelectorAll('.header-row[data-target]');

    toggles.forEach(toggle => {
        const targetId = toggle.getAttribute('data-target');
        const content = document.querySelector(targetId);
        const toggleBtn = toggle.querySelector('.collapse-toggle-btn');

        if (content) {
            toggle.addEventListener('click', function(e) {
                if (e.target.closest('button, a')) {
                    if (!e.target.closest('.collapse-toggle-btn')) {
                        return; 
                    }
                }
                
                content.classList.toggle('collapsed');
                if (toggleBtn) {
                    toggleBtn.classList.toggle('collapsed');
                }
            });
        }
    });
});
// (AJAX สำหรับ Log Pagination)
document.addEventListener('DOMContentLoaded', function() {
    
    document.body.addEventListener('click', function(event) {
        
        const paginationLink = event.target.closest('#admin-log-content .pagination-container a');

        if (paginationLink && !paginationLink.classList.contains('disabled')) {
            
            event.preventDefault(); 
            
            const url = new URL(paginationLink.href); 
            
            url.searchParams.set('ajax', '1'); 
            
            const contentWrapper = document.getElementById('admin-log-content');
            if (!contentWrapper) return; 

            contentWrapper.style.opacity = '0.5';

            fetch(url.href)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(html => {
                    contentWrapper.innerHTML = html;
                    contentWrapper.style.opacity = '1'; 
                })
                .catch(error => {
                    console.error('Failed to load page content:', error);
                    contentWrapper.style.opacity = '1'; 
                    alert('เกิดข้อผิดพลาดในการโหลดหน้า');
                });
        }
    });
});

function confirmDeleteItem(itemId, typeId) {
    Swal.fire({
        title: "คุณแน่ใจหรือไม่?",
        text: "คุณกำลังจะลบอุปกรณ์ชิ้นนี้ออกจากระบบอย่างถาวร (จะลบได้ต่อเมื่อสถานะไม่ใช่ 'ถูกยืม' เท่านั้น)",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#3085d6",
        confirmButtonText: "ใช่, ลบเลย",
        cancelButtonText: "ยกเลิก"
    }).then((result) => {
        if (result.isConfirmed) {
            
            const formData = new FormData();
            formData.append('item_id', itemId);
            formData.append('type_id', typeId); 

            fetch('process/delete_item_process.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire('ลบสำเร็จ!', data.message, 'success')
                    .then(() => location.reload());
                } else {
                    Swal.fire('เกิดข้อผิดพลาด!', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.fire('เกิดข้อผิดพลาด AJAX', error.message, 'error');
            });
        }
    });
}

function openEditItemPopup(itemId) {
    Swal.fire({ title: 'กำลังโหลดข้อมูล...', didOpen: () => { Swal.showLoading(); } });
    
    fetch(`ajax/get_item_data.php?id=${itemId}`)
        .then(response => response.json())
        .then(data => {
            if (data.status !== 'success') throw new Error(data.message);
            const item = data.item;

            const statusOptions = `
                <option value="available" ${item.status === 'available' ? 'selected' : ''}>ว่าง (Available)</option>
                <option value="maintenance" ${item.status === 'maintenance' ? 'selected' : ''}>ซ่อมบำรุง (Maintenance)</option>
            `;

            Swal.fire({
                title: '🔧 แก้ไขอุปกรณ์รายชิ้น (ID: ' + item.id + ')',
                html: `
                <form id="swalEditItemForm" style="text-align: left; margin-top: 20px;">
                    <input type="hidden" name="item_id" value="${item.id}">
                    
                    <div style="margin-bottom: 15px;">
                        <label for="swal_item_name" style="font-weight: bold; display: block; margin-bottom: 5px;">ชื่อเฉพาะ:</label>
                        <input type="text" name="name" id="swal_item_name" value="${item.name}" required style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ddd;">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label for="swal_item_serial" style="font-weight: bold; display: block; margin-bottom: 5px;">เลขซีเรียล (Serial Number):</label>
                        <input type="text" name="serial_number" id="swal_item_serial" value="${item.serial_number || ''}" style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ddd;">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label for="swal_item_desc" style="font-weight: bold; display: block; margin-bottom: 5px;">รายละเอียด/หมายเหตุ:</label>
                        <textarea name="description" id="swal_item_desc" rows="2" style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ddd;">${item.description || ''}</textarea>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label for="swal_item_status" style="font-weight: bold; display: block; margin-bottom: 5px;">สถานะ:</label>
                        <select name="status" id="swal_item_status" required style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ddd;">
                            ${statusOptions}
                        </select>
                    </div>
                </form>`,
                showCancelButton: true,
                confirmButtonText: 'บันทึกการเปลี่ยนแปลง',
                preConfirm: () => {
                    const form = document.getElementById('swalEditItemForm');
                    if (!form.checkValidity()) {
                        Swal.showValidationMessage('กรุณากรอกข้อมูลให้ครบถ้วน');
                        return false;
                    }
                    return fetch('process/edit_item_process.php', { method: 'POST', body: new FormData(form) })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status !== 'success') throw new Error(data.message);
                            return data;
                        })
                        .catch(error => { Swal.showValidationMessage(`เกิดข้อผิดพลาด: ${error.message}`); });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire('บันทึกสำเร็จ!', 'แก้ไขข้อมูลอุปกรณ์เรียบร้อย', 'success').then(() => location.reload());
                }
            });
        })
        .catch(error => {
            Swal.fire('เกิดข้อผิดพลาด', error.message, 'error');
        });
}

function openAddItemPopup(typeId, typeName) {
    Swal.fire({
        title: `➕ เพิ่มชิ้นอุปกรณ์ใหม่`,
        html: `
            <p style="text-align: left;">กำลังเพิ่มอุปกรณ์เข้าไปในประเภท: <strong>${typeName}</strong></p>
            <form id="swalAddItemForm" style="text-align: left; margin-top: 20px;">
                <input type="hidden" name="type_id" value="${typeId}">
                <div style="margin-bottom: 15px;">
                    <label for="swal_item_name" style="font-weight: bold; display: block; margin-bottom: 5px;">ชื่อเฉพาะ (ถ้ามี):</label>
                    <input type="text" name="name" id="swal_item_name" value="${typeName}" required style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ddd;">
                    <small>ปกติจะใช้ชื่อเดียวกับประเภท แต่สามารถตั้งชื่อเฉพาะได้ เช่น 'รถเข็น A-01'</small>
                </div>
                <div style="margin-bottom: 15px;">
                    <label for="swal_item_serial" style="font-weight: bold; display: block; margin-bottom: 5px;">เลขซีเรียล (Serial Number):</label>
                    <input type="text" name="serial_number" id="swal_item_serial" style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ddd;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label for="swal_item_desc" style="font-weight: bold; display: block; margin-bottom: 5px;">รายละเอียด/หมายเหตุ:</label>
                    <textarea name="description" id="swal_item_desc" rows="2" style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ddd;"></textarea>
                </div>
            </form>`,
        showCancelButton: true,
        confirmButtonText: 'บันทึก',
        preConfirm: () => {
            const form = document.getElementById('swalAddItemForm');
            if (!form.checkValidity()) {
                Swal.showValidationMessage('กรุณากรอกข้อมูลให้ครบถ้วน');
                return false;
            }
            return fetch('process/add_item_process.php', { method: 'POST', body: new FormData(form) })
                .then(response => response.json())
                .then(data => {
                    if (data.status !== 'success') throw new Error(data.message);
                    return data;
                })
                .catch(error => { Swal.showValidationMessage(`เกิดข้อผิดพลาด: ${error.message}`); });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire('เพิ่มสำเร็จ!', 'เพิ่มอุปกรณ์ชิ้นใหม่เรียบร้อย', 'success').then(() => {
                Swal.close();
                openManageItemsPopup(typeId); 
            });
        }
    });
}

function openManageItemsPopup(typeId) {
    Swal.fire({
        title: 'กำลังโหลดรายการอุปกรณ์...',
        didOpen: () => { Swal.showLoading(); }
    });

    fetch(`ajax/get_items_for_type.php?type_id=${typeId}`)
        .then(response => response.json())
        .then(data => {
            if (data.status !== 'success') throw new Error(data.message);

            const type = data.type;
            const items = data.items;

            let tableRows = '';
            if (items.length === 0) {
                tableRows = `<tr><td colspan="5" style="text-align: center;">ยังไม่มีอุปกรณ์รายชิ้นในประเภทนี้</td></tr>`;
            } else {
                items.forEach(item => {
                    let statusBadge = '';
                    if (item.status === 'available') {
                        statusBadge = `<span class="status-badge available">Available</span>`;
                    } else if (item.status === 'borrowed') {
                        statusBadge = `<span class="status-badge borrowed">Borrowed</span>`;
                    } else {
                        statusBadge = `<span class="status-badge maintenance">Maintenance</span>`;
                    }

                    let actionButtons = '';
                    if (item.status !== 'borrowed') {
                        actionButtons = `
                            <button class="btn btn-manage btn-sm" onclick="openEditItemPopup(${item.id})"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-danger btn-sm" onclick="confirmDeleteItem(${item.id}, ${item.type_id})"><i class="fas fa-trash"></i></button>
                        `;
                    } else {
                        actionButtons = `<span class="text-muted" style="font-size: 0.9em;">ถูกยืมอยู่</span>`;
                    }

                    tableRows += `
                        <tr>
                            <td>${item.id}</td>
                            <td>${item.name}</td>
                            <td>${item.serial_number || '-'}</td>
                            <td>${statusBadge}</td>
                            <td class="action-buttons" style="gap: 0.25rem;">${actionButtons}</td>
                        </tr>
                    `;
                });
            }

            const popupHtml = `
                <div style="text-align: left; max-height: 60vh; overflow-y: auto; margin-top: 1rem;">
                    <table class="section-card" style="width: 100%;">
                        <thead>
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th>ชื่อ/รุ่น</th>
                                <th>ซีเรียล</th>
                                <th style="width: 120px;">สถานะ</th>
                                <th style="width: 100px;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${tableRows}
                        </tbody>
                    </table>
                </div>
            `;

            Swal.fire({
                title: `รายการอุปกรณ์: ${type.name}`,
                html: popupHtml,
                width: '800px',
                showConfirmButton: true,
                confirmButtonText: `<i class="fas fa-plus"></i> เพิ่มอุปกรณ์ชิ้นใหม่`,
                confirmButtonColor: 'var(--color-success)',
                showCancelButton: true,
                cancelButtonText: 'ปิดหน้าต่าง',
            }).then((result) => {
                if (result.isConfirmed) {
                    openAddItemPopup(typeId, type.name);
                }
            });
        })
        .catch(error => {
            Swal.fire('เกิดข้อผิดพลาด', error.message, 'error');
        });
}