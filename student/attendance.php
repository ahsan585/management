<?php
require_once __DIR__ . '/../config/init.php';
requireRole('student');

$pageTitle  = 'Attendance';
$activePage = 'attendance';
$pageCss    = ['student-attendance'];

$user = currentUser();

if (!$user || !$user['student_id']) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$stStmt = $pdo->prepare('SELECT * FROM students WHERE id = :id');
$stStmt->execute(['id' => $user['student_id']]);
$student = $stStmt->fetch();

if (!$student) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Summary counts
$summaryStmt = $pdo->prepare("
    SELECT
        COUNT(*) FILTER (WHERE status = 'present') AS present_days,
        COUNT(*) FILTER (WHERE status = 'absent')  AS absent_days,
        COUNT(*) FILTER (WHERE status = 'late')    AS late_days,
        COUNT(*) AS total_days
    FROM attendance
    WHERE student_id = :id
");
$summaryStmt->execute(['id' => $student['id']]);
$summary = $summaryStmt->fetch();

$attendancePct = $summary['total_days'] > 0
    ? round(($summary['present_days'] / $summary['total_days']) * 100, 1)
    : 0;

// Filter by month
$filterMonth = sanitize($_GET['month'] ?? '');

if ($filterMonth) {
    $aStmt = $pdo->prepare("
        SELECT attendance_date, status
        FROM attendance
        WHERE student_id = :id
          AND to_char(attendance_date, 'YYYY-MM') = :month
        ORDER BY attendance_date DESC
    ");
    $aStmt->execute(['id' => $student['id'], 'month' => $filterMonth]);
} else {
    $aStmt = $pdo->prepare(
        'SELECT attendance_date, status FROM attendance WHERE student_id = :id ORDER BY attendance_date DESC'
    );
    $aStmt->execute(['id' => $student['id']]);
}
$records = $aStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main student-attendance">
  <div class="topbar">
    <h1>My Attendance</h1>
  </div>

  <!-- Summary cards -->
  <div class="att-stats">
    <div class="att-stat-card present">
      <strong><?php echo (int)$summary['present_days']; ?></strong>
      <span>Present</span>
    </div>
    <div class="att-stat-card absent">
      <strong><?php echo (int)$summary['absent_days']; ?></strong>
      <span>Absent</span>
    </div>
    <div class="att-stat-card late">
      <strong><?php echo (int)$summary['late_days']; ?></strong>
      <span>Late</span>
    </div>
    <div class="att-stat-card total">
      <strong><?php echo $attendancePct; ?>%</strong>
      <span>Attendance</span>
    </div>
  </div>

  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:16px;">
      <h3 style="margin:0;">
        Class: <?php echo htmlspecialchars($student['class']); ?>
      </h3>
      <form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <input type="month" name="month"
               value="<?php echo htmlspecialchars($filterMonth); ?>"
               style="padding:7px 10px;border-radius:8px;border:1px solid rgba(0,0,0,.15);" />
        <button type="submit" class="btn outline" style="padding:7px 14px;">Filter</button>
        <?php if ($filterMonth): ?>
          <a href="?" class="btn outline" style="padding:7px 14px;">Clear</a>
        <?php endif; ?>
      </form>
    </div>

    <?php if (empty($records)): ?>
      <p class="small">
        <?php echo $filterMonth
          ? 'No attendance records for ' . htmlspecialchars($filterMonth) . '.'
          : 'No attendance records yet.'; ?>
      </p>
    <?php else: ?>
      <table class="table">
        <thead>
          <tr>
            <th>#</th>
            <th>Date</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($records as $i => $row): ?>
            <tr>
              <td class="small"><?php echo $i + 1; ?></td>
              <td><?php echo htmlspecialchars(date('d M Y', strtotime($row['attendance_date']))); ?></td>
              <td>
                <span class="status-badge <?php echo htmlspecialchars($row['status']); ?>">
                  <?php echo ucfirst(htmlspecialchars($row['status'])); ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
