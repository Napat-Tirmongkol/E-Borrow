<?php
// includes/footer.php (อัปเกรด: เพิ่ม Footer Nav)

// (ตรวจสอบค่า $current_page ถ้าไม่มี ให้เป็น 'index')
$current_page = $current_page ?? 'index'; 
?>

</main> <nav class="footer-nav">
    
    <a href="index.php" class="<?php echo ($current_page == 'index') ? 'active' : ''; ?>">
        <i class="fas fa-tachometer-alt"></i>
        ภาพรวม
    </a>
    
    <a href="return_dashboard.php" class="<?php echo ($current_page == 'return') ? 'active' : ''; ?>">
        <i class="fas fa-undo-alt"></i>
        คืนอุปกรณ์
    </a>
    
    <a href="manage_equipment.php" class="<?php echo ($current_page == 'manage_equip') ? 'active' : ''; ?>">
        <i class="fas fa-tools"></i>
        จัดการอุปกรณ์
    </a>

    <?php 
    // (เมนู 4, 5, 6 จะแสดงเฉพาะ Admin เท่านั้น)
    if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): 
    ?>
    
    <a href="manage_students.php" class="<?php echo ($current_page == 'manage_user') ? 'active' : ''; ?>">
        <i class="fas fa-users-cog"></i>
        จัดการผู้ใช้
    </a>
    
    <a href="manage_fines.php" class="<?php echo ($current_page == 'manage_fines') ? 'active' : ''; ?>">
        <i class="fas fa-file-invoice-dollar"></i>
        จัดการค่าปรับ
    </a>
    
    <a href="report_borrowed.php" class="<?php echo ($current_page == 'report') ? 'active' : ''; ?>">
        <i class="fas fa-chart-line"></i>
        รายงาน
    </a>
    
    <a href="admin_log.php" class="<?php echo ($current_page == 'admin_log') ? 'active' : ''; ?>">
        <i class="fas fa-history"></i>
        Log Admin
    </a>

    <?php endif; // (จบการเช็ค Admin) ?>
</nav>
<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>

// (ฟังก์ชัน "ยืม")
function openBorrowPopup(typeId) { // (เปลี่ยนเป็น typeId)
    Swal.fire({ title: 'กำลังโหลดข้อมูล...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
    fetch(`get_borrow_form_data.php?type_id=${typeId}`) // (เปลี่ยน URL)
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
                <div style="background: #f4f4f4; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: left;">
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
                    return fetch('borrow_process.php', { method: 'POST', body: new FormData(form) })
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

function openAddEquipmentTypePopup() { // (แก้ชื่อฟังก์ชัน)
    Swal.fire({
        title: '➕ เพิ่มประเภทอุปกรณ์ใหม่',
        html: `
            <form id="swalAddForm" style="text-align: left; margin-top: 20px;">
                <div style="margin-bottom: 15px;">
                    <label for="swal_eq_name" style="font-weight: bold; display: block; margin-bottom: 5px;">ชื่อประเภทอุปกรณ์:</label>
                    <input type="text" name="name" id="swal_eq_name" required style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ddd;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label for="swal_eq_desc" style="font-weight: bold; display: block; margin-bottom: 5px;">รายละเอียด:</label>
                    <textarea name="description" id="swal_eq_desc" rows="3" style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ddd;"></textarea>
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
            return fetch('add_equipment_type_process.php', { method: 'POST', body: new FormData(form) }) // (แก้ URL)
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
function openEditEquipmentTypePopup(typeId) { // (เปลี่ยนชื่อและ parameter)
    Swal.fire({ title: 'กำลังโหลดข้อมูล...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
    fetch(`get_equipment_type_data.php?id=${typeId}`) // (เปลี่ยน URL)
        .then(response => response.json())
        .then(data => {
            if (data.status !== 'success') throw new Error(data.message);
            const type = data.equipment_type;
            
            // (สร้าง HTML สำหรับรูปตัวอย่าง)
            let imagePreviewHtml = `
                <div class="equipment-card-image-placeholder" style="width: 100%; height: 150px; font-size: 3rem; margin-bottom: 15px; display: flex; justify-content: center; align-items: center; background-color: #f0f0f0; color: #ccc; border-radius: 6px;">
                    <i class="fas fa-camera"></i>
                </div>`;
            if (type.image_url) {
                imagePreviewHtml = `
                    <img src="${type.image_url}" 
                         alt="รูปตัวอย่าง" 
                         style="width: 100%; height: 150px; object-fit: cover; border-radius: 6px; margin-bottom: 15px;"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                    <div class="equipment-card-image-placeholder" style="display: none; width: 100%; height: 150px; font-size: 3rem; margin-bottom: 15px; justify-content: center; align-items: center; background-color: #f0f0f0; color: #ccc; border-radius: 6px;"><i class="fas fa-image"></i></div>`;
            }

            Swal.fire({
                title: '🔧 แก้ไขประเภทอุปกรณ์',
                html: `
                <form id="swalEditForm" style="text-align: left; margin-top: 20px;">
                    
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
                showDenyButton: true, // (เพิ่ม) แสดงปุ่มลบ
                denyButtonText: `<i class="fas fa-trash"></i> ลบอุปกรณ์นี้`,
                denyButtonColor: 'var(--color-danger)',
                denyButtonAriaLabel: 'Delete this equipment',

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
                    return fetch('edit_equipment_type_process.php', { method: 'POST', body: new FormData(form) }) // (เปลี่ยน URL)
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
            });

            // (เพิ่ม) Logic สำหรับปุ่ม "ลบ"
            document.querySelector('.swal2-deny').addEventListener('click', function(e) {
                confirmDeleteEquipmentType(e, typeId); // (เปลี่ยนฟังก์ชัน)
            });
        })
        .catch(error => {
            Swal.fire('เกิดข้อผิดพลาด', error.message, 'error');
        });
}

// 3. ฟังก์ชัน "รับคืน"
function openReturnPopup(equipmentId) {
    Swal.fire({ title: 'กำลังโหลดข้อมูลการยืม...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
    fetch(`get_return_form_data.php?id=${equipmentId}`)
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
                <div style="background: #f4f4f4; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: left;">
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
                    return fetch('return_process.php', { method: 'POST', body: new FormData(form) })
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

            fetch('approve_request_process.php', {
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

            fetch('reject_request_process.php', {
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
try {
    const themeToggleBtn = document.getElementById('theme-toggle-btn');
    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', function() {
            // (ตรวจสอบจาก body class ที่ script ใน header อาจจะใส่ไว้)
            if (document.body.classList.contains('dark-mode')) {
                // --- (จากมืด -> ไปสว่าง) ---
                document.documentElement.classList.remove('dark-mode');
                document.body.classList.remove('dark-mode');
                localStorage.setItem('theme', 'light');
            } else {
                // --- (จากสว่าง -> ไปมืด) ---
                document.documentElement.classList.add('dark-mode');
                document.body.classList.add('dark-mode');
                localStorage.setItem('theme', 'dark');
            }
        });
    }
} catch (e) {
    console.error('Theme toggle button error:', e);
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
// (วางต่อจากโค้ด Theme Toggle)

// =========================================
// (ใหม่) Logic สำหรับปุ่ม Collapse (ซ่อน/แสดง)
// =========================================
document.addEventListener('DOMContentLoaded', function() {
    // เลือก .header-row ทั้งหมดที่มี data-target
    const toggles = document.querySelectorAll('.header-row[data-target]');

    toggles.forEach(toggle => {
        const targetId = toggle.getAttribute('data-target');
        const content = document.querySelector(targetId);
        const toggleBtn = toggle.querySelector('.collapse-toggle-btn');

        if (content) {
            toggle.addEventListener('click', function(e) {
                // (ป้องกันการคลิกที่ปุ่มย่อยๆ ภายใน header)
                if (e.target.closest('button, a')) {
                    // ถ้าคลิกที่ปุ่ม "เพิ่ม" หรือ "ลูกศร" (ปุ่มจริงๆ)
                    // ให้เช็คว่าเป็นปุ่มลูกศรหรือไม่
                    if (!e.target.closest('.collapse-toggle-btn')) {
                        return; // ถ้าไม่ใช่ปุ่มลูกศร (เช่น ปุ่ม "เพิ่ม") ให้หยุด ไม่ต้องซ่อน/แสดง
                    }
                }
                
                // สลับคลาส 'collapsed'
                content.classList.toggle('collapsed');
                if (toggleBtn) {
                    toggleBtn.classList.toggle('collapsed');
                }
            });
        }
    });
});
document.addEventListener('DOMContentLoaded', function() {
    
    // (ใช้ Event Delegation ดักจับการคลิกที่ body)
    document.body.addEventListener('click', function(event) {
        
        // (1) ค้นหาว่าสิ่งที่คลิก (หรือแม่ของมัน) คือลิงก์ Pagination ในส่วนของ Log หรือไม่
        const paginationLink = event.target.closest('#admin-log-content .pagination-container a');

        // (2) ถ้าใช่ลิงก์ pagination และลิงก์นั้น "ไม่" disabled
        if (paginationLink && !paginationLink.classList.contains('disabled')) {
            
            // (3) ป้องกันไม่ให้หน้าเว็บรีเฟรช (นี่คือหัวใจของ AJAX)
            event.preventDefault(); 
            
            const url = new URL(paginationLink.href);
            
            // (4) เพิ่ม parameter 'ajax=1' เพื่อบอก PHP ว่าเราต้องการแค่ตาราง
            url.searchParams.set('ajax', '1'); 
            
            // (5) หา Wrapper ที่จะเอาเนื้อหาไปใส่
            const contentWrapper = document.getElementById('admin-log-content');
            if (!contentWrapper) return; // (ถ้าหาไม่เจอ ก็ไม่ต้องทำอะไร)

            // (6) (เสริม) ทำให้ตารางจางลงเล็กน้อย เพื่อบอกว่ากำลังโหลด
            contentWrapper.style.opacity = '0.5';

            // (7) ยิง Request ไปดึงข้อมูลหน้าใหม่
            fetch(url.href)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(html => {
                    // (8) เมื่อได้ HTML ใหม่ (เฉพาะตาราง+ปุ่ม) ให้ยัดลงไปแทนที่ของเดิม
                    contentWrapper.innerHTML = html;
                    contentWrapper.style.opacity = '1'; // (ทำให้กลับมาเข้ม)
                })
                .catch(error => {
                    console.error('Failed to load page content:', error);
                    contentWrapper.style.opacity = '1'; // (คืนค่าถ้าพลาด)
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
            formData.append('type_id', typeId); // (ส่ง type_id ไปด้วย)

            fetch('delete_item_process.php', {
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
    
    // 1. ดึงข้อมูลเดิม
    fetch(`get_item_data.php?id=${itemId}`)
        .then(response => response.json())
        .then(data => {
            if (data.status !== 'success') throw new Error(data.message);
            const item = data.item;

            // 2. สร้างตัวเลือกสถานะ
            const statusOptions = `
                <option value="available" ${item.status === 'available' ? 'selected' : ''}>ว่าง (Available)</option>
                <option value="maintenance" ${item.status === 'maintenance' ? 'selected' : ''}>ซ่อมบำรุง (Maintenance)</option>
            `;

            // 3. แสดง Popup
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
                    return fetch('edit_item_process.php', { method: 'POST', body: new FormData(form) })
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
            return fetch('add_item_process.php', { method: 'POST', body: new FormData(form) })
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
                // (สำคัญ) ปิด Popup ปัจจุบันก่อน แล้วค่อยเปิด Popup รายการใหม่
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

    fetch(`get_items_for_type.php?type_id=${typeId}`)
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
            formData.append('type_id', typeId); // (ส่ง type_id ไปด้วย)

            fetch('delete_item_process.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire('ลบสำเร็จ!', data.message, 'success')
                    .then(() => {
                        Swal.close();
                        openManageItemsPopup(typeId);
                    });
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
    
    // 1. ดึงข้อมูลเดิม
    fetch(`get_item_data.php?id=${itemId}`)
        .then(response => response.json())
        .then(data => {
            if (data.status !== 'success') throw new Error(data.message);
            const item = data.item;

            // 2. สร้างตัวเลือกสถานะ
            const statusOptions = `
                <option value="available" ${item.status === 'available' ? 'selected' : ''}>ว่าง (Available)</option>
                <option value="maintenance" ${item.status === 'maintenance' ? 'selected' : ''}>ซ่อมบำรุง (Maintenance)</option>
            `;

            // 3. แสดง Popup
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
                    return fetch('edit_item_process.php', { method: 'POST', body: new FormData(form) })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status !== 'success') throw new Error(data.message);
                            return data;
                        })
                        .catch(error => { Swal.showValidationMessage(`เกิดข้อผิดพลาด: ${error.message}`); });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire('บันทึกสำเร็จ!', 'แก้ไขข้อมูลอุปกรณ์เรียบร้อย', 'success').then(() => {
                        Swal.close();
                        openManageItemsPopup(item.type_id);
                    });
                }
            });
        })
        .catch(error => {
            Swal.fire('เกิดข้อผิดพลาด', error.message, 'error');
        });
}

</script>

</body>
</html>