<?php
include('db_config.php');
if ($_SESSION['role'] != 'admin') header("location: login.php");

// Query ข้อมูลสรุป
$total_products = mysqli_fetch_array(mysqli_query($conn, "SELECT COUNT(*) as total FROM products"))['total'];
$low_stock = mysqli_fetch_array(mysqli_query($conn, "SELECT COUNT(*) as total FROM products WHERE quantity < 5"))['total'];
$pending_req = mysqli_fetch_array(mysqli_query($conn, "SELECT COUNT(*) as total FROM requisitions WHERE status='pending'"))['total'];
$approved_req = mysqli_fetch_array(mysqli_query($conn, "SELECT COUNT(*) as total FROM requisitions WHERE status='approved'"))['total'];
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>Dashboard - ระบบบริหารพัสดุ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>

<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <?php include('admin_sidebar.php'); ?>

            <div class="col-md-10 main-content">
                <h3 class="mb-4">ภาพรวมระบบพัสดุ (Executive Dashboard)</h3>

                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-primary shadow-sm border-0 p-3" style="border-radius: 15px;">
                            <h6>พัสดุในระบบทั้งหมด</h6>
                            <h2 class="fw-bold"><?php echo $total_products; ?> รายการ</h2>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-danger shadow-sm border-0 p-3" style="border-radius: 15px;">
                            <h6>สินค้าใกล้หมด (Low Stock)</h6>
                            <h2 class="fw-bold"><?php echo $low_stock; ?> รายการ</h2>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-dark shadow-sm border-0 p-3" style="border-radius: 15px;">
                            <h6>รออนุมัติเบิก</h6>
                            <h2 class="fw-bold"><?php echo $pending_req; ?> รายการ</h2>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-success shadow-sm border-0 p-3" style="border-radius: 15px;">
                            <h6>อนุมัติแล้วทั้งหมด</h6>
                            <h2 class="fw-bold"><?php echo $approved_req; ?> ครั้ง</h2>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 p-4" style="border-radius: 15px;">
                    <h5>ระบบจัดการตามเอกสาร Job Summary</h5>
                    <p class="text-muted">ดาวน์โหลดรายงานสรุปพัสดุทั้งหมดในรูปแบบไฟล์ Excel (CSV) เพื่อตรวจสอบประจำปี</p>
                    <a href="export_report.php" class="btn btn-success">
                        <i class="bi bi-file-earmark-excel"></i> ดาวน์โหลดรายงานสรุปพัสดุประจำปี (Excel)
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>

</html>