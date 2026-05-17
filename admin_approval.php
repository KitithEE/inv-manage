<?php 
include('db_config.php'); 
if($_SESSION['role'] != 'admin') header("location: login.php");

// [BUG FIX] เพิ่ม handler สำหรับ reject_id ที่หายไปทั้งหมด (ปุ่มมีแต่ PHP ไม่มี)
if(isset($_GET['reject_id'])){
    $req_id = (int)$_GET['reject_id']; // [BUG FIX] cast เป็น int ป้องกัน SQL Injection
    mysqli_query($conn, "UPDATE requisitions SET status='rejected', admin_id='".$_SESSION['user_id']."' WHERE req_id='$req_id'");
    echo "<script>alert('ปฏิเสธคำขอเรียบร้อยแล้ว'); window.location='admin_approval.php';</script>";
    exit();
}

if(isset($_GET['approve_id'])){
    $req_id = (int)$_GET['approve_id']; // [BUG FIX] cast เป็น int ป้องกัน SQL Injection
    
    $check = mysqli_query($conn, "SELECT rd.product_id, rd.request_qty, p.quantity 
                                  FROM requisition_details rd 
                                  JOIN products p ON rd.product_id = p.product_id 
                                  WHERE rd.req_id = '$req_id'");
    $data = mysqli_fetch_array($check);
    
    if($data && $data['quantity'] >= $data['request_qty']){
        mysqli_query($conn, "UPDATE products SET quantity = quantity - ".(int)$data['request_qty']." WHERE product_id = '".(int)$data['product_id']."'");
        mysqli_query($conn, "UPDATE requisitions SET status='approved', admin_id='".$_SESSION['user_id']."' WHERE req_id='$req_id'");
        echo "<script>alert('อนุมัติสำเร็จ'); window.location='admin_approval.php';</script>";
    } else {
        echo "<script>alert('ขออภัย: พัสดุในสต็อกไม่เพียงพอ ไม่สามารถอนุมัติได้'); window.location='admin_approval.php';</script>";
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายการรออนุมัติ - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <?php include('admin_sidebar.php'); ?>

            <div class="col-md-10 main-content">
                <h3 class="mb-4">รายการรออนุมัติเบิกพัสดุ</h3>
                <div class="card border-0 shadow-sm p-3" style="border-radius: 15px;">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>ผู้ขอเบิก</th>
                                <th>พัสดุ</th>
                                <th>จำนวนที่ขอ</th>
                                <th>สต๊อกคงเหลือล่าสุด</th>
                                <th>วันที่ขอเบิก</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql = "SELECT r.*, u.full_name, p.product_name, p.quantity as current_stock, rd.request_qty 
                                    FROM requisitions r 
                                    JOIN users u ON r.user_id = u.user_id 
                                    JOIN requisition_details rd ON r.req_id = rd.req_id
                                    JOIN products p ON rd.product_id = p.product_id
                                    WHERE r.status = 'pending'
                                    ORDER BY r.req_date ASC";
                            $res = mysqli_query($conn, $sql);
                            while($row = mysqli_fetch_array($res)){
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td class="fw-bold text-primary"><?php echo htmlspecialchars($row['product_name']); ?></td>
                                <td><span class="badge bg-warning text-dark px-3 py-2" style="font-size: 14px;"><?php echo $row['request_qty']; ?></span></td>
                                <td>
                                    <?php 
                                    if($row['current_stock'] < $row['request_qty']){
                                        echo "<span class='text-danger fw-bold'>ไม่พอ (เหลือ ".$row['current_stock'].")</span>";
                                    } else {
                                        echo "<span class='text-success fw-bold'>".$row['current_stock']."</span>";
                                    }
                                    ?>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($row['req_date'])); ?></td>
                                <td>
                                    <?php if($row['current_stock'] < $row['request_qty']){ ?>
                                        <button class="btn btn-secondary btn-sm" disabled><i class="bi bi-slash-circle"></i> ของไม่พอ</button>
                                    <?php } else { ?>
                                        <a href="admin_approval.php?approve_id=<?php echo $row['req_id']; ?>" class="btn btn-success btn-sm" onclick="return confirm('ยืนยันการอนุมัติ? สต็อกจะถูกตัดทันที')">
                                            <i class="bi bi-check-lg"></i> อนุมัติ
                                        </a>
                                    <?php } ?>
                                    <!-- [BUG FIX] ปุ่มปฏิเสธทำงานได้แล้ว เพราะเพิ่ม reject_id handler ใน PHP แล้ว -->
                                    <a href="admin_approval.php?reject_id=<?php echo $row['req_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('ต้องการปฏิเสธคำขอนี้?')">
                                        <i class="bi bi-x-lg"></i> ปฏิเสธ
                                    </a>
                                </td>
                            </tr>
                            <?php } ?>
                            <?php if(mysqli_num_rows($res) == 0) echo "<tr><td colspan='6' class='text-center text-muted py-4'>ไม่มีรายการค้างอนุมัติ</td></tr>"; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
