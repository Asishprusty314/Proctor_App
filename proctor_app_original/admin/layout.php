<?php
// admin/layout.php — v2 Claymorphism with vertical icon rail
if (!function_exists('flash')) {
    require_once __DIR__ . '/../includes/functions.php';
}
if (!isset($page_title)) $page_title = 'Dashboard';
if (!isset($active_page)) $active_page = 'dashboard';
if (!isset($breadcrumb)) $breadcrumb = $page_title;

function adminHeader($title = 'Dashboard', $active = 'dashboard', $breadcrumb = null) {
    $breadcrumb = $breadcrumb ?: $title;
    $userName = $_SESSION['user_name'] ?? 'Admin';
    $initial = strtoupper(substr($userName, 0, 1));
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($title) ?> · ExamProctor</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<style>
/* V2 Architecture Styles */
body{background:#fef9f4;margin:0}
.app{display:flex;min-height:100vh}
.rail{width:84px;background:#fff;display:flex;flex-direction:column;align-items:center;padding:20px 0;gap:8px;box-shadow:4px 0 20px rgba(0,0,0,.04);position:fixed;top:0;left:0;bottom:0;z-index:100}
.rail-logo{width:52px;height:52px;border-radius:18px;background:#10b981;color:#fff;display:flex;align-items:center;justify-content:center;margin-bottom:20px;box-shadow:inset 0 -4px 0 rgba(0,0,0,.12),inset 0 2px 0 rgba(255,255,255,.2),0 8px 18px rgba(16,185,129,.3);text-decoration:none}
.rail-logo svg{width:26px;height:26px}
.rail-btn{width:52px;height:52px;border-radius:16px;background:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;border:none;transition:all .2s;position:relative;box-shadow:inset 0 -2px 0 rgba(0,0,0,.04),0 2px 6px rgba(0,0,0,.04);text-decoration:none}
.rail-btn:hover{transform:translateY(-2px);box-shadow:inset 0 -2px 0 rgba(0,0,0,.04),0 4px 12px rgba(0,0,0,.08)}
.rail-btn svg{width:22px;height:22px;color:#64748b;transition:color .2s}
.rail-btn:hover svg{color:#1e293b}
.rail-btn.on{background:#10b981;box-shadow:inset 0 -3px 0 rgba(0,0,0,.1),inset 0 2px 0 rgba(255,255,255,.2),0 6px 14px rgba(16,185,129,.3)}
.rail-btn.on svg{color:#fff}
.rail-tip{position:absolute;left:64px;background:#1e293b;color:#fff;padding:6px 12px;border-radius:9px;font-size:11px;font-weight:700;white-space:nowrap;opacity:0;pointer-events:none;transition:opacity .2s;z-index:10;box-shadow:0 4px 12px rgba(0,0,0,.15)}
.rail-btn:hover .rail-tip{opacity:1}
.rail-divider{width:32px;height:2px;background:#f1f5f9;border-radius:2px;margin:8px 0}
.rail-avatar{width:44px;height:44px;border-radius:14px;margin-top:auto;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:900;font-size:15px;background:linear-gradient(135deg,#10b981,#34d399);box-shadow:inset 0 -3px 0 rgba(0,0,0,.1),inset 0 2px 0 rgba(255,255,255,.2),0 4px 12px rgba(0,0,0,.1);text-decoration:none}
.main{flex:1;margin-left:84px;background:linear-gradient(180deg,#fef9f4 0%,#fff 300px);min-height:100vh}
.sub{padding:24px 32px 16px;display:flex;justify-content:space-between;align-items:center}
.crumbs{font-size:12px;color:#94a3b8;font-weight:700;margin-bottom:4px;display:flex;align-items:center;gap:6px}
.crumbs svg{width:12px;height:12px}
.crumbs .here{color:#1e293b;font-weight:800}
.page-t{font-size:26px;font-weight:900;color:#0f172a;letter-spacing:-.8px;line-height:1;margin:0}
.sub-right{display:flex;align-items:center;gap:10px}
.ic-btn{width:42px;height:42px;border-radius:14px;background:#fff;display:flex;align-items:center;justify-content:center;border:none;cursor:pointer;transition:all .2s;box-shadow:inset 0 -2px 0 rgba(0,0,0,.04),0 2px 8px rgba(0,0,0,.05);text-decoration:none}
.ic-btn:hover{transform:translateY(-1px)}
.ic-btn svg{width:18px;height:18px;color:#64748b}
.page{padding:8px 32px 40px}
</style>
</head>
<body>
<div class="app">
<!-- VERTICAL ICON RAIL -->
<aside class="rail">
    <a href="<?= BASE_URL ?>/admin/index.php" class="rail-logo">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
    </a>
    <a href="<?= BASE_URL ?>/admin/index.php" class="rail-btn <?= $active==='dashboard'?'on':'' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        <span class="rail-tip">Dashboard</span>
    </a>
    <a href="<?= BASE_URL ?>/admin/live_monitor.php" class="rail-btn <?= $active==='monitor'?'on':'' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
        <span class="rail-tip">Live Monitor</span>
    </a>
    <a href="<?= BASE_URL ?>/admin/exams.php" class="rail-btn <?= $active==='exams'?'on':'' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        <span class="rail-tip">Exams</span>
    </a>
    <div class="rail-divider"></div>
    <a href="<?= BASE_URL ?>/admin/questions.php" class="rail-btn <?= $active==='questions'?'on':'' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
        <span class="rail-tip">Questions</span>
    </a>
    <a href="<?= BASE_URL ?>/admin/students.php" class="rail-btn <?= $active==='students'?'on':'' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        <span class="rail-tip">Students</span>
    </a>
    <a href="<?= BASE_URL ?>/admin/attempts.php" class="rail-btn <?= $active==='attempts'?'on':'' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3 8-8"/><path d="M20 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h11"/></svg>
        <span class="rail-tip">Attempts</span>
    </a>
    <a href="<?= BASE_URL ?>/admin/manage_keys.php" class="rail-btn <?= $active==='keys'?'on':'' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
        <span class="rail-tip">Access Keys</span>
    </a>
    <a href="<?= BASE_URL ?>/logout.php" class="rail-avatar" title="Logout"><?= $initial ?></a>
</aside>

<!-- MAIN CONTENT -->
<main class="main">
    <header class="sub">
        <div>
            <div class="crumbs">Home <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg> <span class="here"><?= htmlspecialchars($breadcrumb) ?></span></div>
            <h1 class="page-t"><?= htmlspecialchars($title) ?></h1>
        </div>
        <div class="sub-right">
            <a href="<?= BASE_URL ?>/logout.php" class="ic-btn" title="Logout">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            </a>
        </div>
    </header>
    <div class="page">
    <?php
    // Flash messages
    foreach (['success', 'error', 'warning', 'info'] as $type) {
        $msg = flash($type);
        if ($msg) {
            $cls = $type === 'error' ? 'alert-danger' : "alert-$type";
            echo "<div class=\"alert $cls\">" . htmlspecialchars($msg) . "</div>";
        }
    }
    ?>
<?php }

function adminFooter() { ?>
    </div>
</main>
</div>
</body>
</html>
<?php }