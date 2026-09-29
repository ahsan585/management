<?php
require_once __DIR__ . '/../config/init.php';
requireRole('teacher');

$pageTitle  = 'Exam Timetable';
$activePage = 'exams';
$pageCss    = ['teacher-exams'];

$entries = $pdo->query("
    SELECT et.*, s.subject_name
    FROM exam_timetable et
    LEFT JOIN subjects s ON s.id = et.subject_id
    ORDER BY et.class_id, et.exam_date, et.start_time
")->fetchAll();

$byClass = [];
foreach ($entries as $e) $byClass[$e['class_id']][] = $e;

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main teacher-exams">

  <div class="te-topbar">
    <div class="te-topbar-left">
      <h1 class="te-title">Exam Timetable</h1>
      <span class="te-count-badge"><?php echo count($entries); ?> Exams</span>
    </div>
  </div>

  <?php if (empty($byClass)): ?>
    <div class="card te-empty">
      <i class="fa-solid fa-calendar-xmark"></i>
      <p>No exam timetable entries yet.</p>
    </div>
  <?php else: ?>
    <?php foreach ($byClass as $cls => $rows): ?>
      <div class="card te-class-card">
        <div class="te-class-head">
          <span class="te-class-badge">
            <i class="fa-solid fa-graduation-cap"></i> Class <?php echo htmlspecialchars($cls); ?>
          </span>
          <span class="te-class-count"><?php echo count($rows); ?> exams</span>
        </div>
        <div class="te-table-wrap">
          <table class="table te-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Exam Name</th>
                <th>Subject</th>
                <th>Date</th>
                <th>Time</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $i => $r):
                $isToday = date('Y-m-d', strtotime($r['exam_date'])) === date('Y-m-d');
              ?>
                <tr <?php echo $isToday ? 'class="te-row-today"' : ''; ?>>
                  <td class="te-col-num"><?php echo $i + 1; ?></td>
                  <td>
                    <?php if ($isToday): ?>
                      <span class="te-today-badge">TODAY</span>
                    <?php endif; ?>
                    <span class="te-exam-name"><?php echo htmlspecialchars($r['exam_name']); ?></span>
                  </td>
                  <td>
                    <span class="te-subject-badge">
                      <?php echo htmlspecialchars($r['subject_name'] ?? '—'); ?>
                    </span>
                  </td>
                  <td>
                    <span class="te-date">
                      <i class="fa-solid fa-calendar" style="color:#3b82f6;"></i>
                      <?php echo date('d M Y', strtotime($r['exam_date'])); ?>
                    </span>
                  </td>
                  <td>
                    <span class="te-time">
                      <i class="fa-solid fa-clock" style="color:#10b981;"></i>
                      <?php echo date('h:i A', strtotime($r['start_time'])); ?> — <?php echo date('h:i A', strtotime($r['end_time'])); ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
