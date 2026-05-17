<?php
include('db_config.php');
if($_SESSION['role'] != 'admin') exit;

// ตั้งค่า Header ให้ Browser รู้ว่าเป็นไฟล์ Excel/CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=annual_inventory_report_' . date('Y') . '.csv');

// ใส่ BOM เพื่อให้ Excel อ่านภาษาไทยออก
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// หัวตารางใน Excel
fputcsv($output, array('รหัสพัสดุ', 'ชื่อพัสดุ', 'หมวดหมู่', 'จำนวนคงเหลือ', 'หน่วยนับ'));

// ดึงข้อมูลพัสดุทั้งหมด
$res = mysqli_query($conn, "SELECT product_code, product_name, category, quantity, unit FROM products ORDER BY category ASC");
while($row = mysqli_fetch_assoc($res)){
    fputcsv($output, $row);
}
fclose($output);
exit();
?>