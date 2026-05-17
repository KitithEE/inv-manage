<?php 
include('db_config.php'); 
if(!isset($_SESSION['user_id'])) { header("location: login.php"); exit(); }

// [BUG FIX] cast $id เป็น int ป้องกัน SQL Injection (เดิมรับค่า GET โดยตรง)
$id = (int)$_GET['id'];
if($id <= 0) { header("location: user_index.php"); exit(); }

$res = mysqli_query($conn, "SELECT * FROM products WHERE product_id = '$id'");
$p   = mysqli_fetch_array($res);
if(!$p) { header("location: user_index.php"); exit(); } // [BUG FIX] ถ้าไม่เจอพัสดุให้เด้งกลับ

$sql_history = "SELECT r.req_date, u.full_name, rd.request_qty
                FROM requisition_details rd 
                JOIN requisitions r ON rd.req_id = r.req_id 
                JOIN users u ON r.user_id = u.user_id 
                WHERE rd.product_id = '$id' AND r.status = 'approved' 
                ORDER BY r.req_date DESC LIMIT 5";
$history_res = mysqli_query($conn, $sql_history);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ข้อมูลพัสดุ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="card border-0 shadow-sm p-4 mb-3" style="border-radius: 20px;">
            <div class="text-center">
                <?php if($p['image'] != ""): ?>
                    <img src="uploads/<?php echo htmlspecialchars($p['image']); ?>" class="mx-auto d-block mb-3" style="width: 150px; border-radius: 10px;">
                <?php endif; ?>
                <h3 class="fw-bold"><?php echo htmlspecialchars($p['product_name']); ?></h3>
                <p class="text-muted">รหัส: <?php echo htmlspecialchars($p['product_code']); ?> | คงเหลือ: <span class="text-primary fw-bold"><?php echo (int)$p['quantity']; ?></span> <?php echo htmlspecialchars($p['unit']); ?></p>
            </div>
            
            <hr>
            
            <?php if($p['quantity'] > 0): ?>
                <a href="user_checkout.php?id=<?php echo $id; ?>" class="btn btn-primary btn-lg w-100 mb-2">
                    <i class="bi bi-cart-plus"></i> ทำรายการเบิกทันที
                </a>
            <?php else: ?>
                <button class="btn btn-secondary btn-lg w-100 mb-2" disabled>
                    <i class="bi bi-slash-circle"></i> พัสดุนี้หมดชั่วคราว
                </button>
            <?php endif; ?>
            
            <a href="#history-section" class="btn btn-outline-secondary w-100">
                <i class="bi bi-clock-history"></i> ดูประวัติการเบิกจ่าย
            </a>
        </div>

        <div id="history-section" class="card border-0 shadow-sm p-3" style="border-radius: 15px;">
            <h6 class="fw-bold mb-3"><i class="bi bi-journal-text text-primary"></i> 5 รายการเบิกล่าสุด</h6>
            <div class="table-responsive">
                <table class="table table-sm" style="font-size: 0.9rem;">
                    <thead class="table-light">
                        <tr>
                            <th>วันที่</th>
                            <th>ผู้เบิก</th>
                            <th>จำนวน</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($history_res) > 0) { 
                                while($h = mysqli_fetch_array($history_res)){ ?>
                            <tr>
                                <td><small><?php echo date('d/m/y', strtotime($h['req_date'])); ?></small></td>
                                <td><?php echo htmlspecialchars($h['full_name']); ?></td>
                                <td class="text-center"><?php echo (int)$h['request_qty']; ?></td>
                            </tr>
                        <?php } } else { ?>
                            <tr><td colspan="3" class="text-center text-muted">ยังไม่มีประวัติการเบิก</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="text-center mt-3">
            <a href="user_index.php" class="text-decoration-none text-muted small">กลับหน้าหลัก</a>
        </div>
    </div>
</body>
</html>
