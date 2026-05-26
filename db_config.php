<?php
// 1. แก้ไขการป้องกันการเข้าถึงไฟล์โดยตรง (ต้องนิยาม APP_INIT จากไฟล์ index หรือไฟล์หลัก)
if (!defined('APP_INIT')) {
    header("HTTP/1.1 403 Forbidden");
    exit("Direct access not allowed.");
}

$host = "localhost";
$user = "root";
$pass = "";
$db   = "inventory_db";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    error_log("DB Connection failed: " . mysqli_connect_error());
    die("ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาติดต่อผู้ดูแลระบบ");
}

mysqli_set_charset($conn, "utf8mb4");

// 2. ปรับปรุง Session Security
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    // ตรวจสอบอัตโนมัติว่าเป็น HTTPS หรือไม่
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
    ini_set('session.cookie_secure', $isSecure ? 1 : 0);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Session Timeout
define('SESSION_TIMEOUT', 1800);
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit();
}
$_SESSION['last_activity'] = time();

// Regenerate Session ID
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} elseif (time() - $_SESSION['created'] > 900) {
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

// Helpers
function verify_csrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function safe_redirect($url) {
    $allowed = ['login.php','admin_inventory.php','admin_dashboard.php',
                'admin_approval.php','admin_history.php','user_index.php','user_history.php'];
    $path = parse_url($url, PHP_URL_PATH);
    $filename = basename($path);
    
    if (in_array($filename, $allowed)) {
        header("Location: $url");
    } else {
        header("Location: login.php");
    }
    exit();
}

// LINE NOTIFY
define('LINE_NOTIFY_TOKEN', 'YOUR_LINE_NOTIFY_TOKEN_HERE'); 
define('LOW_STOCK_THRESHOLD', 5);

function send_line_notify($message) {
    $token = LINE_NOTIFY_TOKEN;
    if ($token === 'YOUR_LINE_NOTIFY_TOKEN_HERE') return false;
    
    $ch = curl_init('https://notify-api.line.me/api/notify');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer $token"],
        CURLOPT_POSTFIELDS     => http_build_query(['message' => $message]),
        CURLOPT_TIMEOUT        => 10,
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

// 3. ปรับปรุงฟังก์ชันแจ้งเตือน (ใช้ Prepared Statement)
function check_low_stock_notify($conn, $product_id = null) {
    if ($product_id) {
        $stmt = mysqli_prepare($conn, "SELECT product_name, product_code, quantity, unit FROM products WHERE product_id = ? AND quantity < ?");
        $threshold = LOW_STOCK_THRESHOLD;
        mysqli_stmt_bind_param($stmt, "ii", $product_id, $threshold);
    } else {
        $stmt = mysqli_prepare($conn, "SELECT product_name, product_code, quantity, unit FROM products WHERE quantity < ? AND quantity >= 0");
        $threshold = LOW_STOCK_THRESHOLD;
        mysqli_stmt_bind_param($stmt, "i", $threshold);
    }
    
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($res) === 0) return;
    
    $msg = "\n🚨 [แจ้งเตือน Low Stock] ระบบพัสดุ RMUTSB\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
    while ($item = mysqli_fetch_assoc($res)) {
        $emoji = $item['quantity'] == 0 ? "🔴" : "🟠";
        $msg .= "$emoji {$item['product_name']} ({$item['product_code']})\n";
        $msg .= "   คงเหลือ: {$item['quantity']} {$item['unit']}\n";
    }
    $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "📅 " . date('d/m/Y H:i') . "\n";
    $msg .= "🔗 กรุณาตรวจสอบในระบบ";
    
    send_line_notify($msg);
}
?>