<?php
require_once __DIR__ . '/../config/init.php';
requireRole('teacher');

$pageTitle  = 'Attendance';
$activePage = 'attendance';
$pageCss    = ['admin-attendance', 'teacher-attendance'];

$errors = [];

$pdo->exec("ALTER TABLE attendance DROP CONSTRAINT IF EXISTS attendance_status_check");
$pdo->exec("ALTER TABLE attendance ADD CONSTRAINT attendance_status_check CHECK (status IN ('present','absent','late','leave'))");

$classes = $pdo->query('SELECT name FROM classes ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);

$selectedClass = sanitize($_GET['class'] ?? '');
$selectedDate  = sanitize($_GET['date']  ?? date('Y-m-d'));

// ── SAVE ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $date     = sanitize($_POST['date']  ?? date('Y-m-d'));
    $class    = sanitize($_POST['class'] ?? '');
    $statuses = $_POST['status'] ?? [];

    if (!$class || !$date)  $errors[] = 'Class and date are required.';
    if (empty($statuses))   $errors[] = 'No students found.';

    if (empty($errors)) {
        $upsert = $pdo->prepare('
            INSERT INTO attendance (student_id, attendance_date, status, created_by)
            VALUES (:student_id, :date, :status, :created_by)
            ON CONFLICT (student_id, attendance_date)
            DO UPDATE SET status = EXCLUDED.status
        ');
        foreach ($statuses as $student_id => $status) {
            $status = in_array($status, ['present','absent','late','leave']) ? $status : 'absent';
            $upsert->execute([
                'student_id' => (int)$student_id,
                'date'       => $date,
                'status'     => $status,
                'created_by' => $_SESSION['user_id'],
            ]);
        }
        flash('success', 'Attendance saved.');
        header('Location: ' . BASE_URL . '/teacher/attendance.php?class='.urlencode($class).'&date='.urlencode($date));
        exit;
    }
    foreach ($errors as $_e) flash('error', $_e);
    header('Location: ' . BASE_URL . '/teacher/attendance.php?class='.urlencode(sanitize($_POST['class']??'')).'&date='.urlencode(sanitize($_POST['date']??date('Y-m-d'))));
    exit;
}

// ── DATA ──────────────────────────────────────────────────────
$students = [];
if ($selectedClass) {
    $sStmt = $pdo->prepare('SELECT id, name, student_key FROM students WHERE class = :class ORDER BY name');
    $sStmt->execute(['class' => $selectedClass]);
    $students = $sStmt->fetchAll();
}

$existing = [];
if ($selectedClass && $selectedDate) {
    $eStmt = $pdo->prepare('
        SELECT a.student_id, a.status FROM attendance a
        JOIN students s ON s.id = a.student_id
        WHERE s.class = :class AND a.attendance_date = :date
    ');
    $eStmt->execute(['class' => $selectedClass, 'date' => $selectedDate]);
    foreach ($eStmt->fetchAll() as $row) $existing[$row['student_id']] = $row['status'];
}

$summary = ['present' => 0, 'absent' => 0, 'late' => 0, 'leave' => 0];
foreach ($existing as $s) { if (isset($summary[$s])) $summary[$s]++; }

$attRows = [];
if ($selectedClass && $selectedDate) {
    $tStmt = $pdo->prepare('
        SELECT s.name, s.student_key, a.status FROM attendance a
        JOIN students s ON s.id = a.student_id
        WHERE s.class = :class AND a.attendance_date = :date ORDER BY s.name
    ');
    $tStmt->execute(['class' => $selectedClass, 'date' => $selectedDate]);
    $attRows = $tStmt->fetchAll();
}

$success = flash('success');

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main teacher-attendance">

  <div class="aa-topbar">
    <div class="aa-topbar-left">
      <h1 class="aa-title">Attendance</h1>
      <?php if ($selectedClass): ?>
        <span class="aa-meta-badge">
          <i class="fa-solid fa-graduation-cap"></i> Class <?php echo htmlspecialchars($selectedClass); ?>
          &nbsp;&bull;&nbsp;
          <i class="fa-solid fa-calendar"></i> <?php echo date('d M Y', strtotime($selectedDate)); ?>
        </span>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── SECTION 1: Filter ── -->
  <div class="aa-section">
    <div class="aa-section-head">
      <span class="aa-section-icon" style="background:#eff6ff;color:#2563eb;">
        <i class="fa-solid fa-sliders"></i>
      </span>
      <span class="aa-section-title">Select Class &amp; Date</span>
    </div>
    <form method="get" class="aa-filter-row">
      <div class="input-group">
        <label>Class</label>
        <select name="class" onchange="this.form.submit()">
          <option value="">— Select class —</option>
          <?php foreach ($classes as $cls): ?>
            <option value="<?php echo htmlspecialchars($cls); ?>"
              <?php echo $selectedClass === $cls ? 'selected' : ''; ?>>
              Class <?php echo htmlspecialchars($cls); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="input-group">
        <label>Date</label>
        <input type="date" name="date"
               value="<?php echo htmlspecialchars($selectedDate); ?>"
               onchange="this.form.submit()" />
      </div>
    </form>
  </div>

  <?php if ($selectedClass): ?>

    <?php if (!empty($students)): ?>
    <!-- ── SECTION 2: Mark Attendance ── -->
    <div class="aa-section">
      <div class="aa-section-head">
        <span class="aa-section-icon" style="background:#f0fdf4;color:#16a34a;">
          <i class="fa-solid fa-clipboard-check"></i>
        </span>
        <span class="aa-section-title">
          Mark Attendance
          <span class="aa-section-sub">Class <?php echo htmlspecialchars($selectedClass); ?> &bull; <?php echo date('d M Y', strtotime($selectedDate)); ?></span>
        </span>
        <?php if (!empty($existing)): ?>
          <div class="aa-summary-pills">
            <span class="aa-pill aa-pill--present">
              <i class="fa-solid fa-circle-check"></i> <?php echo $summary['present']; ?> Present
            </span>
            <span class="aa-pill aa-pill--absent">
              <i class="fa-solid fa-circle-xmark"></i> <?php echo $summary['absent']; ?> Absent
            </span>
            <span class="aa-pill aa-pill--late"><i class="fa-solid fa-clock"></i> <?php echo $summary['late']; ?> Late</span>
            <span class="aa-pill aa-pill--leave"><i class="fa-solid fa-calendar-minus"></i> <?php echo $summary['leave']; ?> Leave</span>
          </div>
        <?php endif; ?>
      </div>

      <form method="post" id="attForm">
        <input type="hidden" name="save_attendance" value="1" />
        <input type="hidden" name="class" value="<?php echo htmlspecialchars($selectedClass); ?>" />
        <input type="hidden" name="date"  value="<?php echo htmlspecialchars($selectedDate); ?>" />

        <div class="aa-quick-bar">
          <span class="aa-quick-label">Quick mark:</span>
          <button type="button" class="aa-quick-btn aa-quick-btn--present" onclick="markAll('present')">
            <i class="fa-solid fa-circle-check"></i> All Present
          </button>
          <button type="button" class="aa-quick-btn aa-quick-btn--absent" onclick="markAll('absent')">
            <i class="fa-solid fa-circle-xmark"></i> All Absent
          </button>
          <button type="button" class="aa-quick-btn aa-quick-btn--late" onclick="markAll('late')">
            <i class="fa-solid fa-clock"></i> All Late
          </button>
          <button type="button" class="aa-quick-btn aa-quick-btn--leave" onclick="markAll('leave')">
            <i class="fa-solid fa-calendar-minus"></i> All Leave
          </button>
        </div>

        <div class="aa-table-wrap">
          <table class="aa-table">
            <thead>
              <tr>
                <th class="aa-col-num">#</th>
                <th>Student</th>
                <th class="aa-col-id">ID</th>
                <th class="aa-col-status aa-col-present">Present</th>
                <th class="aa-col-status aa-col-absent">Absent</th>
                <th class="aa-col-status aa-col-late">Late</th>
                <th class="aa-col-status aa-col-leave">Leave</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($students as $i => $student):
                $status = $existing[$student['id']] ?? 'present';
              ?>
                <tr class="aa-row aa-row--<?php echo $status; ?>">
                  <td class="aa-col-num"><?php echo $i + 1; ?></td>
                  <td class="aa-student-name"><?php echo htmlspecialchars($student['name']); ?></td>
                  <td class="aa-col-id"><code><?php echo htmlspecialchars($student['student_key']); ?></code></td>
                  <td class="aa-col-status">
                    <label class="aa-radio-label aa-radio-label--present">
                      <input type="radio" name="status[<?php echo (int)$student['id']; ?>]" value="present"
                             <?php echo $status === 'present' ? 'checked' : ''; ?>
                             onchange="setRowStatus(this)" />
                    </label>
                  </td>
                  <td class="aa-col-status">
                    <label class="aa-radio-label aa-radio-label--absent">
                      <input type="radio" name="status[<?php echo (int)$student['id']; ?>]" value="absent"
                             <?php echo $status === 'absent' ? 'checked' : ''; ?>
                             onchange="setRowStatus(this)" />
                    </label>
                  </td>
                  <td class="aa-col-status">
                    <label class="aa-radio-label aa-radio-label--late">
                      <input type="radio" name="status[<?php echo (int)$student['id']; ?>]" value="late"
                             <?php echo $status === 'late' ? 'checked' : ''; ?>
                             onchange="setRowStatus(this)" />
                    </label>
                  </td>
                  <td class="aa-col-status">
                    <label class="aa-radio-label aa-radio-label--leave">
                      <input type="radio" name="status[<?php echo (int)$student['id']; ?>]" value="leave"
                             <?php echo $status === 'leave' ? 'checked' : ''; ?>
                             onchange="setRowStatus(this)" />
                    </label>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="aa-form-footer">
          <button type="submit" class="aa-save-btn">
            <i class="fa-solid fa-floppy-disk"></i> Save Attendance
          </button>
          <span class="aa-count-note"><?php echo count($students); ?> students</span>
        </div>
      </form>
    </div>
    <?php else: ?>
      <div class="aa-section">
        <p class="aa-empty-note"><i class="fa-solid fa-user-slash"></i> No students in Class <?php echo htmlspecialchars($selectedClass); ?>.</p>
      </div>
    <?php endif; ?>

    <!-- ── SECTION 3: Saved Record ── -->
    <?php if (!empty($attRows)): ?>
    <div class="aa-section">
      <div class="aa-section-head">
        <span class="aa-section-icon" style="background:#faf5ff;color:#7c3aed;">
          <i class="fa-solid fa-list-check"></i>
        </span>
        <span class="aa-section-title">
          Saved Record
          <span class="aa-section-sub">Class <?php echo htmlspecialchars($selectedClass); ?> &bull; <?php echo date('d M Y', strtotime($selectedDate)); ?></span>
        </span>
      </div>
      <div class="aa-table-wrap">
        <table class="aa-table">
          <thead>
            <tr>
              <th class="aa-col-num">#</th>
              <th>Student</th>
              <th class="aa-col-id">ID</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($attRows as $i => $row): ?>
              <tr>
                <td class="aa-col-num"><?php echo $i + 1; ?></td>
                <td class="aa-student-name"><?php echo htmlspecialchars($row['name']); ?></td>
                <td class="aa-col-id"><code><?php echo htmlspecialchars($row['student_key']); ?></code></td>
                <td>
                  <span class="aa-status-badge aa-status-badge--<?php echo $row['status']; ?>">
                    <?php echo ucfirst($row['status']); ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

  <?php endif; ?>

</div>

<script>
function markAll(status) {
    document.querySelectorAll('.aa-table input[type="radio"][value="' + status + '"]')
        .forEach(r => { r.checked = true; setRowStatus(r); });
}
function setRowStatus(radio) {
    const row = radio.closest('tr');
    if (!row) return;
    row.className = 'aa-row aa-row--' + radio.value;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
