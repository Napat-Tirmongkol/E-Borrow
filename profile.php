<?php
// edit_profile.php (หน้าตั้งค่า/แก้ไขโปรไฟล์)
@session_start(); 
include('includes/check_student_session.php'); 
require_once('includes/db_connect.php'); 

$student_id = $_SESSION['student_id']; 

// 1. Query ข้อมูลมาก่อน
try {
    $stmt = $pdo->prepare("SELECT * FROM med_students WHERE id = ?");
    $stmt->execute([$student_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user_data) { header("Location: logout.php"); exit; }
} catch (PDOException $e) { die("เกิดข้อผิดพลาดในการดึงข้อมูลผู้ใช้: " . $e->getMessage()); }

// 2. Logic แยกชื่อ
$curr_full_name = $user_data['full_name'] ?? '';
$parts = explode(' ', trim($curr_full_name));
$db_prefix = ''; $db_firstname = ''; $db_lastname = '';
$standard_prefixes = ['นาย', 'นางสาว', 'นาง'];

if (count($parts) >= 2) {
    if (in_array($parts[0], $standard_prefixes)) { $db_prefix = array_shift($parts); } 
    else { $db_prefix = 'other'; } // หรือ logic อื่น
    $db_lastname = array_pop($parts);
    $db_firstname = implode(' ', $parts);
} else {
    $db_firstname = $curr_full_name;
}

$page_title = "ตั้งค่าโปรไฟล์";
$active_page = 'settings'; 
include('includes/student_header.php');
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<main class="main-container">
    <div class="section-card">
        <h2 class="section-title">ตั้งค่าโปรไฟล์</h2>
        <form action="process/edit_profile_process.php" method="POST" id="profileForm">
            <div class="form-group">
                <label>ชื่อ-นามสกุล <span style="color:red;">*</span></label>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 110px;">
                        <select name="prefix" id="prefix" style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ddd;" required onchange="togglePrefixOther(this.value)">
                            <option value="">คำนำหน้า...</option>
                            <option value="นาย" <?php echo ($db_prefix == 'นาย') ? 'selected' : ''; ?>>นาย</option>
                            <option value="นางสาว" <?php echo ($db_prefix == 'นางสาว') ? 'selected' : ''; ?>>นางสาว</option>
                            <option value="นาง" <?php echo ($db_prefix == 'นาง') ? 'selected' : ''; ?>>นาง</option>
                            <option value="other" <?php echo (!in_array($db_prefix, $standard_prefixes) && !empty($db_prefix)) ? 'selected' : ''; ?>>อื่นๆ (ระบุ)</option>
                        </select>
                        <input type="text" name="prefix_other" id="prefix_other" placeholder="เช่น ดร. นพ." value="<?php echo (!in_array($db_prefix, $standard_prefixes)) ? htmlspecialchars($db_prefix) : ''; ?>" style="display: <?php echo (!in_array($db_prefix, $standard_prefixes) && !empty($db_prefix)) ? 'block' : 'none'; ?>; margin-top: 5px;">
                    </div>
                    <div style="flex: 2; min-width: 150px;"><input type="text" name="first_name" id="first_name" placeholder="ชื่อจริง" value="<?php echo htmlspecialchars($db_firstname); ?>" required></div>
                    <div style="flex: 2; min-width: 150px;"><input type="text" name="last_name" id="last_name" placeholder="นามสกุล" value="<?php echo htmlspecialchars($db_lastname); ?>" required></div>
                </div>
            </div>
            <button type="submit" class="btn-loan" style="width: 100%; font-size: 1rem; padding: 12px;"><i class="fas fa-save"></i> บันทึกการเปลี่ยนแปลง</button>
        </form>
    </div>
</div> 

<script>
function togglePrefixOther(val) {
    const inputOther = document.getElementById('prefix_other');
    if (val === 'other') { inputOther.style.display = 'block'; inputOther.required = true; inputOther.focus(); } 
    else { inputOther.style.display = 'none'; inputOther.required = false; inputOther.value = ''; }
}

function showMyQRCode() {
    const studentCode = "<?php echo htmlspecialchars($user_data['student_personnel_id']); ?>"; 
    const studentName = "<?php echo htmlspecialchars($user_data['full_name']); ?>";
    const studentDbId = "<?php echo $_SESSION['student_id']; ?>";

    const qrData = "MEDLOAN_STUDENT:" + studentCode + ":" + studentDbId;

    Swal.fire({
        title: 'บัตรประจำตัวดิจิทัล',
        html: `
            <div style="display: flex; justify-content: center; align-items: center; margin: 20px 0; padding: 10px; background: #fff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                <div id="qrcode-container"></div>
            </div>
            <h3 style="margin-bottom: 5px;">${studentCode}</h3>
            <p style="color: #666;">${studentName}</p>
            <p class="text-muted" style="font-size: 0.9em; margin-top: 15px;"><i class="fas fa-info-circle"></i> ยื่นให้เจ้าหน้าที่สแกนเพื่อยืมอุปกรณ์</p>
        `,
        didOpen: () => {
            new QRCode(document.getElementById("qrcode-container"), { text: qrData, width: 220, height: 220, correctLevel : QRCode.CorrectLevel.H });
        },
        confirmButtonText: 'ปิด', confirmButtonColor: '#6c757d'
    });
}
</script>
<?php include('includes/student_footer.php'); ?>