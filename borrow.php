<?php
// borrow_list.php (อัปเดตสำหรับ V5 - ใช้ Types)

@session_start(); 
include('includes/check_student_session.php'); // (◀️ เพิ่มยามเข้ามา)
// (⚠️ ลบส่วน Development Mode ออกแล้ว ⚠️)

require_once('db_connect.php');

$student_id = $_SESSION['student_id']; 

// 3. (แก้ไข Query) ◀️ ดึงข้อมูลจาก "ประเภท" (Types) ที่มีของว่าง
try {
    $sql = "SELECT id, name, description, image_url, available_quantity 
            FROM med_equipment_types 
            WHERE available_quantity > 0
            ORDER BY name ASC";
    
    $stmt_equip = $pdo->prepare($sql);
    $stmt_equip->execute();
    $equipment_types = $stmt_equip->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $equipment_types = [];
    $equip_error = "เกิดข้อผิดพลาด: " . $e->getMessage(); // ◀️ แก้ไข .getMessage
}

// 4. ตั้งค่าตัวแปรสำหรับ Header
$page_title = "ยืมอุปกรณ์";
$active_page = 'borrow'; 
include('includes/student_header.php');
?>

<div class="main-container">

    <div class="filter-row">
        
        <i class="fas fa-search" style="color: var(--color-text-muted);"></i>

        <input type="text" 
               name="search" 
               id="liveSearchInput" 
               placeholder="ค้นหาชื่อประเภทอุปกรณ์, รายละเอียด..." 
               style="flex-grow: 1; border: none; outline: none; font-size: 1rem;">
        
        <button type="button" id="clearSearchBtn" class="btn btn-secondary" style="display: none; flex-shrink: 0;">
            <i class="fas fa-times"></i>
        </button>

        <div id="search-results-container">
            </div>

    </div> <div class="section-card" style="background: none; box-shadow: none; padding: 0;">
        
        <h2 class="section-title">อุปกรณ์ที่พร้อมให้ยืม</h2>
        <p class="text-muted">เลือกประเภทอุปกรณ์ที่คุณต้องการส่งคำขอยืม</p>

        <?php if (isset($equip_error)) echo "<p style='color: red;'>$equip_error</p>"; ?>

        <div class="equipment-grid" id="equipment-grid-container">
            
            <?php if (empty($equipment_types)): ?>
                <p style="grid-column: 1 / -1; text-align: center; margin-top: 2rem;">
                    ไม่มีอุปกรณ์ที่ว่างในขณะนี้
                </p>
            <?php else: ?>
                <?php foreach ($equipment_types as $row): ?>
                    
                    <div class="equipment-card">
                        
                        <?php
                            if (!empty($row['image_url'])):
                                $image_to_show = $row['image_url'];
                        ?>
                                <img src="<?php echo htmlspecialchars($image_to_show); ?>" 
                                     alt="<?php echo htmlspecialchars($row['name']); ?>" 
                                     class="equipment-card-image"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"> 
                                <div class="equipment-card-image-placeholder" style="display: none;"><i class="fas fa-image"></i></div>
                        
                        <?php else: ?>
                                <div class="equipment-card-image-placeholder">
                                    <i class="fas fa-camera"></i>
                                </div>
                        <?php endif; ?>

                        <div class="equipment-card-content">
                            <h3 class="equipment-card-title"><?php echo htmlspecialchars($row['name']); ?></h3>
                            <p class="equipment-card-desc"><?php echo htmlspecialchars($row['description'] ?? 'ไม่มีรายละเอียด'); ?></p>
                        </div>
                        
                        <div class="equipment-card-footer">
                            <span class="equipment-card-price" style="font-weight: bold; color: var(--color-primary);">
                                ว่าง: <?php echo $row['available_quantity']; ?> ชิ้น
                            </span>

                            <button type="button" 
                                    class="btn-loan" 
                                    title="ส่งคำขอยืม"
                                    onclick="openRequestPopup(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['name'])); ?>')">+</button>
                        </div>

                    </div>

                <?php endforeach; ?>
            <?php endif; ?>

        </div> 
    </div>

</div> 

<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>

// (JS สำหรับ Live Search)
const searchInput = document.getElementById('liveSearchInput');
const resultsContainer = document.getElementById('search-results-container');
const gridContainer = document.getElementById('equipment-grid-container');
const clearBtn = document.getElementById('clearSearchBtn');
let searchTimeout; 

searchInput.addEventListener('keyup', () => {
    clearTimeout(searchTimeout);
    const query = searchInput.value.trim();
    if (query.length < 2) { hideResults(); return; }
    searchTimeout = setTimeout(() => { performSearch(query); }, 300);
});

function performSearch(query) {
    clearBtn.style.display = 'flex';
    gridContainer.style.display = 'none';
    resultsContainer.style.display = 'block';
    resultsContainer.innerHTML = '<p style="padding: 1rem; text-align: center;">กำลังค้นหา...</p>';

    // ◀️ (แก้ไข) เปลี่ยนไปเรียก live_search_types.php (ไฟล์ใหม่)
    fetch(`live_search_equipment.php?term=${encodeURIComponent(query)}`) // (เราจะแก้ไฟล์ live_search_equipment.php ต่อ)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.results.length > 0) {
                displayResults(data.results);
            } else {
                resultsContainer.innerHTML = '<p style="padding: 1rem; text-align: center;">ไม่พบอุปกรณ์ที่ตรงกับคำค้นหา</p>';
            }
        })
        .catch(error => {
            resultsContainer.innerHTML = `<p style="padding: 1rem; text-align: center; color: red;">เกิดข้อผิดพลาด: ${error.message}</p>`;
        });
}

function displayResults(results) {
    resultsContainer.innerHTML = ''; 
    results.forEach(item => {
        let imageHtml = ''; 
        if (item.image_url) {
            imageHtml = `<img src="${escapeJS(item.image_url)}" alt="${escapeJS(item.name)}" class="search-result-image" onerror="this.parentElement.innerHTML = '<div class=\'search-result-image-placeholder\'><i class=\'fas fa-image\'></i></div>'">`;
        } else {
            imageHtml = `<div class="search-result-image-placeholder"><i class="fas fa-camera"></i></div>`;
        }
        const itemHtml = `
            <div class="search-result-item" role="button" onclick="openRequestPopup(${item.id}, '${escapeJS(item.name)}')">
                ${imageHtml} <div class="search-result-info">
                    <h4>${item.name}</h4>
                    <p>ว่าง: ${item.available_quantity || 0} ชิ้น</p> 
                </div>
            </div>`;
        resultsContainer.innerHTML += itemHtml;
    });
}
function hideResults() {
    clearBtn.style.display = 'none';
    resultsContainer.style.display = 'none';
    resultsContainer.innerHTML = '';
    gridContainer.style.display = 'grid'; 
}
clearBtn.addEventListener('click', () => {
    searchInput.value = ''; 
    hideResults(); 
});
function escapeJS(str) {
    if (!str) return '';
    return str.replace(/'/g, "\\'").replace(/"/g, '\\"');
}

// (JS สำหรับ Popup ยืมของ)
function openRequestPopup(typeId, typeName) { // ◀️ (แก้ไข) เปลี่ยนชื่อตัวแปรเป็น typeId
    Swal.fire({
        title: 'กำลังโหลดข้อมูล...',
        text: 'กรุณารอสักครู่',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
    fetch(`get_staff_list.php`)
        .then(response => response.json())
        .then(data => {
            if (data.status !== 'success') {
                throw new Error(data.message || 'ไม่สามารถดึงรายชื่อพนักงานได้');
            }
            let staffOptions = '<option value="">--- กรุณาเลือก ---</option>';
            if (data.staff.length > 0) {
                data.staff.forEach(staff => {
                    staffOptions += `<option value="${staff.id}">${staff.full_name}</option>`;
                });
            } else {
                staffOptions = '<option value="" disabled>ไม่มีข้อมูลพนักงาน</option>';
            }
            const formHtml = `
                <form id="swalRequestForm" style="text-align: left; margin-top: 20px;">
                    <input type="hidden" name="type_id" value="${typeId}">
                    
                    <div style="margin-bottom: 15px;">
                        <label for="swal_reason" style="font-weight: bold; display: block; margin-bottom: 5px;">1. เหตุผลการยืม: <span style="color:red;">*</span></label>
                        <textarea name="reason_for_borrowing" id="swal_reason" rows="3" required 
                                  style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ddd;"></textarea>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label for="swal_staff_id" style="font-weight: bold; display: block; margin-bottom: 5px;">2. ระบุพนักงานผู้ให้ยืม (ผู้อนุมัติ): <span style="color:red;">*</span></label>
                        <select name="lending_staff_id" id="swal_staff_id" required 
                                style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ddd;">
                            ${staffOptions}
                        </select>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label for="swal_due_date" style="font-weight: bold; display: block; margin-bottom: 5px;">3. วันที่กำหนดคืน: <span style="color:red;">*</span></label>
                        <input type="date" name="due_date" id="swal_due_date" required 
                               style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ddd;">
                    </div>
                </form>`;

            Swal.fire({
                title: `📝 ส่งคำขอยืม: ${typeName}`, // ◀️ (แก้ไข)
                html: formHtml,
                width: '600px',
                showCancelButton: true,
                confirmButtonText: 'ยืนยันส่งคำขอ',
                cancelButtonText: 'ยกเลิก',
                confirmButtonColor: 'var(--color-success, #16a34a)',
                focusConfirm: false,
                preConfirm: () => {
                    const form = document.getElementById('swalRequestForm');
                    const reason = form.querySelector('#swal_reason').value;
                    const staffId = form.querySelector('#swal_staff_id').value;
                    const dueDate = form.querySelector('#swal_due_date').value;
                    const typeIdHidden = form.querySelector('input[name="type_id"]').value;
                    
                    if (!reason || !staffId || !dueDate || !typeIdHidden || typeIdHidden == 0) {
                        Swal.showValidationMessage('กรุณากรอกข้อมูลที่มีเครื่องหมาย * ให้ครบถ้วน');
                        return false;
                    }
                    
                    return fetch('request_borrow_process.php', { 
                        method: 'POST',
                        body: new FormData(form)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status !== 'success') {
                            throw new Error(data.message);
                        }
                        return data;
                    })
                    .catch(error => {
                        Swal.showValidationMessage(`เกิดข้อผิดพลาด: ${error.message}`);
                    });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire('ส่งคำขอสำเร็จ!', 'คำขอของคุณถูกส่งไปให้ Admin พิจารณาแล้ว', 'success')
                    .then(() => location.href = 'request_history.php'); 
                }
            });
        })
        .catch(error => {
            Swal.fire('เกิดข้อผิดพลาด', error.message, 'error');
        });
}
</script>

<?php
include('includes/student_footer.php'); 
?>