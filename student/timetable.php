<?php
require_once __DIR__ . '/../config/init.php';
requireRole('student');

$pageTitle  = 'Timetable';
$activePage = 'timetable';
$pageCss    = ['timetable'];

$user = currentUser();
$stStmt = $pdo->prepare('SELECT * FROM students WHERE id = :id');
$stStmt->execute(['id' => $user['student_id']]);
$student = $stStmt->fetch();

$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

// Auto-load student's own class timetable
$ttRows = [];
if ($student['class']) {
    $stmt = $pdo->prepare('SELECT * FROM timetable WHERE class_id = :c ORDER BY day, period');
    $stmt->execute(['c' => $student['class']]);
    foreach ($stmt->fetchAll() as $row) {
        $ttRows[$row['day']][] = $row;
    }
}

// Exam timetable for student's class
$examRows = [];
if ($student['class']) {
    $stmt = $pdo->prepare("
        SELECT et.*, s.subject_name
        FROM exam_timetable et
        LEFT JOIN subjects s ON s.id = et.subject_id
        WHERE et.class_id = :c
        ORDER BY et.exam_date, et.start_time
    ");
    $stmt->execute(['c' => $student['class']]);
    $examRows = $stmt->fetchAll();
}

$today = date('l');

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main student-timetable">
  <div class="topbar">
    <h1>My Timetable</h1>
    <span class="small">Class: <?php echo htmlspecialchars($student['class']); ?></span>
  </div>

  <?php if (empty($ttRows)): ?>
    <div class="card">
      <p class="small">No timetable available for your class yet.</p>
    </div>
  <?php else: ?>

    <!-- Today highlight -->
    <?php if (isset($ttRows[$today])): ?>
    <div class="card today-card">
      <div class="tt-day-label today-label">
        <i class="fa-solid fa-calendar-day"></i> Today — <?php echo $today; ?>
      </div>
      <table class="table">
        <thead>
          <tr>
            <th>Period</th>
            <th>Time</th>
            <th>Subject</th>
            <th>Teacher</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($ttRows[$today] as $row): ?>
            <tr>
              <td><?php echo (int)$row['period']; ?></td>
              <td class="small"><?php echo htmlspecialchars(substr($row['start_time'],0,5) . ' – ' . substr($row['end_time'],0,5)); ?></td>
              <td><strong><?php echo htmlspecialchars($row['subject']); ?></strong></td>
              <td class="small"><?php echo htmlspecialchars($row['teacher'] ?? '—'); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <!-- Full week -->
    <div class="card">
      <h3>Full Week Schedule</h3>
      <?php foreach ($days as $day):
        if (!isset($ttRows[$day])) continue; ?>
        <div class="tt-day-group <?php echo $day === $today ? 'is-today' : ''; ?>">
          <div class="tt-day-label"><?php echo $day; ?><?php echo $day === $today ? ' <span class="today-badge">Today</span>' : ''; ?></div>
          <table class="table">
            <thead>
              <tr>
                <th>Period</th>
                <th>Time</th>
                <th>Subject</th>
                <th>Teacher</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($ttRows[$day] as $row): ?>
                <tr>
                  <td><?php echo (int)$row['period']; ?></td>
                  <td class="small"><?php echo htmlspecialchars(substr($row['start_time'],0,5) . ' – ' . substr($row['end_time'],0,5)); ?></td>
                  <td><?php echo htmlspecialchars($row['subject']); ?></td>
                  <td class="small"><?php echo htmlspecialchars($row['teacher'] ?? '—'); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endforeach; ?>
    </div>

  <?php endif; ?>

  <!-- EXAM TIMETABLE -->
  <div class="card" style="margin-top:20px;">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid #f1f5f9;">
      <span style="background:#fef3c7;border:1px solid #fde68a;color:#d97706;width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:0.95rem;">
        <i class="fa-solid fa-calendar-check"></i>
      </span>
      <div>
        <div style="font-weight:700;font-size:0.95rem;color:#0f172a;">Exam Timetable</div>
        <div style="font-size:0.75rem;color:#64748b;">Class <?php echo htmlspecialchars($student['class']); ?></div>
      </div>
    </div>

    <?php if (empty($examRows)): ?>
      <div style="text-align:center;padding:32px 20px;color:#94a3b8;">
        <i class="fa-solid fa-calendar-xmark" style="font-size:2rem;display:block;margin-bottom:10px;opacity:.35;"></i>
        <p style="margin:0;font-size:0.88rem;">No exams scheduled yet.</p>
      </div>
    <?php else: ?>
      <div style="overflow-x:auto;">
        <table class="table">
          <thead>
            <tr>
              <th>Exam Name</th>
              <th>Subject</th>
              <th>Date</th>
              <th>Time</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($examRows as $ex): ?>
              <?php
                $isPast    = strtotime($ex['exam_date']) < strtotime('today');
                $isToday   = date('Y-m-d', strtotime($ex['exam_date'])) === date('Y-m-d');
                $rowStyle  = $isToday ? 'background:#fffbeb;' : ($isPast ? 'opacity:.6;' : '');
              ?>
              <tr style="<?php echo $rowStyle; ?>">
                <td style="font-weight:600;">
                  <?php if ($isToday): ?>
                    <span style="background:#fef3c7;color:#d97706;font-size:0.72rem;font-weight:700;padding:2px 7px;border-radius:4px;margin-right:6px;">TODAY</span>
                  <?php endif; ?>
                  <?php echo htmlspecialchars($ex['exam_name']); ?>
                </td>
                <td>
                  <span style="background:#f3e8ff;color:#7c3aed;padding:3px 10px;border-radius:20px;font-size:0.8rem;font-weight:600;">
                    <?php echo htmlspecialchars($ex['subject_name'] ?? '—'); ?>
                  </span>
                </td>
                <td>
                  <i class="fa-solid fa-calendar" style="color:#3b82f6;margin-right:5px;"></i>
                  <?php echo date('d M Y', strtotime($ex['exam_date'])); ?>
                </td>
                <td style="white-space:nowrap;">
                  <i class="fa-solid fa-clock" style="color:#10b981;margin-right:5px;"></i>
                  <?php echo date('h:i A', strtotime($ex['start_time'])); ?>
                  — <?php echo date('h:i A', strtotime($ex['end_time'])); ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
