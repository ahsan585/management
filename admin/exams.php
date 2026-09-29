<?php
require_once __DIR__ . '/../config/init.php';
requireRole('admin');

$pageTitle  = 'Exam Timetable';
$activePage = 'exams';
$pageCss    = ['admin-exams'];

// ── Ensure exam_timetable table exists ───────────────────────
$pdo->exec("
    CREATE TABLE IF NOT EXISTS exam_timetable (
        id          SERIAL       PRIMARY KEY,
        exam_name   VARCHAR(255) NOT NULL,
        class_id    VARCHAR(100) NOT NULL,
        subject_id  INTEGER      REFERENCES subjects(id) ON DELETE SET NULL,
        exam_date   DATE         NOT NULL,
        start_time  TIME         NOT NULL,
        end_time    TIME         NOT NULL,
        created_by  INTEGER      REFERENCES users(id) ON DELETE SET NULL,
        created_at  TIMESTAMPTZ  DEFAULT now()
    )
");

// ── DELETE ───────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $pdo->prepare('DELETE FROM exam_timetable WHERE id = :id')->execute(['id' => (int)$_GET['delete']]);
    flash('success', 'Entry deleted.');
    header('Location: exams.php'); exit;
}

// ── ADD ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exam_name  = trim($_POST['exam_name']  ?? '');
    $class_id   = trim($_POST['class_id']   ?? '');
    $subject_id = (int)($_POST['subject_id'] ?? 0);
    $exam_date  = trim($_POST['exam_date']  ?? '');
    $start_time = trim($_POST['start_time'] ?? '');
    $end_time   = trim($_POST['end_time']   ?? '');

    if (!$exam_name || !$class_id || !$subject_id || !$exam_date || !$start_time || !$end_time) {
        flash('error', 'All fields are required.');
        header('Location: exams.php'); exit;
    }

    if ($start_time >= $end_time) {
        flash('error', 'End time must be after start time.');
        header('Location: exams.php'); exit;
    }

    $pdo->prepare('
        INSERT INTO exam_timetable (exam_name, class_id, subject_id, exam_date, start_time, end_time, created_by)
        VALUES (:exam_name, :class_id, :subject_id, :exam_date, :start_time, :end_time, :created_by)
    ')->execute([
        'exam_name'  => $exam_name,
        'class_id'   => $class_id,
        'subject_id' => $subject_id,
        'exam_date'  => $exam_date,
        'start_time' => $start_time,
        'end_time'   => $end_time,
        'created_by' => $_SESSION['user_id'],
    ]);

    flash('success', 'Exam entry added.');
    header('Location: exams.php'); exit;
}

// ── FETCH ────────────────────────────────────────────────────
$classes  = $pdo->query('SELECT name FROM classes ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
$subjects = $pdo->query('SELECT id, subject_name, class_id FROM subjects ORDER BY class_id, subject_name')->fetchAll();

// Group subjects by class for JS
$subjectsByClass = [];
foreach ($subjects as $s) {
    $subjectsByClass[$s['class_id']][] = ['id' => $s['id'], 'name' => $s['subject_name']];
}

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

<div class="main admin-exams">

  <div class="et-topbar">
    <h1 class="et-title">Exam Timetable</h1>
    <span class="et-badge"><?php echo count($entries); ?> Entries</span>
  </div>

  <div class="et-layout">

    <!-- FORM -->
    <div class="et-form-col">
      <div class="card et-form-card">
        <div class="et-form-head">
          <span class="et-form-icon"><i class="fa-solid fa-calendar-check"></i></span>
          <div>
            <div class="et-form-title">Add Exam Entry</div>
            <div class="et-form-sub">Class → Subject → Date & Time</div>
          </div>
        </div>

        <?php if (empty($classes)): ?>
          <div class="et-warn">
            <i class="fa-solid fa-triangle-exclamation"></i>
            First add <a href="classes.php">Classes</a> and <a href="subjects.php">Subjects</a>.
          </div>
        <?php else: ?>
        <form method="post" id="examForm">
          <div class="input-group">
            <label>Exam Name</label>
            <input name="exam_name" placeholder="e.g. Mid Term 2026, Final Exam" required />
          </div>

          <div class="input-group">
            <label>Class</label>
            <select name="class_id" id="classSelect" required onchange="loadSubjects(this.value)">
              <option value="">— Select class —</option>
              <?php foreach ($classes as $cls): ?>
                <option value="<?php echo htmlspecialchars($cls); ?>">
                  <?php echo htmlspecialchars($cls); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="input-group">
            <label>Subject</label>
            <select name="subject_id" id="subjectSelect" required>
              <option value="">— Select class first —</option>
            </select>
          </div>

          <div class="input-group">
            <label>Exam Date</label>
            <input type="date" name="exam_date" required />
          </div>

          <div class="et-time-row">
            <div class="input-group">
              <label>Start Time</label>
              <input type="time" name="start_time" required />
            </div>
            <div class="input-group">
              <label>End Time</label>
              <input type="time" name="end_time" required />
            </div>
          </div>

          <button type="submit" class="et-add-btn">
            <i class="fa-solid fa-plus"></i> Add Entry
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- TIMETABLE -->
    <div class="et-list-col">
      <?php if (empty($byClass)): ?>
        <div class="card et-empty">
          <i class="fa-solid fa-calendar-xmark"></i>
          <p>No exam entries yet.<br>Add entries using the form.</p>
        </div>
      <?php else: ?>
        <?php foreach ($byClass as $cls => $rows): ?>
          <div class="card et-class-card">
            <div class="et-class-head">
              <span class="et-class-badge">
                <i class="fa-solid fa-graduation-cap"></i>
                Class <?php echo htmlspecialchars($cls); ?>
              </span>
              <span class="et-class-count"><?php echo count($rows); ?> exams</span>
            </div>
            <div class="et-table-wrap">
              <table class="table et-table">
                <thead>
                  <tr>
                    <th>Exam Name</th>
                    <th>Subject</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($rows as $r): ?>
                    <tr>
                      <td>
                        <span class="et-exam-name">
                          <i class="fa-solid fa-file-pen"></i>
                          <?php echo htmlspecialchars($r['exam_name']); ?>
                        </span>
                      </td>
                      <td>
                        <span class="et-subject-badge">
                          <?php echo htmlspecialchars($r['subject_name'] ?? '—'); ?>
                        </span>
                      </td>
                      <td>
                        <span class="et-date">
                          <i class="fa-solid fa-calendar"></i>
                          <?php echo date('d M Y', strtotime($r['exam_date'])); ?>
                        </span>
                      </td>
                      <td>
                        <span class="et-time">
                          <i class="fa-solid fa-clock"></i>
                          <?php echo date('h:i A', strtotime($r['start_time'])); ?>
                          — <?php echo date('h:i A', strtotime($r['end_time'])); ?>
                        </span>
                      </td>
                      <td>
                        <a class="et-del-btn"
                           href="?delete=<?php echo (int)$r['id']; ?>"
                           onclick="return confirm('Delete this entry?')">
                          <i class="fa-solid fa-trash"></i>
                        </a>
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

  </div>
</div>

<script>
var subjectsByClass = <?php echo json_encode($subjectsByClass); ?>;

function loadSubjects(cls) {
    var sel = document.getElementById('subjectSelect');
    sel.innerHTML = '<option value="">— Select subject —</option>';
    if (!cls || !subjectsByClass[cls]) {
        sel.innerHTML = '<option value="">— No subjects for this class —</option>';
        return;
    }
    subjectsByClass[cls].forEach(function(s) {
        var opt = document.createElement('option');
        opt.value = s.id;
        opt.textContent = s.name;
        sel.appendChild(opt);
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
