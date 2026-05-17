<?php 
include('db_config.php'); 
if($_SESSION['role'] != 'admin') { header("location: login.php"); exit(); } // [BUG FIX] เพิ่ม exit() หลัง redirect
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8"><title>ประวัติการเบิก - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include('admin_sidebar.php'); ?>

            <div class="col-md-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3>ประวัติการเบิกพัสดุทั้งหมด</h3>
                    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm"><i class="bi bi-printer"></i> พิมพ์รายงาน</button>
                </div>
                
                <div class="card border-0 shadow-sm p-3">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>วันที่</th>
                                <th>ผู้เบิก</th>
                                <th>พัสดุ</th>
                                <th>จำนวน</th>
                                <th>สถานะ</th>
                                <th>ผู้อนุมัติ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql = "SELECT r.*, u.full_name as requester, p.product_name, rd.request_qty, a.full_name as admin_name
                                    FROM requisitions r
                                    JOIN users u ON r.user_id = u.user_id
                                    JOIN requisition_details rd ON r.req_id = rd.req_id
                                    JOIN products p ON rd.product_id = p.product_id
                                    LEFT JOIN users a ON r.admin_id = a.user_id
                                    WHERE r.status != 'pending'
                                    ORDER BY r.req_date DESC";
                            $res = mysqli_query($conn, $sql);
                            while($row = mysqli_fetch_array($res)){
                            ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($row['req_date'])); ?></td>
                                <td><?php echo htmlspecialchars($row['requester']); ?></td><!-- [BUG FIX] htmlspecialchars -->
                                <td class="fw-bold"><?php echo htmlspecialchars($row['product_name']); ?></td>
                                <td><?php echo $row['request_qty']; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo ($row['status']=='approved')?'success':'danger'; ?>">
                                        <?php echo ($row['status']=='approved')?'อนุมัติแล้ว':'ไม่อนุมัติ'; ?>
                                    </span>
                                </td>
                                <td><small class="text-muted"><?php echo htmlspecialchars($row['admin_name'] ?? '-'); ?></small></td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>