<?php
require_once __DIR__ . '/../config/init.php';
requireRole('admin');

$pageTitle  = 'Results';
$activePage = 'results';
$pageCss    = ['admin-results'];

// ── Ensure exam_timetable exists ─────────────────────────────
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

// ── Migrate results table: drop exam_id FK, add exam_name+class_id ──
// Check if old exam_id column exists
$colCheck = $pdo->query("
    SELECT column_name FROM information_schema.columns
    WHERE table_name = 'results' AND column_name = 'exam_id'
")->fetch();

if ($colCheck) {
    // Add new columns if not present
    $pdo->exec("ALTER TABLE results ADD COLUMN IF NOT EXISTS exam_name VARCHAR(255)");
    $pdo->exec("ALTER TABLE results ADD COLUMN IF NOT EXISTS exam_class VARCHAR(100)");

    // Copy data from joined exams table (if exams table exists)
    $examsExists = $pdo->query("
        SELECT to_regclass('public.exams')
    ")->fetchColumn();

    if ($examsExists) {
        $pdo->exec("
            UPDATE results r
            SET exam_name  = e.exam_name,
                exam_class = e.class_id
            FROM exams e
            WHERE e.id = r.exam_id
              AND r.exam_name IS NULL
        ");
    }

    // Drop old FK column
    $pdo->exec("ALTER TABLE results DROP COLUMN IF EXISTS exam_id");

    // Drop old unique constraint if exists
    $pdo->exec("ALTER TABLE results DROP CONSTRAINT IF EXISTS results_student_id_exam_id_subject_id_key");
}

// Ensure new columns exist (fresh install)
$pdo->exec("ALTER TABLE results ADD COLUMN IF NOT EXISTS exam_name  VARCHAR(255)");
$pdo->exec("ALTER TABLE results ADD COLUMN IF NOT EXISTS exam_class VARCHAR(100)");

// Ensure new unique constraint
$pdo->exec("
    DO \$\$
    BEGIN
        IF NOT EXISTS (
            SELECT 1 FROM pg_constraint
            WHERE conname = 'results_student_exam_subject_unique'
        ) THEN
            ALTER TABLE results
            ADD CONSTRAINT results_student_exam_subject_unique
            UNIQUE (student_id, exam_name, exam_class, subject_id);
        END IF;
    END
    \$\$;
");

// ── DELETE ───────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $pdo->prepare('DELETE FROM results WHERE id = :id')->execute(['id' => (int)$_GET['delete']]);
    flash('success', 'Result deleted successfully.');
    header('Location: results.php'); exit;
}

// ── ADD RESULT ───────────────────────────────────────────────
if (isset($_POST['upload_result'])) {
    $exam_key   = trim($_POST['exam_key']   ?? '');  // "exam_name||class_id"
    $student_id = (int)($_POST['student_id'] ?? 0);
    $subject_id = (int)($_POST['subject_id'] ?? 0);
    $marks      = floatval($_POST['marks']   ?? 0);
    $total      = floatval($_POST['total']   ?? 100);

    $errors = [];

    // Parse exam_key
    $examParts = explode('||', $exam_key);
    $examName  = trim($examParts[0] ?? '');
    $examClass = trim($examParts[1] ?? '');

    if (!$examName || !$examClass) $errors[] = 'Please select an exam.';
    if (!$student_id)              $errors[] = 'Please select a student.';
    if (!$subject_id)              $errors[] = 'Please select a subject.';
    if ($total <= 0)               $errors[] = 'Total marks must be greater than 0.';
    if ($marks < 0)                $errors[] = 'Marks cannot be negative.';
    if ($marks > $total)           $errors[] = 'Marks cannot exceed total marks.';

    if (empty($errors)) {
        $ttCheck = $pdo->prepare('SELECT id FROM exam_timetable WHERE exam_name = :n AND class_id = :c LIMIT 1');
        $ttCheck->execute(['n' => $examName, 'c' => $examClass]);
        if (!$ttCheck->fetch()) $errors[] = 'This exam was not found in the timetable. Please add it to Exam Timetable first.';
    }

    if (empty($errors)) {
        try {
            $pdo->prepare('
                INSERT INTO results (student_id, exam_name, exam_class, subject_id, marks, total_marks, created_by)
                VALUES (:sid, :ename, :eclass, :subid, :marks, :total, :by)
                ON CONFLICT ON CONSTRAINT results_student_exam_subject_unique
                DO UPDATE SET marks = EXCLUDED.marks, total_marks = EXCLUDED.total_marks
            ')->execute([
                'sid'    => $student_id,
                'ename'  => $examName,
                'eclass' => $examClass,
                'subid'  => $subject_id,
                'marks'  => $marks,
                'total'  => $total,
                'by'     => $_SESSION['user_id'],
            ]);
            flash('success', 'Result saved successfully.');
        } catch (PDOException $e) {
            flash('error', 'DB Error: ' . htmlspecialchars($e->getMessage()));
        }
    } else {
        foreach ($errors as $err) flash('error', $err);
    }
    header('Location: results.php'); exit;
}

// ── FETCH DATA ───────────────────────────────────────────────
$classes  = $pdo->query('SELECT name FROM classes ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
$students = $pdo->query('SELECT id, name, class, student_key FROM students ORDER BY class, name')->fetchAll();
$subjects = $pdo->query('SELECT id, subject_name, class_id FROM subjects ORDER BY class_id, subject_name')->fetchAll();

// Distinct exams from exam_timetable (exam_name + class_id + earliest date)
$ttExams = $pdo->query("
    SELECT exam_name, class_id,
           MIN(exam_date) AS first_date
    FROM exam_timetable
    GROUP BY exam_name, class_id
    ORDER BY first_date DESC
")->fetchAll();

// Timetable entries grouped by exam_name||class_id
$timetableEntries = $pdo->query("
    SELECT et.exam_name, et.class_id, et.exam_date, et.start_time, et.end_time,
           s.id AS subject_id, s.subject_name
    FROM exam_timetable et
    LEFT JOIN subjects s ON s.id = et.subject_id
    ORDER BY et.exam_date, et.start_time
")->fetchAll();

$timetableByExam = [];
foreach ($timetableEntries as $t) {
    $key = $t['exam_name'] . '||' . $t['class_id'];
    $timetableByExam[$key][] = $t;
}

// Group for JS dropdowns
$studentsByClass = [];
foreach ($students as $s) {
    $studentsByClass[$s['class']][] = ['id' => $s['id'], 'name' => $s['name'] . ' (' . $s['student_key'] . ')'];
}

$subjectsByClass = [];
foreach ($subjects as $s) {
    $subjectsByClass[$s['class_id']][] = ['id' => $s['id'], 'name' => $s['subject_name']];
}

// Exams grouped by class for JS — key = "exam_name||class_id"
$examsByClass = [];
foreach ($ttExams as $e) {
    $examsByClass[$e['class_id']][] = [
        'key'        => $e['exam_name'] . '||' . $e['class_id'],
        'name'       => $e['exam_name'] . ' (' . date('d M Y', strtotime($e['first_date'])) . ')',
        'exam_name'  => $e['exam_name'],
        'class_id'   => $e['class_id'],
    ];
}

// Timetable for JS panel
$timetableForJs = [];
foreach ($timetableByExam as $key => $rows) {
    $timetableForJs[$key] = array_map(fn($r) => [
        'subject_id'   => $r['subject_id'],
        'subject_name' => $r['subject_name'],
        'exam_date'    => $r['exam_date'],
        'start_time'   => $r['start_time'],
        'end_time'     => $r['end_time'],
    ], $rows);
}

$noTimetable = empty($ttExams);
$filterClass = trim($_GET['class'] ?? '');

// ── FETCH RESULTS ────────────────────────────────────────────
$sql = "
    SELECT r.id          AS result_id,
           r.marks,
           r.total_marks,
           r.exam_name,
           r.exam_class  AS class_id,
           s.name        AS student,
           s.student_key,
           sub.subject_name,
           agg.total_obtained,
           agg.total_possible,
           ROUND((agg.total_obtained / NULLIF(agg.total_possible,0) * 100)::numeric, 2) AS overall_pct,
           CASE
               WHEN (agg.total_obtained / NULLIF(agg.total_possible,0) * 100) >= 80 THEN 'A+'
               WHEN (agg.total_obtained / NULLIF(agg.total_possible,0) * 100) >= 70 THEN 'A'
               WHEN (agg.total_obtained / NULLIF(agg.total_possible,0) * 100) >= 60 THEN 'B'
               WHEN (agg.total_obtained / NULLIF(agg.total_possible,0) * 100) >= 50 THEN 'C'
               WHEN (agg.total_obtained / NULLIF(agg.total_possible,0) * 100) >= 45 THEN 'D'
               ELSE 'F'
           END AS overall_grade,
           et.exam_date  AS tt_date,
           et.start_time AS tt_start,
           et.end_time   AS tt_end
    FROM results r
    JOIN students s   ON s.id   = r.student_id
    JOIN subjects sub ON sub.id = r.subject_id
    JOIN (
        SELECT student_id, exam_name, exam_class,
               SUM(marks)       AS total_obtained,
               SUM(total_marks) AS total_possible
        FROM results
        GROUP BY student_id, exam_name, exam_class
    ) agg ON agg.student_id = r.student_id
         AND agg.exam_name  = r.exam_name
         AND agg.exam_class = r.exam_class
    LEFT JOIN exam_timetable et
           ON et.exam_name  = r.exam_name
          AND et.class_id   = r.exam_class
          AND et.subject_id = r.subject_id
";

if ($filterClass !== '') {
    $stmt = $pdo->prepare($sql . ' WHERE r.exam_class = :c ORDER BY r.exam_name, s.name, sub.subject_name LIMIT 500');
    $stmt->execute(['c' => $filterClass]);
    $rawResults = $stmt->fetchAll();
} else {
    $rawResults = $pdo->query($sql . ' ORDER BY r.exam_name, s.name, sub.subject_name LIMIT 500')->fetchAll();
}

// Group by student + exam
$grouped = [];
foreach ($rawResults as $row) {
    $key = $row['student_key'] . '||' . $row['exam_name'] . '||' . $row['class_id'];
    if (!isset($grouped[$key])) {
        $ttKey     = $row['exam_name'] . '||' . $row['class_id'];
        $ttEntries = $timetableByExam[$ttKey] ?? [];
        // Get first exam date from timetable
        $firstDate = !empty($ttEntries) ? $ttEntries[0]['exam_date'] : null;
        $grouped[$key] = [
            'student'       => $row['student'],
            'student_key'   => $row['student_key'],
            'class_id'      => $row['class_id'],
            'exam_name'     => $row['exam_name'],
            'exam_date'     => $firstDate,
            'total_obt'     => $row['total_obtained'],
            'total_pos'     => $row['total_possible'],
            'overall_pct'   => $row['overall_pct'],
            'overall_grade' => $row['overall_grade'],
            'subjects'      => [],
            'has_timetable' => !empty($ttEntries),
        ];
    }
    $grouped[$key]['subjects'][] = [
        'subject_name' => $row['subject_name'],
        'marks'        => $row['marks'],
        'total_marks'  => $row['total_marks'],
        'result_id'    => $row['result_id'],
        'tt_date'      => $row['tt_date'],
        'tt_start'     => $row['tt_start'],
        'tt_end'       => $row['tt_end'],
    ];
}

$gradeColors = [
    'A+' => ['bg' => '#dcfce7', 'color' => '#16a34a'],
    'A'  => ['bg' => '#dbeafe', 'color' => '#2563eb'],
    'B'  => ['bg' => '#e0f2fe', 'color' => '#0369a1'],
    'C'  => ['bg' => '#fef9c3', 'color' => '#ca8a04'],
    'D'  => ['bg' => '#ffedd5', 'color' => '#ea580c'],
    'F'  => ['bg' => '#fee2e2', 'color' => '#dc2626'],
];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main admin-results">

  <div class="ar-topbar">
    <div class="ar-topbar-left">
      <h1 class="ar-title">Results</h1>
      <span class="ar-badge"><?php echo count($grouped); ?> Records</span>
    </div>
  </div>

  <div class="ar-layout">

    <!-- FORM -->
    <div class="ar-form-col">
      <div class="card ar-form-card">
        <div class="ar-form-head">
          <span class="ar-form-icon"><i class="fa-solid fa-chart-line"></i></span>
          <div>
            <div class="ar-form-title">Add Result</div>
            <div class="ar-form-sub">Class → Exam → Student → Subject → Marks</div>
          </div>
        </div>

        <?php if (empty($classes)): ?>
          <div class="ar-warn">
            <i class="fa-solid fa-triangle-exclamation"></i>
            First add <a href="classes.php">Classes</a>, <a href="subjects.php">Subjects</a> and students.
          </div>

        <?php elseif ($noTimetable): ?>
          <div class="ar-warn">
            <i class="fa-solid fa-triangle-exclamation"></i>
            No exams found. Please add an exam to <a href="exams.php">Exam Timetable</a> first.
          </div>

        <?php else: ?>
        <form method="post" id="resultForm">
          <input type="hidden" name="upload_result" value="1" />

          <div class="input-group">
            <label>Class</label>
            <select name="class_filter" id="classSelect" required onchange="arLoadClass(this.value)">
              <option value="">— Select class —</option>
              <?php foreach ($classes as $cls): ?>
                <option value="<?php echo htmlspecialchars($cls); ?>">
                  <?php echo htmlspecialchars($cls); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="input-group">
            <label>Exam</label>
            <select name="exam_key" id="examSelect" required onchange="arOnExamChange(this.value)">
              <option value="">— Select class first —</option>
            </select>
          </div>

          <!-- Timetable info panel -->
          <div id="ttInfoPanel" class="ar-tt-panel" style="display:none;"></div>

          <div class="input-group">
            <label>Student</label>
            <select name="student_id" id="studentSelect" required>
              <option value="">— Select class first —</option>
            </select>
          </div>

          <div class="input-group">
            <label>Subject</label>
            <select name="subject_id" id="subjectSelect" required>
              <option value="">— Select class first —</option>
            </select>
          </div>

          <div class="ar-marks-row">
            <div class="input-group">
              <label>Marks Obtained</label>
              <input type="number" name="marks" step="0.5" min="0" placeholder="e.g. 78" required />
            </div>
            <div class="input-group">
              <label>Total Marks</label>
              <input type="number" name="total" step="0.5" min="1" value="100" required />
            </div>
          </div>

          <button type="submit" class="ar-add-btn">
            <i class="fa-solid fa-floppy-disk"></i> Save Result
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- RESULTS LIST -->
    <div class="ar-list-col">

      <div class="ar-filter-bar">
        <form method="get" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
          <select name="class" class="pro-filter-select" onchange="this.form.submit()">
            <option value="">All Classes</option>
            <?php foreach ($classes as $cls): ?>
              <option value="<?php echo htmlspecialchars($cls); ?>"
                <?php echo $filterClass === $cls ? 'selected' : ''; ?>>
                Class <?php echo htmlspecialchars($cls); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if ($filterClass): ?>
            <a href="results.php" class="ar-clear-btn">
              <i class="fa-solid fa-xmark"></i> Clear
            </a>
          <?php endif; ?>
        </form>
        <span class="ar-result-count"><?php echo count($grouped); ?> results</span>
      </div>

      <?php if (empty($grouped)): ?>
        <div class="card ar-empty">
          <i class="fa-solid fa-chart-bar"></i>
          <p>No results yet.<br>Add a result using the form.</p>
        </div>
      <?php else: ?>
        <?php foreach ($grouped as $entry):
          $gc       = $gradeColors[$entry['overall_grade']] ?? $gradeColors['F'];
          $pct      = (float)$entry['overall_pct'];
          $barColor = $pct >= 80 ? '#16a34a' : ($pct >= 60 ? '#2563eb' : ($pct >= 45 ? '#d97706' : '#dc2626'));
        ?>
          <div class="card ar-result-card">

            <div class="ar-card-head">
              <div class="ar-student-info">
                <div class="ar-student-name">
                  <i class="fa-solid fa-user-graduate"></i>
                  <?php echo htmlspecialchars($entry['student']); ?>
                </div>
                <div class="ar-student-meta">
                  <span class="ar-class-badge">
                    <i class="fa-solid fa-graduation-cap"></i>
                    Class <?php echo htmlspecialchars($entry['class_id']); ?>
                  </span>
                  <span class="ar-id-badge">
                    <i class="fa-solid fa-id-card"></i>
                    <?php echo htmlspecialchars($entry['student_key']); ?>
                  </span>
                </div>
              </div>
              <div class="ar-exam-info">
                <div class="ar-exam-name">
                  <i class="fa-solid fa-file-pen"></i>
                  <?php echo htmlspecialchars($entry['exam_name']); ?>
                </div>
                <?php if ($entry['exam_date']): ?>
                  <div class="ar-exam-date">
                    <i class="fa-solid fa-calendar"></i>
                    <?php echo date('d M Y', strtotime($entry['exam_date'])); ?>
                  </div>
                <?php endif; ?>
                <?php if ($entry['has_timetable']): ?>
                  <a href="exams.php" class="ar-tt-link" title="Exam Timetable available">
                    <i class="fa-solid fa-calendar-check"></i> Timetable
                  </a>
                <?php endif; ?>
              </div>
              <div class="ar-grade-box">
                <span class="ar-grade-pill"
                      style="background:<?php echo $gc['bg']; ?>;color:<?php echo $gc['color']; ?>;">
                  <?php echo htmlspecialchars($entry['overall_grade']); ?>
                </span>
                <div class="ar-pct"><?php echo htmlspecialchars($entry['overall_pct']); ?>%</div>
              </div>
            </div>

            <div class="ar-progress-wrap">
              <div class="ar-progress-bar"
                   style="width:<?php echo min(100, $pct); ?>%;background:<?php echo $barColor; ?>;"></div>
            </div>

            <div class="ar-subjects-wrap">
              <table class="table ar-table">
                <thead>
                  <tr>
                    <th>Subject</th>
                    <th>Marks</th>
                    <th>Out of</th>
                    <th>%</th>
                    <th>Exam Date</th>
                    <th>Time</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($entry['subjects'] as $sub):
                    $subPct = $sub['total_marks'] > 0
                        ? round(($sub['marks'] / $sub['total_marks']) * 100, 1) : 0;
                  ?>
                    <tr>
                      <td><span class="ar-subject-name"><?php echo htmlspecialchars($sub['subject_name']); ?></span></td>
                      <td><strong><?php echo htmlspecialchars($sub['marks']); ?></strong></td>
                      <td class="ar-muted"><?php echo htmlspecialchars($sub['total_marks']); ?></td>
                      <td>
                        <span class="ar-sub-pct <?php echo $subPct >= 50 ? 'pass' : 'fail'; ?>">
                          <?php echo $subPct; ?>%
                        </span>
                      </td>
                      <td class="ar-muted">
                        <?php if ($sub['tt_date']): ?>
                          <span class="ar-tt-date-cell">
                            <i class="fa-solid fa-calendar-day"></i>
                            <?php echo date('d M Y', strtotime($sub['tt_date'])); ?>
                          </span>
                        <?php else: ?>
                          <span class="ar-tt-none">—</span>
                        <?php endif; ?>
                      </td>
                      <td class="ar-muted">
                        <?php if ($sub['tt_start'] && $sub['tt_end']): ?>
                          <span class="ar-tt-time-cell">
                            <i class="fa-solid fa-clock"></i>
                            <?php echo date('h:i A', strtotime($sub['tt_start'])); ?>
                            – <?php echo date('h:i A', strtotime($sub['tt_end'])); ?>
                          </span>
                        <?php else: ?>
                          <span class="ar-tt-none">—</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <a class="ar-del-btn"
                           href="?delete=<?php echo (int)$sub['result_id']; ?>&class=<?php echo urlencode($filterClass); ?>"
                           onclick="return confirm('Delete this result?')">
                          <i class="fa-solid fa-trash"></i>
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot>
                  <tr class="ar-total-row">
                    <td><strong>Total</strong></td>
                    <td><strong><?php echo htmlspecialchars($entry['total_obt']); ?></strong></td>
                    <td class="ar-muted"><?php echo htmlspecialchars($entry['total_pos']); ?></td>
                    <td colspan="4"><strong><?php echo htmlspecialchars($entry['overall_pct']); ?>%</strong></td>
                  </tr>
                </tfoot>
              </table>
            </div>

          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div>
</div>

<script>
var arExams    = <?php echo json_encode($examsByClass); ?>;
var arStudents = <?php echo json_encode($studentsByClass); ?>;
var arSubjects = <?php echo json_encode($subjectsByClass); ?>;
var arTimetable = <?php echo json_encode($timetableForJs); ?>;

function arLoadClass(cls) {
    arFillExams(arExams[cls] || []);
    arFill('studentSelect', arStudents[cls] || [], '— No students in this class —');
    arFill('subjectSelect', arSubjects[cls] || [], '— No subjects in this class —');
    document.getElementById('ttInfoPanel').style.display = 'none';
}

function arFillExams(items) {
    var sel = document.getElementById('examSelect');
    sel.innerHTML = '';
    if (!items.length) {
        sel.innerHTML = '<option value="">— No exams found for this class —</option>';
        return;
    }
    var def = document.createElement('option');
    def.value = ''; def.textContent = '— Select exam —';
    sel.appendChild(def);
    items.forEach(function(item) {
        var opt = document.createElement('option');
        opt.value = item.key;
        opt.textContent = item.name;
        sel.appendChild(opt);
    });
}

function arOnExamChange(key) {
    var panel = document.getElementById('ttInfoPanel');
    if (!key || !arTimetable[key]) { panel.style.display = 'none'; return; }
    var entries = arTimetable[key];
    var html = '<div class="ar-tt-panel-title"><i class="fa-solid fa-calendar-check"></i> Exam Schedule</div>';
    html += '<table class="ar-tt-mini-table"><thead><tr><th>Subject</th><th>Date</th><th>Time</th></tr></thead><tbody>';
    entries.forEach(function(e) {
        var d = e.exam_date ? new Date(e.exam_date).toLocaleDateString('en-GB', {day:'2-digit',month:'short',year:'numeric'}) : '—';
        var t = (e.start_time && e.end_time) ? (fmtTime(e.start_time) + ' – ' + fmtTime(e.end_time)) : '—';
        html += '<tr><td>' + (e.subject_name||'—') + '</td><td>' + d + '</td><td>' + t + '</td></tr>';
    });
    html += '</tbody></table>';
    panel.innerHTML = html;
    panel.style.display = 'block';
}

function fmtTime(t) {
    if (!t) return '—';
    var p = t.split(':'), h = parseInt(p[0]), m = p[1];
    var ap = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    return h + ':' + m + ' ' + ap;
}

function arFill(selId, items, emptyMsg) {
    var sel = document.getElementById(selId);
    sel.innerHTML = '';
    if (!items.length) { sel.innerHTML = '<option value="">' + emptyMsg + '</option>'; return; }
    var def = document.createElement('option');
    def.value = ''; def.textContent = '— Select —';
    sel.appendChild(def);
    items.forEach(function(item) {
        var opt = document.createElement('option');
        opt.value = item.id;
        opt.textContent = item.name;
        sel.appendChild(opt);
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
