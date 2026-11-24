<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/layout.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');

    $duration = (int)($_POST['duration'] ?? 0);
    $total_marks = (int)($_POST['total_marks'] ?? 0);

    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';

    $status = $_POST['status'] ?? 'active';

    // Security settings
    $webcam = isset($_POST['webcam_enabled']) ? 1 : 0;
    $fullscreen = isset($_POST['fullscreen_required']) ? 1 : 0;
    $randomize = isset($_POST['randomize_questions']) ? 1 : 0;
    $shuffle = isset($_POST['shuffle_options']) ? 1 : 0;
    $audio_monitoring = isset($_POST['audio_monitoring']) ? 1 : 0;
    $face_detection = isset($_POST['face_detection']) ? 1 : 0;

    // Allow 0 values
    $tab_limit = max(0, (int)($_POST['tab_switch_limit'] ?? 0));
    $snapshot_interval = max(0, (int)($_POST['snapshot_interval'] ?? 0));
    $max_violations = max(0, (int)($_POST['max_violations'] ?? 0));

    // Reattempt feature
    $max_attempts = max(1, (int)($_POST['max_attempts'] ?? 1));

    $access_key = trim($_POST['access_key'] ?? '');

    // Validation
    if (
        empty($title) ||
        $duration <= 0 ||
        empty($start_time) ||
        empty($end_time)
    ) {

        $error = 'Please fill in all required fields.';

    } elseif (strtotime($end_time) <= strtotime($start_time)) {

        $error = 'End time must be after start time.';

    } else {

        $stmt = $conn->prepare("
            INSERT INTO exams
            (
                title,
                description,
                duration,
                total_marks,
                start_time,
                end_time,
                status,
                webcam_enabled,
                tab_switch_limit,
                fullscreen_required,
                randomize_questions,
                shuffle_options,
                access_key,
                audio_monitoring,
                face_detection,
                snapshot_interval,
                max_violations,
                max_attempts,
                created_by
            )

            VALUES
            (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )
        ");

        $userId = (int)$_SESSION['user_id'];

        $ak = !empty($access_key) ? $access_key : null;

        $stmt->bind_param(
            "ssiisssiiiiisiiiiii",

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
            $ak,
            $audio_monitoring,
            $face_detection,
            $snapshot_interval,
            $max_violations,
            $max_attempts,
            $userId
        );

        if ($stmt->execute()) {

            flash('success', 'Exam created successfully!');

            header('Location: ' . BASE_URL . '/admin/exams.php');
            exit;

        } else {

            $error = 'Failed to create exam: ' . $stmt->error;
        }

        $stmt->close();
    }
}

adminHeader('Create Exam', 'exams');
?>

<?php if ($error): ?>
    <div class="alert alert-danger">
        ⚠️ <?= sanitize($error) ?>
    </div>
<?php endif; ?>

<div class="card">

    <div class="card-header">
        <h2>Create Exam</h2>

        <a href="<?= BASE_URL ?>/admin/exams.php"
           class="btn btn-outline btn-sm">

            ← Back to Exams

        </a>
    </div>

    <div class="card-body">

        <form method="POST" action="">

            <!-- Exam Title -->
            <div class="form-group">
                <label>Exam Title *</label>

                <input
                    type="text"
                    name="title"
                    class="form-control"
                    required
                >
            </div>

            <!-- Description -->
            <div class="form-group">
                <label>Description</label>

                <textarea
                    name="description"
                    class="form-control"
                    rows="4"
                ></textarea>
            </div>

            <!-- Duration + Marks -->
            <div class="form-row">

                <div class="form-group">
                    <label>Duration (Minutes) *</label>

                    <input
                        type="number"
                        name="duration"
                        min="1"
                        class="form-control"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>Total Marks *</label>

                    <input
                        type="number"
                        name="total_marks"
                        min="0"
                        class="form-control"
                        required
                    >
                </div>

            </div>

            <!-- Start + End -->
            <div class="form-row">

                <div class="form-group">
                    <label>Start Time *</label>

                    <input
                        type="datetime-local"
                        name="start_time"
                        class="form-control"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>End Time *</label>

                    <input
                        type="datetime-local"
                        name="end_time"
                        class="form-control"
                        required
                    >
                </div>

            </div>

            <!-- Status -->
            <div class="form-group">

                <label>Status</label>

                <select name="status" class="form-control">

                    <option value="active">Active</option>

                    <option value="inactive">Inactive</option>

                </select>

            </div>

            <hr>

            <h3>Security Settings</h3>

            <!-- Access Key -->
            <div class="form-group">

                <label>Access Key</label>

                <input
                    type="text"
                    name="access_key"
                    class="form-control"
                    placeholder="Optional exam key"
                >
            </div>

            <!-- Max Attempts -->
            <div class="form-group">

                <label>Maximum Attempts</label>

                <input
                    type="number"
                    name="max_attempts"
                    min="1"
                    value="1"
                    class="form-control"
                >
            </div>

            <!-- Limits -->
            <div class="form-row">

                <div class="form-group">
                    <label>Tab Switch Limit</label>

                    <input
                        type="number"
                        name="tab_switch_limit"
                        min="0"
                        value="0"
                        class="form-control"
                    >
                </div>

                <div class="form-group">
                    <label>Snapshot Interval (Seconds)</label>

                    <input
                        type="number"
                        name="snapshot_interval"
                        min="0"
                        value="0"
                        class="form-control"
                    >
                </div>

                <div class="form-group">
                    <label>Max Violations</label>

                    <input
                        type="number"
                        name="max_violations"
                        min="0"
                        value="0"
                        class="form-control"
                    >
                </div>

            </div>

            <!-- Checkboxes -->
            <div class="form-group">

                <label>
                    <input type="checkbox" name="webcam_enabled">
                    Enable Webcam Monitoring
                </label>

                <br>

                <label>
                    <input type="checkbox" name="audio_monitoring">
                    Enable Audio Monitoring
                </label>

                <br>

                <label>
                    <input type="checkbox" name="face_detection">
                    Enable Face Detection
                </label>

                <br>

                <label>
                    <input type="checkbox" name="fullscreen_required">
                    Require Fullscreen
                </label>

                <br>

                <label>
                    <input type="checkbox" name="randomize_questions">
                    Randomize Questions
                </label>

                <br>

                <label>
                    <input type="checkbox" name="shuffle_options">
                    Shuffle Options
                </label>

            </div>

            <button type="submit" class="btn btn-primary">
                Create Exam
            </button>

        </form>

    </div>

</div>

<?php adminFooter(); ?>