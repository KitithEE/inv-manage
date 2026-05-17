<?php 
include('db_config.php'); 

// ถ้าล็อกอินอยู่แล้ว ให้เด้งไปหน้าหลักเลย
if(isset($_SESSION['user_id'])){
    if($_SESSION['role'] == 'admin'){
        header("location: admin_inventory.php");
    } else {
        header("location: user_index.php");
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เข้าสู่ระบบ - ระบบบริหารพัสดุ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f7f6; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card { width: 400px; border: none; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .btn-primary { background: #0d6efd; border: none; border-radius: 8px; padding: 12px; }
    </style>
</head>
<body>
    <div class="card login-card p-4">
        <div class="text-center mb-4">
            <h4 class="fw-bold text-primary">RMUTSB Inventory</h4>
            <p class="text-muted">กรุณาเข้าสู่ระบบเพื่อใช้งาน</p>
        </div>
        <form action="" method="POST">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" placeholder="ชื่อผู้ใช้งาน" required>
            </div>
            <div class="mb-4">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" placeholder="รหัสผ่าน" required>
            </div>
            <button type="submit" name="login" class="btn btn-primary w-100">เข้าสู่ระบบ</button>
        </form>

        <?php
        if(isset($_POST['login'])){
            // [BUG FIX] เพิ่ม mysqli_real_escape_string ป้องกัน SQL Injection
            $u = mysqli_real_escape_string($conn, $_POST['username']);
            $p = mysqli_real_escape_string($conn, $_POST['password']);

            $sql = "SELECT * FROM users WHERE username='$u' AND password='$p' AND status='active'";
            $res = mysqli_query($conn, $sql);
            if(mysqli_num_rows($res) > 0){
                $row = mysqli_fetch_array($res);
                $_SESSION['user_id']   = $row['user_id'];
                $_SESSION['role']      = $row['role'];
                $_SESSION['full_name'] = $row['full_name'];
                
                if($row['role'] == 'admin'){
                    header("location: admin_inventory.php");
                } else {
                    header("location: user_index.php");
                }
                exit();
            } else {
                echo "<div class='alert alert-danger mt-3 text-center'>ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง!</div>";
            }
        }
        ?>
    </div>
</body>
</html>
