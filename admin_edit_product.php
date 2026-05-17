<?php 
include('db_config.php'); 
if($_SESSION['role'] != 'admin') header("location: login.php");

// [BUG FIX] $id ต้อง cast เป็น int เสมอ เดิมไม่ได้ escape ค่าจาก GET
$id = (int)$_GET['id'];
if($id <= 0) { header("location: admin_inventory.php"); exit(); }

$res  = mysqli_query($conn, "SELECT * FROM products WHERE product_id = '$id'");
$data = mysqli_fetch_array($res);
if(!$data) { header("location: admin_inventory.php"); exit(); } // [BUG FIX] ถ้าไม่เจอข้อมูลให้เด้งกลับ

if(isset($_POST['update_product'])){
    // [BUG FIX] escape ทุก input (เดิมไม่มีการ escape เลย)
    $name = mysqli_real_escape_string($conn, trim($_POST['p_name']));
    $cat  = mysqli_real_escape_string($conn, $_POST['p_cat']);
    $qty  = (int)$_POST['p_qty'];
    $unit = mysqli_real_escape_string($conn, trim($_POST['p_unit']));
    $image_name = $data['image'];

    if(isset($_FILES['p_image']) && $_FILES['p_image']['error'] == 0){
        // [BUG FIX] ตรวจสอบนามสกุลไฟล์
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($_FILES['p_image']['name'], PATHINFO_EXTENSION));
        if(in_array($ext, $allowed_ext)){
            $image_name = "prod_" . time() . "." . $ext;
            move_uploaded_file($_FILES['p_image']['tmp_name'], "uploads/" . $image_name);
        }
    }

    $sql = "UPDATE products SET 
            product_name='$name', category='$cat', 
            quantity='$qty', unit='$unit', image='$image_name' 
            WHERE product_id='$id'";
    
    if(mysqli_query($conn, $sql)){
        echo "<script>alert('อัปเดตข้อมูลสำเร็จ'); window.location='admin_inventory.php';</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แก้ไขพัสดุ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm p-4" style="border-radius: 15px;">
                    <h4 class="mb-4">แก้ไขข้อมูลพัสดุ: <?php echo htmlspecialchars($data['product_code']); ?></h4>
                    <form action="" method="POST" enctype="multipart/form-data">
                        <div class="mb-3 text-center">
                            <?php if($data['image']): ?>
                                <img src="uploads/<?php echo htmlspecialchars($data['image']); ?>" width="150" class="img-thumbnail mb-2">
                            <?php endif; ?>
                            <input type="file" name="p_image" class="form-control" accept="image/*">
                            <small class="text-muted">เว้นว่างไว้หากไม่ต้องการเปลี่ยนรูป</small>
                        </div>
                        <div class="mb-3">
                            <label>ชื่อพัสดุ</label>
                            <input type="text" name="p_name" class="form-control" value="<?php echo htmlspecialchars($data['product_name']); ?>" required>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label>หมวดหมู่</label>
                                <select name="p_cat" class="form-select">
                                    <option value="เครื่องเขียน" <?php if($data['category']=='เครื่องเขียน') echo 'selected'; ?>>เครื่องเขียน</option>
                                    <option value="อุปกรณ์คอมพิวเตอร์" <?php if($data['category']=='อุปกรณ์คอมพิวเตอร์') echo 'selected'; ?>>อุปกรณ์คอมพิวเตอร์</option>
                                    <option value="วัสดุสำนักงาน" <?php if($data['category']=='วัสดุสำนักงาน') echo 'selected'; ?>>วัสดุสำนักงาน</option>
                                </select>
                            </div>
                            <div class="col-3 mb-3">
                                <label>จำนวน</label>
                                <input type="number" name="p_qty" class="form-control" min="0" value="<?php echo (int)$data['quantity']; ?>">
                            </div>
                            <div class="col-3 mb-3">
                                <label>หน่วย</label>
                                <input type="text" name="p_unit" class="form-control" value="<?php echo htmlspecialchars($data['unit']); ?>">
                            </div>
                        </div>
                        <button type="submit" name="update_product" class="btn btn-primary w-100">บันทึกการแก้ไข</button>
                        <a href="admin_inventory.php" class="btn btn-link w-100 mt-2 text-decoration-none text-muted">ยกเลิก</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
