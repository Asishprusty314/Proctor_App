<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/layout.php';

$examId = (int)($_GET['exam_id'] ?? 0);

// Handle delete
if (isset($_GET['delete'])) {
    $qid = (int)$_GET['delete'];
    $conn->query("DELETE FROM questions WHERE id = $qid");
    flash('success', 'Question deleted.');
    header('Location: ' . BASE_URL . '/admin/questions.php' . ($examId ? "?exam_id=$examId" : ''));
    exit;
}

// Get exams for filter
$allExams = $conn->query("SELECT id, title FROM exams ORDER BY title");

// Get questions
$where = $examId ? "WHERE q.exam_id = $examId" : "";
$questions = $conn->query("
    SELECT q.*, e.title as exam_title 
    FROM questions q 
    JOIN exams e ON q.exam_id = e.id 
    $where
    ORDER BY q.exam_id, q.sort_order, q.id
");

$currentExamTitle = '';
if ($examId) {
    $r = $conn->query("SELECT title FROM exams WHERE id=$examId");
    $row = $r->fetch_assoc();
    $currentExamTitle = $row ? $row['title'] : '';
}

adminHeader($examId ? "Questions: $currentExamTitle" : 'All Questions', 'questions');
?>

<?php if ($msg = flash('success')): ?>
    <div class="alert alert-success">✓ <?= sanitize($msg) ?></div>
<?php endif; ?>

<div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
    <div style="display: flex; gap: 12px; align-items: center;">
        <form method="GET" style="display: flex; gap: 8px; align-items: center;">
            <select name="exam_id" class="form-control" style="width: 250px;" onchange="this.form.submit()">
                <option value="">All Exams</option>
                <?php while ($e = $allExams->fetch_assoc()): ?>
                    <option value="<?= $e['id'] ?>" <?= $examId==$e['id']?'selected':'' ?>><?= sanitize($e['title']) ?></option>
                <?php endwhile; ?>
            </select>
        </form>
        <span class="text-muted"><?= $questions->num_rows ?> question(s)</span>
    </div>
    <a href="<?= BASE_URL ?>/admin/add_question.php<?= $examId ? "?exam_id=$examId" : '' ?>" class="btn btn-primary">+ Add Question</a>
</div>

<div class="card">
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Exam</th>
                    <th>Question</th>
                    <th>Type</th>
                    <th>Marks</th>
                    <th>Correct</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($questions->num_rows === 0): ?>
                    <tr><td colspan="7" class="text-center text-muted" style="padding: 40px;">No questions found</td></tr>
                <?php else: $i=1; while ($q = $questions->fetch_assoc()): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><small class="text-muted"><?= sanitize($q['exam_title']) ?></small></td>
                        <td style="max-width: 300px;">
                            <?= sanitize(substr($q['question'], 0, 80)) ?><?= strlen($q['question']) > 80 ? '...' : '' ?>
                        </td>
                        <td>
                            <span class="badge <?= $q['type']==='mcq'?'badge-info':'badge-warning' ?>">
                                <?= strtoupper($q['type']) ?>
                            </span>
                        </td>
                        <td><?= $q['marks'] ?></td>
                        <td>
                            <?php if ($q['type'] === 'mcq' && $q['correct_answer']): ?>
                                <?php foreach (explode(',', $q['correct_answer']) as $ca): ?>
                                    <span class="badge badge-success" style="margin-right: 3px;"><?= trim($ca) ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="text-muted">Manual</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group">
                                <a href="<?= BASE_URL ?>/admin/add_question.php?edit=<?= $q['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                                <a href="?exam_id=<?= $examId ?>&delete=<?= $q['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirmDelete()">Delete</a>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php adminFooter(); ?>
