<?php
// ===== SECURITY: ป้องกันการเข้าถึงไฟล์โดยตรง =====
defined('APP_INIT') or define('APP_INIT', true);

$host = "localhost";
$user = "root";
$pass = "";
$db   = "inventory_db";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    // ไม่แสดง error detail ใน production
    error_log("DB Connection failed: " . mysqli_connect_error());
    die("ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาติดต่อผู้ดูแลระบบ");
}

mysqli_set_charset($conn, "utf8mb4");

if (session_status() === PHP_SESSION_NONE) {
    // ===== SECURITY: Secure session settings =====
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0); // เปลี่ยนเป็น 1 เมื่อใช้ HTTPS
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

// ===== SECURITY: CSRF Token Generator =====
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ===== SECURITY: Session timeout (30 นาที) =====
define('SESSION_TIMEOUT', 1800);
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit();
}
$_SESSION['last_activity'] = time();

// ===== SECURITY: Regenerate session ID เป็นระยะ =====
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} elseif (time() - $_SESSION['created'] > 900) { // ทุก 15 นาที
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

// ===== Helper: CSRF Verify =====
function verify_csrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ===== Helper: Safe redirect =====
function safe_redirect($url) {
    $allowed = ['login.php','admin_inventory.php','admin_dashboard.php',
                'admin_approval.php','admin_history.php','user_index.php','user_history.php'];
    if (in_array(basename(parse_url($url, PHP_URL_PATH)), $allowed)) {
        header("Location: $url");
    } else {
        header("Location: login.php");
    }
    exit();
}

// ===== LINE NOTIFY CONFIG =====
define('LINE_NOTIFY_TOKEN', 'YOUR_LINE_NOTIFY_TOKEN_HERE'); // ใส่ token ของคุณ
define('LOW_STOCK_THRESHOLD', 5);   // แจ้งเตือนเมื่อเหลือน้อยกว่านี้
define('ADMIN_EMAIL', 'admin@yourdomain.com'); // Email admin

// ===== Helper: Send LINE Notify =====
function send_line_notify($message) {
    $token = LINE_NOTIFY_TOKEN;
    if ($token === 'YOUR_LINE_NOTIFY_TOKEN_HERE') return false; // ยังไม่ได้ตั้งค่า
    
    $ch = curl_init('https://notify-api.line.me/api/notify');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer $token"],
        CURLOPT_POSTFIELDS     => http_build_query(['message' => $message]),
        CURLOPT_TIMEOUT        => 10,
    ]);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $httpCode === 200;
}

// ===== Helper: Check & Notify Low Stock =====
function check_low_stock_notify($conn, $product_id = null) {
    if ($product_id) {
        $pid = (int)$product_id;
        $sql = "SELECT product_name, product_code, quantity, unit FROM products 
                WHERE product_id = $pid AND quantity < " . LOW_STOCK_THRESHOLD;
    } else {
        $sql = "SELECT product_name, product_code, quantity, unit FROM products 
                WHERE quantity < " . LOW_STOCK_THRESHOLD . " AND quantity >= 0";
    }
    
    $res = mysqli_query($conn, $sql);
    if (!$res || mysqli_num_rows($res) === 0) return;
    
    $items = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $items[] = $row;
    }
    
    if (empty($items)) return;
    
    $msg = "\n🚨 [แจ้งเตือน Low Stock] ระบบพัสดุ RMUTSB\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
    foreach ($items as $item) {
        $emoji = $item['quantity'] == 0 ? "🔴" : "🟠";
        $msg .= "$emoji {$item['product_name']} ({$item['product_code']})\n";
        $msg .= "   คงเหลือ: {$item['quantity']} {$item['unit']}\n";
    }
    $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "📅 " . date('d/m/Y H:i') . "\n";
    $msg .= "🔗 กรุณาเติมสต็อกพัสดุด่วน!";
    
    send_line_notify($msg);
}
?>