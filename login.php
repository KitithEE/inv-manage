<?php
define('APP_INIT', true);
include('db_config.php');

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'admin') {
        header("Location: admin_dashboard.php"); exit();
    } else {
        header("Location: user_index.php"); exit();
    }
}

$error = '';
$timeout = isset($_GET['timeout']) ? true : false;

// ===== SECURITY: Brute Force Protection =====
if (!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = 0;
if (!isset($_SESSION['login_lockout']))  $_SESSION['login_lockout']  = 0;

$lockout_duration = 300; // 5 นาที
$max_attempts     = 5;
$locked_out       = (time() < $_SESSION['login_lockout']);

if (isset($_POST['login'])) {
    // CSRF check
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'คำขอไม่ถูกต้อง กรุณาลองใหม่';
    } elseif ($locked_out) {
        $remain = ceil(($_SESSION['login_lockout'] - time()) / 60);
        $error = "บัญชีถูกล็อกชั่วคราว กรุณารอ {$remain} นาที";
    } else {
        $u = mysqli_real_escape_string($conn, trim($_POST['username']));
        $p = $_POST['password']; // จะ verify กับ password_hash

        // ===== SECURITY: ใช้ password_hash/verify แทน plaintext =====
        // NOTE: ถ้า DB ยังเก็บ plaintext ชั่วคราวให้ใช้ direct compare ก่อน
        $sql = "SELECT * FROM users WHERE username='$u' AND status='active' LIMIT 1";
        $res = mysqli_query($conn, $sql);

        if ($res && mysqli_num_rows($res) > 0) {
            $row = mysqli_fetch_assoc($res);
            // รองรับทั้ง password_hash และ plaintext (migration path)
            $pass_ok = password_verify($p, $row['password']) || ($row['password'] === $p);

            if ($pass_ok) {
                // อัปเกรด hash ถ้ายังเป็น plaintext
                if ($row['password'] === $p) {
                    $hashed = password_hash($p, PASSWORD_DEFAULT);
                    $uid_esc = (int)$row['user_id'];
                    mysqli_query($conn, "UPDATE users SET password='$hashed' WHERE user_id=$uid_esc");
                }

                $_SESSION['login_attempts'] = 0;
                $_SESSION['user_id']   = $row['user_id'];
                $_SESSION['role']      = $row['role'];
                $_SESSION['full_name'] = $row['full_name'];
                $_SESSION['created']   = time();
                session_regenerate_id(true);

                // Log login
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $uid_log = (int)$row['user_id'];
                mysqli_query($conn, "INSERT INTO activity_logs (user_id, action, ip_address, created_at) 
                                     VALUES ($uid_log, 'login', '$ip', NOW())
                                     ON DUPLICATE KEY UPDATE created_at=NOW()");

                if ($row['role'] == 'admin') {
                    header("Location: admin_dashboard.php");
                } else {
                    header("Location: user_index.php");
                }
                exit();
            }
        }

        // Failed
        $_SESSION['login_attempts']++;
        if ($_SESSION['login_attempts'] >= $max_attempts) {
            $_SESSION['login_lockout'] = time() + $lockout_duration;
            $error = "พยายามล็อกอินผิดเกินกำหนด บัญชีถูกล็อก 5 นาที";
        } else {
            $remain_try = $max_attempts - $_SESSION['login_attempts'];
            $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง (เหลืออีก {$remain_try} ครั้ง)";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - ระบบบริหารพัสดุ RMUTSB</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Sarabun', sans-serif;
        }
        .login-wrapper { width: 100%; max-width: 420px; }
        .login-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, #1a73e8, #0d47a1);
            padding: 2rem;
            text-align: center;
            color: white;
        }
        .login-body { padding: 2rem; }
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: 0.3s;
        }
        .form-control:focus {
            border-color: #1a73e8;
            box-shadow: 0 0 0 0.2rem rgba(26,115,232,.15);
        }
        .btn-login {
            background: linear-gradient(135deg, #1a73e8, #0d47a1);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: 0.3s;
        }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(26,115,232,0.4); }
        .input-group-text { border-radius: 10px 0 0 10px; background: #f8f9fa; border: 2px solid #e9ecef; }
        .attempts-bar { height: 4px; background: #e9ecef; border-radius: 2px; margin-top: 8px; }
        .attempts-fill { height: 100%; border-radius: 2px; transition: 0.3s; }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="card login-card">
            <div class="login-header">
                <i class="bi bi-boxes fs-1 mb-2 d-block"></i>
                <h4 class="fw-bold mb-1">RMUTSB Inventory</h4>
                <p class="mb-0 opacity-75 small">ระบบบริหารจัดการพัสดุ</p>
            </div>
            <div class="login-body">
                <?php if ($timeout): ?>
                    <div class="alert alert-warning d-flex align-items-center" role="alert">
                        <i class="bi bi-clock-history me-2"></i>
                        หมดเวลาการใช้งาน กรุณาเข้าสู่ระบบใหม่
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                    <?php if ($_SESSION['login_attempts'] > 0 && !$locked_out): ?>
                        <div class="attempts-bar mb-3">
                            <div class="attempts-fill bg-danger" style="width: <?php echo ($_SESSION['login_attempts']/$max_attempts)*100; ?>%"></div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <form method="POST" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">ชื่อผู้ใช้งาน</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person text-muted"></i></span>
                            <input type="text" name="username" class="form-control"
                                   placeholder="กรอกชื่อผู้ใช้" required
                                   <?php echo $locked_out ? 'disabled' : ''; ?>
                                   maxlength="50" autocomplete="off">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold">รหัสผ่าน</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock text-muted"></i></span>
                            <input type="password" name="password" class="form-control"
                                   placeholder="กรอกรหัสผ่าน" id="pwdInput" required
                                   <?php echo $locked_out ? 'disabled' : ''; ?>
                                   maxlength="100" autocomplete="new-password">
                            <button type="button" class="btn btn-outline-secondary border-2" id="togglePwd">
                                <i class="bi bi-eye" id="pwdIcon"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" name="login" class="btn btn-login btn-primary w-100 text-white"
                            <?php echo $locked_out ? 'disabled' : ''; ?>>
                        <i class="bi bi-box-arrow-in-right me-2"></i>เข้าสู่ระบบ
                    </button>
                </form>
                <p class="text-center text-muted small mt-3 mb-0">
                    <i class="bi bi-shield-check me-1"></i>ระบบรักษาความปลอดภัย — Session หมดอายุใน 30 นาที
                </p>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('togglePwd').addEventListener('click', function() {
            const pwd = document.getElementById('pwdInput');
            const icon = document.getElementById('pwdIcon');
            if (pwd.type === 'password') {
                pwd.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                pwd.type = 'password';
                icon.className = 'bi bi-eye';
            }
        });
    </script>
</body>
</html>