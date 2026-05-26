<?php
define('APP_INIT', true);
include('db_config.php');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php"); exit();
}

$report_type = $_GET['type'] ?? 'monthly';
$year        = (int)($_GET['year']  ?? date('Y'));
$month       = (int)($_GET['month'] ?? date('m'));

// ===== SECURITY: validate inputs =====
if ($year < 2020 || $year > 2099) $year = (int)date('Y');
if ($month < 1 || $month > 12)    $month = (int)date('m');

// ===== Generate CSV for download =====
if (isset($_GET['download']) && isset($_GET['csrf']) && verify_csrf($_GET['csrf'])) {
    $dl_type = $_GET['download'];

    if ($dl_type === 'monthly') {
        $ym_start = sprintf('%04d-%02d-01', $year, $month);
        $ym_end   = date('Y-m-t', strtotime($ym_start));
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=monthly_report_' . sprintf('%04d%02d', $year, $month) . '.csv');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, ['วันที่', 'ผู้เบิก', 'รหัสพัสดุ', 'ชื่อพัสดุ', 'จำนวน', 'หน่วย', 'สถานะ', 'ผู้อนุมัติ']);
        $sql = "SELECT r.req_date, u.full_name, p.product_code, p.product_name,
                       rd.request_qty, p.unit, r.status, a.full_name as admin_name
                FROM requisitions r
                JOIN users u ON r.user_id=u.user_id
                JOIN requisition_details rd ON r.req_id=rd.req_id
                JOIN products p ON rd.product_id=p.product_id
                LEFT JOIN users a ON r.admin_id=a.user_id
                WHERE r.req_date BETWEEN '$ym_start' AND '$ym_end 23:59:59'
                ORDER BY r.req_date DESC";
        $res = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($res)) {
            $status_th = ['pending'=>'รออนุมัติ','approved'=>'อนุมัติแล้ว','rejected'=>'ไม่อนุมัติ'][$row['status']] ?? $row['status'];
            fputcsv($out, [
                date('d/m/Y H:i', strtotime($row['req_date'])),
                $row['full_name'], $row['product_code'], $row['product_name'],
                $row['request_qty'], $row['unit'], $status_th, $row['admin_name'] ?? '-'
            ]);
        }
        fclose($out); exit();

    } elseif ($dl_type === 'annual') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=annual_inventory_' . $year . '.csv');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, ['รหัสพัสดุ', 'ชื่อพัสดุ', 'หมวดหมู่', 'จำนวนคงเหลือ', 'หน่วย',
                       'เบิกทั้งปี (ครั้ง)', 'จำนวนเบิกทั้งหมด (ชิ้น)']);
        $sql = "SELECT p.product_code, p.product_name, p.category, p.quantity, p.unit,
                       COUNT(rd.req_id) as total_reqs,
                       COALESCE(SUM(CASE WHEN r.status='approved' THEN rd.request_qty ELSE 0 END),0) as total_issued
                FROM products p
                LEFT JOIN requisition_details rd ON p.product_id=rd.product_id
                LEFT JOIN requisitions r ON rd.req_id=r.req_id AND YEAR(r.req_date)=$year
                GROUP BY p.product_id
                ORDER BY p.category ASC, p.product_name ASC";
        $res = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($res)) {
            fputcsv($out, [$row['product_code'], $row['product_name'], $row['category'],
                           $row['quantity'], $row['unit'], $row['total_reqs'], $row['total_issued']]);
        }
        fclose($out); exit();
    }
}

// ===== Stats for display =====
$ym_start = sprintf('%04d-%02d-01', $year, $month);
$ym_end   = date('Y-m-t', strtotime($ym_start));

$monthly_stats = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as total,
            SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN status='pending'  THEN 1 ELSE 0 END) as pending
     FROM requisitions
     WHERE req_date BETWEEN '$ym_start' AND '$ym_end 23:59:59'"));

$top_items = mysqli_query($conn,
    "SELECT p.product_name, SUM(rd.request_qty) as total_qty, COUNT(*) as req_count
     FROM requisition_details rd
     JOIN requisitions r ON rd.req_id=r.req_id
     JOIN products p ON rd.product_id=p.product_id
     WHERE r.status='approved' AND YEAR(r.req_date)=$year AND MONTH(r.req_date)=$month
     GROUP BY p.product_id ORDER BY total_qty DESC LIMIT 5");

$monthly_trend = mysqli_query($conn,
    "SELECT MONTH(req_date) as m, COUNT(*) as cnt
     FROM requisitions
     WHERE YEAR(req_date)=$year
     GROUP BY MONTH(req_date) ORDER BY m ASC");
$trend_data = array_fill(1, 12, 0);
while ($tr = mysqli_fetch_assoc($monthly_trend)) $trend_data[(int)$tr['m']] = (int)$tr['cnt'];

$low_stock_list = mysqli_query($conn,
    "SELECT product_code, product_name, quantity, unit FROM products
     WHERE quantity < " . LOW_STOCK_THRESHOLD . " ORDER BY quantity ASC");

$csrf = $_SESSION['csrf_token'];
$months_th = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานระบบพัสดุ - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .main-content { margin-left:16.66%; background:#f8f9fa; padding:30px; min-height:100vh; }
        .stat-card { border:none; border-radius:15px; padding:1.2rem; }
        .report-card { border:none; border-radius:15px; box-shadow:0 2px 15px rgba(0,0,0,.06); }
        .section-title { font-size:1rem; font-weight:600; color:#495057; margin-bottom:1rem; }
        .low-badge { font-size:0.75rem; }
    </style>
</head>
<body class="bg-light">
<div class="container-fluid">
    <div class="row">
        <?php include('admin_sidebar.php'); ?>
        <div class="col-md-10 main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3><i class="bi bi-bar-chart-line me-2 text-primary"></i>ระบบรายงานพัสดุ</h3>
                <span class="text-muted small"><i class="bi bi-clock me-1"></i>อัปเดต: <?php echo date('d/m/Y H:i'); ?></span>
            </div>

            <!-- Filter Row -->
            <div class="card report-card p-3 mb-4">
                <form method="GET" class="row g-3 align-items-end">
                    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">ปี (พ.ศ.)</label>
                        <select name="year" class="form-select">
                            <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                                <option value="<?php echo $y; ?>" <?php echo $y==$year?'selected':''; ?>>
                                    <?php echo $y+543; ?> (<?php echo $y; ?>)
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">เดือน</label>
                        <select name="month" class="form-select">
                            <?php foreach ($months_th as $i => $mn):
                                if ($i === 0) continue; ?>
                                <option value="<?php echo $i; ?>" <?php echo $i==$month?'selected':''; ?>>
                                    <?php echo $mn; ?> (<?php echo sprintf('%02d',$i); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-funnel me-1"></i>กรองข้อมูล
                        </button>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex gap-2">
                            <a href="?download=monthly&year=<?php echo $year; ?>&month=<?php echo $month; ?>&csrf=<?php echo urlencode($csrf); ?>"
                               class="btn btn-outline-success btn-sm flex-fill">
                                <i class="bi bi-file-earmark-excel"></i> รายเดือน
                            </a>
                            <a href="?download=annual&year=<?php echo $year; ?>&csrf=<?php echo urlencode($csrf); ?>"
                               class="btn btn-outline-primary btn-sm flex-fill">
                                <i class="bi bi-file-earmark-excel"></i> รายปี
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="stat-card shadow-sm" style="background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="small mb-1 opacity-75">คำขอทั้งหมด</p>
                                <h2 class="fw-bold mb-0"><?php echo (int)$monthly_stats['total']; ?></h2>
                                <small class="opacity-75"><?php echo $months_th[$month].' '.$year+543; ?></small>
                            </div>
                            <i class="bi bi-file-text fs-2 opacity-50"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card shadow-sm" style="background:linear-gradient(135deg,#11998e,#38ef7d);color:#fff;">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="small mb-1 opacity-75">อนุมัติแล้ว</p>
                                <h2 class="fw-bold mb-0"><?php echo (int)$monthly_stats['approved']; ?></h2>
                                <small class="opacity-75">รายการ</small>
                            </div>
                            <i class="bi bi-check-circle fs-2 opacity-50"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card shadow-sm" style="background:linear-gradient(135deg,#f093fb,#f5576c);color:#fff;">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="small mb-1 opacity-75">ไม่อนุมัติ</p>
                                <h2 class="fw-bold mb-0"><?php echo (int)$monthly_stats['rejected']; ?></h2>
                                <small class="opacity-75">รายการ</small>
                            </div>
                            <i class="bi bi-x-circle fs-2 opacity-50"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card shadow-sm" style="background:linear-gradient(135deg,#fa8231,#f7b731);color:#fff;">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="small mb-1 opacity-75">รออนุมัติ</p>
                                <h2 class="fw-bold mb-0"><?php echo (int)$monthly_stats['pending']; ?></h2>
                                <small class="opacity-75">รายการ</small>
                            </div>
                            <i class="bi bi-hourglass-split fs-2 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <!-- Trend Chart -->
                <div class="col-md-8 mb-3">
                    <div class="card report-card p-4">
                        <p class="section-title"><i class="bi bi-graph-up me-1 text-primary"></i>แนวโน้มการเบิกพัสดุ ปี <?php echo $year+543; ?></p>
                        <canvas id="trendChart" height="100"></canvas>
                    </div>
                </div>
                <!-- Top Items -->
                <div class="col-md-4 mb-3">
                    <div class="card report-card p-4 h-100">
                        <p class="section-title"><i class="bi bi-trophy me-1 text-warning"></i>พัสดุเบิกสูงสุด (เดือนนี้)</p>
                        <?php $rank = 1; while ($item = mysqli_fetch_assoc($top_items)): ?>
                        <div class="d-flex align-items-center mb-3">
                            <span class="badge <?php echo $rank===1?'bg-warning text-dark':($rank===2?'bg-secondary':'bg-light text-dark'); ?> me-2" style="width:28px;height:28px;display:flex;align-items:center;justify-content:center;border-radius:50%;">
                                <?php echo $rank; ?>
                            </span>
                            <div class="flex-grow-1">
                                <div class="fw-semibold small"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                <div class="text-muted" style="font-size:0.75rem;">เบิกแล้ว <?php echo (int)$item['total_qty']; ?> ชิ้น (<?php echo (int)$item['req_count']; ?> ครั้ง)</div>
                            </div>
                        </div>
                        <?php $rank++; endwhile; ?>
                        <?php if ($rank === 1): ?>
                            <p class="text-muted text-center small mt-3">ยังไม่มีข้อมูลเดือนนี้</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Low Stock Alert Table -->
            <?php $lc = mysqli_num_rows($low_stock_list); ?>
            <div class="card report-card p-4 mb-4 <?php echo $lc>0?'border-start border-danger border-3':''; ?>">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <p class="section-title mb-0">
                        <i class="bi bi-exclamation-triangle me-1 <?php echo $lc>0?'text-danger':'text-success'; ?>"></i>
                        รายการพัสดุใกล้หมด (Low Stock &lt; <?php echo LOW_STOCK_THRESHOLD; ?>)
                        <?php if ($lc>0): ?>
                            <span class="badge bg-danger ms-2"><?php echo $lc; ?> รายการ</span>
                        <?php endif; ?>
                    </p>
                    <?php if ($lc>0): ?>
                        <a href="notify_lowstock.php?csrf=<?php echo urlencode($csrf); ?>"
                           class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-bell me-1"></i>แจ้งเตือน LINE ตอนนี้
                        </a>
                    <?php endif; ?>
                </div>
                <?php if ($lc === 0): ?>
                    <p class="text-success mb-0"><i class="bi bi-check-circle-fill me-1"></i>สต็อกพัสดุทุกรายการอยู่ในระดับปกติ</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr><th>รหัส</th><th>ชื่อพัสดุ</th><th class="text-center">คงเหลือ</th><th>หน่วย</th><th>สถานะ</th></tr>
                            </thead>
                            <tbody>
                            <?php while ($ls = mysqli_fetch_assoc($low_stock_list)): ?>
                                <tr class="<?php echo $ls['quantity']==0?'table-danger':'table-warning'; ?>">
                                    <td><code><?php echo htmlspecialchars($ls['product_code']); ?></code></td>
                                    <td><?php echo htmlspecialchars($ls['product_name']); ?></td>
                                    <td class="text-center fw-bold"><?php echo (int)$ls['quantity']; ?></td>
                                    <td><?php echo htmlspecialchars($ls['unit']); ?></td>
                                    <td>
                                        <?php if ($ls['quantity']==0): ?>
                                            <span class="badge bg-danger low-badge">หมดแล้ว</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark low-badge">ใกล้หมด</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Monthly Detail Table -->
            <div class="card report-card p-4">
                <p class="section-title">
                    <i class="bi bi-table me-1 text-primary"></i>รายละเอียดการเบิก — <?php echo $months_th[$month].' '.($year+543); ?>
                </p>
                <div class="table-responsive">
                    <table class="table table-hover align-middle table-sm">
                        <thead class="table-light">
                            <tr><th>วันที่</th><th>ผู้เบิก</th><th>พัสดุ</th><th class="text-center">จำนวน</th><th>สถานะ</th><th>ผู้อนุมัติ</th></tr>
                        </thead>
                        <tbody>
                        <?php
                        $sql2 = "SELECT r.req_date, u.full_name, p.product_name, rd.request_qty,
                                        r.status, a.full_name as admin_name
                                 FROM requisitions r
                                 JOIN users u ON r.user_id=u.user_id
                                 JOIN requisition_details rd ON r.req_id=rd.req_id
                                 JOIN products p ON rd.product_id=p.product_id
                                 LEFT JOIN users a ON r.admin_id=a.user_id
                                 WHERE r.req_date BETWEEN '$ym_start' AND '$ym_end 23:59:59'
                                 ORDER BY r.req_date DESC";
                        $res2 = mysqli_query($conn, $sql2);
                        $detail_count = mysqli_num_rows($res2);
                        while ($row2 = mysqli_fetch_assoc($res2)):
                            $badge = ['pending'=>'bg-warning text-dark','approved'=>'bg-success','rejected'=>'bg-danger'][$row2['status']] ?? 'bg-secondary';
                            $label = ['pending'=>'รออนุมัติ','approved'=>'อนุมัติแล้ว','rejected'=>'ไม่อนุมัติ'][$row2['status']] ?? '-';
                        ?>
                        <tr>
                            <td><small><?php echo date('d/m/y H:i', strtotime($row2['req_date'])); ?></small></td>
                            <td><?php echo htmlspecialchars($row2['full_name']); ?></td>
                            <td class="fw-semibold"><?php echo htmlspecialchars($row2['product_name']); ?></td>
                            <td class="text-center"><?php echo (int)$row2['request_qty']; ?></td>
                            <td><span class="badge <?php echo $badge; ?>"><?php echo $label; ?></span></td>
                            <td><small class="text-muted"><?php echo $row2['admin_name'] ?? '-'; ?></small></td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if ($detail_count === 0): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">ไม่มีรายการในเดือนนี้</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const trendCtx = document.getElementById('trendChart');
new Chart(trendCtx, {
    type: 'bar',
    data: {
        labels: ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'],
        datasets: [{
            label: 'จำนวนคำขอเบิก',
            data: <?php echo json_encode(array_values($trend_data)); ?>,
            backgroundColor: 'rgba(99,102,241,0.7)',
            borderColor: 'rgba(99,102,241,1)',
            borderWidth: 2,
            borderRadius: 8,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 } }
        }
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>