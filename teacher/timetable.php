<?php
require_once __DIR__ . '/../config/init.php';
requireRole('teacher');

$pageTitle  = 'Timetable';
$activePage = 'timetable';
$pageCss    = ['teacher-timetable'];

$days    = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
$classes = $pdo->query('SELECT name FROM classes ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);

$filterClass = sanitize($_GET['class'] ?? '');

$ttRows = [];
if ($filterClass) {
    $stmt = $pdo->prepare('SELECT * FROM timetable WHERE class_id = :c ORDER BY day, period');
    $stmt->execute(['c' => $filterClass]);
    foreach ($stmt->fetchAll() as $row) $ttRows[$row['day']][] = $row;
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main teacher-timetable">

  <!-- Topbar -->
  <div class="tt-topbar">
    <div class="tt-topbar-left">
      <h1 class="tt-page-title">Timetable</h1>
    </div>
  </div>

  <!-- ── Section 1: Class Timetable ── -->
  <div class="card tt-section-card">
    <div class="tt-section-head">
      <span class="tt-section-icon tt-icon-blue">
        <i class="fa-solid fa-calendar-week"></i>
      </span>
      <div class="tt-section-info">
        <div class="tt-section-title">Class Timetable</div>
        <div class="tt-section-sub">Weekly schedule by class</div>
      </div>
      <form method="get" class="tt-filter-form">
        <select name="class" onchange="this.form.submit()" class="tt-select">
          <option value="">— Select class —</option>
          <?php foreach ($classes as $cls): ?>
            <option value="<?php echo htmlspecialchars($cls); ?>"
              <?php echo $filterClass === $cls ? 'selected' : ''; ?>>
              Class: <?php echo htmlspecialchars($cls); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if ($filterClass): ?>
          <a href="?" class="tt-clear-btn">Clear</a>
        <?php endif; ?>
      </form>
    </div>

    <?php if (!$filterClass): ?>
      <div class="tt-hint">
        <i class="fa-solid fa-circle-info"></i>
        Select a class above to view its weekly timetable.
      </div>
    <?php elseif (empty($ttRows)): ?>
      <div class="tt-empty-inline">
        <i class="fa-solid fa-calendar-xmark"></i>
        No timetable set for Class <?php echo htmlspecialchars($filterClass); ?> yet.
      </div>
    <?php else: ?>
      <?php foreach ($days as $day):
        if (!isset($ttRows[$day])) continue; ?>
        <div class="tt-day-block">
          <div class="tt-day-label">
            <i class="fa-solid fa-circle-dot"></i> <?php echo $day; ?>
            <span class="tt-day-count"><?php echo count($ttRows[$day]); ?> periods</span>
          </div>
          <div class="tt-table-wrap">
            <table class="tt-table">
              <thead>
                <tr>
                  <th class="tt-col-period">Period</th>
                  <th class="tt-col-time">Time</th>
                  <th>Subject</th>
                  <th>Teacher</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($ttRows[$day] as $row): ?>
                  <tr>
                    <td><span class="tt-period-pill"><?php echo (int)$row['period']; ?></span></td>
                    <td class="tt-time-cell">
                      <?php echo htmlspecialchars(substr($row['start_time'],0,5) . ' – ' . substr($row['end_time'],0,5)); ?>
                    </td>
                    <td class="tt-subject-cell"><?php echo htmlspecialchars($row['subject']); ?></td>
                    <td class="tt-teacher-cell"><?php echo htmlspecialchars($row['teacher'] ?? '—'); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
