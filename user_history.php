<?php 
include('db_config.php'); 
if(!isset($_SESSION['user_id'])) { header("location: login.php"); exit(); } // [BUG FIX] เพิ่ม exit() หลัง redirect
$user_id = (int)$_SESSION['user_id']; // [BUG FIX] cast เป็น int ป้องกัน SQL Injection
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8"><title>ประวัติของฉัน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .status-card { border-radius: 12px; border: none; margin-bottom: 15px; transition: 0.2s; }
        .status-card:hover { transform: scale(1.01); }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4" style="max-width: 600px;">
        <div class="d-flex align-items-center mb-4">
            <a href="user_index.php" class="btn btn-light rounded-circle me-3"><i class="bi bi-arrow-left"></i></a>
            <h4 class="mb-0 fw-bold">ประวัติการเบิกของฉัน</h4>
        </div>

        <?php
        $sql = "SELECT r.*, p.product_name, rd.request_qty, p.image 
                FROM requisitions r 
                JOIN requisition_details rd ON r.req_id = rd.req_id
                JOIN products p ON rd.product_id = p.product_id
                WHERE r.user_id = '$user_id' ORDER BY r.req_date DESC";
        $res = mysqli_query($conn, $sql);
        while($row = mysqli_fetch_array($res)){
            $bg = 'bg-warning'; $txt = 'รออนุมัติ';
            if($row['status'] == 'approved'){ $bg = 'bg-success'; $txt = 'อนุมัติแล้ว'; }
            if($row['status'] == 'rejected'){ $bg = 'bg-danger'; $txt = 'ไม่อนุมัติ'; }
        ?>
        <div class="card status-card shadow-sm">
            <div class="card-body d-flex align-items-center">
                <!-- [BUG FIX] เช็คก่อนว่ามีรูปหรือเปล่า ถ้าไม่มีแสดง icon แทน ป้องกัน broken image -->
                <?php if(!empty($row['image'])): ?>
                    <img src="uploads/<?php echo htmlspecialchars($row['image']); ?>" width="60" height="60" class="rounded me-3" style="object-fit: cover; flex-shrink: 0;">
                <?php else: ?>
                    <div class="bg-secondary text-white rounded d-flex align-items-center justify-content-center me-3" style="width:60px;height:60px;flex-shrink:0;">
                        <i class="bi bi-box-seam"></i>
                    </div>
                <?php endif; ?>
                <div class="flex-grow-1">
                    <!-- [BUG FIX] เพิ่ม htmlspecialchars() ป้องกัน XSS -->
                    <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($row['product_name']); ?></h6>
                    <small class="text-muted">จำนวน: <?php echo (int)$row['request_qty']; ?> | <?php echo date('d/m/y H:i', strtotime($row['req_date'])); ?></small>
                </div>
                <span class="badge <?php echo $bg; ?>"><?php echo $txt; ?></span>
            </div>
        </div>
        <?php } ?>
    </div>
</body>
</html>