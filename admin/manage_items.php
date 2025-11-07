<?php
// manage_items.php
// (ไฟล์ใหม่)

// 1. "จ้างยาม" และ "เชื่อมต่อ DB"
include('includes/check_session.php');
require_once('db_connect.php');

// 2. ตรวจสอบสิทธิ์ Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit;
}

// 3. รับ type_id จาก URL
$type_id = isset($_GET['type_id']) ? (int)$_GET['type_id'] : 0;
if ($type_id == 0) {
    die("ไม่ได้ระบุประเภทอุปกรณ์ <a href='manage_equipment.php'>กลับไปหน้าจัดการประเภท</a>");
}

// 4. ดึงข้อมูล "ประเภท" อุปกรณ์
try {
    $stmt_type = $pdo->prepare("SELECT * FROM med_equipment_types WHERE id = ?");
    $stmt_type->execute([$type_id]);
    $equipment_type = $stmt_type->fetch(PDO::FETCH_ASSOC);

    if (!$equipment_type) {
        die("ไม่พบประเภทอุปกรณ์นี้ <a href='manage_equipment.php'>กลับไปหน้าจัดการประเภท</a>");
    }

    // 5. ดึงข้อมูล "ชิ้น" อุปกรณ์ทั้งหมดในประเภทนี้
    $stmt_items = $pdo->prepare("SELECT * FROM med_equipment_items WHERE type_id = ? ORDER BY name ASC, serial_number ASC");
    $stmt_items->execute([$type_id]);
    $equipment_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // ◀️ (แก้ไข) ใช้ ->
    die("เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage());
}

// 6. ตั้งค่าตัวแปรสำหรับ Header
$page_title = "จัดการอุปกรณ์รายชิ้น - " . htmlspecialchars($equipment_type['name']);
$current_page = "manage_equip";
include('includes/header.php');
?>

<div class="header-row">
    <div>
        <h2 style="display: flex; align-items: center; gap: 1rem;">
            <a href="manage_equipment.php" class="btn btn-secondary" style="font-size: 1rem;">
                <i class="fas fa-chevron-left"></i>
            </a>
            <i class="fas fa-list-ol"></i>
            จัดการอุปกรณ์รายชิ้น: <strong><?php echo htmlspecialchars($equipment_type['name']); ?></strong>
        </h2>
        <p class="text-muted" style="margin-left: 55px;">
            เพิ่ม/ลบ/แก้ไข อุปกรณ์แต่ละชิ้นที่อยู่ในประเภทนี้
        </p>
    </div>
    <button class="add-btn" onclick="openAddItemPopup(<?php echo $type_id; ?>, '<?php echo htmlspecialchars(addslashes($equipment_type['name'])); ?>')">
        <i class="fas fa-plus"></i> เพิ่มอุปกรณ์ชิ้นใหม่
    </button>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th style="width: 80px;">ID ชิ้น</th>
                <th>ชื่อเฉพาะ (ถ้ามี)</th>
                <th>เลขซีเรียล</th>
                <th>รายละเอียด/หมายเหตุ</th>
                <th style="width: 150px;">สถานะ</th>
                <th style="width: 180px;">จัดการ</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($equipment_items)): ?>
                <tr>
                    <td colspan="6" style="text-align: center;">ยังไม่มีอุปกรณ์รายชิ้นในประเภทนี้</td>
                </tr>
            <?php else: ?>
                <?php foreach ($equipment_items as $item): ?>
                    <tr>
                        <td><?php echo $item['id']; ?></td>
                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                        <td><?php echo htmlspecialchars($item['serial_number'] ?? '-'); ?></td>
                        <td style="white-space: pre-wrap;"><?php echo htmlspecialchars($item['description'] ?? '-'); ?></td>
                        <td>
                            <?php
                                $status = $item['status'];
                                $badge_class = 'available';
                                if ($status == 'borrowed') {
                                    $badge_class = 'borrowed';
                                } elseif ($status == 'maintenance') {
                                    $badge_class = 'maintenance';
                                }
                                echo "<span class='status-badge {$badge_class}'>" . ucfirst($status) . "</span>";
                            ?>
                        </td>
                        <td class="action-buttons">
                            <?php if ($item['status'] != 'borrowed'): // (ถ้าไม่ถูกยืมอยู่) ?>
                                <button type="button" class="btn btn-manage" onclick="openEditItemPopup(<?php echo $item['id']; ?>)">
                                    <i class="fas fa-edit"></i> แก้ไข
                                </button>
                                <button type="button" class="btn btn-danger" onclick="confirmDeleteItem(<?php echo $item['id']; ?>, <?php echo $item['type_id']; ?>)">
                                    <i class="fas fa-trash"></i> ลบ
                                </button>
                            <?php else: // (ถ้าถูกยืมอยู่) ?>
                                <span class="text-muted">ถูกยืมอยู่</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
include('includes/footer.php');
?>