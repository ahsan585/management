<?php
require_once __DIR__ . '/../config/init.php';
requireRole('admin');

$pageTitle  = 'Manage Classes';
$activePage = 'classes';
$pageCss    = ['admin-classes'];

// DELETE
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    $cls = $pdo->prepare('SELECT name FROM classes WHERE id = :id');
    $cls->execute(['id' => $delId]);
    $clsRow = $cls->fetch();
    if ($clsRow) {
        $cnt = $pdo->prepare('SELECT COUNT(*) FROM students WHERE class = :c');
        $cnt->execute(['c' => $clsRow['name']]);
        if ((int)$cnt->fetchColumn() > 0) {
            flash('error', 'Cannot delete — students are enrolled in this class.');
            header('Location: classes.php'); exit;
        }
        $pdo->prepare('DELETE FROM subjects WHERE class_id = :c')->execute(['c' => $clsRow['name']]);
        $pdo->prepare('DELETE FROM classes WHERE id = :id')->execute(['id' => $delId]);
        flash('success', 'Class deleted.');
    }
    header('Location: classes.php'); exit;
}

// ADD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = strtoupper(trim($_POST['name']    ?? ''));
    $section = strtoupper(trim($_POST['section'] ?? ''));
    $name    = preg_replace('/[^A-Z0-9\-]/', '', $name);
    $section = preg_replace('/[^A-Z0-9]/', '', $section);

    if (!$name) { flash('error', 'Class name is required.'); header('Location: classes.php'); exit; }

    $fullName = $section ? $name . '-' . $section : $name;
    try {
        $pdo->prepare('INSERT INTO classes (name, section) VALUES (:name, :section)')
            ->execute(['name' => $fullName, 'section' => $section ?: null]);
        flash('success', "Class \"$fullName\" added.");
    } catch (PDOException $e) {
        flash('error', "Class \"$fullName\" already exists.");
    }
    header('Location: classes.php'); exit;
}

// FETCH
$classes = $pdo->query('
    SELECT c.*,
        (SELECT COUNT(*) FROM students s WHERE s.class = c.name)  AS student_count,
        (SELECT COUNT(*) FROM subjects sub WHERE sub.class_id = c.name) AS subject_count
    FROM classes c ORDER BY c.name
')->fetchAll();
$totalClasses = count($classes);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main admin-classes">

  <!-- 3-step setup banner -->
  <div class="ac-setup-flow">
    <div class="ac-setup-step <?php echo $totalClasses > 0 ? 'done' : 'active'; ?>">
      <span class="ac-step-num">1</span>
      <i class="fa-solid fa-chalkboard"></i> Classes
      <?php if ($totalClasses > 0): ?><i class="fa-solid fa-check ac-step-check"></i><?php endif; ?>
    </div>
    <div class="ac-setup-arrow"><i class="fa-solid fa-arrow-right"></i></div>
    <div class="ac-setup-step <?php echo $totalClasses > 0 ? 'active' : ''; ?>">
      <span class="ac-step-num">2</span>
      <i class="fa-solid fa-book"></i> Subjects
      <a href="subjects.php" class="ac-step-link">Go →</a>
    </div>
    <div class="ac-setup-arrow"><i class="fa-solid fa-arrow-right"></i></div>
    <div class="ac-setup-step">
      <span class="ac-step-num">3</span>
      <i class="fa-solid fa-user-graduate"></i> Students
      <a href="students.php" class="ac-step-link">Go →</a>
    </div>
  </div>

  <div class="ac-topbar">
    <div class="ac-topbar-left">
      <h1 class="ac-title">Classes</h1>
      <span class="ac-count-badge"><?php echo $totalClasses; ?></span>
    </div>
  </div>

  <div class="ac-layout">

    <!-- FORM -->
    <div class="ac-form-col">
      <div class="card ac-form-card">
        <div class="ac-form-head">
          <span class="ac-form-icon"><i class="fa-solid fa-chalkboard"></i></span>
          <div>
            <div class="ac-form-title">Add New Class</div>
            <div class="ac-form-sub">e.g. 10 + A = "10-A"</div>
          </div>
        </div>
        <form method="post">
          <div class="input-group">
            <label>Class Name / Number</label>
            <input name="name" placeholder="e.g. 10, KG1, Nursery" required />
          </div>
          <div class="input-group">
            <label>Section <span style="color:#94a3b8;font-weight:400;">(optional)</span></label>
            <input name="section" placeholder="e.g. A, B, C" />
          </div>
          <div class="ac-preview" id="acPreview" style="display:none;">
            Preview: <strong id="acPreviewText"></strong>
          </div>
          <button type="submit" class="ac-add-btn">
            <i class="fa-solid fa-plus"></i> Add Class
          </button>
        </form>
      </div>

      <!-- Quick Add -->
      <div class="card ac-quick-card">
        <div class="ac-quick-head"><i class="fa-solid fa-bolt"></i> Quick Add</div>
        <div class="ac-quick-grid">
          <?php
          $quickClasses = ['Montessori','Nursery','KG1','KG2','1','2','3','4','5','6','7','8','9','10'];
          foreach ($quickClasses as $qc):
            $exists = false;
            foreach ($classes as $c) { if ($c['name'] === $qc) { $exists = true; break; } }
          ?>
            <form method="post" style="display:inline;">
              <input type="hidden" name="name" value="<?php echo htmlspecialchars($qc); ?>" />
              <button type="submit" class="ac-quick-btn <?php echo $exists ? 'ac-quick-exists' : ''; ?>"
                      <?php echo $exists ? 'disabled' : ''; ?>>
                <?php echo htmlspecialchars($qc); ?>
                <?php if ($exists): ?><i class="fa-solid fa-check"></i><?php endif; ?>
              </button>
            </form>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- LIST -->
    <div class="ac-list-col">
      <div class="card ac-list-card">
        <div class="ac-list-head">
          <span class="ac-list-icon"><i class="fa-solid fa-list-ul"></i></span>
          <span class="ac-list-title">All Classes</span>
        </div>
        <?php if (empty($classes)): ?>
          <div class="ac-empty">
            <i class="fa-solid fa-chalkboard"></i>
            <p>No classes yet. Add a class to get started.</p>
          </div>
        <?php else: ?>
          <div class="ac-table-wrap">
            <table class="table ac-table">
              <thead>
                <tr>
                  <th>#</th><th>Class</th><th>Section</th>
                  <th>Subjects</th><th>Students</th><th>Added</th><th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($classes as $i => $cls): ?>
                  <tr>
                    <td class="ac-num"><?php echo $i + 1; ?></td>
                    <td>
                      <span class="ac-class-badge">
                        <i class="fa-solid fa-graduation-cap"></i>
                        <?php echo htmlspecialchars($cls['name']); ?>
                      </span>
                    </td>
                    <td class="small"><?php echo htmlspecialchars($cls['section'] ?? '—'); ?></td>
                    <td>
                      <a href="subjects.php?class=<?php echo urlencode($cls['name']); ?>" class="ac-subject-count">
                        <i class="fa-solid fa-book"></i> <?php echo (int)$cls['subject_count']; ?>
                        <?php if ((int)$cls['subject_count'] === 0): ?>
                          <span class="ac-add-hint">+ Add</span>
                        <?php endif; ?>
                      </a>
                    </td>
                    <td>
                      <span class="ac-student-count">
                        <i class="fa-solid fa-users"></i> <?php echo (int)$cls['student_count']; ?>
                      </span>
                    </td>
                    <td class="small"><?php echo date('d M Y', strtotime($cls['created_at'])); ?></td>
                    <td>
                      <?php if ((int)$cls['student_count'] === 0): ?>
                        <a class="ac-del-btn" href="?delete=<?php echo (int)$cls['id']; ?>"
                           onclick="return confirm('Delete class <?php echo htmlspecialchars($cls['name']); ?> and its subjects?')">
                          <i class="fa-solid fa-trash"></i>
                        </a>
                      <?php else: ?>
                        <span class="ac-del-disabled" title="Students enrolled"><i class="fa-solid fa-lock"></i></span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<script>
var ni = document.querySelector('input[name="name"]');
var si = document.querySelector('input[name="section"]');
var pr = document.getElementById('acPreview');
var pt = document.getElementById('acPreviewText');
function upPrev() {
  var n = ni.value.trim().toUpperCase();
  var s = si.value.trim().toUpperCase();
  if (n) { pr.style.display='block'; pt.textContent = s ? n+'-'+s : n; }
  else   { pr.style.display='none'; }
}
if (ni) ni.addEventListener('input', upPrev);
if (si) si.addEventListener('input', upPrev);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
