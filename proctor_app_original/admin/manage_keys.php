<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/layout.php';

$examId = (int)($_GET['exam_id'] ?? 0);

// Handle bulk key generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_keys'])) {
    $gExamId = (int)$_POST['exam_id'];
    $count = min((int)$_POST['count'], 100);
    $prefix = trim($_POST['prefix'] ?? 'EX');
    
    for ($i = 0; $i < $count; $i++) {
        $key = strtoupper($prefix) . '-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
        $stmt = $conn->prepare("INSERT INTO exam_keys (exam_id, access_key) VALUES (?, ?)");
        $stmt->bind_param("is", $gExamId, $key);
        $stmt->execute();
        $stmt->close();
    }
    flash('success', "$count access keys generated!");
    header("Location: " . BASE_URL . "/admin/manage_keys.php?exam_id=$gExamId");
    exit;
}

// Handle single key delete
if (isset($_GET['delete'])) {
    $kid = (int)$_GET['delete'];
    $conn->query("DELETE FROM exam_keys WHERE id = $kid");
    header("Location: " . BASE_URL . "/admin/manage_keys.php?exam_id=$examId");
    exit;
}

$allExams = $conn->query("SELECT id, title FROM exams ORDER BY title");

$keys = null;
if ($examId) {
    $keys = $conn->query("
        SELECT ek.*, u.name as assigned_to, u2.name as used_by_name
        FROM exam_keys ek
        LEFT JOIN users u ON ek.user_id = u.id
        LEFT JOIN users u2 ON ek.used_by = u2.id
        WHERE ek.exam_id = $examId
        ORDER BY ek.created_at DESC
    ");
}

adminHeader('Access Keys', 'keys');
?>

<?php if ($msg = flash('success')): ?>
    <div class="alert alert-success"><?= sanitize($msg) ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:340px 1fr;gap:20px;">
    <!-- Generate keys panel -->
    <div class="card" style="height:fit-content;">
        <div class="card-header"><h2>Generate Keys</h2></div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="generate_keys" value="1">
                <div class="form-group">
                    <label>Exam</label>
                    <select name="exam_id" class="form-control" required>
                        <option value="">Select exam...</option>
                        <?php while ($e = $allExams->fetch_assoc()): ?>
                            <option value="<?= $e['id'] ?>" <?= $examId==$e['id']?'selected':'' ?>><?= sanitize($e['title']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Number of keys</label>
                    <input type="number" name="count" class="form-control" value="10" min="1" max="100">
                </div>
                <div class="form-group">
                    <label>Key prefix</label>
                    <input type="text" name="prefix" class="form-control" value="EX" maxlength="5" placeholder="EX">
                </div>
                <button type="submit" class="btn btn-primary btn-block">Generate Keys</button>
            </form>
        </div>
    </div>

    <!-- Keys list -->
    <div class="card">
        <div class="card-header">
            <h2>Keys <?= $examId ? "($keys->num_rows)" : '' ?></h2>
            <form method="GET" style="display:flex;gap:8px;">
                <select name="exam_id" class="form-control" style="width:200px;" onchange="this.form.submit()">
                    <option value="">Filter by exam...</option>
                    <?php 
                    $allExams->data_seek(0);
                    while ($e = $allExams->fetch_assoc()): ?>
                        <option value="<?= $e['id'] ?>" <?= $examId==$e['id']?'selected':'' ?>><?= sanitize($e['title']) ?></option>
                    <?php endwhile; ?>
                </select>
            </form>
        </div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead><tr><th>Key</th><th>Assigned To</th><th>Status</th><th>Used By</th><th>Used At</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if (!$keys || $keys->num_rows === 0): ?>
                    <tr><td colspan="6" class="text-center text-muted" style="padding:40px;">
                        <?= $examId ? 'No keys generated for this exam' : 'Select an exam to view keys' ?>
                    </td></tr>
                <?php else: while ($k = $keys->fetch_assoc()): ?>
                    <tr>
                        <td><code style="font-family:'JetBrains Mono',monospace;font-size:13px;font-weight:600;letter-spacing:1px;"><?= sanitize($k['access_key']) ?></code></td>
                        <td><?= $k['assigned_to'] ? sanitize($k['assigned_to']) : '<span class="text-muted">Any user</span>' ?></td>
                        <td>
                            <?php if ($k['is_used']): ?>
                                <span class="badge badge-success">Used</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Available</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $k['used_by_name'] ? sanitize($k['used_by_name']) : '-' ?></td>
                        <td class="text-muted"><small><?= $k['used_at'] ? formatDateTime($k['used_at']) : '-' ?></small></td>
                        <td>
                            <?php if (!$k['is_used']): ?>
                                <a href="?exam_id=<?= $examId ?>&delete=<?= $k['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this key?')">Delete</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php adminFooter(); ?>
