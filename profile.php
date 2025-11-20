<?php
// edit_profile.php (หน้าตั้งค่า/แก้ไขโปรไฟล์)

// 1. "จ้างยาม" และ "เชื่อมต่อ DB"
@session_start(); 
include('includes/check_student_session.php'); // (◀️ เปิดยาม)
require_once('includes/db_connect.php'); //

// 2. ดึง ID นักศึกษาจาก Session
$student_id = $_SESSION['student_id']; 

// 3. (Query ข้อมูลผู้ใช้ปัจจุบัน)
try {
    $stmt = $pdo->prepare("SELECT * FROM med_students WHERE id = ?");
    $stmt->execute([$student_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user_data) {
        // (ถ้าหา ID ไม่เจอ ให้เด้งออก)
        header("Location: logout.php");
        exit;
    }
} catch (PDOException $e) {
    die("เกิดข้อผิดพลาดในการดึงข้อมูลผู้ใช้: " . $e->getMessage());
}

// 4. ตั้งค่าตัวแปรสำหรับ Header
$page_title = "ตั้งค่าโปรไฟล์";
$active_page = 'settings'; // (บอก Footer ว่าเมนูไหน Active)
include('includes/student_header.php');
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<main class="main-container">

    <?php 
    // (ส่วนสำหรับแสดงข้อความ Success หรือ Error หลังจากกดบันทึก)
    if (isset($_GET['status'])): 
    ?>
        <div class="section-card" style="padding: 1.25rem; margin-bottom: 1rem; border: 2px solid <?php echo ($_GET['status'] == 'success') ? 'var(--color-success)' : 'var(--color-danger)'; ?>;">
            <?php if ($_GET['status'] == 'success'): ?>
                <p style="color: var(--color-success); font-weight: bold; margin: 0;">
                    <i class="fas fa-check-circle"></i> บันทึกข้อมูลสำเร็จ!
                </p>
            <?php else: ?>
                <p style="color: var(--color-danger); font-weight: bold; margin: 0;">
                    <i class="fas fa-times-circle"></i> เกิดข้อผิดพลาด: <?php echo htmlspecialchars($_GET['message'] ?? 'ไม่ทราบสาเหตุ'); ?>
                </p>
            <?php endif; ?>
        </div>
    <?php endif; 
    // (จบส่วนแสดงข้อความ)
    ?>

    <div class="section-card">
        <h2 class="section-title">ตั้งค่าโปรไฟล์</h2>
        <p class="text-muted">คุณสามารถแก้ไขข้อมูลส่วนตัวของคุณได้ที่นี่ (ข้อมูลสถานภาพจะถูกใช้โดย Admin เท่านั้น)</p>
        
        <form action="process/edit_profile_process.php" method="POST" id="profileForm">
            
            <div class="form-group">
                <label for="full_name">ชื่อ-นามสกุล <span style="color:red;">*</span></label>
                <input type="text" name="full_name" id="full_name" value="<?php echo htmlspecialchars($user_data['full_name']); ?>" required>
            </div>

            <div class="form-group">
                <label for="department">คณะ/หน่วยงาน/สถาบัน</label>
                <input type="text" name="department" id="department" value="<?php echo htmlspecialchars($user_data['department'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="status">สถานภาพ (ดูได้อย่างเดียว)</label>
                <input type="text" id="status" value="<?php 
                    echo htmlspecialchars($user_data['status']); 
                    if ($user_data['status'] == 'other') {
                        echo ' (' . htmlspecialchars($user_data['status_other']) . ')';
                    }
                ?>" disabled style="background-color: #f4f4f4;">
            </div>

            <div class="form-group">
                <label for="student_personnel_id">รหัสผู้ใช้งาน/บุคลากร</label>
                <input type="text" name="student_personnel_id" id="student_personnel_id" value="<?php echo htmlspecialchars($user_data['student_personnel_id'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="phone_number">เบอร์โทรศัพท์</label>
                <input type="text" name="phone_number" id="phone_number" value="<?php echo htmlspecialchars($user_data['phone_number'] ?? ''); ?>">
            </div>

            <button type="submit" class="btn-loan" style="width: 100%; font-size: 1rem; padding: 12px;">
                <i class="fas fa-save"></i> บันทึกการเปลี่ยนแปลง
            </button>
        </form>
    </div>
</div> 
<script>
function showMyQRCode() {
    // ดึงรหัสนักศึกษา และชื่อ จาก PHP มาใส่ในตัวแปร JS
    const studentCode = "<?php echo htmlspecialchars($student['student_personnel_id']); ?>"; 
    const studentName = "<?php echo htmlspecialchars($student['full_name']); ?>";
    
    // (สำคัญ) สร้างข้อมูลสำหรับ QR Code 
    // รูปแบบ: "MEDLOAN_STUDENT:รหัสนักศึกษา"
    // การใส่ Prefix "MEDLOAN_STUDENT:" ช่วยให้ระบบรู้ว่านี่คือ QR ของเราจริงๆ ไม่ใช่ QR จ่ายเงิน
    const qrData = "MEDLOAN_STUDENT:" + studentCode;

    // แสดง Popup ด้วย SweetAlert2
    Swal.fire({
        title: 'บัตรประจำตัวดิจิทัล',
        html: `
            <div style="display: flex; justify-content: center; align-items: center; margin: 20px 0; padding: 10px; background: #fff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                <div id="qrcode-container"></div>
            </div>
            <h3 style="margin-bottom: 5px;">${studentCode}</h3>
            <p style="color: #666;">${studentName}</p>
            <p class="text-muted" style="font-size: 0.9em; margin-top: 15px;">
                <i class="fas fa-info-circle"></i> ยื่นให้เจ้าหน้าที่สแกนเพื่อยืมอุปกรณ์
            </p>
        `,
        didOpen: () => {
            // สั่งให้ Library วาด QR Code ลงใน div id="qrcode-container"
            new QRCode(document.getElementById("qrcode-container"), {
                text: qrData,
                width: 220,
                height: 220,
                colorDark : "#000000",
                colorLight : "#ffffff",
                correctLevel : QRCode.CorrectLevel.H // ระดับความชัดสูง (เผื่อโลโก้บัง)
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