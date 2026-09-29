<?php
require_once __DIR__ . '/../config/init.php';
requireRole('teacher');

$pageTitle  = 'Notices';
$activePage = 'notices';
$pageCss    = ['teacher-notices'];

$uid = $_SESSION['user_id'];

// Migration: add class_id column to notices table if not exists
$pdo->exec("ALTER TABLE notices ADD COLUMN IF NOT EXISTS class_id VARCHAR(100)");

// Teacher classes — detect from attendance records
$myClasses = $pdo->prepare("
    SELECT DISTINCT s.class FROM attendance a
    JOIN students s ON s.id = a.student_id
    WHERE a.created_by = :uid
    ORDER BY s.class
");
$myClasses->execute(['uid' => $uid]);
$myClasses = $myClasses->fetchAll(PDO::FETCH_COLUMN);

// Fallback: all classes
if (empty($myClasses)) {
    $myClasses = $pdo->query('SELECT name FROM classes ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
}

// Mark all relevant notices as read
$unreadIds = $pdo->prepare("
    SELECT n.id FROM notices n
    WHERE (n.target = 'all' OR n.target = 'teachers' OR n.target = 'students')
      AND n.id NOT IN (SELECT notice_id FROM notice_reads WHERE user_id = :uid)
");
$unreadIds->execute(['uid' => $uid]);
$toMark = $unreadIds->fetchAll(PDO::FETCH_COLUMN);

if (!empty($toMark)) {
    $ins = $pdo->prepare('INSERT INTO notice_reads (notice_id, user_id) VALUES (:nid, :uid) ON CONFLICT DO NOTHING');
    foreach ($toMark as $nid) $ins->execute(['nid' => $nid, 'uid' => $uid]);
}

// POST — notice publish
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title    = sanitize($_POST['title']    ?? '');
    $message  = sanitize($_POST['message']  ?? '');
    $classId  = sanitize($_POST['class_id'] ?? '');

    if (!$title || !$message) {
        flash('error', 'Title and message are required.');
    } elseif (!$classId || !in_array($classId, $myClasses)) {
        flash('error', 'Please select a valid class.');
    } else {
        $pdo->prepare('
            INSERT INTO notices (title, message, target, class_id, created_by)
            VALUES (:title, :message, :target, :class_id, :created_by)
        ')->execute([
            'title'      => $title,
            'message'    => $message,
            'target'     => 'students',
            'class_id'   => $classId,
            'created_by' => $uid,
        ]);
        flash('success', 'Notice posted for Class ' . $classId . '.');
    }
    header('Location: ' . BASE_URL . '/teacher/notices.php');
    exit;
}

// Fetch notices — teacher ki apni + admin ki saari
$notices = $pdo->query("
    SELECT n.*, u.username AS author
    FROM notices n
    LEFT JOIN users u ON u.id = n.created_by
    ORDER BY n.created_at DESC
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main teacher-notices">

  <div class="tn-topbar">
    <div class="tn-topbar-left">
      <h1 class="tn-title">Notices</h1>
      <span class="tn-count-badge"><?php echo count($notices); ?></span>
    </div>
  </div>

  <div class="tn-layout">

    <!-- LEFT: Post form -->
    <div class="tn-form-col">
      <div class="card tn-form-card">
        <div class="tn-form-head">
          <span class="tn-form-icon"><i class="fa-solid fa-paper-plane"></i></span>
          <div>
            <div class="tn-form-title">Post Notice</div>
            <div class="tn-form-sub">Send notices to your class students</div>
          </div>
        </div>

        <?php if (empty($myClasses)): ?>
          <div class="tn-warn">
            <i class="fa-solid fa-triangle-exclamation"></i>
            First enter attendance or marks for a class — it will then appear here.
          </div>
        <?php else: ?>
          <form method="post">
            <div class="input-group">
              <label>Class</label>
              <select name="class_id" required>
                <option value="">— Select class —</option>
                <?php foreach ($myClasses as $cls): ?>
                  <option value="<?php echo htmlspecialchars($cls); ?>">
                    Class <?php echo htmlspecialchars($cls); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="input-group">
              <label>Title</label>
              <input name="title" placeholder="e.g. Class test on Monday" required />
            </div>
            <div class="input-group">
              <label>Message</label>
              <textarea name="message" rows="5" placeholder="Write your notice here..." required></textarea>
            </div>
            <div class="tn-target-info">
              <i class="fa-solid fa-circle-info"></i>
              This notice will only be sent to students of the selected class.
            </div>
            <button type="submit" class="tn-publish-btn">
              <i class="fa-solid fa-paper-plane"></i> Publish Notice
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- RIGHT: Notices list -->
    <div class="tn-list-col">
      <div class="card tn-list-card">
        <div class="tn-list-head">
          <span class="tn-list-icon"><i class="fa-solid fa-list-ul"></i></span>
          <span class="tn-list-title">All Notices</span>
        </div>

        <?php if (empty($notices)): ?>
          <div class="tn-empty">
            <i class="fa-regular fa-bell-slash"></i>
            <p>No notices posted yet.</p>
          </div>
        <?php else: ?>
          <div class="tn-cards">
            <?php
            $targetLabels = ['all' => 'Everyone', 'students' => 'Students', 'teachers' => 'Teachers'];
            foreach ($notices as $notice):
            ?>
              <div class="tn-card">
                <div class="tn-card-header">
                  <div class="tn-card-title-row">
                    <span class="tn-card-title"><?php echo htmlspecialchars($notice['title']); ?></span>
                    <div class="tn-badges">
                      <?php if (!empty($notice['class_id'])): ?>
                        <span class="tn-class-badge">
                          <i class="fa-solid fa-graduation-cap"></i> Class <?php echo htmlspecialchars($notice['class_id']); ?>
                        </span>
                      <?php endif; ?>
                      <span class="tn-target-badge tn-target-<?php echo htmlspecialchars($notice['target']); ?>">
                        <?php echo $targetLabels[$notice['target']] ?? $notice['target']; ?>
                      </span>
                    </div>
                  </div>
                </div>
                <p class="tn-card-msg"><?php echo htmlspecialchars($notice['message']); ?></p>
                <div class="tn-card-footer">
                  <div class="tn-meta">
                    <span><i class="fa-regular fa-user"></i> <?php echo htmlspecialchars($notice['author'] ?? 'Admin'); ?></span>
                    <span><i class="fa-regular fa-calendar"></i> <?php echo date('d M Y', strtotime($notice['created_at'])); ?></span>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
