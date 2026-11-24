<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/layout.php';

$editId = (int)($_GET['edit'] ?? 0);
$examId = (int)($_GET['exam_id'] ?? 0);
$question = null;

if ($editId) {
    $question = $conn->query("SELECT * FROM questions WHERE id = $editId")->fetch_assoc();
    if ($question) $examId = $question['exam_id'];
}

$allExams = $conn->query("SELECT id, title FROM exams ORDER BY title");
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $qExamId = (int)($_POST['exam_id'] ?? 0);
    $type = $_POST['type'] ?? 'mcq';
    $qText = trim($_POST['question'] ?? '');
    $marks = (int)($_POST['marks'] ?? 1);
    $optA = trim($_POST['option_a'] ?? '');
    $optB = trim($_POST['option_b'] ?? '');
    $optC = trim($_POST['option_c'] ?? '');
    $optD = trim($_POST['option_d'] ?? '');
    $correct = isset($_POST['correct_answer']) ? implode(',', $_POST['correct_answer']) : '';

    if (empty($qText) || !$qExamId) {
        $error = 'Please fill in all required fields.';
    } elseif ($type === 'mcq' && (empty($optA) || empty($optB) || empty($correct))) {
        $error = 'MCQ requires at least options A & B and a correct answer.';
    } else {
        if ($type !== 'mcq') {
            $optA = $optB = $optC = $optD = $correct = null;
        }

        if ($editId) {
            $stmt = $conn->prepare("UPDATE questions SET exam_id=?, type=?, question=?, option_a=?, option_b=?, option_c=?, option_d=?, correct_answer=?, marks=? WHERE id=?");
            $stmt->bind_param("isssssssii", $qExamId, $type, $qText, $optA, $optB, $optC, $optD, $correct, $marks, $editId);
        } else {
            $stmt = $conn->prepare("INSERT INTO questions (exam_id, type, question, option_a, option_b, option_c, option_d, correct_answer, marks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssssssi", $qExamId, $type, $qText, $optA, $optB, $optC, $optD, $correct, $marks);
        }
        
        if ($stmt->execute()) {
            // Auto-update total marks for the exam
            $conn->query("UPDATE exams SET total_marks = (SELECT COALESCE(SUM(marks),0) FROM questions WHERE exam_id = $qExamId) WHERE id = $qExamId");
            
            flash('success', $editId ? 'Question updated!' : 'Question added!');
            header('Location: ' . BASE_URL . '/admin/questions.php?exam_id=' . $qExamId);
            exit;
        } else {
            $error = 'Failed to save question.';
        }
        $stmt->close();
    }
}

adminHeader($editId ? 'Edit Question' : 'Add Question', 'questions');
?>

<?php if ($error): ?>
    <div class="alert alert-danger">⚠️ <?= sanitize($error) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2><?= $editId ? 'Edit Question' : 'Add New Question' ?></h2>
        <a href="<?= BASE_URL ?>/admin/questions.php<?= $examId ? "?exam_id=$examId" : '' ?>" class="btn btn-outline btn-sm">← Back</a>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <div class="form-row">
                <div class="form-group">
                    <label>Select Exam *</label>
                    <select name="exam_id" class="form-control" required>
                        <option value="">-- Choose Exam --</option>
                        <?php while ($e = $allExams->fetch_assoc()): ?>
                            <option value="<?= $e['id'] ?>" <?= ($question ? $question['exam_id'] : $examId)==$e['id']?'selected':'' ?>>
                                <?= sanitize($e['title']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Question Type *</label>
                    <select name="type" id="q-type" class="form-control" onchange="toggleOptions()" required>
                        <option value="mcq" <?= ($question && $question['type']==='mcq') || !$question?'selected':'' ?>>Multiple Choice (MCQ)</option>
                        <option value="descriptive" <?= $question && $question['type']==='descriptive'?'selected':'' ?>>Descriptive</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Question Text *</label>
                <textarea name="question" class="form-control" rows="3" required><?= sanitize($question['question'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label>Marks *</label>
                <input type="number" name="marks" class="form-control" style="max-width: 150px;" min="1" value="<?= $question['marks'] ?? 1 ?>" required>
            </div>

            <div id="mcq-options">
                <h3 style="margin: 24px 0 16px; font-size: 16px; color: var(--text-secondary);">MCQ Options</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Option A *</label>
                        <input type="text" name="option_a" class="form-control" value="<?= sanitize($question['option_a'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Option B *</label>
                        <input type="text" name="option_b" class="form-control" value="<?= sanitize($question['option_b'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Option C</label>
                        <input type="text" name="option_c" class="form-control" value="<?= sanitize($question['option_c'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Option D</label>
                        <input type="text" name="option_d" class="form-control" value="<?= sanitize($question['option_d'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Correct Answer(s) * <small style="font-weight:400;color:var(--text-muted);">(Select one or more)</small></label>
                    <div style="display: flex; gap: 20px; margin-top: 6px;">
                        <?php 
                        $correctAnswers = explode(',', $question['correct_answer'] ?? '');
                        foreach (['A','B','C','D'] as $opt): 
                        ?>
                            <label class="form-check">
                                <input type="checkbox" name="correct_answer[]" value="<?= $opt ?>" 
                                    <?= in_array($opt, $correctAnswers) ? 'checked' : '' ?>>
                                Option <?= $opt ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div style="margin-top: 32px;">
                <button type="submit" class="btn btn-primary"><?= $editId ? 'Update Question' : 'Add Question' ?></button>
                <a href="<?= BASE_URL ?>/admin/questions.php<?= $examId ? "?exam_id=$examId" : '' ?>" class="btn btn-outline" style="margin-left: 8px;">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
function toggleOptions() {
    const type = document.getElementById('q-type').value;
    document.getElementById('mcq-options').style.display = type === 'mcq' ? 'block' : 'none';
}
toggleOptions();
</script>

<?php adminFooter(); ?>
