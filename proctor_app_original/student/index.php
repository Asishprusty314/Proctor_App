<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireStudent();
require_once __DIR__ . '/layout.php';

$userId = $_SESSION['user_id'];

// Available exams (active, within time window, not yet completed)
$availableExams = $conn->query("
    SELECT e.*, 
           (SELECT COUNT(*) FROM questions WHERE exam_id=e.id) as question_count,
           (SELECT status FROM exam_attempts WHERE user_id=$userId AND exam_id=e.id ORDER BY id DESC LIMIT 1) as my_status,
           (SELECT score FROM exam_attempts WHERE user_id=$userId AND exam_id=e.id ORDER BY id DESC LIMIT 1) as my_score
    FROM exams e 
    WHERE e.status='active' AND NOW() BETWEEN e.start_time AND e.end_time
    ORDER BY e.id DESC
");

// Stats
$totalCompleted = $conn->query("SELECT COUNT(*) as c FROM exam_attempts WHERE user_id=$userId AND status IN('completed','auto_submitted')")->fetch_assoc()['c'] ?? 0;
$totalAvailable = $availableExams ? $availableExams->num_rows : 0;
$avgScore = $conn->query("SELECT ROUND(AVG(CASE WHEN total_marks>0 THEN (score/total_marks)*100 ELSE 0 END)) as a FROM exam_attempts WHERE user_id=$userId AND status IN('completed','auto_submitted')")->fetch_assoc()['a'] ?? 0;
$lastScore = $conn->query("SELECT CASE WHEN total_marks>0 THEN ROUND((score/total_marks)*100) ELSE 0 END as s, (SELECT title FROM exams WHERE id=exam_id) as t FROM exam_attempts WHERE user_id=$userId AND status IN('completed','auto_submitted') ORDER BY id DESC LIMIT 1")->fetch_assoc();

// Pick featured exam (first available one not completed)
$featured = null;
$otherExams = [];
if ($availableExams && $availableExams->num_rows > 0) {
    while ($e = $availableExams->fetch_assoc()) {
        if (!$featured && $e['my_status'] !== 'completed' && $e['my_status'] !== 'auto_submitted') {
            $featured = $e;
        } else {
            $otherExams[] = $e;
        }
    }
}

$donutOffset = 352 - (352 * ($avgScore / 100));

studentHeader('My Dashboard', 'dashboard');
?>

<!-- WELCOME + DONUT -->
<div style="display:grid;grid-template-columns:1fr 240px;gap:20px;margin-bottom:20px">
    <div style="background:#fff;border-radius:24px;padding:24px 28px;box-shadow:0 10px 28px rgba(14,165,233,.08),inset 0 -5px 0 rgba(14,165,233,.06),inset 0 2px 0 rgba(255,255,255,1);display:flex;flex-direction:column;justify-content:space-between;position:relative;overflow:hidden">
        <div style="position:absolute;top:-80px;right:-80px;width:240px;height:240px;border-radius:50%;background:radial-gradient(circle,rgba(14,165,233,.1),transparent 70%);pointer-events:none"></div>
        <div style="position:relative;z-index:2">
            <div style="font-size:11px;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:1.5px;margin-bottom:6px"><?= date('l · F j, Y') ?></div>
            <div style="font-size:24px;font-weight:900;color:#0f172a;letter-spacing:-.6px;margin-bottom:6px">
                <?= date('G') < 12 ? 'Good morning' : (date('G') < 17 ? 'Good afternoon' : 'Good evening') ?>, <?= htmlspecialchars(explode(' ', $_SESSION['user_name'] ?? 'Student')[0]) ?>! 📚
            </div>
            <div style="font-size:13px;color:#64748b;font-weight:600;line-height:1.5">
                <?php if ($totalAvailable > 0): ?>
                    You have <strong style="color:#0f172a"><?= $totalAvailable ?> exam<?= $totalAvailable!=1?'s':'' ?></strong> available right now.
                    <?php if ($lastScore): ?> Your last score was <strong style="color:#0f172a"><?= $lastScore['s'] ?>%</strong> in <?= htmlspecialchars(substr($lastScore['t'], 0, 30)) ?>.<?php endif; ?>
                <?php else: ?>
                    No exams available right now. Check back later!
                <?php endif; ?>
            </div>
        </div>
        <div style="display:flex;gap:10px;margin-top:16px;position:relative;z-index:2">
            <a href="<?= BASE_URL ?>/student/exams.php" style="padding:11px 18px;border-radius:13px;font-size:12px;font-weight:800;background:#0ea5e9;color:#fff;text-decoration:none;display:inline-flex;align-items:center;gap:6px;box-shadow:0 5px 12px rgba(14,165,233,.3),inset 0 -3px 0 rgba(0,0,0,.12),inset 0 2px 0 rgba(255,255,255,.2);transition:all .2s" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polygon points="5 3 19 12 5 21 5 3"/></svg>View all exams
            </a>
            <a href="<?= BASE_URL ?>/student/results.php" style="padding:11px 18px;border-radius:13px;font-size:12px;font-weight:800;background:#f1f5f9;color:#475569;text-decoration:none;display:inline-flex;align-items:center;gap:6px;box-shadow:inset 0 -2px 0 rgba(0,0,0,.05)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 11l3 3 8-8"/></svg>My results
            </a>
        </div>
    </div>

    <div style="background:#fff;border-radius:24px;padding:20px;box-shadow:0 10px 28px rgba(14,165,233,.08),inset 0 -5px 0 rgba(14,165,233,.06),inset 0 2px 0 rgba(255,255,255,1);display:flex;flex-direction:column;align-items:center;justify-content:center">
        <div style="position:relative;width:140px;height:140px">
            <svg width="140" height="140" style="transform:rotate(-90deg)">
                <circle cx="70" cy="70" r="56" stroke="#f1f5f9" stroke-width="12" fill="none"/>
                <circle cx="70" cy="70" r="56" stroke="url(#donSky)" stroke-width="12" fill="none" stroke-linecap="round" stroke-dasharray="352" stroke-dashoffset="<?= $donutOffset ?>"/>
                <defs><linearGradient id="donSky" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#0ea5e9"/><stop offset="100%" stop-color="#38bdf8"/></linearGradient></defs>
            </svg>
            <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center">
                <div style="font-size:30px;font-weight:900;color:#0f172a;letter-spacing:-1.5px;line-height:1"><?= $avgScore ?><span style="font-size:18px;color:#94a3b8">%</span></div>
                <div style="font-size:11px;color:#64748b;font-weight:700;margin-top:2px">Avg score</div>
            </div>
        </div>
        <div style="margin-top:12px;font-size:10px;font-weight:800;padding:4px 10px;border-radius:10px;background:#e0f2fe;color:#0369a1;box-shadow:inset 0 -2px 0 rgba(14,165,233,.1)">
            <?= $totalCompleted ?> completed
        </div>
    </div>
</div>

<!-- FEATURED EXAM HERO -->
<?php if ($featured): ?>
<div style="background:linear-gradient(135deg,#0ea5e9,#38bdf8);border-radius:24px;padding:28px;position:relative;overflow:hidden;margin-bottom:20px;box-shadow:0 14px 32px rgba(14,165,233,.3),inset 0 -5px 0 rgba(0,0,0,.1),inset 0 2px 0 rgba(255,255,255,.15)">
    <div style="position:absolute;top:-60px;right:-60px;width:240px;height:240px;border-radius:50%;background:rgba(255,255,255,.1)"></div>
    <div style="position:absolute;bottom:-40px;left:40%;width:160px;height:160px;border-radius:50%;background:rgba(255,255,255,.06)"></div>
    <div style="position:relative;z-index:2;display:flex;justify-content:space-between;align-items:center;gap:20px;flex-wrap:wrap">
        <div style="flex:1;min-width:280px">
            <div style="font-size:10px;font-weight:900;color:#fff;background:rgba(255,255,255,.2);padding:4px 12px;border-radius:10px;display:inline-block;text-transform:uppercase;letter-spacing:1.2px;margin-bottom:10px;backdrop-filter:blur(8px)">▸ Available now</div>
            <div style="font-size:26px;font-weight:900;color:#fff;letter-spacing:-.6px;margin-bottom:6px"><?= htmlspecialchars($featured['title']) ?></div>
            <div style="font-size:13px;color:rgba(255,255,255,.85);font-weight:600;margin-bottom:16px;max-width:500px"><?= htmlspecialchars($featured['description'] ?? 'Take this exam to test your knowledge.') ?></div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
                <div style="background:rgba(255,255,255,.18);padding:5px 12px;border-radius:12px;font-size:11px;color:#fff;font-weight:800;display:inline-flex;align-items:center;gap:5px;backdrop-filter:blur(8px)">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><?= $featured['duration'] ?> min
                </div>
                <div style="background:rgba(255,255,255,.18);padding:5px 12px;border-radius:12px;font-size:11px;color:#fff;font-weight:800;display:inline-flex;align-items:center;gap:5px;backdrop-filter:blur(8px)">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1"/></svg><?= $featured['question_count'] ?> questions
                </div>
                <div style="background:rgba(255,255,255,.18);padding:5px 12px;border-radius:12px;font-size:11px;color:#fff;font-weight:800;display:inline-flex;align-items:center;gap:5px;backdrop-filter:blur(8px)">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="8" r="7"/></svg><?= $featured['total_marks'] ?> marks
                </div>
            </div>
            <a href="<?= BASE_URL ?>/student/pre_exam.php?id=<?= $featured['id'] ?>" style="background:#fff;color:#0369a1;padding:12px 24px;border-radius:14px;font-size:13px;font-weight:900;text-decoration:none;display:inline-flex;align-items:center;gap:8px;box-shadow:0 6px 16px rgba(0,0,0,.15),inset 0 -3px 0 rgba(0,0,0,.08);transition:all .2s" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                Start exam now<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </a>
        </div>
        <div style="width:100px;height:100px;border-radius:28px;background:rgba(255,255,255,.15);backdrop-filter:blur(10px);display:flex;align-items:center;justify-content:center;box-shadow:inset 0 -4px 0 rgba(0,0,0,.1),inset 0 2px 0 rgba(255,255,255,.2);flex-shrink:0">
            <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- MINI EXAM CARDS -->
<?php if (count($otherExams) > 0): ?>
<div style="margin:8px 0 10px;font-size:14px;font-weight:900;color:#0f172a;display:flex;justify-content:space-between;align-items:center">
    More exams
    <a href="<?= BASE_URL ?>/student/exams.php" style="padding:6px 12px;font-size:11px;font-weight:800;background:#f1f5f9;color:#475569;border-radius:10px;text-decoration:none;display:inline-flex;align-items:center;gap:4px;box-shadow:inset 0 -2px 0 rgba(0,0,0,.05)">
        View all <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="9 18 15 12 9 6"/></svg>
    </a>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px">
<?php foreach (array_slice($otherExams, 0, 6) as $e): 
    $colors = ['#10b981','#f59e0b','#a855f7','#0ea5e9','#f43f5e'];
    $color = $colors[$e['id'] % 5];
    $done = in_array($e['my_status'], ['completed','auto_submitted']);
?>
<div style="background:#fff;border-radius:20px;padding:18px;transition:all .25s;box-shadow:0 8px 20px rgba(14,165,233,.08),inset 0 -4px 0 rgba(14,165,233,.06),inset 0 2px 0 rgba(255,255,255,1)" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px">
        <div style="width:40px;height:40px;border-radius:13px;background:<?= $color ?>;display:flex;align-items:center;justify-content:center;color:#fff;box-shadow:inset 0 -3px 0 rgba(0,0,0,.1),inset 0 2px 0 rgba(255,255,255,.2)">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        </div>
        <?php if ($done): ?>
            <span style="padding:3px 9px;border-radius:10px;font-size:10px;font-weight:800;background:#d1fae5;color:#047857;box-shadow:inset 0 -2px 0 rgba(16,185,129,.1)">✓ Done</span>
        <?php else: ?>
            <span style="padding:3px 9px;border-radius:10px;font-size:10px;font-weight:800;background:#d1fae5;color:#047857;box-shadow:inset 0 -2px 0 rgba(16,185,129,.1);display:inline-flex;align-items:center;gap:4px"><span style="width:5px;height:5px;border-radius:50%;background:#10b981;display:block;animation:pulse 2s ease infinite"></span>Live</span>
        <?php endif; ?>
    </div>
    <div style="font-size:14px;font-weight:900;color:#0f172a;margin-bottom:3px"><?= htmlspecialchars($e['title']) ?></div>
    <div style="font-size:11px;color:#64748b;font-weight:600;margin-bottom:12px;line-height:1.4;min-height:30px"><?= htmlspecialchars(substr($e['description'] ?? 'Test your knowledge', 0, 50)) ?></div>
    <div style="display:flex;gap:12px;padding:10px;background:#f0f9ff;border-radius:12px;margin-bottom:10px;box-shadow:inset 0 -2px 0 rgba(14,165,233,.06)">
        <div style="flex:1;text-align:center"><div style="font-size:14px;font-weight:900;color:#0c4a6e"><?= $e['duration'] ?></div><div style="font-size:9px;color:#0369a1;font-weight:800;text-transform:uppercase">Min</div></div>
        <div style="flex:1;text-align:center"><div style="font-size:14px;font-weight:900;color:#0c4a6e"><?= $e['question_count'] ?></div><div style="font-size:9px;color:#0369a1;font-weight:800;text-transform:uppercase">Q's</div></div>
        <div style="flex:1;text-align:center"><div style="font-size:14px;font-weight:900;color:#0c4a6e"><?= $e['total_marks'] ?></div><div style="font-size:9px;color:#0369a1;font-weight:800;text-transform:uppercase">Marks</div></div>
    </div>
    <?php if ($done): ?>
        <a href="<?= BASE_URL ?>/student/results.php" style="width:100%;padding:9px;border-radius:11px;font-size:11px;font-weight:900;background:#fff;color:#0369a1;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:5px;box-shadow:0 3px 8px rgba(14,165,233,.15),inset 0 -2px 0 rgba(14,165,233,.08)">View result</a>
    <?php else: ?>
        <a href="<?= BASE_URL ?>/student/pre_exam.php?id=<?= $e['id'] ?>" style="width:100%;padding:9px;border-radius:11px;font-size:11px;font-weight:900;background:#0ea5e9;color:#fff;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:5px;box-shadow:0 3px 8px rgba(14,165,233,.3),inset 0 -2px 0 rgba(0,0,0,.12),inset 0 1px 0 rgba(255,255,255,.2)">Start<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polygon points="5 3 19 12 5 21 5 3"/></svg></a>
    <?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<style>@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.8)}}</style>

<?php studentFooter(); ?>