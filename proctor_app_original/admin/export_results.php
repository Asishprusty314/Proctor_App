<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

// Handle export
if (isset($_GET['do_export'])) {
    $examFilter = (int)($_GET['exam_id'] ?? 0);
    $where = $examFilter ? "WHERE ea.exam_id = $examFilter" : "";
    
    $result = $conn->query("
        SELECT u.name as Student, u.email as Email, e.title as Exam, 
               ea.score as Score, ea.total_marks as Total_Marks,
               ea.tab_switches as Tab_Switches, ea.status as Status,
               ea.start_time as Start_Time, ea.end_time as End_Time
        FROM exam_attempts ea
        JOIN users u ON ea.user_id = u.id
        JOIN exams e ON ea.exam_id = e.id
        $where
        ORDER BY e.title, ea.score DESC
    ");
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    exportToCSV($data, 'exam_results_' . date('Y-m-d') . '.csv');
    exit;
}

require_once __DIR__ . '/layout.php';
$allExams = $conn->query("SELECT id, title FROM exams ORDER BY title");

adminHeader('Export Results', 'export');
?>

<div class="card">
    <div class="card-header">
        <h2>Export Results to CSV</h2>
    </div>
    <div class="card-body">
        <p style="margin-bottom: 24px; color: var(--text-secondary);">
            Download exam results as a CSV file. You can filter by exam or export all results.
        </p>
        
        <form method="GET" action="">
            <input type="hidden" name="do_export" value="1">
            <div class="form-group">
                <label>Select Exam (optional)</label>
                <select name="exam_id" class="form-control" style="max-width: 400px;">
                    <option value="0">All Exams</option>
                    <?php while ($e = $allExams->fetch_assoc()): ?>
                        <option value="<?= $e['id'] ?>"><?= sanitize($e['title']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">📥 Export CSV</button>
        </form>
    </div>
</div>

<?php adminFooter(); ?>
