```php
<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/layout.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . BASE_URL . '/admin/exams.php'); exit; }

$exam = $conn->query("SELECT * FROM exams WHERE id = $id")->fetch_assoc();
if (!$exam) { header('Location: ' . BASE_URL . '/admin/exams.php'); exit; }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $duration = (int)($_POST['duration'] ?? 0);
    $total_marks = (int)($_POST['total_marks'] ?? 0);
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $status = $_POST['status'] ?? 'active';

    $webcam = isset($_POST['webcam_enabled']) ? 1 : 0;

    // ✅ FIXED (min = 0)
    $tab_limit = max(0, (int)($_POST['tab_switch_limit'] ?? 0));
    $snapshot_interval = max(0, (int)($_POST['snapshot_interval'] ?? 0));
    $max_violations = max(0, (int)($_POST['max_violations'] ?? 0));

    $fullscreen = isset($_POST['fullscreen_required']) ? 1 : 0;
    $randomize = isset($_POST['randomize_questions']) ? 1 : 0;
    $shuffle = isset($_POST['shuffle_options']) ? 1 : 0;

    $access_key = trim($_POST['access_key'] ?? '') ?: null;
    $audio_monitoring = isset($_POST['audio_monitoring']) ? 1 : 0;
    $face_detection = isset($_POST['face_detection']) ? 1 : 0;

    if (empty($title) || $duration <= 0 || empty($start_time) || empty($end_time)) {
        $error = 'Please fill in all required fields.';
    } else {
        $stmt = $conn->prepare("UPDATE exams SET 
            title=?, description=?, duration=?, total_marks=?, start_time=?, end_time=?, status=?, 
            webcam_enabled=?, tab_switch_limit=?, fullscreen_required=?, randomize_questions=?, shuffle_options=?, 
            access_key=?, audio_monitoring=?, face_detection=?, snapshot_interval=?, max_violations=? 
            WHERE id=?");

        // ✅ FIXED bind_param (18 params)
        $stmt->bind_param(
            "ssiisssiiiiisiiiii",
            $title,
            $description,
            $duration,
            $total_marks,
            $start_time,
            $end_time,
            $status,
            $webcam,
            $tab_limit,
            $fullscreen,
            $randomize,
            $shuffle,
            $access_key,
            $audio_monitoring,
            $face_detection,
            $snapshot_interval,
            $max_violations,
            $id
        );

        if ($stmt->execute()) {
            flash('success', 'Exam updated successfully!');
            header('Location: ' . BASE_URL . '/admin/exams.php');
            exit;
        } else {
            $error = 'Failed to update exam.';
        }
        $stmt->close();
    }

    $exam = array_merge($exam, $_POST);
}

// Format datetime
$startFormatted = date('Y-m-d\TH:i', strtotime($exam['start_time']));
$endFormatted = date('Y-m-d\TH:i', strtotime($exam['end_time']));

adminHeader('Edit Exam', 'exams');
?>

<?php if ($error): ?>
<div class="alert alert-danger">⚠️ <?= sanitize($error) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>Edit: <?= sanitize($exam['title']) ?></h2>
    </div>

    <div class="card-body">
        <form method="POST">

            <input type="text" name="title" value="<?= sanitize($exam['title']) ?>" required>
            <textarea name="description"><?= sanitize($exam['description']) ?></textarea>

            <input type="number" name="duration" min="1" value="<?= $exam['duration'] ?>" required>
            <input type="number" name="total_marks" min="0" value="<?= $exam['total_marks'] ?>" required>

            <input type="datetime-local" name="start_time" value="<?= $startFormatted ?>" required>
            <input type="datetime-local" name="end_time" value="<?= $endFormatted ?>" required>

            <!-- ✅ FRONTEND FIX -->
            <input type="number" name="tab_switch_limit" min="0" value="<?= $exam['tab_switch_limit'] ?>">
            <input type="number" name="max_violations" min="0" value="<?= $exam['max_violations'] ?>">
            <input type="number" name="snapshot_interval" min="0" value="<?= $exam['snapshot_interval'] ?>">

            <button type="submit">Update</button>

        </form>
    </div>
</div>

<?php adminFooter(); ?>
```
