<?php
require_once __DIR__ . '/../config/init.php';
requireRole('student');

$pageTitle  = 'Notes';
$activePage = 'notes';
$pageCss    = ['student-notes'];

$user    = currentUser();
$stStmt  = $pdo->prepare('SELECT * FROM students WHERE id = :id');
$stStmt->execute(['id' => $user['student_id']]);
$student = $stStmt->fetch();

$filterSubject = sanitize($_GET['subject'] ?? '');

$where  = ['n.class = :class'];
$params = ['class' => $student['class']];
if ($filterSubject) { $where[] = 'n.subject = :subject'; $params['subject'] = $filterSubject; }

$nStmt = $pdo->prepare(
    'SELECT n.*, u.username AS uploader
     FROM notes n LEFT JOIN users u ON u.id = n.uploaded_by
     WHERE ' . implode(' AND ', $where) .
    ' ORDER BY n.subject, n.created_at DESC'
);
$nStmt->execute($params);
$notes = $nStmt->fetchAll();

$notesBySubject = [];
foreach ($notes as $note) $notesBySubject[$note['subject']][] = $note;

// All subjects for this class (for filter)
$allSubStmt = $pdo->prepare(
    "SELECT DISTINCT subject FROM notes WHERE class = :class ORDER BY subject"
);
$allSubStmt->execute(['class' => $student['class']]);
$allSubjects = $allSubStmt->fetchAll(PDO::FETCH_COLUMN);

$totalNotes = count($notes);

// Mark all unread notes for this student as read
if ($user && $student) {
    $unreadIds = $pdo->prepare("
        SELECT id FROM notes
        WHERE class = :class
          AND id NOT IN (SELECT note_id FROM note_reads WHERE user_id = :uid)
    ");
    $unreadIds->execute(['class' => $student['class'], 'uid' => $user['id']]);
    $toMark = $unreadIds->fetchAll(PDO::FETCH_COLUMN);
    if ($toMark) {
        $ins = $pdo->prepare("INSERT INTO note_reads (note_id, user_id) VALUES (:nid, :uid) ON CONFLICT DO NOTHING");
        foreach ($toMark as $nid) {
            $ins->execute(['nid' => $nid, 'uid' => $user['id']]);
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main student-notes">

  <div class="sn-topbar">
    <div class="sn-topbar-left">
      <h1 class="sn-title">Notes</h1>
      <span class="sn-class-badge">
        <i class="fa-solid fa-graduation-cap"></i> Class <?php echo htmlspecialchars($student['class']); ?>
      </span>
      <span class="sn-count-badge"><?php echo $totalNotes; ?> Files</span>
    </div>
  </div>

  <div class="sn-card">
    <div class="sn-card-head">
      <span class="sn-head-icon">
        <i class="fa-solid fa-file-lines"></i>
      </span>
      <div class="sn-head-title">Your Class Notes</div>

      <?php if (!empty($allSubjects)): ?>
        <div class="sn-filters">
          <form method="get" id="snFilterForm">
            <select name="subject" onchange="document.getElementById('snFilterForm').submit()" class="sn-select">
              <option value="">All Subjects</option>
              <?php foreach ($allSubjects as $sub): ?>
                <option value="<?php echo htmlspecialchars($sub); ?>"
                  <?php echo $filterSubject === $sub ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($sub); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if ($filterSubject): ?>
              <a href="?" class="sn-clear-btn"><i class="fa-solid fa-xmark"></i> Clear</a>
            <?php endif; ?>
          </form>
        </div>
      <?php endif; ?>
    </div>

    <?php if (empty($notesBySubject)): ?>
      <div class="sn-empty">
        <i class="fa-solid fa-folder-open"></i>
        <p>No notes available for your class<?php echo $filterSubject ? ' in this subject' : ''; ?> yet.</p>
      </div>
    <?php else: ?>
      <?php foreach ($notesBySubject as $subject => $subNotes): ?>
        <div class="sn-subject-group">
          <div class="sn-subject-label">
            <i class="fa-solid fa-book-open"></i>
            <?php echo htmlspecialchars($subject); ?>
            <span class="sn-subject-count"><?php echo count($subNotes); ?></span>
          </div>

          <div class="sn-note-list">
            <?php foreach ($subNotes as $note):
              $ext     = strtolower(pathinfo($note['file_path'], PATHINFO_EXTENSION));
              $isPdf   = $ext === 'pdf';
              $iconCls = $isPdf ? 'fa-file-pdf'  : 'fa-file-image';
              $iconClr = $isPdf ? '#ef4444'       : '#3b82f6';
              $iconBg  = $isPdf ? '#fee2e2'       : '#dbeafe';
              $fileUrl = BASE_URL . '/' . htmlspecialchars($note['file_path']);
            ?>
              <div class="sn-note-row">
                <span class="sn-note-icon" style="background:<?php echo $iconBg; ?>;color:<?php echo $iconClr; ?>;">
                  <i class="fa-solid <?php echo $iconCls; ?>"></i>
                </span>

                <div class="sn-note-info">
                  <div class="sn-note-title"><?php echo htmlspecialchars($note['title']); ?></div>
                  <div class="sn-note-meta">
                    <?php if ($note['uploader']): ?>
                      <span><i class="fa-solid fa-user"></i> <?php echo htmlspecialchars($note['uploader']); ?></span>
                    <?php endif; ?>
                    <span><i class="fa-regular fa-calendar"></i> <?php echo date('d M Y', strtotime($note['created_at'])); ?></span>
                    <span class="sn-ext-badge <?php echo $isPdf ? 'pdf' : 'img'; ?>"><?php echo strtoupper($ext); ?></span>
                  </div>
                </div>

                <div class="sn-note-actions">
                  <a href="<?php echo $fileUrl; ?>" target="_blank"
                     class="sn-btn sn-btn-view" title="View">
                    <i class="fa-solid fa-eye"></i> View
                  </a>
                  <a href="<?php echo $fileUrl; ?>"
                     download="<?php echo htmlspecialchars($note['title'] . '.' . $ext); ?>"
                     class="sn-btn sn-btn-dl" title="Download">
                    <i class="fa-solid fa-download"></i>
                  </a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
