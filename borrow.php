<?php
// [แก้ไขไฟล์: napat-tirmongkol/e-borrow/E-Borrow-c4df732f98db10bf52a8e9d7299e212b6f2abd37/borrow.php]
// borrow_list.php (อัปเดตสำหรับ V5 - ใช้ Types)

@session_start(); 
include('includes/check_student_session.php'); 
require_once('includes/db_connect.php');

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
<script src="assets/js/student_app.js"></script>

<?php
include('includes/student_footer.php'); 
?>