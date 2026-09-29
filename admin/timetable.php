<?php
require_once __DIR__ . '/../config/init.php';
requireRole('admin');

$pageTitle  = 'Timetable';
$activePage = 'timetable';
$pageCss    = ['admin-buttons', 'timetable'];

$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

// Classes from classes table
$classes = $pdo->query('SELECT name FROM classes ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);

// Subjects grouped by class for JS dropdown
$allSubjects = $pdo->query('SELECT id, subject_name, class_id FROM subjects ORDER BY class_id, subject_name')->fetchAll();
$subjectsByClass = [];
foreach ($allSubjects as $s) {
    $subjectsByClass[$s['class_id']][] = ['id' => $s['id'], 'name' => $s['subject_name']];
}

// Teachers list
$teachers = $pdo->query('SELECT name FROM teachers ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);

// DELETE
if (isset($_GET['delete'])) {
    $pdo->prepare('DELETE FROM timetable WHERE id = :id')->execute(['id' => (int)$_GET['delete']]);
    flash('success', 'Entry deleted.');
    $back = isset($_GET['class']) ? '?class=' . urlencode($_GET['class']) : '';
    header('Location: timetable.php' . $back); exit;
}

// ADD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_id   = trim($_POST['class_id']   ?? '');
    $day        = trim($_POST['day']        ?? '');
    $period     = (int)($_POST['period']    ?? 0);
    $subject    = trim($_POST['subject']    ?? '');
    $teacher    = trim($_POST['teacher']    ?? '');
    $start_time = trim($_POST['start_time'] ?? '');
    $end_time   = trim($_POST['end_time']   ?? '');

    if (!$class_id || !$day || !$period || !$subject || !$start_time || !$end_time) {
        flash('error', 'All fields except teacher are required.');
        header('Location: timetable.php'); exit;
    }

    $pdo->prepare('
        INSERT INTO timetable (class_id, day, period, subject, teacher, start_time, end_time)
        VALUES (:class_id, :day, :period, :subject, :teacher, :start_time, :end_time)
        ON CONFLICT (class_id, day, period)
        DO UPDATE SET subject=EXCLUDED.subject, teacher=EXCLUDED.teacher,
                      start_time=EXCLUDED.start_time, end_time=EXCLUDED.end_time
    ')->execute([
        'class_id'   => $class_id,
        'day'        => $day,
        'period'     => $period,
        'subject'    => $subject,
        'teacher'    => $teacher,
        'start_time' => $start_time,
        'end_time'   => $end_time,
    ]);
    flash('success', 'Timetable entry saved.');
    header('Location: timetable.php?class=' . urlencode($class_id)); exit;
}

$filterClass = trim($_GET['class'] ?? '');

$ttRows = [];
if ($filterClass) {
    $stmt = $pdo->prepare('SELECT * FROM timetable WHERE class_id = :c ORDER BY day, period');
    $stmt->execute(['c' => $filterClass]);
    foreach ($stmt->fetchAll() as $row) {
        $ttRows[$row['day']][] = $row;
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main admin-timetable">
  <div class="topbar"><h1>Timetable</h1></div>

  <!-- ADD FORM -->
  <div class="card">
    <div class="pro-card-head">
      <span class="pro-head-icon" style="background:#dbeafe;color:#2563eb;">
        <i class="fa-solid fa-calendar-plus"></i>
      </span>
      <h3>Add / Update Entry</h3>
    </div>

    <?php if (empty($classes)): ?>
      <div style="padding:12px 14px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;font-size:0.85rem;color:#92400e;display:flex;align-items:center;gap:8px;">
        <i class="fa-solid fa-triangle-exclamation"></i>
        First add <a href="classes.php" style="color:#1d4ed8;font-weight:700;text-decoration:none;">Classes</a> and
        <a href="subjects.php" style="color:#1d4ed8;font-weight:700;text-decoration:none;">Subjects</a>.
      </div>
    <?php else: ?>
    <form method="post" id="ttForm">
      <div class="form-row">
        <div class="input-group">
          <label>Class</label>
          <select name="class_id" id="ttClass" required onchange="ttLoadSubjects(this.value)">
            <option value="">— Select class —</option>
            <?php foreach ($classes as $cls): ?>
              <option value="<?php echo htmlspecialchars($cls); ?>"
                <?php echo (($_POST['class_id'] ?? $filterClass) === $cls) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($cls); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="input-group">
          <label>Day</label>
          <select name="day" required>
            <option value="">— Select day —</option>
            <?php foreach ($days as $d): ?>
              <option value="<?php echo $d; ?>"
                <?php echo (($_POST['day'] ?? '') === $d) ? 'selected' : ''; ?>>
                <?php echo $d; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="input-group">
          <label>Period #</label>
          <input type="number" name="period" min="1" max="10"
                 value="<?php echo htmlspecialchars($_POST['period'] ?? ''); ?>"
                 placeholder="e.g. 1" required />
        </div>
        <div class="input-group">
          <label>Subject</label>
          <select name="subject" id="ttSubject" required>
            <option value="">— Select class first —</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="input-group">
          <label>Teacher <span style="color:#94a3b8;font-weight:400;">(optional)</span></label>
          <select name="teacher">
            <option value="">— Select teacher —</option>
            <?php foreach ($teachers as $t): ?>
              <option value="<?php echo htmlspecialchars($t); ?>"
                <?php echo (($_POST['teacher'] ?? '') === $t) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($t); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="input-group">
          <label>Start Time</label>
          <input type="time" name="start_time"
                 value="<?php echo htmlspecialchars($_POST['start_time'] ?? ''); ?>" required />
        </div>
      </div>
      <div class="form-row">
        <div class="input-group">
          <label>End Time</label>
          <input type="time" name="end_time"
                 value="<?php echo htmlspecialchars($_POST['end_time'] ?? ''); ?>" required />
        </div>
        <div class="input-group"></div>
      </div>
      <button type="submit" class="btn"><i class="fa-solid fa-plus"></i> Save Entry</button>
    </form>
    <?php endif; ?>
  </div>

  <!-- VIEW -->
  <div class="card">
    <div class="pro-card-head">
      <span class="pro-head-icon" style="background:#dcfce7;color:#16a34a;">
        <i class="fa-solid fa-table-cells"></i>
      </span>
      <h3>View Timetable</h3>
      <form method="get" style="display:flex;gap:8px;align-items:center;margin-left:auto;">
        <select name="class" onchange="this.form.submit()" class="pro-filter-select">
          <option value="">— Select class —</option>
          <?php foreach ($classes as $cls): ?>
            <option value="<?php echo htmlspecialchars($cls); ?>"
              <?php echo $filterClass === $cls ? 'selected' : ''; ?>>
              Class: <?php echo htmlspecialchars($cls); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if ($filterClass): ?>
          <a href="?" class="btn outline" style="padding:7px 12px;">Clear</a>
        <?php endif; ?>
      </form>
    </div>

    <?php if (!$filterClass): ?>
      <p class="small">Select a class to view its timetable.</p>
    <?php elseif (empty($ttRows)): ?>
      <p class="small">No timetable entries for Class <?php echo htmlspecialchars($filterClass); ?>.</p>
    <?php else: ?>
      <?php foreach ($days as $day):
        if (!isset($ttRows[$day])) continue; ?>
        <div class="tt-day-group">
          <div class="tt-day-label"><?php echo $day; ?></div>
          <table class="table">
            <thead>
              <tr>
                <th>Period</th><th>Time</th><th>Subject</th><th>Teacher</th><th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($ttRows[$day] as $row): ?>
                <tr>
                  <td><span class="tt-period-badge"><?php echo (int)$row['period']; ?></span></td>
                  <td class="small"><?php echo htmlspecialchars(substr($row['start_time'],0,5).' – '.substr($row['end_time'],0,5)); ?></td>
                  <td><?php echo htmlspecialchars($row['subject']); ?></td>
                  <td class="small"><?php echo htmlspecialchars($row['teacher'] ?? '—'); ?></td>
                  <td>
                    <a class="btn delete-btn"
                       href="?delete=<?php echo (int)$row['id']; ?>&class=<?php echo urlencode($filterClass); ?>"
                       onclick="return confirm('Delete this entry?')">
                      <i class="fa-solid fa-trash"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<script>
var ttSubjects = <?php echo json_encode($subjectsByClass); ?>;

function ttLoadSubjects(cls) {
    var sel = document.getElementById('ttSubject');
    sel.innerHTML = '';
    if (!cls || !ttSubjects[cls] || ttSubjects[cls].length === 0) {
        sel.innerHTML = '<option value="">— No subjects for this class —</option>';
        return;
    }
    var def = document.createElement('option');
    def.value = ''; def.textContent = '— Select subject —';
    sel.appendChild(def);
    ttSubjects[cls].forEach(function(s) {
        var opt = document.createElement('option');
        opt.value = s.name;
        opt.textContent = s.name;
        sel.appendChild(opt);
    });
}

// Auto-load if class already selected (on page load)
var preClass = document.getElementById('ttClass');
if (preClass && preClass.value) ttLoadSubjects(preClass.value);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
