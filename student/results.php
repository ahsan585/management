<?php
require_once __DIR__ . '/../config/init.php';
requireRole('student');

$pageTitle  = 'My Results';
$activePage = 'results';
$pageCss    = ['student-results'];

$user = currentUser();

// Get student row
$stStmt = $pdo->prepare('SELECT * FROM students WHERE id = :id');
$stStmt->execute(['id' => $user['student_id']]);
$student = $stStmt->fetch();

// Grade helper
function getGrade(float $pct): string {
    if ($pct >= 80) return 'A+';
    if ($pct >= 70) return 'A';
    if ($pct >= 60) return 'B';
    if ($pct >= 50) return 'C';
    if ($pct >= 45) return 'D';
    return 'F';
}

// Remark helper
function getRemark(float $pct): string {
    if ($pct >= 80) return 'Excellent';
    if ($pct >= 70) return 'Very Good';
    if ($pct >= 60) return 'Good';
    if ($pct >= 50) return 'Satisfactory';
    if ($pct >= 45) return 'Pass';
    return 'Fail';
}

// Grade badge CSS class
function gradeClass(string $grade): string {
    return match($grade) {
        'A+', 'A' => 'grade-ap',
        'B'       => 'grade-b',
        'C'       => 'grade-c',
        'D'       => 'grade-d',
        default   => 'grade-f',
    };
}

// Fetch from results table only (admin entered results)
$stmt2 = $pdo->prepare("
    SELECT
        r.marks        AS marks_obtained,
        r.total_marks,
        r.exam_name,
        r.exam_class,
        sub.subject_name,
        et.exam_date,
        NULL           AS exam_type
    FROM results r
    JOIN subjects sub ON sub.id = r.subject_id
    LEFT JOIN exam_timetable et
           ON et.exam_name  = r.exam_name
          AND et.class_id   = r.exam_class
          AND et.subject_id = r.subject_id
    WHERE r.student_id = :id
    ORDER BY et.exam_date DESC, sub.subject_name ASC
");
$stmt2->execute(['id' => $student['id']]);
$allRows = $stmt2->fetchAll();

// Group by exam_name
$grouped = [];
foreach ($allRows as $row) {
    $key = $row['exam_name'];
    if (!isset($grouped[$key])) {
        $grouped[$key] = [
            'exam_name'  => $row['exam_name'],
            'exam_date'  => $row['exam_date'] ?? null,
            'exam_type'  => $row['exam_type'] ?? '',
            'subjects'   => [],
            'total_obt'  => 0,
            'total_pos'  => 0,
        ];
    }
    $grouped[$key]['subjects'][] = [
        'subject_name'   => $row['subject_name'],
        'marks_obtained' => $row['marks_obtained'],
        'total_marks'    => $row['total_marks'],
    ];
    $grouped[$key]['total_obt'] += $row['marks_obtained'];
    $grouped[$key]['total_pos'] += $row['total_marks'];
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main student-results">
  <div class="topbar">
    <h1>My Results</h1>
  </div>

  <?php if (empty($grouped)): ?>
    <div class="card">
      <p class="small">No results available yet.</p>
    </div>
  <?php else: ?>

    <?php foreach ($grouped as $entry):
      $pct    = $entry['total_pos'] > 0
                  ? round(($entry['total_obt'] / $entry['total_pos']) * 100, 2)
                  : 0;
      $grade  = getGrade($pct);
      $remark = getRemark($pct);
      $gClass = gradeClass($grade);
    ?>
      <div class="card result-card">

        <!-- Exam header -->
        <div class="result-header">
          <div>
            <h3><?php echo htmlspecialchars($entry['exam_name']); ?></h3>
            <span class="small">
              <?php echo htmlspecialchars($entry['exam_date']); ?>
              <?php if ($entry['exam_type']): ?>
                &nbsp;&bull;&nbsp;<?php echo htmlspecialchars($entry['exam_type']); ?>
              <?php endif; ?>
            </span>
          </div>
          <div class="grade-badge <?php echo $gClass; ?>">
            <?php echo $grade; ?>
          </div>
        </div>

        <!-- Subject-wise marks table -->
        <table class="table">
          <thead>
            <tr>
              <th>Subject</th>
              <th>Total Marks</th>
              <th>Marks Obtained</th>
              <th>%</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($entry['subjects'] as $sub):
              $subPct = $sub['total_marks'] > 0
                          ? round(($sub['marks_obtained'] / $sub['total_marks']) * 100, 1)
                          : 0;
            ?>
              <tr>
                <td><?php echo htmlspecialchars($sub['subject_name']); ?></td>
                <td><?php echo htmlspecialchars($sub['total_marks']); ?></td>
                <td><?php echo htmlspecialchars($sub['marks_obtained']); ?></td>
                <td><?php echo $subPct; ?>%</td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr class="total-row">
              <td><strong>Total</strong></td>
              <td><strong><?php echo htmlspecialchars($entry['total_pos']); ?></strong></td>
              <td><strong><?php echo htmlspecialchars($entry['total_obt']); ?></strong></td>
              <td><strong><?php echo $pct; ?>%</strong></td>
            </tr>
          </tfoot>
        </table>

        <!-- Summary bar -->
        <div class="result-summary">
          <div class="summary-item">
            <span class="summary-label">Percentage</span>
            <span class="summary-value"><?php echo $pct; ?>%</span>
          </div>
          <div class="summary-item">
            <span class="summary-label">Grade</span>
            <span class="summary-value grade-badge <?php echo $gClass; ?>"><?php echo $grade; ?></span>
          </div>
          <div class="summary-item">
            <span class="summary-label">Remark</span>
            <span class="summary-value remark-<?php echo strtolower(str_replace(' ', '-', $remark)); ?>">
              <?php echo $remark; ?>
            </span>
          </div>
        </div>

        <!-- Progress bar -->
        <div class="progress-wrap">
          <div class="progress-bar" style="width:<?php echo min($pct, 100); ?>%;"
               data-pct="<?php echo $pct; ?>"></div>
        </div>

      </div>
    <?php endforeach; ?>

  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
