<?php include('db_config.php');
if (!isset($_SESSION['user_id'])) { header("location: login.php"); exit(); }

// [BUG FIX] ช่องค้นหาเดิมเป็นแค่ HTML ไม่มี PHP logic รองรับ → เพิ่ม search filter
$search = isset($_GET['q']) ? mysqli_real_escape_string($conn, trim($_GET['q'])) : '';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เบิกพัสดุออนไลน์</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .navbar { background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .product-card { border: none; border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); transition: 0.3s; }
        .product-card:hover { transform: scale(1.02); }
        .badge-qty { position: absolute; top: 10px; right: 10px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold text-primary" href="#">RMUTSB INVENTORY</a>
            <div class="ms-auto">
                <span class="me-3">สวัสดี, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                <a href="logout.php" class="btn btn-outline-danger btn-sm">ออกจากระบบ</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- [BUG FIX] เปลี่ยน input ธรรมดาเป็น form GET จริง ให้ search ทำงานได้ -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h3>รายการพัสดุพร้อมเบิก
                    <?php if($search): ?>
                        <small class="text-muted fs-6">: ผลการค้นหา "<?php echo htmlspecialchars($search); ?>"</small>
                    <?php endif; ?>
                </h3>
            </div>
            <div class="col-md-4">
                <form method="GET" action="">
                    <div class="input-group">
                        <input type="text" name="q" class="form-control" 
                               placeholder="ค้นหาชื่อพัสดุ..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-outline-primary"><i class="bi bi-search"></i></button>
                        <?php if($search): ?>
                            <a href="user_index.php" class="btn btn-outline-secondary"><i class="bi bi-x"></i></a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="row">
            <?php
            // [BUG FIX] SQL รองรับ search filter ด้วย
            if($search != ''){
                $sql = "SELECT * FROM products WHERE quantity > 0 AND (product_name LIKE '%$search%' OR category LIKE '%$search%' OR product_code LIKE '%$search%')";
            } else {
                $sql = "SELECT * FROM products WHERE quantity > 0";
            }
            $res = mysqli_query($conn, $sql);
            $count = mysqli_num_rows($res);
            
            if($count == 0): ?>
                <div class="col-12 text-center py-5 text-muted">
                    <i class="bi bi-search fs-1 d-block mb-2"></i>
                    ไม่พบพัสดุที่ค้นหา "<?php echo htmlspecialchars($search); ?>"
                    <br><a href="user_index.php" class="btn btn-outline-primary btn-sm mt-3">ดูทั้งหมด</a>
                </div>
            <?php else:
                while ($row = mysqli_fetch_array($res)) { ?>
                <div class="col-md-3 mb-4">
                    <div class="card product-card h-100 p-3 position-relative">
                        <span class="badge bg-success badge-qty">คงเหลือ <?php echo (int)$row['quantity']; ?></span>
                        <div class="text-center my-3">
                            <?php if($row['image'] != ""): ?>
                                <img src="uploads/<?php echo htmlspecialchars($row['image']); ?>" style="width:80px;height:80px;object-fit:cover;border-radius:10px;">
                            <?php else: ?>
                                <i class="bi bi-box-seam text-primary" style="font-size: 3rem;"></i>
                            <?php endif; ?>
                        </div>
                        <div class="card-body p-0">
                            <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($row['product_name']); ?></h6>
                            <p class="text-muted small mb-3"><?php echo htmlspecialchars($row['category']); ?></p>
                            <a href="user_checkout.php?id=<?php echo $row['product_id']; ?>" class="btn btn-primary w-100">
                                <i class="bi bi-cart-plus me-1"></i> ทำรายการเบิก
                            </a>
                        </div>
                    </div>
                </div>
            <?php } endif; ?>
        </div>
    </div>

    <script src="https://unpkg.com/html5-qrcode"></script>
    <div class="container mt-4">
        <div class="row">
            <div class="col-12 mb-4">
                <div class="card border-0 shadow-sm p-3 text-center" style="border-radius: 15px; background: linear-gradient(45deg, #0d6efd, #0099ff); color: white;">
                    <h4><i class="bi bi-qr-code-scan me-2"></i> สแกนเพื่อเบิกพัสดุ</h4>
                    <p class="small">สแกน QR Code ที่ติดอยู่บนตัวพัสดุเพื่อทำรายการทันที</p>
                    <button class="btn btn-light fw-bold" onclick="startScan()">เปิดกล้องสแกน</button>
                    <div id="reader-container" style="display:none;" class="mt-3">
                        <div id="reader" style="width: 100%; max-width: 400px; margin: auto; border-radius: 10px; overflow: hidden;"></div>
                        <button class="btn btn-danger btn-sm mt-2" onclick="stopScan()">ปิดกล้อง</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container mb-4">
        <div class="row g-2">
            <div class="col-6">
                <a href="user_history.php" class="btn btn-outline-primary w-100 py-3 shadow-sm" style="border-radius: 12px; border: 2px dashed;">
                    <i class="bi bi-clock-history d-block fs-4 mb-1"></i>
                    ประวัติการเบิก
                </a>
            </div>
            <div class="col-6">
                <a href="#" class="btn btn-outline-secondary w-100 py-3 shadow-sm" style="border-radius: 12px; border: 2px dashed;" onclick="alert('ติดต่อเจ้าหน้าที่พัสดุ โทร. 0XX-XXXXXXX')">
                    <i class="bi bi-info-circle d-block fs-4 mb-1"></i>
                    ช่วยเหลือ
                </a>
            </div>
        </div>
    </div>

    <script>
        let html5QrCode;
        function startScan() {
            document.getElementById('reader-container').style.display = 'block';
            html5QrCode = new Html5Qrcode("reader");
            const config = { fps: 10, qrbox: { width: 250, height: 250 } };
            html5QrCode.start({ facingMode: "environment" }, config, (decodedText) => {
                window.location.href = decodedText;
                stopScan();
            });
        }
        function stopScan() {
            if (html5QrCode) {
                html5QrCode.stop().then(() => {
                    document.getElementById('reader-container').style.display = 'none';
                });
            }
        }
    </script>
</body>
</html>
