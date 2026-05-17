<?php
// นับจำนวนรายการที่ค้างอนุมัติสำหรับจุดแดง
include('db_config.php');
if ($_SESSION['role'] != 'admin') { header("location: login.php"); exit(); } // [BUG FIX] เพิ่ม exit() หลัง redirect

$notif_res = mysqli_query($conn, "SELECT COUNT(*) as total FROM requisitions WHERE status='pending'");
$notif_data = mysqli_fetch_array($notif_res);
$pending_count = $notif_data['total'];
?>
<div class="col-md-2 sidebar">
    <h5 class="text-primary fw-bold mb-4">Inventory Sys</h5>
    <ul class="nav flex-column">
        <li class="nav-item mb-2">
            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php') ? 'active fw-bold text-primary' : 'text-dark'; ?>" href="admin_dashboard.php">
                <i class="bi bi-pie-chart me-2"></i> แดชบอร์ด
            </a>
        </li>
        <li class="nav-item mb-2">
            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_inventory.php') ? 'active fw-bold text-primary' : 'text-dark'; ?>" href="admin_inventory.php">
                <i class="bi bi-box-seam me-2"></i> คลังพัสดุ
            </a>
        </li>
        <li class="nav-item mb-2">
            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_approval.php') ? 'active fw-bold text-primary' : 'text-dark'; ?>" href="admin_approval.php">
                <i class="bi bi-file-earmark-text me-2"></i> รายการเบิก
                <?php if($pending_count > 0): ?>
                    <span class="badge rounded-pill bg-danger ms-1" style="font-size: 0.7rem;"><?php echo $pending_count; ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item mb-2">
            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_history.php') ? 'active fw-bold text-primary' : 'text-dark'; ?>" href="admin_history.php">
                <i class="bi bi-clock-history me-2"></i> ประวัติการเบิก
            </a>
        </li>
        <hr>
        <li class="nav-item">
            <a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> ออกจากระบบ</a>
        </li>
    </ul>
</div>

<style>
    .sidebar { background: #fff; height: 100vh; border-right: 1px solid #eee; padding: 20px; position: fixed; width: 16.66%; }
    .main-content { margin-left: 16.66%; background: #f8f9fa; padding: 30px; min-height: 100vh; }
    .nav-link:hover { background: #f0f7ff; border-radius: 8px; }
</style>