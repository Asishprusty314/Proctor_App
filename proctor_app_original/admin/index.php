<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
require_once __DIR__ . '/layout.php';

// Stats
$totalExams = $conn->query("SELECT COUNT(*) as c FROM exams")->fetch_assoc()['c'] ?? 0;
$totalStudents = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='student'")->fetch_assoc()['c'] ?? 0;
$totalAttempts = $conn->query("SELECT COUNT(*) as c FROM exam_attempts")->fetch_assoc()['c'] ?? 0;
$liveNow = $conn->query("SELECT COUNT(*) as c FROM exam_attempts WHERE status='in_progress'")->fetch_assoc()['c'] ?? 0;
$todayViolations = $conn->query("SELECT COUNT(*) as c FROM violations WHERE DATE(created_at)=CURDATE()")->fetch_assoc()['c'] ?? 0;
$avgScore = $conn->query("SELECT ROUND(AVG(CASE WHEN total_marks>0 THEN (score/total_marks)*100 ELSE 0 END)) as a FROM exam_attempts WHERE status IN('completed','auto_submitted')")->fetch_assoc()['a'] ?? 0;
$passRate = $conn->query("SELECT ROUND(AVG(CASE WHEN (score/total_marks)*100>=50 THEN 100 ELSE 0 END)) as p FROM exam_attempts WHERE total_marks>0")->fetch_assoc()['p'] ?? 0;

// Weekly attempt counts (last 7 days)
$weekly = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $r = $conn->query("SELECT COUNT(*) as c FROM exam_attempts WHERE DATE(start_time)='$d'")->fetch_assoc();
    $weekly[] = ['day' => date('D', strtotime($d)), 'count' => (int)($r['c'] ?? 0)];
}
$maxWeekly = max(array_column($weekly, 'count')) ?: 1;

// Recent activity
$recent = $conn->query("SELECT ea.*, u.name as student_name, e.title as exam_title FROM exam_attempts ea JOIN users u ON ea.user_id=u.id JOIN exams e ON ea.exam_id=e.id ORDER BY ea.start_time DESC LIMIT 5");

// Live exams
$liveExams = $conn->query("SELECT e.*, (SELECT COUNT(*) FROM exam_attempts WHERE exam_id=e.id) as attempt_count FROM exams e WHERE status='active' ORDER BY attempt_count DESC LIMIT 3");

adminHeader('Dashboard', 'dashboard');

$donutOffset = 352 - (352 * ($passRate / 100));
?>

<!-- WELCOME + DONUT -->
<div class="welcome" style="display:grid;grid-template-columns:1fr 240px;gap:20px;margin-bottom:20px">
    <div class="welcome-left" style="background:#fff;border-radius:24px;padding:24px 28px;box-shadow:0 10px 28px rgba(16,185,129,.08),inset 0 -5px 0 rgba(16,185,129,.06),inset 0 2px 0 rgba(255,255,255,1);display:flex;flex-direction:column;justify-content:space-between;position:relative;overflow:hidden">
        <div style="position:absolute;top:-80px;right:-80px;width:240px;height:240px;border-radius:50%;background:radial-gradient(circle,rgba(16,185,129,.1),transparent 70%);pointer-events:none"></div>
        <div style="position:relative;z-index:2">
            <div style="font-size:11px;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:1.5px;margin-bottom:6px"><?= date('l · F j, Y') ?></div>
            <div style="font-size:24px;font-weight:900;color:#0f172a;letter-spacing:-.6px;margin-bottom:6px"><?= date('G') < 12 ? 'Good morning' : (date('G') < 17 ? 'Good afternoon' : 'Good evening') ?>, Admin 👋</div>
            <div style="font-size:13px;color:#64748b;font-weight:600;line-height:1.5">You have <strong style="color:#0f172a"><?= $liveNow ?> live exam session<?= $liveNow!=1?'s':'' ?></strong> running now and <strong style="color:#0f172a"><?= $totalAttempts ?> total attempts</strong> in your system.</div>
        </div>
        <div style="display:flex;gap:10px;margin-top:16px;position:relative;z-index:2;flex-wrap:wrap">
            <a href="<?= BASE_URL ?>/admin/add_exam.php" class="wa-btn" style="padding:11px 18px;border-radius:13px;font-size:12px;font-weight:800;background:#10b981;color:#fff;text-decoration:none;display:inline-flex;align-items:center;gap:6px;box-shadow:0 5px 12px rgba(16,185,129,.3),inset 0 -3px 0 rgba(0,0,0,.12),inset 0 2px 0 rgba(255,255,255,.2);transition:all .2s" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Create exam
            </a>
            <a href="<?= BASE_URL ?>/admin/live_monitor.php" class="wa-btn" style="padding:11px 18px;border-radius:13px;font-size:12px;font-weight:800;background:#f1f5f9;color:#475569;text-decoration:none;display:inline-flex;align-items:center;gap:6px;box-shadow:inset 0 -2px 0 rgba(0,0,0,.05)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>Live monitor
            </a>
        </div>
    </div>

    <div class="welcome-donut" style="background:#fff;border-radius:24px;padding:20px;box-shadow:0 10px 28px rgba(16,185,129,.08),inset 0 -5px 0 rgba(16,185,129,.06),inset 0 2px 0 rgba(255,255,255,1);display:flex;flex-direction:column;align-items:center;justify-content:center">
        <div style="position:relative;width:140px;height:140px">
            <svg width="140" height="140" style="transform:rotate(-90deg)">
                <circle cx="70" cy="70" r="56" stroke="#f1f5f9" stroke-width="12" fill="none"/>
                <circle cx="70" cy="70" r="56" stroke="url(#donEm)" stroke-width="12" fill="none" stroke-linecap="round" stroke-dasharray="352" stroke-dashoffset="<?= $donutOffset ?>"/>
                <defs><linearGradient id="donEm" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#10b981"/><stop offset="100%" stop-color="#34d399"/></linearGradient></defs>
            </svg>
            <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center">
                <div style="font-size:30px;font-weight:900;color:#0f172a;letter-spacing:-1.5px;line-height:1"><?= $passRate ?><span style="font-size:18px;color:#94a3b8">%</span></div>
                <div style="font-size:11px;color:#64748b;font-weight:700;margin-top:2px">Pass rate</div>
            </div>
        </div>
        <div style="margin-top:12px;font-size:10px;font-weight:800;padding:4px 10px;border-radius:10px;background:#d1fae5;color:#047857;box-shadow:inset 0 -2px 0 rgba(16,185,129,.1)">Live data</div>
    </div>
</div>

<!-- WIDGET MASONRY -->
<div style="display:grid;grid-template-columns:repeat(12,1fr);gap:16px">

    <!-- KPI: Total Exams -->
    <div style="grid-column:span 3;background:#fff;border-radius:22px;padding:18px;box-shadow:0 8px 20px rgba(0,0,0,.04),inset 0 -4px 0 rgba(0,0,0,.03),inset 0 2px 0 rgba(255,255,255,1)">
        <div style="width:44px;height:44px;border-radius:14px;background:#10b981;color:#fff;display:flex;align-items:center;justify-content:center;margin-bottom:12px;box-shadow:inset 0 -3px 0 rgba(0,0,0,.1),inset 0 2px 0 rgba(255,255,255,.2)">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        </div>
        <div style="font-size:32px;font-weight:900;color:#0f172a;letter-spacing:-1.5px;line-height:1"><?= $totalExams ?></div>
        <div style="font-size:11px;color:#64748b;font-weight:700;margin-top:4px">Total exams</div>
    </div>

    <!-- KPI: Students -->
    <div style="grid-column:span 3;background:#fff;border-radius:22px;padding:18px;box-shadow:0 8px 20px rgba(0,0,0,.04),inset 0 -4px 0 rgba(0,0,0,.03),inset 0 2px 0 rgba(255,255,255,1)">
        <div style="width:44px;height:44px;border-radius:14px;background:#0ea5e9;color:#fff;display:flex;align-items:center;justify-content:center;margin-bottom:12px;box-shadow:inset 0 -3px 0 rgba(0,0,0,.1),inset 0 2px 0 rgba(255,255,255,.2)">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        </div>
        <div style="font-size:32px;font-weight:900;color:#0f172a;letter-spacing:-1.5px;line-height:1"><?= $totalStudents ?></div>
        <div style="font-size:11px;color:#64748b;font-weight:700;margin-top:4px">Registered students</div>
    </div>

    <!-- KPI: Attempts -->
    <div style="grid-column:span 3;background:#fff;border-radius:22px;padding:18px;box-shadow:0 8px 20px rgba(0,0,0,.04),inset 0 -4px 0 rgba(0,0,0,.03),inset 0 2px 0 rgba(255,255,255,1)">
        <div style="width:44px;height:44px;border-radius:14px;background:#f59e0b;color:#fff;display:flex;align-items:center;justify-content:center;margin-bottom:12px;box-shadow:inset 0 -3px 0 rgba(0,0,0,.1),inset 0 2px 0 rgba(255,255,255,.2)">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M9 11l3 3 8-8"/><path d="M20 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h11"/></svg>
        </div>
        <div style="font-size:32px;font-weight:900;color:#0f172a;letter-spacing:-1.5px;line-height:1"><?= $totalAttempts ?></div>
        <div style="font-size:11px;color:#64748b;font-weight:700;margin-top:4px">Total attempts</div>
    </div>

    <!-- KPI: Violations -->
    <div style="grid-column:span 3;background:#fff;border-radius:22px;padding:18px;box-shadow:0 8px 20px rgba(0,0,0,.04),inset 0 -4px 0 rgba(0,0,0,.03),inset 0 2px 0 rgba(255,255,255,1)">
        <div style="width:44px;height:44px;border-radius:14px;background:#f43f5e;color:#fff;display:flex;align-items:center;justify-content:center;margin-bottom:12px;box-shadow:inset 0 -3px 0 rgba(0,0,0,.1),inset 0 2px 0 rgba(255,255,255,.2)">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <div style="font-size:32px;font-weight:900;color:#0f172a;letter-spacing:-1.5px;line-height:1"><?= $todayViolations ?></div>
        <div style="font-size:11px;color:#64748b;font-weight:700;margin-top:4px">Violations today</div>
    </div>

    <!-- Weekly Chart -->
    <div style="grid-column:span 7;background:#fff;border-radius:22px;overflow:hidden;box-shadow:0 8px 20px rgba(0,0,0,.04),inset 0 -4px 0 rgba(0,0,0,.03),inset 0 2px 0 rgba(255,255,255,1)">
        <div style="padding:16px 20px 8px;display:flex;justify-content:space-between;align-items:center">
            <h3 style="font-size:13px;font-weight:900;color:#0f172a;display:flex;align-items:center;gap:8px;margin:0">
                <span style="width:28px;height:28px;border-radius:10px;background:#10b981;display:flex;align-items:center;justify-content:center;box-shadow:inset 0 -2px 0 rgba(0,0,0,.08)"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg></span>
                Weekly attempts
            </h3>
        </div>
        <div style="padding:12px 20px 18px">
            <div style="height:140px;padding:4px 0;display:flex;align-items:flex-end;gap:6px">
                <?php foreach ($weekly as $d): $h = $d['count'] > 0 ? max(15, ($d['count'] / $maxWeekly) * 100) : 8; $color = $d['count'] === $maxWeekly && $d['count'] > 0 ? '#10b981' : '#6ee7b7'; ?>
                <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:5px;height:100%">
                    <div style="width:100%;flex:1;display:flex;flex-direction:column;justify-content:flex-end">
                        <div style="width:70%;margin:0 auto;border-radius:6px 6px 0 0;background:<?= $color ?>;height:<?= $h ?>%;box-shadow:inset 0 -2px 0 rgba(0,0,0,.1),inset 0 1px 0 rgba(255,255,255,.2);transition:height .6s cubic-bezier(.34,1.56,.64,1)" title="<?= $d['count'] ?> attempts"></div>
                    </div>
                    <div style="font-size:10px;color:#94a3b8;font-weight:800;text-transform:uppercase"><?= $d['day'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Recent Activity Timeline -->
    <div style="grid-column:span 5;background:#fff;border-radius:22px;overflow:hidden;box-shadow:0 8px 20px rgba(0,0,0,.04),inset 0 -4px 0 rgba(0,0,0,.03),inset 0 2px 0 rgba(255,255,255,1)">
        <div style="padding:16px 20px 8px;display:flex;justify-content:space-between;align-items:center">
            <h3 style="font-size:13px;font-weight:900;color:#0f172a;display:flex;align-items:center;gap:8px;margin:0">
                <span style="width:28px;height:28px;border-radius:10px;background:#0ea5e9;display:flex;align-items:center;justify-content:center;box-shadow:inset 0 -2px 0 rgba(0,0,0,.08)"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span>
                Recent activity
            </h3>
        </div>
        <div style="padding:8px 20px 18px;position:relative">
            <div style="position:absolute;left:35px;top:16px;bottom:16px;width:2px;background:#e2e8f0;border-radius:2px"></div>
            <?php if ($recent && $recent->num_rows > 0): while ($r = $recent->fetch_assoc()): 
                $colors = ['#10b981','#a855f7','#f59e0b','#0ea5e9','#f43f5e'];
                $color = $colors[crc32($r['student_name']) % 5];
                $initial = strtoupper(substr($r['student_name'], 0, 1));
            ?>
            <div style="display:flex;gap:14px;padding:8px 0;position:relative;align-items:center">
                <div style="width:32px;height:32px;border-radius:12px;background:<?= $color ?>;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:900;color:#fff;flex-shrink:0;position:relative;z-index:2;box-shadow:inset 0 -2px 0 rgba(0,0,0,.1),inset 0 1px 0 rgba(255,255,255,.2),0 2px 6px rgba(0,0,0,.1)"><?= $initial ?></div>
                <div style="flex:1;background:#f8fafc;border-radius:12px;padding:8px 12px;box-shadow:inset 0 -2px 0 rgba(0,0,0,.03)">
                    <div style="font-size:12px;font-weight:800;color:#0f172a"><?= htmlspecialchars($r['student_name']) ?></div>
                    <div style="font-size:11px;color:#64748b;font-weight:600;margin-top:1px"><?= ucfirst(str_replace('_',' ',$r['status'])) ?> · <?= htmlspecialchars(substr($r['exam_title'],0,25)) ?></div>
                </div>
                <div style="font-size:10px;color:#94a3b8;font-weight:700;flex-shrink:0"><?= timeAgo($r['start_time']) ?></div>
            </div>
            <?php endwhile; else: ?>
            <div style="padding:30px;text-align:center;color:#94a3b8;font-size:12px;font-weight:600">No recent activity</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Live Exams -->
    <div style="grid-column:span 7;background:#fff;border-radius:22px;overflow:hidden;box-shadow:0 8px 20px rgba(0,0,0,.04),inset 0 -4px 0 rgba(0,0,0,.03),inset 0 2px 0 rgba(255,255,255,1)">
        <div style="padding:16px 20px 8px;display:flex;justify-content:space-between;align-items:center">
            <h3 style="font-size:13px;font-weight:900;color:#0f172a;display:flex;align-items:center;gap:8px;margin:0">
                <span style="width:28px;height:28px;border-radius:10px;background:#f59e0b;display:flex;align-items:center;justify-content:center;box-shadow:inset 0 -2px 0 rgba(0,0,0,.08)"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg></span>
                Active exams
            </h3>
            <a href="<?= BASE_URL ?>/admin/exams.php" style="padding:6px 14px;border-radius:10px;font-size:11px;font-weight:800;background:#f1f5f9;color:#475569;text-decoration:none;box-shadow:inset 0 -2px 0 rgba(0,0,0,.04)">Manage</a>
        </div>
        <div style="padding:4px 20px 18px">
            <?php if ($liveExams && $liveExams->num_rows > 0): while ($e = $liveExams->fetch_assoc()): 
                $colors = ['#10b981','#f59e0b','#0ea5e9','#a855f7'];
                $color = $colors[$e['id'] % 4];
                $initial = strtoupper(substr($e['title'], 0, 1));
            ?>
            <div style="display:flex;align-items:center;gap:12px;padding:12px;border-radius:14px;background:#f8fafc;margin-bottom:8px;box-shadow:inset 0 -2px 0 rgba(0,0,0,.03)">
                <div style="width:40px;height:40px;border-radius:12px;background:<?= $color ?>;display:flex;align-items:center;justify-content:center;color:#fff;font-size:14px;font-weight:900;flex-shrink:0;box-shadow:inset 0 -2px 0 rgba(0,0,0,.1),inset 0 1px 0 rgba(255,255,255,.2)"><?= $initial ?></div>
                <div style="flex:1;min-width:0">
                    <div style="font-size:13px;font-weight:800;color:#0f172a"><?= htmlspecialchars($e['title']) ?></div>
                    <div style="font-size:10px;color:#64748b;font-weight:700;margin-top:1px"><?= $e['duration'] ?> min · <?= $e['total_marks'] ?> marks</div>
                </div>
                <div style="text-align:right">
                    <div style="font-size:14px;font-weight:900;color:#10b981"><?= $e['attempt_count'] ?></div>
                    <div style="font-size:9px;color:#94a3b8;font-weight:700;text-transform:uppercase">Attempts</div>
                </div>
            </div>
            <?php endwhile; else: ?>
            <div style="padding:30px;text-align:center;color:#94a3b8;font-size:12px;font-weight:600">No active exams yet — <a href="<?= BASE_URL ?>/admin/add_exam.php" style="color:#10b981;font-weight:800">create one</a></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Actions -->
    <div style="grid-column:span 5;background:linear-gradient(135deg,#10b981,#34d399);border-radius:22px;overflow:hidden;color:#fff;box-shadow:0 10px 28px rgba(16,185,129,.25),inset 0 -4px 0 rgba(0,0,0,.08)">
        <div style="padding:16px 20px 8px">
            <h3 style="font-size:13px;font-weight:900;color:#fff;display:flex;align-items:center;gap:8px;margin:0">
                <span style="width:28px;height:28px;border-radius:10px;background:rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg></span>
                Quick actions
            </h3>
        </div>
        <div style="padding:4px 20px 18px;display:flex;flex-direction:column;gap:6px">
            <a href="<?= BASE_URL ?>/admin/add_exam.php" style="background:rgba(255,255,255,.2);color:#fff;padding:10px 14px;border-radius:11px;font-size:12px;font-weight:800;text-decoration:none;display:flex;align-items:center;gap:8px;backdrop-filter:blur(8px);box-shadow:inset 0 -2px 0 rgba(0,0,0,.1)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>New exam
            </a>
            <a href="<?= BASE_URL ?>/admin/add_question.php" style="background:rgba(255,255,255,.2);color:#fff;padding:10px 14px;border-radius:11px;font-size:12px;font-weight:800;text-decoration:none;display:flex;align-items:center;gap:8px;backdrop-filter:blur(8px);box-shadow:inset 0 -2px 0 rgba(0,0,0,.1)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1"/></svg>Add question
            </a>
            <a href="<?= BASE_URL ?>/admin/manage_keys.php" style="background:rgba(255,255,255,.2);color:#fff;padding:10px 14px;border-radius:11px;font-size:12px;font-weight:800;text-decoration:none;display:flex;align-items:center;gap:8px;backdrop-filter:blur(8px);box-shadow:inset 0 -2px 0 rgba(0,0,0,.1)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777"/></svg>Generate keys
            </a>
            <a href="<?= BASE_URL ?>/admin/students.php" style="background:rgba(255,255,255,.2);color:#fff;padding:10px 14px;border-radius:11px;font-size:12px;font-weight:800;text-decoration:none;display:flex;align-items:center;gap:8px;backdrop-filter:blur(8px);box-shadow:inset 0 -2px 0 rgba(0,0,0,.1)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>View students
            </a>
        </div>
    </div>

</div>

<?php adminFooter(); ?>