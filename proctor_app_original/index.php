
<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php'; //  FIXED (always include)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ✅ Ensure redirect exists
if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: " . $url);
        exit;
    }
}

// Already logged in? Redirect
if (isLoggedIn()) {
    if (isAdmin()) redirect('admin/index.php');
    else redirect('student/index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] === 'admin') redirect('admin/index.php');
            else redirect('student/index.php');
        } else {
            $error = 'Invalid email or password.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In · ExamProctor</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">

<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Nunito',system-ui,sans-serif;-webkit-font-smoothing:antialiased;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;background:linear-gradient(135deg,#d1fae5 0%,#dbeafe 100%);position:relative;overflow:hidden}
body::before{content:'';position:absolute;width:320px;height:320px;border-radius:50%;background:#10b981;opacity:.18;top:-80px;right:-80px;filter:blur(50px);pointer-events:none}
body::after{content:'';position:absolute;width:300px;height:300px;border-radius:50%;background:#0ea5e9;opacity:.18;bottom:-80px;left:-80px;filter:blur(50px);pointer-events:none}

.card{background:#fff;border-radius:32px;padding:40px;width:100%;max-width:420px;position:relative;z-index:2;box-shadow:0 30px 60px rgba(0,0,0,.12),inset 0 -8px 0 rgba(0,0,0,.03),inset 0 2px 0 rgba(255,255,255,1);animation:clayIn .5s cubic-bezier(.34,1.56,.64,1)}
@keyframes clayIn{from{opacity:0;transform:scale(.95) translateY(10px)}to{opacity:1;transform:scale(1) translateY(0)}}

.logo{text-align:center;margin-bottom:28px}
.logo-ic{width:72px;height:72px;border-radius:22px;background:linear-gradient(135deg,#10b981,#0ea5e9);display:inline-flex;align-items:center;justify-content:center;margin-bottom:14px;box-shadow:inset 0 -5px 0 rgba(0,0,0,.15),inset 0 3px 0 rgba(255,255,255,.3),0 12px 28px rgba(16,185,129,.35)}
.logo-ic svg{width:36px;height:36px;color:#fff}
.logo h1{font-size:24px;font-weight:900;color:#0f172a;letter-spacing:-.5px}
.logo p{font-size:13px;color:#64748b;font-weight:600;margin-top:3px}

.error{background:#ffe4e6;color:#be123c;padding:12px 16px;border-radius:12px;font-size:13px;font-weight:700;margin-bottom:16px}

.fg{margin-bottom:16px}
.fg label{display:block;font-size:12px;font-weight:800;color:#0f172a;margin-bottom:6px}
.fg input{width:100%;padding:13px 16px;background:#f1f5f9;border:none;border-radius:13px;font-family:inherit;font-size:14px;font-weight:700;color:#0f172a;outline:none}
.fg input:focus{background:#fff}

.btn{width:100%;padding:14px;border-radius:15px;font-size:14px;font-weight:900;background:linear-gradient(135deg,#10b981,#0ea5e9);color:#fff;border:none;cursor:pointer}

.footer{text-align:center;margin-top:18px;font-size:13px;color:#64748b;font-weight:600}
.footer a{color:#10b981;font-weight:800;text-decoration:none}

</style>
</head>
<body>

<div class="card">
    <div class="logo">
        <div class="logo-ic">✔</div>
        <h1>Welcome back</h1>
        <p>Sign in to continue</p>
    </div>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="fg">
            <label>Email</label>
            <input type="email" name="email" required>
        </div>

        <div class="fg">
            <label>Password</label>
            <input type="password" name="password" required>
        </div>

        <button class="btn">Login</button>
    </form>

    <div class="footer">
        Don't have an account? <a href="<?= BASE_URL ?>register.php">Register</a>
    </div>

</div>

</body>
</html>
