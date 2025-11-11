<?php
// [แก้ไขไฟล์: napat-tirmongkol/e-borrow/E-Borrow-c4df732f98db10bf52a8e9d7299e212b6f2abd37/admin/manage_items.php]

// 1. "จ้างยาม" และ "เชื่อมต่อ DB"
// ◀️ (แก้ไข) เพิ่ม ../ ◀️
include('../includes/check_session.php'); 
require_once('../includes/db_connect.php');

// 2. ตรวจสอบสิทธิ์ Admin
$allowed_roles = ['admin', 'editor'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header("Location: index.php");
    exit;
}

// 3. รับ Type ID
$type_id = isset($_GET['type_id']) ? (int)$_GET['type_id'] : 0;
if ($type_id == 0) {
    header("Location: manage_equipment.php"); // (ถ้าไม่มี ID ให้เด้งกลับ)
    exit;
}

// 4. (Query ที่ 1) ดึงข้อมูล "ประเภท"
try {
    $stmt_type = $pdo->prepare("SELECT * FROM med_equipment_types WHERE id = ?");
    $stmt_type->execute([$type_id]);
    $type_info = $stmt_type->fetch(PDO::FETCH_ASSOC);

    if (!$type_info) {
        header("Location: manage_equipment.php"); // (ถ้า ID ผิด ให้เด้งกลับ)
        exit;
    }
} catch (PDOException $e) {
    die("เกิดข้อผิดพลาดในการดึงข้อมูลประเภท: " . $e->getMessage());
}

// 5. (Query ที่ 2) ดึงข้อมูล "ชิ้น" อุปกรณ์ (items)
try {
    $sql_items = "SELECT 
                    i.*, 
                    s.full_name as student_name, 
                    t.borrow_date, t.due_date
                  FROM med_equipment_items i
                  LEFT JOIN med_transactions t ON i.id = t.item_id AND t.status = 'borrowed'
                  LEFT JOIN med_students s ON t.borrower_student_id = s.id
                  WHERE i.type_id = ?
                  ORDER BY i.status ASC, i.id ASC";
    $stmt_items = $pdo->prepare($sql_items);
    $stmt_items->execute([$type_id]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $items_error = "เกิดข้อผิดพลาดในการดึงข้อมูลอุปกรณ์: " . $e->getMessage();
    $items = [];
}

// 6. ตั้งค่าตัวแปรสำหรับ Header
$page_title = "จัดการรายชิ้น: " . htmlspecialchars($type_info['name']);
$current_page = "manage_equip"; // (ให้เมนู "จัดการอุปกรณ์" Active)
// ◀️ (แก้ไข) เพิ่ม ../ ◀️
include('../includes/header.php');
?>

<div class="header-row">
    <a href="admin/manage_equipment.php" class="btn btn-secondary" style="margin-right: 1rem;">
        <i class="fas fa-arrow-left"></i> กลับ
    </a>
    <h2><i class="fas fa-list-ol"></i> จัดการอุปกรณ์รายชิ้น</h2>
</div>

<div class="add-user-button-wrapper" style="margin-bottom: 1.5rem;">
    <button class="add-btn" 
            onclick="openAddItemPopup(<?php echo $type_id; ?>, '<?php echo htmlspecialchars(addslashes($type_info['name'])); ?>')">
        <i class="fas fa-plus"></i> เพิ่มอุปกรณ์ชิ้นใหม่
    </button>
</div>


<div class="section-card" style="margin-bottom: 1.5rem;">
    <h4><?php echo htmlspecialchars($type_info['name']); ?></h4>
    <p class="text-muted" style="white-space: pre-wrap;"><?php echo htmlspecialchars($type_info['description'] ?? 'ไม่มีรายละเอียด'); ?></p>
    <div style="display: flex; gap: 1rem; font-weight: bold;">
        <span>จำนวนทั้งหมด: <?php echo $type_info['total_quantity']; ?> ชิ้น</span>
        <span style="color: var(--color-success);">ว่าง: <?php echo $type_info['available_quantity']; ?> ชิ้น</span>
    </div>
</div>

<div class="table-container desktop-only">
    <?php if (isset($items_error)) echo "<p style='color: red; padding: 15px;'>$items_error</p>"; ?>
    <table>
        <thead>
            <tr>
                <th style="width: 60px;">ID</th>
                <th>ชื่อ/รุ่น</th>
                <th>เลขซีเรียล (S/N)</th>
                <th>สถานะ</th>
                <th>ข้อมูลการยืม (ถ้ามี)</th>
                <th style="width: 200px;">จัดการ</th> </tr>
        </thead>
        <tbody>
            <?php if (empty($items)): ?>
                <tr>
                    <td colspan="6" style="text-align: center;">ยังไม่มีอุปกรณ์รายชิ้นในประเภทนี้</td>
                </tr>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><strong><?php echo $item['id']; ?></strong></td>
                        <td class="truncate-text" title="<?php echo htmlspecialchars($item['name']); ?>">
                            <?php echo htmlspecialchars($item['name']); ?>
                        </td>
                        <td><?php echo htmlspecialchars($item['serial_number'] ?? '-'); ?></td>
                        <td>
                            <?php 
                            $status = $item['status'];
                            if ($status == 'available') {
                                echo '<span class="status-badge available">ว่าง</span>';
                            } elseif ($status == 'borrowed') {
                                echo '<span class="status-badge borrowed">ถูกยืม</span>';
                            } elseif ($status == 'maintenance') {
                                echo '<span class="status-badge maintenance">ซ่อมบำรุง</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php if ($status == 'borrowed' && $item['student_name']): ?>
                                <strong>ผู้ยืม:</strong> <?php echo htmlspecialchars($item['student_name']); ?><br>
                                <small>กำหนดคืน: <?php echo date('d/m/Y', strtotime($item['due_date'])); ?></small>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="action-buttons">
                            
                            <button type="button" class="btn btn-manage btn-sm" title="แก้ไข" onclick="openEditItemPopup(<?php echo $item['id']; ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            
                            <button type="button" class="btn btn-secondary btn-sm" title="ดูประวัติการยืม"
                                    onclick="openItemHistoryPopup(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars(addslashes($item['name'])); ?>')">
                                <i class="fas fa-history"></i>
                            </button>
                            
                            <?php if ($status != 'borrowed'): // (ป้องกันการลบตอนถูกยืมอยู่) ?>
                            <button type="button" class="btn btn-danger btn-sm" title="ลบ" 
                                    onclick="confirmDeleteItem(<?php echo $item['id']; ?>, <?php echo $item['type_id']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="student-card-list">
    <?php if (isset($items_error)) echo "<p style='color: red; padding: 15px;'>$items_error</p>"; ?>

    <?php if (empty($items)): ?>
        <div class="history-card">
            <p style="text-align: center; width: 100%;">ยังไม่มีอุปกรณ์รายชิ้นในประเภทนี้</p>
        </div>
    <?php else: ?>
        <?php foreach ($items as $item): ?>
            <div class="history-card">
                
                <div class="history-card-icon">
                    <?php
                    // (เลือกสีไอคอนตามสถานะ)
                    $status = $item['status'];
                    $badge_class = 'grey';
                    $icon_class = 'fas fa-tools';
                    if ($status == 'available') {
                        $badge_class = 'green';
                        $icon_class = 'fas fa-check-circle';
                    } elseif ($status == 'borrowed') {
                        $badge_class = 'blue'; // (ใช้ 'blue' สำหรับ 'borrowed' ใน dark mode จะชัดกว่า)
                        $icon_class = 'fas fa-user-clock';
                    } elseif ($status == 'maintenance') {
                        $badge_class = 'yellow';
                        $icon_class = 'fas fa-wrench';
                    }
                    ?>
                    <span class="status-badge <?php echo $badge_class; ?>">
                        <i class="<?php echo $icon_class; ?>"></i>
                    </span>
                </div>

                <div class="history-card-info">
                    <h4 class="truncate-text" title="<?php echo htmlspecialchars($item['name']); ?>">
                        <?php echo htmlspecialchars($item['name']); ?> (ID: <?php echo $item['id']; ?>)
                    </h4>
                    <p>S/N: <?php echo htmlspecialchars($item['serial_number'] ?? '-'); ?></p>
                    <?php if ($status == 'borrowed' && $item['student_name']): ?>
                         <p style="font-size: 0.9em; color: var(--color-text-dark);">
                            ยืมโดย: <strong><?php echo htmlspecialchars($item['student_name']); ?></strong>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="action-buttons" style="flex-shrink: 0;">
                    <button type="button" class="btn btn-manage btn-sm" title="แก้ไข" onclick="openEditItemPopup(<?php echo $item['id']; ?>)">
                        <i class="fas fa-edit"></i>
                    </button>
                    
                    <button type="button" class="btn btn-secondary btn-sm" title="ดูประวัติการยืม"
                            onclick="openItemHistoryPopup(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars(addslashes($item['name'])); ?>')">
                        <i class="fas fa-history"></i>
                    </button>
                    
                    <?php if ($item['status'] != 'borrowed'): ?>
                    <button type="button" class="btn btn-danger btn-sm" title="ลบ" 
                            onclick="confirmDeleteItem(<?php echo $item['id']; ?>, <?php echo $item['type_id']; ?>)">
                        <i class="fas fa-trash"></i>
                    </button>
                    <?php endif; ?>
                </div>

            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>


<?php
// 7. เรียกใช้ Footer
// (เราดึงฟังก์ชัน JS มาจาก footer.php)
// ◀️ (แก้ไข) เพิ่ม ../ ◀️
include('../includes/footer.php');
?>