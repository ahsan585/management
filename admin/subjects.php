<?php
require_once __DIR__ . '/../config/init.php';
requireRole('admin');

$pageTitle  = 'Manage Subjects';
$activePage = 'subjects';
$pageCss    = ['admin-buttons', 'admin-subjects'];

$classes     = $pdo->query('SELECT name FROM classes ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
$filterClass = trim($_GET['class'] ?? '');
$noClasses   = empty($classes);

// DELETE
if (isset($_GET['delete'])) {
    $pdo->prepare('DELETE FROM subjects WHERE id = :id')->execute(['id' => (int)$_GET['delete']]);
    flash('success', 'Subject deleted.');
    header('Location: subjects.php'); exit;
}

// ADD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject_name = trim($_POST['subject_name'] ?? '');
    $class_id     = trim($_POST['class_id']     ?? '');
    if (!$subject_name || !$class_id) {
        flash('error', 'Subject name and class are required.');
        header('Location: subjects.php'); exit;
    }
    try {
        $pdo->prepare('INSERT INTO subjects (subject_name, class_id) VALUES (:s, :c)')
            ->execute(['s' => $subject_name, 'c' => $class_id]);
        flash('success', "\"$subject_name\" added to class $class_id.");
    } catch (PDOException $e) {
        flash('error', 'This subject already exists for that class.');
    }
    header('Location: subjects.php'); exit;
}

$subjects    = $pdo->query('SELECT * FROM subjects ORDER BY class_id, subject_name')->fetchAll();
$subsByClass = [];
foreach ($subjects as $s) $subsByClass[$s['class_id']][] = $s;

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="main admin-subjects">

  <div class="topbar">
    <h1>Subjects</h1>
    <span class="pro-count-badge"><?php echo count($subjects); ?> Subjects</span>
  </div>

  <div class="pro-layout">

    <!-- FORM -->
    <div class="pro-form-col">
      <div class="card">
        <div class="pro-card-head">
          <span class="pro-head-icon" style="background:#f3e8ff;color:#9333ea;">
            <i class="fa-solid fa-book"></i>
          </span>
          <h3>Add Subject</h3>
        </div>

        <?php if ($noClasses): ?>
          <div class="pro-no-class-warn">
            <i class="fa-solid fa-triangle-exclamation"></i>
            First add <a href="classes.php">Classes</a>, then you can add subjects.
          </div>
        <?php else: ?>
        <form method="post">
          <div class="input-group">
            <label>Subject Name</label>
            <input name="subject_name" placeholder="e.g. Mathematics" required />
          </div>
          <div class="input-group">
            <label>Class</label>
            <select name="class_id" required>
              <option value="">— Select class —</option>
              <?php foreach ($classes as $cls): ?>
                <option value="<?php echo htmlspecialchars($cls); ?>"
                  <?php echo $filterClass === $cls ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($cls); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <button class="btn pro-btn-full" type="submit">
            <i class="fa-solid fa-plus"></i> Add Subject
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- LIST -->
    <div class="pro-list-col">
      <div class="card">
        <div class="pro-card-head">
          <span class="pro-head-icon" style="background:#fef9c3;color:#ca8a04;">
            <i class="fa-solid fa-list"></i>
          </span>
          <h3>All Subjects</h3>
        </div>

        <?php if (empty($subsByClass)): ?>
          <div class="pro-empty">
            <i class="fa-solid fa-book-open"></i>
            <p>No subjects added yet.</p>
          </div>
        <?php else: ?>
          <?php foreach ($subsByClass as $cls => $clsSubs): ?>
            <div class="pro-group">
              <div class="pro-group-label">
                <i class="fa-solid fa-graduation-cap"></i>
                Class: <?php echo htmlspecialchars($cls); ?>
                <span class="pro-group-count"><?php echo count($clsSubs); ?></span>
              </div>
              <div class="pro-subject-chips">
                <?php foreach ($clsSubs as $sub): ?>
                  <div class="pro-subject-chip">
                    <span><?php echo htmlspecialchars($sub['subject_name']); ?></span>
                    <a class="pro-chip-del"
                       href="?delete=<?php echo (int)$sub['id']; ?>"
                       onclick="return confirm('Delete this subject?')">
                      <i class="fa-solid fa-xmark"></i>
                    </a>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
