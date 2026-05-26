<?php
define('APP_INIT', true);
include('db_config.php');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php"); exit();
}

// ===== SECURITY: CSRF สำหรับ action ทั้งหมด =====
function handle_action($conn, $action, $req_id) {
    if ($action === 'reject') {
        $admin_id = (int)$_SESSION['user_id'];
        mysqli_query($conn, "UPDATE requisitions SET status='rejected', admin_id=$admin_id WHERE req_id=$req_id AND status='pending'");
        
        // LINE notify ผู้ขอเบิก
        $info = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT u.full_name, p.product_name, rd.request_qty
             FROM requisitions r
             JOIN users u ON r.user_id=u.user_id
             JOIN requisition_details rd ON r.req_id=rd.req_id
             JOIN products p ON rd.product_id=p.product_id
             WHERE r.req_id=$req_id LIMIT 1"));
        if ($info) {
            send_line_notify("\n❌ [ไม่อนุมัติ] คำขอเบิกพัสดุ\n━━━━━━━━━━━━━━\nผู้ขอ: {$info['full_name']}\nพัสดุ: {$info['product_name']}\nจำนวน: {$info['request_qty']}\n📅 " . date('d/m/Y H:i'));
        }
        echo "<script>alert('ปฏิเสธคำขอเรียบร้อยแล้ว'); window.location='admin_approval.php';</script>";

    } elseif ($action === 'approve') {
        $check = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT rd.product_id, rd.request_qty, p.quantity, p.product_name, u.full_name
             FROM requisitions r
             JOIN users u ON r.user_id=u.user_id
             JOIN requisition_details rd ON r.req_id=rd.req_id
             JOIN products p ON rd.product_id=p.product_id
             WHERE r.req_id=$req_id AND r.status='pending' LIMIT 1"));

        if ($check && $check['quantity'] >= $check['request_qty']) {
            $admin_id  = (int)$_SESSION['user_id'];
            $prod_id   = (int)$check['product_id'];
            $req_qty   = (int)$check['request_qty'];

            mysqli_query($conn, "UPDATE products SET quantity=quantity-$req_qty WHERE product_id=$prod_id");
            mysqli_query($conn, "UPDATE requisitions SET status='approved', admin_id=$admin_id WHERE req_id=$req_id");

            // LINE notify
            send_line_notify("\n✅ [อนุมัติแล้ว] คำขอเบิกพัสดุ\n━━━━━━━━━━━━━━\nผู้ขอ: {$check['full_name']}\nพัสดุ: {$check['product_name']}\nจำนวน: $req_qty\n📅 " . date('d/m/Y H:i'));

            // ตรวจ low stock หลังตัดยอด
            check_low_stock_notify($conn, $prod_id);

            echo "<script>alert('อนุมัติสำเร็จ'); window.location='admin_approval.php';</script>";
        } else {
            echo "<script>alert('พัสดุในสต็อกไม่เพียงพอ'); window.location='admin_approval.php';</script>";
        }
    }
    exit();
}

if (isset($_GET['reject_id']) && isset($_GET['csrf']) && verify_csrf($_GET['csrf'])) {
    handle_action($conn, 'reject', (int)$_GET['reject_id']);
}
if (isset($_GET['approve_id']) && isset($_GET['csrf']) && verify_csrf($_GET['csrf'])) {
    handle_action($conn, 'approve', (int)$_GET['approve_id']);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายการรออนุมัติ - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .main-content { margin-left: 16.66%; background: #f8f9fa; padding: 30px; min-height: 100vh; }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <?php include('admin_sidebar.php'); ?>
            <div class="col-md-10 main-content">
                <h3 class="mb-4"><i class="bi bi-file-earmark-check me-2 text-primary"></i>รายการรออนุมัติเบิกพัสดุ</h3>

                <div class="card border-0 shadow-sm p-3" style="border-radius: 15px;">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>ผู้ขอเบิก</th>
                                <th>พัสดุ</th>
                                <th class="text-center">จำนวนที่ขอ</th>
                                <th class="text-center">สต๊อกคงเหลือ</th>
                                <th>วันที่ขอ</th>
                                <th class="text-center">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $csrf = $_SESSION['csrf_token'];
                        $sql  = "SELECT r.req_id, u.full_name, p.product_name, p.quantity as stock,
                                        rd.request_qty, r.req_date
                                 FROM requisitions r
                                 JOIN users u ON r.user_id=u.user_id
                                 JOIN requisition_details rd ON r.req_id=rd.req_id
                                 JOIN products p ON rd.product_id=p.product_id
                                 WHERE r.status='pending'
                                 ORDER BY r.req_date ASC";
                        $res = mysqli_query($conn, $sql);
                        $count = mysqli_num_rows($res);
                        while ($row = mysqli_fetch_assoc($res)):
                            $enough = $row['stock'] >= $row['request_qty'];
                        ?>
                        <tr>
                            <td>
                                <span class="fw-semibold"><?php echo htmlspecialchars($row['full_name']); ?></span>
                            </td>
                            <td class="fw-bold text-primary"><?php echo htmlspecialchars($row['product_name']); ?></td>
                            <td class="text-center">
                                <span class="badge bg-warning text-dark px-3 py-2" style="font-size:14px;">
                                    <?php echo (int)$row['request_qty']; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($enough): ?>
                                    <span class="text-success fw-bold"><?php echo (int)$row['stock']; ?></span>
                                <?php else: ?>
                                    <span class="text-danger fw-bold">ไม่พอ (<?php echo (int)$row['stock']; ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td><small><?php echo date('d/m/Y H:i', strtotime($row['req_date'])); ?></small></td>
                            <td class="text-center">
                                <?php if ($enough): ?>
                                    <a href="admin_approval.php?approve_id=<?php echo $row['req_id']; ?>&csrf=<?php echo urlencode($csrf); ?>"
                                       class="btn btn-success btn-sm"
                                       onclick="return confirm('ยืนยันการอนุมัติ? สต็อกจะถูกตัดทันที')">
                                        <i class="bi bi-check-lg"></i> อนุมัติ
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-sm" disabled>
                                        <i class="bi bi-slash-circle"></i> ของไม่พอ
                                    </button>
                                <?php endif; ?>
                                <a href="admin_approval.php?reject_id=<?php echo $row['req_id']; ?>&csrf=<?php echo urlencode($csrf); ?>"
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('ต้องการปฏิเสธคำขอนี้?')">
                                    <i class="bi bi-x-lg"></i> ปฏิเสธ
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if ($count === 0): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="bi bi-check-circle-fill text-success fs-1 d-block mb-2"></i>
                                    ไม่มีรายการค้างอนุมัติ
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>