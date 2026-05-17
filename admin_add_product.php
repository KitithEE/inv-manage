<?php 
include('db_config.php'); 
if($_SESSION['role'] != 'admin') header("location: login.php");

if(isset($_POST['add_product'])){
    // [BUG FIX] escape ทุก input ป้องกัน SQL Injection (เดิม $cat, $qty, $unit ไม่ได้ escape)
    $code = mysqli_real_escape_string($conn, trim($_POST['p_code']));
    $name = mysqli_real_escape_string($conn, trim($_POST['p_name']));
    $cat  = mysqli_real_escape_string($conn, $_POST['p_cat']);
    $qty  = (int)$_POST['p_qty'];   // [BUG FIX] cast เป็น int เสมอ
    $unit = mysqli_real_escape_string($conn, trim($_POST['p_unit']));

    // ตรวจสอบรหัสพัสดุซ้ำ
    $check_duplicate = mysqli_query($conn, "SELECT product_code FROM products WHERE product_code = '$code'");
    if(mysqli_num_rows($check_duplicate) > 0){
        echo "<script>alert('ผิดพลาด: รหัสพัสดุ [$code] นี้มีอยู่ในระบบแล้ว กรุณาใช้รหัสอื่น'); window.history.back();</script>";
        exit();
    }

    $image_name = ""; 
    if(isset($_FILES['p_image']) && $_FILES['p_image']['error'] == 0){
        // [BUG FIX] ตรวจสอบนามสกุลไฟล์รูปให้ปลอดภัย
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($_FILES['p_image']['name'], PATHINFO_EXTENSION));
        if(!in_array($ext, $allowed_ext)){
            echo "<script>alert('ไฟล์รูปภาพต้องเป็น jpg, jpeg, png, gif หรือ webp เท่านั้น'); window.history.back();</script>";
            exit();
        }
        $image_name = "prod_" . time() . "." . $ext;
        move_uploaded_file($_FILES['p_image']['tmp_name'], "uploads/" . $image_name);
    }

    $sql = "INSERT INTO products (product_code, product_name, category, quantity, unit, image) 
            VALUES ('$code', '$name', '$cat', '$qty', '$unit', '$image_name')";
    
    if(mysqli_query($conn, $sql)){
        echo "<script>alert('เพิ่มพัสดุใหม่สำเร็จ!'); window.location='admin_inventory.php';</script>";
    } else {
        echo "<script>alert('เกิดข้อผิดพลาด: " . mysqli_error($conn) . "'); window.history.back();</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8"><title>เพิ่มพัสดุใหม่</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="card mx-auto shadow-sm border-0" style="max-width: 600px; border-radius: 15px;">
            <div class="card-body p-4">
                <h4 class="fw-bold mb-4">เพิ่มรายการพัสดุใหม่</h4>
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">รูปภาพ</label>
                        <input type="file" name="p_image" class="form-control" accept="image/*" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">รหัสพัสดุ</label>
                        <input type="text" name="p_code" class="form-control" placeholder="เช่น BT-001" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ชื่อพัสดุ</label>
                        <input type="text" name="p_name" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">หมวดหมู่</label>
                            <select name="p_cat" class="form-select">
                                <option value="เครื่องเขียน">เครื่องเขียน</option>
                                <option value="อุปกรณ์คอมพิวเตอร์">อุปกรณ์คอมพิวเตอร์</option>
                                <option value="วัสดุสำนักงาน">วัสดุสำนักงาน</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">จำนวน</label>
                            <input type="number" name="p_qty" class="form-control" value="0" min="0" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">หน่วย</label>
                            <input type="text" name="p_unit" class="form-control" placeholder="ชิ้น/อัน" required>
                        </div>
                    </div>
                    <button type="submit" name="add_product" class="btn btn-primary w-100 py-2 mt-3">บันทึกข้อมูล</button>
                    <a href="admin_inventory.php" class="btn btn-light w-100 mt-2">ยกเลิก</a>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
