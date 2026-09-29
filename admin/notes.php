<?php
require_once __DIR__ . '/../config/init.php';
requireRole('admin');

$pageTitle  = 'Notes';
$activePage = 'notes';
$pageCss    = ['admin-notes'];

$errors  = [];

// Classes from classes table (not students)
$classes = $pdo->query(
    "SELECT name FROM classes ORDER BY name"
)->fetchAll(PDO::FETCH_COLUMN);

// AJAX: return subjects for a class
if (isset($_GET['get_subjects'])) {
    $cls = sanitize($_GET['get_subjects']);
    $rows = $pdo->prepare(
        "SELECT subject_name FROM subjects WHERE class_id = :c ORDER BY subject_name"
    );
    $rows->execute(['c' => $cls]);
    header('Content-Type: application/json');
    echo json_encode($rows->fetchAll(PDO::FETCH_COLUMN));
    exit;
}

// ── DELETE ──────────────────────────────────────────────────
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete') {
    $delId = (int)$_GET['id'];
    $fStmt = $pdo->prepare('SELECT file_path FROM notes WHERE id = :id');
    $fStmt->execute(['id' => $delId]);
    $fRow  = $fStmt->fetch();
    if ($fRow && $fRow['file_path']) {
        $fullPath = __DIR__ . '/../' . $fRow['file_path'];
        if (file_exists($fullPath)) @unlink($fullPath);
    }
    $pdo->prepare('DELETE FROM notes WHERE id = :id')->execute(['id' => $delId]);
    flash('success', 'Note deleted successfully.');
    header('Location: ' . BASE_URL . '/admin/notes.php');
    exit;
}

// ── UPLOAD ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title   = sanitize($_POST['title']   ?? '');
    $class   = sanitize($_POST['class']   ?? '');
    $subject = sanitize($_POST['subject'] ?? '');

    if (!$title)   $errors[] = 'Title is required.';
    if (!$class)   $errors[] = 'Class is required.';
    if (!$subject) $errors[] = 'Subject is required.';

    $maxSize = 10 * 1024 * 1024; // 10 MB
    if (empty($_FILES['file']['name'])) {
        $errors[] = 'Please choose a file.';
    } elseif ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload error (code ' . $_FILES['file']['error'] . ').';
    } elseif ($_FILES['file']['size'] > $maxSize) {
        $errors[] = 'File size must not exceed 10 MB.';
    } else {
        // Validate MIME via finfo (not spoofable like $_FILES['type'])
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file($_FILES['file']['tmp_name']);
        $allowed  = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($realMime, $allowed, true)) {
            $errors[] = 'Only PDF, JPEG, PNG, or WebP files are allowed.';
        }
    }

    if (empty($errors)) {
        $origName = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
        $ext      = strtolower($origName);
        $filename = 'note_' . uniqid() . '.' . $ext;
        $destDir  = __DIR__ . '/../uploads/notes/';
        if (!is_dir($destDir)) mkdir($destDir, 0755, true);
        move_uploaded_file($_FILES['file']['tmp_name'], $destDir . $filename);

        $pdo->prepare(
            'INSERT INTO notes (title, class, subject, file_path, uploaded_by)
             VALUES (:title, :class, :subject, :file_path, :uploaded_by)'
        )->execute([
            'title'       => $title,
            'class'       => $class,
            'subject'     => $subject,
            'file_path'   => 'uploads/notes/' . $filename,
            'uploaded_by' => $_SESSION['user_id'],
        ]);

        flash('success', 'Note uploaded for Class ' . $class . '.');
        header('Location: ' . BASE_URL . '/admin/notes.php?filter_class=' . urlencode($class));
        exit;
    }

    foreach ($errors as $e) flash('error', $e);
    header('Location: ' . BASE_URL . '/admin/notes.php');
    exit;
}

// ── FETCH NOTES ─────────────────────────────────────────────
$filterClass   = sanitize($_GET['filter_class'] ?? '');
$filterSubject = sanitize($_GET['filter_subject'] ?? '');

$where  = [];
$params = [];
if ($filterClass)   { $where[] = 'n.class = :class';     $params['class']   = $filterClass; }
if ($filterSubject) { $where[] = 'n.subject = :subject';  $params['subject'] = $filterSubject; }

$sql = 'SELECT n.*, u.username AS uploader
        FROM notes n
        LEFT JOIN users u ON u.id = n.uploaded_by'
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . ' ORDER BY n.class, n.subject, n.created_at DESC';

$nStmt = $pdo->prepare($sql);
$nStmt->execute($params);
$notes = $nStmt->fetchAll();

// Group by class → subject
$notesByClass = [];
foreach ($notes as $note) {
    $notesByClass[$note['class']][$note['subject']][] = $note;
}

// All subjects for filter dropdown
$allSubjects = $pdo->query(
    "SELECT DISTINCT subject FROM notes ORDER BY subject"
)->fetchAll(PDO::FETCH_COLUMN);

$totalNotes = count($notes);
$success    = flash('success');
$errorFlash = flash('error');

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main admin-notes">

  <!-- ── TOPBAR ── -->
  <div class="an-topbar">
    <div class="an-topbar-left">
      <h1 class="an-title">Notes</h1>
      <span class="an-count-badge"><?php echo $totalNotes; ?> Files</span>
    </div>
  </div>

  <?php if ($success): ?>
    <div class="an-alert an-alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>
  <?php if ($errorFlash): ?>
    <div class="an-alert an-alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($errorFlash); ?></div>
  <?php endif; ?>

  <div class="an-layout">

    <!-- ── UPLOAD FORM ── -->
    <div class="an-form-col">
      <div class="an-card">
        <div class="an-card-head">
          <span class="an-head-icon" style="background:#dbeafe;color:#2563eb;border-color:#bfdbfe;">
            <i class="fa-solid fa-upload"></i>
          </span>
          <div>
            <div class="an-form-title">Upload Note</div>
            <div class="an-form-sub">PDF or image, max 10 MB</div>
          </div>
        </div>

        <form method="post" enctype="multipart/form-data" id="uploadForm" novalidate>
          <div class="an-field">
            <label>Title</label>
            <input name="title" placeholder="e.g. Chapter 3 Notes"
                   value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required />
          </div>

          <div class="an-field">
            <label>Class</label>
            <select name="class" id="noteClass" required>
              <option value="">— Select class —</option>
              <?php foreach ($classes as $cls): ?>
                <option value="<?php echo htmlspecialchars($cls); ?>"
                  <?php echo (($_POST['class'] ?? '') === $cls) ? 'selected' : ''; ?>>
                  Class <?php echo htmlspecialchars($cls); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if (empty($classes)): ?>
              <p class="an-field-warn"><i class="fa-solid fa-triangle-exclamation"></i>
                No classes found. <a href="classes.php">Add classes first →</a>
              </p>
            <?php endif; ?>
          </div>

          <div class="an-field">
            <label>Subject</label>
            <select name="subject" id="noteSubject" required>
              <option value="">— Select class first —</option>
              <?php
              // If POST failed and class was selected, pre-load subjects
              if (!empty($_POST['class'])) {
                $preSubs = $pdo->prepare("SELECT subject_name FROM subjects WHERE class_id = :c ORDER BY subject_name");
                $preSubs->execute(['c' => $_POST['class']]);
                foreach ($preSubs->fetchAll(PDO::FETCH_COLUMN) as $s):
              ?>
                <option value="<?php echo htmlspecialchars($s); ?>"
                  <?php echo (($_POST['subject'] ?? '') === $s) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($s); ?>
                </option>
              <?php endforeach; } ?>
            </select>
            <p class="an-field-warn" id="noSubjectWarn" style="display:none;">
              <i class="fa-solid fa-triangle-exclamation"></i>
              No subjects for this class. <a href="subjects.php">Add subjects →</a>
            </p>
          </div>

          <div class="an-field">
            <label>File <span class="an-hint">(PDF / JPEG / PNG / WebP)</span></label>
            <label class="an-file-drop" id="fileDrop">
              <i class="fa-solid fa-cloud-arrow-up"></i>
              <span id="fileLabel">Click or drag file here</span>
              <small id="fileSize"></small>
              <input type="file" name="file" id="fileInput"
                     accept="application/pdf,image/jpeg,image/png,image/webp" required />
            </label>
          </div>

          <button type="submit" class="an-submit-btn">
            <i class="fa-solid fa-upload"></i> Upload Note
          </button>
        </form>
      </div>

      <!-- Stats box -->
      <div class="an-stats-box">
        <div class="an-stat">
          <span class="an-stat-num"><?php echo $pdo->query("SELECT COUNT(*) FROM notes")->fetchColumn(); ?></span>
          <span class="an-stat-lbl">Total Notes</span>
        </div>
        <div class="an-stat">
          <span class="an-stat-num"><?php echo $pdo->query("SELECT COUNT(DISTINCT class) FROM notes")->fetchColumn(); ?></span>
          <span class="an-stat-lbl">Classes</span>
        </div>
        <div class="an-stat">
          <span class="an-stat-num"><?php echo $pdo->query("SELECT COUNT(DISTINCT subject) FROM notes")->fetchColumn(); ?></span>
          <span class="an-stat-lbl">Subjects</span>
        </div>
      </div>
    </div>

    <!-- ── NOTES LIST ── -->
    <div class="an-list-col">
      <div class="an-card">
        <div class="an-card-head">
          <span class="an-head-icon" style="background:#dcfce7;color:#16a34a;border-color:#bbf7d0;">
            <i class="fa-solid fa-file-lines"></i>
          </span>
          <div class="an-list-title">All Notes</div>

          <div class="an-filters">
            <form method="get" id="filterForm">
              <select name="filter_class" onchange="document.getElementById('filterForm').submit()" class="an-select">
                <option value="">All Classes</option>
                <?php foreach ($classes as $cls): ?>
                  <option value="<?php echo htmlspecialchars($cls); ?>"
                    <?php echo $filterClass === $cls ? 'selected' : ''; ?>>
                    Class <?php echo htmlspecialchars($cls); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <select name="filter_subject" onchange="document.getElementById('filterForm').submit()" class="an-select">
                <option value="">All Subjects</option>
                <?php foreach ($allSubjects as $sub): ?>
                  <option value="<?php echo htmlspecialchars($sub); ?>"
                    <?php echo $filterSubject === $sub ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($sub); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php if ($filterClass || $filterSubject): ?>
                <a href="?" class="an-clear-btn"><i class="fa-solid fa-xmark"></i> Clear</a>
              <?php endif; ?>
            </form>
          </div>
        </div>

        <?php if (empty($notesByClass)): ?>
          <div class="an-empty">
            <i class="fa-solid fa-folder-open"></i>
            <p>No notes found<?php echo ($filterClass || $filterSubject) ? ' for selected filters.' : ' yet.'; ?></p>
          </div>
        <?php else: ?>
          <?php foreach ($notesByClass as $cls => $subjects): ?>
            <div class="an-class-group">
              <div class="an-class-label">
                <i class="fa-solid fa-graduation-cap"></i>
                Class <?php echo htmlspecialchars($cls); ?>
                <span class="an-class-count">
                  <?php echo array_sum(array_map('count', $subjects)); ?>
                </span>
              </div>

              <?php foreach ($subjects as $subj => $subNotes): ?>
                <div class="an-subject-group">
                  <div class="an-subject-label">
                    <i class="fa-solid fa-book-open"></i>
                    <?php echo htmlspecialchars($subj); ?>
                    <span class="an-subject-count"><?php echo count($subNotes); ?></span>
                  </div>

                  <div class="an-note-list">
                    <?php foreach ($subNotes as $note):
                      $ext      = strtolower(pathinfo($note['file_path'], PATHINFO_EXTENSION));
                      $isPdf    = $ext === 'pdf';
                      $iconCls  = $isPdf ? 'fa-file-pdf'   : 'fa-file-image';
                      $iconClr  = $isPdf ? '#ef4444'        : '#3b82f6';
                      $iconBg   = $isPdf ? '#fee2e2'        : '#dbeafe';
                      $fileUrl  = BASE_URL . '/' . htmlspecialchars($note['file_path']);
                      $origFile = basename($note['file_path']);
                    ?>
                      <div class="an-note-row">
                        <span class="an-note-icon" style="background:<?php echo $iconBg; ?>;color:<?php echo $iconClr; ?>;">
                          <i class="fa-solid <?php echo $iconCls; ?>"></i>
                        </span>

                        <div class="an-note-info">
                          <div class="an-note-title"><?php echo htmlspecialchars($note['title']); ?></div>
                          <div class="an-note-meta">
                            <span><i class="fa-solid fa-user"></i> <?php echo htmlspecialchars($note['uploader'] ?? '—'); ?></span>
                            <span><i class="fa-regular fa-calendar"></i> <?php echo date('d M Y', strtotime($note['created_at'])); ?></span>
                            <span class="an-ext-badge <?php echo $isPdf ? 'pdf' : 'img'; ?>"><?php echo strtoupper($ext); ?></span>
                          </div>
                        </div>

                        <div class="an-note-actions">
                          <a href="<?php echo $fileUrl; ?>" target="_blank"
                             class="an-btn an-btn-view" title="View">
                            <i class="fa-solid fa-eye"></i> View
                          </a>
                          <a href="<?php echo $fileUrl; ?>" download="<?php echo htmlspecialchars($note['title'] . '.' . $ext); ?>"
                             class="an-btn an-btn-dl" title="Download">
                            <i class="fa-solid fa-download"></i>
                          </a>
                          <a href="?action=delete&id=<?php echo (int)$note['id']; ?>"
                             class="an-btn an-btn-del" title="Delete"
                             onclick="return confirm('Delete \'<?php echo addslashes($note['title']); ?>\'?')">
                            <i class="fa-solid fa-trash"></i>
                          </a>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<script>
// File picker label update
(function () {
  const input = document.getElementById('fileInput');
  const label = document.getElementById('fileLabel');
  const sizeEl = document.getElementById('fileSize');
  if (!input) return;
  input.addEventListener('change', function () {
    if (this.files && this.files[0]) {
      const f    = this.files[0];
      const mb   = (f.size / 1048576).toFixed(2);
      label.textContent = f.name;
      sizeEl.textContent = mb + ' MB';
      if (f.size > 10 * 1048576) {
        sizeEl.style.color = '#dc2626';
        sizeEl.textContent += ' — exceeds 10 MB limit';
      } else {
        sizeEl.style.color = '#16a34a';
      }
    }
  });
})();

// Dynamic subject dropdown linked to class
(function () {
  const clsSel  = document.getElementById('noteClass');
  const subSel  = document.getElementById('noteSubject');
  const noWarn  = document.getElementById('noSubjectWarn');
  if (!clsSel || !subSel) return;

  clsSel.addEventListener('change', function () {
    const cls = this.value;
    subSel.innerHTML = '<option value="">Loading…</option>';
    subSel.disabled  = true;
    noWarn.style.display = 'none';

    if (!cls) {
      subSel.innerHTML = '<option value="">— Select class first —</option>';
      subSel.disabled  = false;
      return;
    }

    fetch('notes.php?get_subjects=' + encodeURIComponent(cls))
      .then(r => r.json())
      .then(subjects => {
        subSel.disabled = false;
        if (!subjects.length) {
          subSel.innerHTML = '<option value="">No subjects found</option>';
          noWarn.style.display = 'flex';
          return;
        }
        subSel.innerHTML = '<option value="">— Select subject —</option>';
        subjects.forEach(s => {
          const opt = document.createElement('option');
          opt.value       = s;
          opt.textContent = s;
          subSel.appendChild(opt);
        });
      })
      .catch(() => {
        subSel.innerHTML = '<option value="">Error loading subjects</option>';
        subSel.disabled  = false;
      });
  });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
