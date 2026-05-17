<?php 
include('db_config.php'); 
if(!isset($_SESSION['user_id'])) { header("location: login.php"); exit(); }

// [BUG FIX] cast $id เป็น int ป้องกัน SQL Injection (เดิมรับค่า GET โดยตรงไม่มี sanitize)
$id = (int)$_GET['id'];
if($id <= 0) { header("location: user_index.php"); exit(); }

$p_res = mysqli_query($conn, "SELECT * FROM products WHERE product_id = '$id'");
$p = mysqli_fetch_array($p_res);
if(!$p) { header("location: user_index.php"); exit(); } // [BUG FIX] ถ้าไม่เจอพัสดุให้เด้งกลับ

if(isset($_POST['submit_req'])){
    // [BUG FIX] validate req_qty เป็น int ป้องกันค่าไม่ถูกต้อง (เดิมรับค่า POST โดยตรง)
    $req_qty = (int)$_POST['req_qty'];
    $u_id    = (int)$_SESSION['user_id'];

    if($req_qty <= 0){
        header("location: user_checkout.php?id=$id&err=invalid");
        exit();
    } else if($p['quantity'] <= 0){
        header("location: user_checkout.php?id=$id&err=empty");
        exit();
    } else if($req_qty > $p['quantity']){
        header("location: user_checkout.php?id=$id&err=over");
        exit();
    } else {
        mysqli_query($conn, "INSERT INTO requisitions (user_id, status, req_date) VALUES ('$u_id', 'pending', NOW())");
        $req_id = mysqli_insert_id($conn);
        mysqli_query($conn, "INSERT INTO requisition_details (req_id, product_id, request_qty) VALUES ('$req_id', '$id', '$req_qty')");
        
        echo "<script>alert('ส่งคำขอเบิกเรียบร้อยแล้ว รอการอนุมัติ'); window.location='user_history.php';</script>";
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8"><title>ทำรายการเบิก</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="card mx-auto shadow-sm border-0" style="max-width: 500px; border-radius: 15px;">
            <div class="card-body p-4 text-center">
                
                <?php if(isset($_GET['err'])): ?>
                    <div class="alert alert-danger d-flex align-items-center mb-3 text-start shadow-sm" role="alert" style="border-radius: 10px;">
                        <i class="bi bi-exclamation-triangle-fill me-3 fs-3"></i>
                        <div>
                            <?php 
                                if($_GET['err'] == 'over')    echo "<strong>ข้อผิดพลาด!</strong><br>จำนวนที่ระบุเกินจำนวนพัสดุที่มีในคลัง";
                                if($_GET['err'] == 'empty')   echo "<strong>ขออภัย!</strong><br>พัสดุนี้หมดชั่วคราว ไม่สามารถทำรายการได้";
                                if($_GET['err'] == 'invalid') echo "<strong>ข้อผิดพลาด!</strong><br>จำนวนที่ระบุต้องมากกว่า 0";
                            ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if($p['image'] != "") { ?>
                    <img src="uploads/<?php echo htmlspecialchars($p['image']); ?>" width="150" class="rounded mb-3 shadow-sm" style="object-fit: cover;">
                <?php } else { ?>
                    <div class="bg-secondary text-white rounded d-flex align-items-center justify-content-center mx-auto mb-3" style="width:150px; height:150px;">No Image</div>
                <?php } ?>
                
                <h4 class="fw-bold"><?php echo htmlspecialchars($p['product_name']); ?></h4>
                <p class="text-muted mb-4">คงเหลือในคลัง: <span class="text-primary fw-bold fs-5"><?php echo (int)$p['quantity']; ?></span> <?php echo htmlspecialchars($p['unit']); ?></p>
                
                <form method="POST">
                    <div class="mb-4 text-start">
                        <label class="form-label fw-bold">ระบุจำนวนที่ต้องการเบิก</label>
                        <input type="number" name="req_qty" class="form-control form-control-lg text-center" 
                               min="1" max="<?php echo (int)$p['quantity']; ?>" value="1" required 
                               <?php if($p['quantity'] <= 0) echo 'disabled'; ?>>
                    </div>
                    
                    <button type="submit" name="submit_req" class="btn btn-primary btn-lg w-100 shadow-sm" 
                            <?php if($p['quantity'] <= 0) echo 'disabled'; ?>>
                        <i class="bi bi-cart-check"></i> ยืนยันการทำรายการเบิก
                    </button>
                    <a href="user_index.php" class="btn btn-light w-100 mt-2">ยกเลิกและกลับหน้าหลัก</a>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
