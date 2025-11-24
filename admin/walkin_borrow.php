<?php
// [แก้ไขไฟล์: admin/walkin_borrow.php]
// (เวอร์ชัน V4 - ระบบตะกร้าสินค้า Cart System)

include('../includes/check_session.php');
require_once('../includes/db_connect.php');

if (!in_array($_SESSION['role'], ['admin', 'employee'])) {
    header("Location: index.php");
    exit;
}

$page_title = "ยืมอุปกรณ์ (Walk-in)";
include('../includes/header.php');
?>

<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

<style>
    /* Grid Layout สำหรับ Desktop */
    .dashboard-grid {
        display: grid;
        grid-template-columns: 1fr 1.5fr;
        gap: 20px;
    }

    /* === Dark Mode Readability Fixes === */
    /* ✅ (1) บังคับสีพื้นหลังและสีตัวอักษรในกล่องข้อมูลผู้ยืม/อุปกรณ์ */
    body.dark-mode #student-info-box,
    body.dark-mode #item-info-box {
        background-color: #2d3748 !important; 
        border-left-color: #4a5568 !important; 
        color: #e2e8f0 !important; /* ทำให้ตัวอักษรโดยรวม (Label) เป็นสีขาว */
    }

    /* ✅ (2) แก้สีชื่อ/ข้อมูลแบบ Dynamic ในกล่อง */
    body.dark-mode #student-display,
    body.dark-mode #student-display strong,
    body.dark-mode #item-info-box small {
        color: #e2e8f0 !important; /* สีขาวสำหรับชื่อ/ข้อมูล */
    }


    /* =============== Mobile Layout (แก้ไข Footer) =============== */
    @media (max-width: 768px) {
        
        /* 1. ปรับ Grid เป็นแนวตั้ง (บนลงล่าง) */
        .dashboard-grid {
            grid-template-columns: 1fr !important; 
            display: flex !important;
            flex-direction: column;
            gap: 15px;
        }

        /* 2. จัดการ Body และ Main Container */
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            padding-bottom: 0 !important; 
        }
        
        .main-container {
            flex: 1;
            padding-bottom: 80px !important; /* ดันเนื้อหาหนี Footer */
            overflow-y: auto; 
        }

        /* 3. ล็อก Footer ไว้ที่ด้านล่าง */
        .footer-nav {
            position: fixed !important;
            bottom: 0 !important;
            left: 0 !important;
            right: 0 !important;
            width: 100% !important;
            height: 60px; 
            background-color: var(--color-primary);
            z-index: 9999 !important; 
            box-shadow: 0 -4px 10px rgba(0,0,0,0.1); 
            display: flex !important; 
        }
    }
</style>


<div class="main-container">
    <div class="header-row">
        <h2><i class="fas fa-shopping-cart"></i> ยืมอุปกรณ์ (ระบบตะกร้า)</h2>
    </div>

   <div class="dashboard-grid">
        
        <div class="section-card">
            <h3 style="color: var(--color-primary);">
                <i class="fas fa-camera"></i> เครื่องมือ
            </h3>

            <div id="reader" style="width: 100%; min-height: 250px; background: #000; border-radius: 8px; overflow: hidden; position: relative;">
                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: white; text-align: center;">
                    <i class="fas fa-video-slash" style="font-size: 3rem; opacity: 0.5;"></i>
                    <p style="margin-top: 10px;">กล้องปิดอยู่</p>
                </div>
            </div>

            <div style="margin-top: 15px; display: flex; gap: 10px; justify-content: center;">
                <button type="button" class="btn btn-primary btn-sm" id="startCameraBtn" onclick="startCamera()">
                    <i class="fas fa-power-off"></i> เปิดกล้อง
                </button>
                <button type="button" class="btn btn-danger btn-sm" id="stopCameraBtn" onclick="stopCamera()" style="display: none;">
                    <i class="fas fa-stop"></i> ปิด
                </button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('qr-input-file').click()">
                    <i class="fas fa-image"></i> รูปภาพ
                </button>
                <input type="file" id="qr-input-file" accept="image/*" style="display: none;" onchange="scanFromFile(this)">
            </div>
            
            <hr>

            <div id="item-selector-box">
                <label style="font-weight: bold; margin-bottom: 5px; display: block;">เลือกอุปกรณ์ (Manual):</label>
                <div style="display: flex; gap: 5px;">
                    <select id="manual_type_id" class="form-control" style="flex: 1;">
                        <option value="">-- เลือกรายการ --</option>
                        <?php 
                        $stmt = $pdo->query("SELECT * FROM med_equipment_types WHERE available_quantity > 0 ORDER BY name ASC");
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            // (สำคัญ: ใส่ data-max เพื่อเช็คจำนวนว่าง)
                            echo "<option value='{$row['id']}' data-name='{$row['name']}' data-max='{$row['available_quantity']}'>";
                            echo "{$row['name']} (เหลือ {$row['available_quantity']})";
                            echo "</option>";
                        }
                        ?>
                    </select>
                    <button type="button" class="btn btn-success" onclick="addManualItem()">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="section-card">
            <h3>รายการที่จะยืม</h3>
            
            <form id="walkinForm">
                <div id="student-info-box" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #ccc;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-weight: bold;">👤 ผู้ยืม:</div>
                            <div id="student-display" style="color: #666;">ยังไม่ได้ระบุ (สแกน QR)</div>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="resetStudent()">เปลี่ยน</button>
                    </div>
                    <input type="hidden" name="student_id" id="input_student_id" required>
                </div>

                <div class="table-container" style="max-height: 300px; overflow-y: auto; border: 1px solid #eee; border-radius: 8px; margin-bottom: 15px;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead style="background: #f0f0f0; position: sticky; top: 0;">
                            <tr>
                                <th style="padding: 10px; text-align: left;">อุปกรณ์</th>
                                <th style="padding: 10px; text-align: center; width: 120px;">จำนวน</th>
                                <th style="padding: 10px; text-align: center; width: 50px;">ลบ</th>
                            </tr>
                        </thead>
                        <tbody id="cart-body">
                            <tr id="empty-cart-row">
                                <td colspan="3" style="text-align: center; padding: 20px; color: #999;">
                                    ยังไม่มีรายการ (สแกน Barcode หรือเลือกเพิ่ม)
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="form-group" style="margin-bottom: 15px;">
                    <label>กำหนดคืน (ทุกชิ้น):</label>
                    <input type="date" name="due_date" id="input_due_date" class="form-control" required style="width: 100%; padding: 10px;" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                </div>

                <input type="hidden" name="cart_data" id="input_cart_data">

                <button type="submit" class="btn btn-primary" id="submitBtn" style="width: 100%; padding: 12px; font-size: 1.1em;" disabled>
                    <i class="fas fa-save"></i> ยืนยันการยืมทั้งหมด
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    // ตัวแปรจัดการกล้อง
    let html5QrCode = null;
    let cart = []; 
	let scanLock = false;

    // --- 1. จัดการตะกร้า (Cart Logic) ---
    
    function addToCart(typeId, typeName, maxQty, specificId = null) {
        let item = cart.find(i => i.type_id == typeId);
        if (item) {
            if (item.qty < item.max) {
                item.qty++;
                if (specificId) item.specific_ids.push(specificId);
            } else {
                Swal.fire('เต็มจำนวน', 'อุปกรณ์ชนิดนี้หมดแล้ว หรือเลือกครบจำนวนที่มี', 'warning');
                return;
            }
        } else {
            cart.push({
                type_id: typeId,
                name: typeName,
                qty: 1,
                max: parseInt(maxQty),
                specific_ids: specificId ? [specificId] : []
            });
        }
        renderCart();
    }

    function updateQty(index, change) {
        let item = cart[index];
        let newQty = item.qty + change;
        if (newQty > item.max) {
            Swal.fire('แจ้งเตือน', 'เกินจำนวนที่มีอยู่', 'warning');
            return;
        }
        if (newQty <= 0) {
            removeFromCart(index);
            return;
        }
        if (change < 0 && item.specific_ids.length > 0 && newQty < item.specific_ids.length) {
            item.specific_ids.pop(); 
        }
        item.qty = newQty;
        renderCart();
    }

    function removeFromCart(index) {
        cart.splice(index, 1);
        renderCart();
    }

    function renderCart() {
        const tbody = document.getElementById('cart-body');
        tbody.innerHTML = '';
        if (cart.length === 0) {
            tbody.innerHTML = `<tr><td colspan="3" style="text-align: center; padding: 20px; color: #999;">ยังไม่มีรายการ</td></tr>`;
            checkFormReady();
            return;
        }
        cart.forEach((item, index) => {
            let specificLabel = item.specific_ids.length > 0 ? `<br><small style="color:green;">(สแกนแล้ว: ${item.specific_ids.length} ชิ้น)</small>` : '';
            const row = `
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 10px;"><strong>${item.name}</strong>${specificLabel}</td>
                    <td style="padding: 10px; text-align: center;">
                        <button type="button" class="qty-btn" onclick="updateQty(${index}, -1)">-</button>
                        <span style="margin: 0 8px; font-weight: bold;">${item.qty}</span>
                        <button type="button" class="qty-btn" onclick="updateQty(${index}, 1)">+</button>
                    </td>
                    <td style="padding: 10px; text-align: center;">
                        <button type="button" class="btn btn-sm btn-danger" onclick="removeFromCart(${index})"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            `;
            tbody.innerHTML += row;
        });
        document.getElementById('input_cart_data').value = JSON.stringify(cart);
        checkFormReady();
    }

    function addManualItem() {
        const select = document.getElementById('manual_type_id');
        const typeId = select.value;
        if (!typeId) return;
        const option = select.options[select.selectedIndex];
        const name = option.getAttribute('data-name');
        const max = option.getAttribute('data-max');
        addToCart(typeId, name, max);
        select.value = '';
    }

    // --- 2. ระบบสแกน ---

    function onScanSuccess(decodedText, decodedResult) {
        if (scanLock) { 
            return; // 🛑 ถ้ายังล็อกอยู่ ให้หยุดทันที
        }
        
        // 1. ตั้งล็อกชั่วคราว
        scanLock = true;
        setTimeout(() => { 
            scanLock = false; 
        }, 500); // ปลดล็อกใน 0.5 วินาที (ป้องกันการสแกนรัวๆ จากเฟรมถัดไป)

        console.log("Scan result:", decodedText);

        if (decodedText.startsWith("MEDLOAN_STUDENT:")) {
            const studentCode = decodedText.split(":")[1];
            fetchStudent(studentCode);
        } else if (decodedText.startsWith("EQ-")) {
            const itemId = decodedText.replace("EQ-", "");
            fetchItem(itemId);
        } else {
            Swal.fire('ไม่รู้จักรหัส', 'รหัสนี้ไม่ใช่ของระบบ MedLoan (' + decodedText + ')', 'warning');
        }
    }

    function fetchStudent(code) {
        if (document.getElementById('input_student_id').value) return; // ไม่โหลดซ้ำ
        
        // ✅ แก้ไข Path แล้ว
        fetch(`/e_Borrow_test/ajax/get_student_by_code.php?id=${code}`)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    const s = data.student;
                    document.getElementById('input_student_id').value = s.id;
                    document.getElementById('student-display').innerHTML = `
                        <strong style="color: var(--color-primary);">${s.full_name}</strong><br>
                        <small>รหัส: ${s.student_personnel_id}</small>
                    `;
                    document.getElementById('student-info-box').style.borderLeftColor = 'var(--color-success)';
                    Swal.fire({icon: 'success', title: 'ระบุตัวตนสำเร็จ', text: s.full_name, timer: 1000, showConfirmButton: false});
                    checkFormReady();
                } else {
                    Swal.fire('ไม่พบข้อมูล', 'ไม่พบนักศึกษารหัสนี้', 'error');
                }
            });
    }

    function fetchItem(id) {
        // เช็คซ้ำในตะกร้า
        for (let i of cart) {
            if (i.specific_ids.includes(id)) {
                Swal.fire('ซ้ำ', 'อุปกรณ์ชิ้นนี้อยู่ในตะกร้าแล้ว', 'info');
                return;
            }
        }
        
        // ✅ แก้ไข Path แล้ว
        fetch(`/e_Borrow_test/ajax/get_item_data.php?id=${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    const item = data.item;
                    if (item.status !== 'available') {
                        Swal.fire('แจ้งเตือน', `อุปกรณ์นี้ไม่ว่าง (${item.status})`, 'warning');
                        return;
                    }
                    // ดึงจำนวน Max ของ Type มาด้วย
                    fetch(`/e_Borrow_test/ajax/get_equipment_type_data.php?id=${item.type_id}`)
                        .then(r => r.json())
                        .then(tData => {
                             const max = tData.equipment_type.available_quantity;
                             addToCart(item.type_id, item.name, max, item.id);
                             Swal.fire({icon: 'success', title: 'เพิ่มลงตะกร้า', text: item.name, timer: 800, showConfirmButton: false});
                        });
                }
            });
    }

    // --- 3. กล้อง & Utility ---

    function startCamera() {
        if (html5QrCode) return;
        html5QrCode = new Html5Qrcode("reader");
        html5QrCode.start({ facingMode: "environment" }, { fps: 10, qrbox: { width: 250, height: 250 } }, onScanSuccess)
        .then(() => {
            document.getElementById('startCameraBtn').style.display = 'none';
            document.getElementById('stopCameraBtn').style.display = 'inline-block';
        })
        .catch(err => Swal.fire('Error', 'เปิดกล้องไม่ได้', 'error'));
    }

    function stopCamera() {
        if (html5QrCode) {
            html5QrCode.stop().then(() => {
                html5QrCode.clear(); html5QrCode = null;
                document.getElementById('startCameraBtn').style.display = 'inline-block';
                document.getElementById('stopCameraBtn').style.display = 'none';
            });
        }
    }
    
   function scanFromFile(input) {
        if (input.files && input.files[0]) {
            // (เรายังต้องใช้ Logic Lock ด้านบนอยู่ แต่ต้องสร้าง Instance ก่อน)
            if (!html5QrCode) html5QrCode = new Html5Qrcode("reader");
            
            Swal.fire({ title: 'กำลังประมวลผล...', didOpen: () => { Swal.showLoading(); } });
            
            html5QrCode.scanFile(input.files[0], true)
                .then(decodedText => {
                    Swal.close();
                    onScanSuccess(decodedText, null); // เรียก onScanSuccess ที่มี Lock
                })
                .catch(err => {
                    Swal.close();
                    Swal.fire('ไม่พบ QR/Barcode', 'ไม่สามารถอ่านรหัสจากรูปภาพนี้ได้', 'error');
                });
            input.value = ''; 
        }
    }

    function checkFormReady() {
        const stId = document.getElementById('input_student_id').value;
        const hasItems = cart.length > 0;
        document.getElementById('submitBtn').disabled = !(stId && hasItems);
    }

    function resetStudent() {
        document.getElementById('input_student_id').value = '';
        document.getElementById('student-display').innerText = 'ยังไม่ได้ระบุ (สแกน QR)';
        document.getElementById('student-info-box').style.borderLeftColor = '#ccc';
        checkFormReady();
    }

    // ✅ Submit Form (แก้ไข Path แล้ว)
    document.getElementById('walkinForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('lending_staff_id', '<?php echo $_SESSION['user_id']; ?>');

        fetch('/e_Borrow_test/process/admin_direct_borrow_process.php', {
            method: 'POST',
            body: formData
        }).then(res => res.json()).then(data => {
            if(data.status === 'success') {
                Swal.fire('สำเร็จ', `บันทึกการยืม ${data.count} รายการเรียบร้อย`, 'success')
                .then(() => location.reload());
            } else {
                Swal.fire('ผิดพลาด', data.message, 'error');
            }
        });
    });
</script>
<?php include('../includes/footer.php'); ?>