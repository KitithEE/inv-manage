<?php 
include('db_config.php'); 
if($_SESSION['role'] != 'admin') header("location: login.php");

// [BUG FIX] cast delete_id เป็น int ป้องกัน SQL Injection (เดิมรับค่า GET โดยตรง)
if(isset($_GET['delete_id'])){
    $id = (int)$_GET['delete_id'];
    if($id > 0){
        $res = mysqli_query($conn, "SELECT image FROM products WHERE product_id = '$id'");
        $data = mysqli_fetch_array($res);
        if($data){
            if($data['image'] != "") { @unlink("uploads/" . $data['image']); }
            mysqli_query($conn, "DELETE FROM products WHERE product_id = '$id'");
            echo "<script>alert('ลบรายการเรียบร้อย'); window.location='admin_inventory.php';</script>";
        }
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>คลังพัสดุ - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <?php include('admin_sidebar.php'); ?>

            <div class="col-md-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3><i class="bi bi-box-seam me-2"></i>รายการพัสดุทั้งหมดในคลัง</h3>
                    <div>
                        <a href="admin_history.php" class="btn btn-outline-primary me-2"><i class="bi bi-clock-history"></i> ประวัติการเบิก</a>
                        <a href="admin_add_product.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> เพิ่มพัสดุใหม่</a>
                    </div>
                </div>

                <div class="card border-0 shadow-sm p-3" style="border-radius: 15px;">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="100">รูปภาพ</th>
                                <th>รหัสพัสดุ</th>
                                <th>ชื่อพัสดุ</th>
                                <th>หมวดหมู่</th>
                                <th>คงเหลือ</th>
                                <th>หน่วย</th>
                                <th width="220" class="text-center">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql = "SELECT * FROM products ORDER BY product_id DESC";
                            $res = mysqli_query($conn, $sql);
                            while($row = mysqli_fetch_array($res)){
                                $stock_class = ($row['quantity'] < 5) ? 'text-danger fw-bold' : '';
                            ?>
                            <tr>
                                <td>
                                    <?php if($row['image'] != ""){ ?>
                                        <img src="uploads/<?php echo htmlspecialchars($row['image']); ?>" width="50" height="50" class="rounded shadow-sm" style="object-fit: cover;">
                                    <?php } else { ?>
                                        <div class="bg-secondary text-white rounded d-flex align-items-center justify-content-center" style="width:50px; height:50px; font-size: 10px;">No Image</div>
                                    <?php } ?>
                                </td>
                                <td><code><?php echo htmlspecialchars($row['product_code']); ?></code></td>
                                <td class="fw-bold"><?php echo htmlspecialchars($row['product_name']); ?></td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['category']); ?></span></td>
                                <td class="<?php echo $stock_class; ?>"><?php echo (int)$row['quantity']; ?></td>
                                <td><?php echo htmlspecialchars($row['unit']); ?></td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-info text-white me-1" data-bs-toggle="modal" data-bs-target="#qrModal<?php echo $row['product_id']; ?>">
                                        <i class="bi bi-qr-code"></i> QR
                                    </button>
                                    <a href="admin_edit_product.php?id=<?php echo $row['product_id']; ?>" class="btn btn-sm btn-warning me-1">
                                        <i class="bi bi-pencil-square"></i> แก้ไข
                                    </a>
                                    <a href="admin_inventory.php?delete_id=<?php echo $row['product_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบรายการนี้? การลบจะไม่สามารถย้อนกลับได้')">
                                        <i class="bi bi-trash"></i> ลบ
                                    </a>
                                </td>
                            </tr>

                            <!-- QR Modal -->
                            <div class="modal fade" id="qrModal<?php echo $row['product_id']; ?>" tabindex="-1" aria-hidden="true">
                              <div class="modal-dialog modal-dialog-centered modal-sm">
                                <div class="modal-content" style="border-radius: 15px;">
                                  <div class="modal-header border-0 pb-0">
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                  </div>
                                  <div class="modal-body text-center pb-4">
                                    <h5 class="fw-bold text-primary mb-3"><?php echo htmlspecialchars($row['product_code']); ?></h5>
                                    <?php
                                        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
                                        $host = $_SERVER['HTTP_HOST'];
                                        $path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
                                        $checkout_url = $protocol . "://" . $host . $path . "/user_checkout.php?id=" . $row['product_id'];
                                        $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($checkout_url);
                                    ?>
                                    <img src="<?php echo $qr_url; ?>" alt="QR Code" class="img-fluid border p-2 rounded shadow-sm mb-3">
                                    <h6 class="fw-bold"><?php echo htmlspecialchars($row['product_name']); ?></h6>
                                    <p class="text-muted small mb-0">ผู้ใช้สามารถสแกนเพื่อเข้าสู่หน้าเบิกพัสดุรายการนี้ได้ทันที</p>
                                  </div>
                                </div>
                              </div>
                            </div>
                            <?php } ?>
                            <?php if(mysqli_num_rows($res) == 0) echo "<tr><td colspan='7' class='text-center py-4 text-muted'>ยังไม่มีรายการพัสดุในระบบ</td></tr>"; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
