// [ฉบับสมบูรณ์: assets/js/admin_app.js]
// (รวมทุกฟังก์ชันและแก้ไขปัญหา Global Scope แล้ว)

// =========================================
// ✅ Global Variables & Helper Functions สำหรับ Bulk Barcode Printing
// =========================================

let printCart = []; 

// 1. ฟังก์ชันสร้าง HTML ตะกร้า (ถูกเรียกจาก renderCartHtml และใน Popup)
const renderCartHtml = () => {
    let html = '';
    if (printCart.length === 0) {
        return '<div style="padding: 20px; color: #999; text-align: center;">ยังไม่มีรายการในตะกร้า</div>';
    }
    
    html += `<table style="width: 100%; border-collapse: collapse; font-size: 0.95em;">
                <thead style="background: #f0f0f0;">
                    <tr><th style="padding: 8px; text-align: left;">รายการ</th>
                        <th style="padding: 8px; width: 100px;">จำนวน</th>
                        <th style="padding: 8px; width: 40px;">ลบ</th></tr>
                </thead><tbody>`;
    
    printCart.forEach((item, index) => {
        html += `
            <tr style="border-bottom: 1px solid #eee;">
                <td style="padding: 8px;"><strong>${item.name}</strong></td>
                <td style="padding: 8px; text-align: center;">
                    <input type="number" min="1" max="${item.max}" value="${item.qty}" 
                           onchange="updatePrintQty(${index}, this.value)"
                           style="width: 50px; text-align: center; padding: 5px;">
                </td>
                <td style="padding: 8px; text-align: center;">
                    <button type="button" class="btn btn-danger btn-sm" onclick="removePrintItem(${index})"><i class="fas fa-trash"></i></button>
                </td>
            </tr>`;
    });
    html += '</tbody></table>';
    return html;
};

// 2. ฟังก์ชันอัปเดตจำนวน (ถูกเรียกโดยตรงจาก HTML)
function updatePrintQty(index, value) {
    const qty = parseInt(value);
    if (isNaN(qty) || qty < 1) {
        document.getElementById('cart-display').innerHTML = renderCartHtml(); 
        return;
    }
    const max = printCart[index].max;
    if (qty > max) {
        Swal.showValidationMessage(`จำนวนต้องไม่เกิน ${max} ชิ้น`);
        return;
    }
    printCart[index].qty = qty;
    document.getElementById('cart-display').innerHTML = renderCartHtml();
    Swal.update();
}

// 3. ฟังก์ชันลบรายการ
function removePrintItem(index) {
    printCart.splice(index, 1);
    document.getElementById('cart-display').innerHTML = renderCartHtml();
    Swal.update();
}

// 4. ฟังก์ชันเพิ่มรายการลงตะกร้า (เรียกโดยตรงจากปุ่ม "เพิ่ม" ใน Modal)
function addTypeToCart() {
    const select = document.getElementById('bulk_type_id');
    const typeId = select.value;
    if (!typeId) return;

    const option = select.options[select.selectedIndex];
    const name = option.getAttribute('data-name');
    const max = parseInt(option.getAttribute('data-max'));
    
    if (max === 0) {
        Swal.fire('ไม่มีของว่าง', 'อุปกรณ์นี้มีจำนวนว่างเป็น 0', 'warning');
        return;
    }

    if (printCart.find(i => i.type_id == typeId)) {
        Swal.fire('รายการซ้ำ', 'ประเภทอุปกรณ์นี้อยู่ในตะกร้าแล้ว', 'info');
        return;
    }

    printCart.push({ type_id: typeId, name: name, qty: 1, max: max });
    document.getElementById('cart-display').innerHTML = renderCartHtml();
    select.value = ''; 
    Swal.update();
}


// =========================================
// ✅ 1. ฟังก์ชันชำระค่าปรับ (FINES)
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
                
                if (onSuccessCallback) {
                    onSuccessCallback(); 
                } else {
                    location.reload(); 
                }
            });
        }
    });
}

// 2. Popup สำหรับ "รับชำระเงิน" (สำหรับข้อมูลเก่า)
function openRecordPaymentPopup(fineId, studentName, amountDue, onSuccessCallback = null) {
    
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
                
                if (onSuccessCallback) {
                    onSuccessCallback(); 
                } else {
                    location.reload(); 
                }
            });
        }
    });
}

// 3. ฟังก์ชัน Wrapper สำหรับ Workflow ใหม่
function openFineAndReturnPopup(transactionId, studentId, studentName, equipName, daysOverdue, calculatedFine, equipmentId) {
    
    const returnCallback = () => {
        openReturnPopup(equipmentId);
    };

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
// ✅ 2. ฟังก์ชันสำหรับ "จัดการอุปกรณ์" และ "ยืมของ"
// =========================================


// (ฟังก์ชัน "ยืม" - สำหรับ Admin Dashboard)
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

// (ฟังก์ชันเพิ่มประเภทอุปกรณ์)
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

// (ฟังก์ชันแก้ไขประเภทอุปกรณ์)
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

// (ฟังก์ชัน Item Popup และจัดการ)
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

// (ฟังก์ชัน Barcode และ History)
function openItemBarcodePopup(itemId, itemName, serialNumber) {
    // สร้างรหัสสำหรับ Barcode (Format: EQ-ID)
    const barcodeValue = "EQ-" + itemId; 
    const serialText = serialNumber && serialNumber !== '-' ? `(S/N: ${serialNumber})` : '';

    Swal.fire({
        title: '🏷️ บาร์โค้ดอุปกรณ์',
        html: `
            <div style="margin-bottom: 10px;">
                <strong>${itemName}</strong> ${serialText}
            </div>
            <div style="background: white; padding: 20px; border-radius: 8px; display: inline-block;">
                <svg id="barcode-display"></svg>
            </div>
            <p style="margin-top: 15px; font-size: 0.9em; color: #666;">
                รหัส Item ID: <strong>${itemId}</strong> (ใช้รหัส ${barcodeValue} สำหรับสแกน)
            </p>
        `,
        didOpen: () => {
            // สั่งให้ JsBarcode วาดรูปลงใน <svg>
            try {
                JsBarcode("#barcode-display", barcodeValue, {
                    format: "CODE128", // รูปแบบมาตรฐาน
                    lineColor: "#000",
                    width: 2,
                    height: 80,
                    displayValue: true, // แสดงตัวเลขใต้บาร์โค้ด
                    fontSize: 18
                });
            } catch (e) {
                console.error("Barcode Error:", e);
                document.getElementById('barcode-display').outerHTML = '<p style="color:red;">เกิดข้อผิดพลาดในการสร้างบาร์โค้ด</p>';
            }
        },
        confirmButtonText: '<i class="fas fa-times"></i> ปิด', 
        showCancelButton: true,
        cancelButtonText: '<i class="fas fa-print"></i> พิมพ์',
        cancelButtonColor: 'var(--color-primary)'
    }).then((result) => {
        if (result.dismiss === Swal.DismissReason.cancel) {
            window.print(); // สั่งพิมพ์เมื่อกดปุ่ม "พิมพ์"
        }
    });
}

function openItemHistoryPopup(itemId, itemName) {
    Swal.fire({
        title: 'กำลังโหลดประวัติ...',
        text: `สำหรับอุปกรณ์: ${itemName}`,
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // (เรียก API ที่เราเพิ่งสร้าง)
    fetch(`ajax/get_item_history.php?item_id=${itemId}`)
        .then(response => response.json())
        .then(data => {
            if (data.status !== 'success') {
                throw new Error(data.message);
            }

            let historyHtml = '';
            
            // (ตรวจสอบว่ามีประวัติหรือไม่)
            if (data.history.length === 0) {
                historyHtml = '<p style="text-align: center; padding: 1rem 0;">ยังไม่มีประวัติการยืมสำหรับอุปกรณ์ชิ้นนี้</p>';
            } else {
                // (สร้างตาราง HTML)
                historyHtml = `
                    <div style="text-align: left; max-height: 40vh; overflow-y: auto; margin-top: 1rem;">
                        <table class="section-card" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th>ผู้ยืม</th>
                                    <th>วันที่ยืม</th>
                                    <th>วันที่คืน</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${data.history.map(row => {
                                    // (แปลง Format วันที่ให้อ่านง่าย)
                                    const borrowDate = new Date(row.borrow_date).toLocaleDateString('th-TH', {
                                        day: 'numeric', month: 'short', year: 'numeric'
                                    });
                                    
                                    // (ถ้ายังไม่คืน ให้แสดงเป็น - )
                                    const returnDate = row.return_date 
                                        ? new Date(row.return_date).toLocaleDateString('th-TH', {
                                            day: 'numeric', month: 'short', year: 'numeric'
                                          }) 
                                        : '<span style="color: var(--color-text-muted);">(ยังไม่คืน)</span>';

                                    return `
                                        <tr>
                                            <td>${row.borrower_name}</td>
                                            <td>${borrowDate}</td>
                                            <td>${returnDate}</td>
                                        </tr>
                                    `;
                                }).join('')}
                            </tbody>
                        </table>
                    </div>
                `;
            }

            // (แสดง Popup พร้อมผลลัพธ์)
            Swal.fire({
                title: `ประวัติการยืม: ${itemName}`,
                html: historyHtml,
                width: '600px',
                confirmButtonText: 'ปิด',
                confirmButtonColor: 'var(--color-primary)'
            });

        })
        .catch(error => {
            Swal.fire('เกิดข้อผิดพลาด', error.message, 'error');
        });
}


// =========================================
// ✅ Global Helper Functions & Logic สำหรับ Bulk Barcode Printing
// =========================================

// 1. ฟังก์ชันสร้าง HTML ตะกร้า (ถูกเรียกจาก renderCartHtml และใน Popup)
const renderCartHtml = () => {
    let html = '';
    if (printCart.length === 0) {
        return '<div style="padding: 20px; color: #999; text-align: center;">ยังไม่มีรายการในตะกร้า</div>';
    }
    
    html += `<table style="width: 100%; border-collapse: collapse; font-size: 0.95em;">
                <thead style="background: #f0f0f0;">
                    <tr><th style="padding: 8px; text-align: left;">รายการ</th>
                        <th style="padding: 8px; width: 100px;">จำนวน</th>
                        <th style="padding: 8px; width: 40px;">ลบ</th></tr>
                </thead><tbody>`;
    
    printCart.forEach((item, index) => {
        html += `
            <tr style="border-bottom: 1px solid #eee;">
                <td style="padding: 8px;"><strong>${item.name}</strong></td>
                <td style="padding: 8px; text-align: center;">
                    <input type="number" min="1" max="${item.max}" value="${item.qty}" 
                           onchange="updatePrintQty(${index}, this.value)"
                           style="width: 50px; text-align: center; padding: 5px;">
                </td>
                <td style="padding: 8px; text-align: center;">
                    <button type="button" class="btn btn-danger btn-sm" onclick="removePrintItem(${index})"><i class="fas fa-trash"></i></button>
                </td>
            </tr>`;
    });
    html += '</tbody></table>';
    return html;
};

// 2. ฟังก์ชันอัปเดตจำนวน (ถูกเรียกโดยตรงจาก HTML)
function updatePrintQty(index, value) {
    const qty = parseInt(value);
    if (isNaN(qty) || qty < 1) {
        document.getElementById('cart-display').innerHTML = renderCartHtml(); 
        return;
    }
    const max = printCart[index].max;
    if (qty > max) {
        Swal.showValidationMessage(`จำนวนต้องไม่เกิน ${max} ชิ้น`);
        return;
    }
    printCart[index].qty = qty;
    document.getElementById('cart-display').innerHTML = renderCartHtml();
    Swal.update();
}

// 3. ฟังก์ชันลบรายการ
function removePrintItem(index) {
    printCart.splice(index, 1);
    document.getElementById('cart-display').innerHTML = renderCartHtml();
    Swal.update();
}

// 4. ฟังก์ชันเพิ่มรายการลงตะกร้า
function addTypeToCart() {
    const select = document.getElementById('bulk_type_id');
    const typeId = select.value;
    if (!typeId) return;

    const option = select.options[select.selectedIndex];
    const name = option.getAttribute('data-name');
    const max = parseInt(option.getAttribute('data-max'));
    
    if (max === 0) {
        Swal.fire('ไม่มีของว่าง', 'อุปกรณ์นี้มีจำนวนว่างเป็น 0', 'warning');
        return;
    }

    if (printCart.find(i => i.type_id == typeId)) {
        Swal.fire('รายการซ้ำ', 'ประเภทอุปกรณ์นี้อยู่ในตะกร้าแล้ว', 'info');
        return;
    }

    printCart.push({ type_id: typeId, name: name, qty: 1, max: max });
    document.getElementById('cart-display').innerHTML = renderCartHtml();
    select.value = ''; 
    Swal.update();
}


// =========================================
// ✅ ฟังก์ชันหลัก openBulkBarcodeForm
// =========================================

function openBulkBarcodeForm() {
    
    // 1. เคลียร์ตะกร้าเก่าก่อนเริ่มฟอร์มใหม่
    printCart.length = 0; 
    
    let optionsHtml = '';
    // equipmentTypesData ถูกส่งมาจาก manage_equipment.php
    if (typeof equipmentTypesData !== 'undefined') {
        equipmentTypesData.forEach(type => {
            if (type.available_quantity > 0) {
                optionsHtml += `<option value="${type.id}" data-name="${type.name}" data-max="${type.available_quantity}">
                                    ${type.name} (ว่าง: ${type.available_quantity} ชิ้น)
                                </option>`;
            }
        });
    }

    if (!optionsHtml) {
        Swal.fire('ไม่มีอุปกรณ์', 'ไม่มีอุปกรณ์ที่พร้อมใช้งานในสต็อกเพื่อพิมพ์บาร์โค้ด', 'info');
        return;
    }

    // 2. แสดง Popup
    Swal.fire({
        title: '🖨️ พิมพ์บาร์โค้ดจำนวนมาก',
        html: `
            <div style="text-align: left; margin-top: 15px;">
                <label style="font-weight: bold; display: block; margin-bottom: 5px;">1. เพิ่มประเภทอุปกรณ์:</label>
                <div style="display: flex; gap: 5px; margin-bottom: 20px;">
                    <select id="bulk_type_id" class="swal2-input" style="flex: 1;">
                        ${optionsHtml}
                    </select>
                    <button type="button" class="btn btn-success" onclick="addTypeToCart()" style="padding: 5px 15px;">
                        <i class="fas fa-plus"></i> เพิ่ม
                    </button>
                </div>
            </div>

            <div id="cart-display" style="max-height: 250px; overflow-y: auto; border: 1px solid #ddd; border-radius: 8px;">
                ${renderCartHtml()}
            </div>
        `,
        width: '650px',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-print"></i> สั่งพิมพ์บาร์โค้ด',
        cancelButtonText: 'ยกเลิก',
        preConfirm: () => {
            if (printCart.length === 0) {
                Swal.showValidationMessage('กรุณาเพิ่มอุปกรณ์ที่ต้องการพิมพ์ลงในตะกร้า');
                return false;
            }

            // เปิดหน้าต่างใหม่เพื่อพิมพ์
            const printData = JSON.stringify(printCart.map(item => ({
                id: item.type_id,
                qty: item.qty
            })));
            
            // ส่งข้อมูล Cart ทั้งหมดผ่าน URL
            const printUrl = `admin/print_barcodes.php?cart_data=${encodeURIComponent(printData)}`;
            window.open(printUrl, '_blank');
            return true;
        }
    });
}