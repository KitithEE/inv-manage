<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "inventory_db";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// ตั้งค่าภาษาไทย
mysqli_set_charset($conn, "utf8mb4");
// เช็คก่อนว่ามี Session เปิดอยู่แล้วหรือยัง ถ้ายังไม่มีถึงจะเปิดใหม่
if (session_status() === PHP_SESSION_NONE) {
    session_start();
} // เริ่มต้น Session สำหรับระบบสมาชิก
?>