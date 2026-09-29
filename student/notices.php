<?php
require_once __DIR__ . '/../config/init.php';
requireRole('student');

$pageTitle  = 'Notices';
$activePage = 'notices';
$pageCss    = ['student-notices'];

$user = currentUser();
$uid  = $user['id'];

// Student ki class
$studentStmt = $pdo->prepare('SELECT class FROM students WHERE id = :id');
$studentStmt->execute(['id' => $user['student_id']]);
$studentClass = $studentStmt->fetchColumn();

// Mark all relevant notices as read
$unreadIds = $pdo->prepare("
    SELECT n.id FROM notices n
    WHERE (n.target = 'all' OR n.target = 'students')
      AND (n.class_id IS NULL OR n.class_id = :class)
      AND n.id NOT IN (SELECT notice_id FROM notice_reads WHERE user_id = :uid)
");
$unreadIds->execute(['class' => $studentClass, 'uid' => $uid]);
$toMark = $unreadIds->fetchAll(PDO::FETCH_COLUMN);

if (!empty($toMark)) {
    $ins = $pdo->prepare('INSERT INTO notice_reads (notice_id, user_id) VALUES (:nid, :uid) ON CONFLICT DO NOTHING');
    foreach ($toMark as $nid) $ins->execute(['nid' => $nid, 'uid' => $uid]);
}

// Fetch notices — apni class ki + general (class_id NULL = admin ki saari)
$noticeStmt = $pdo->prepare("
    SELECT n.*, u.username AS author
    FROM notices n
    LEFT JOIN users u ON u.id = n.created_by
    WHERE (n.target = 'all' OR n.target = 'students')
      AND (n.class_id IS NULL OR n.class_id = :class)
    ORDER BY n.created_at DESC
");
$noticeStmt->execute(['class' => $studentClass]);
$notices = $noticeStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main student-notices">
  <div class="topbar">
    <h1>Notices</h1>
    <span class="sn-count"><?php echo count($notices); ?> total</span>
  </div>

  <div class="card">
    <?php if (empty($notices)): ?>
      <div class="sn-empty">
        <i class="fa-regular fa-bell-slash"></i>
        <p>No notices yet.</p>
      </div>
    <?php else: ?>
      <div class="sn-list">
        <?php foreach ($notices as $notice): ?>
          <div class="sn-card">
            <div class="sn-card-top">
              <div class="sn-title">
                <i class="fa-solid fa-bullhorn sn-icon"></i>
                <?php echo htmlspecialchars($notice['title']); ?>
              </div>
              <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                <?php if (!empty($notice['class_id'])): ?>
                  <span class="sn-badge" style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;">
                    <i class="fa-solid fa-graduation-cap"></i> Class <?php echo htmlspecialchars($notice['class_id']); ?>
                  </span>
                <?php endif; ?>
                <span class="sn-badge <?php echo htmlspecialchars($notice['target']); ?>">
                  <?php
                    $labels = ['all' => 'Everyone', 'students' => 'Students', 'teachers' => 'Teachers'];
                    echo $labels[$notice['target']] ?? htmlspecialchars($notice['target']);
                  ?>
                </span>
              </div>
            </div>
            <p class="sn-msg"><?php echo htmlspecialchars($notice['message']); ?></p>
            <div class="sn-footer">
              <span><i class="fa-regular fa-user"></i> <?php echo htmlspecialchars($notice['author'] ?? 'Admin'); ?></span>
              <span><i class="fa-regular fa-calendar"></i> <?php echo date('d M Y', strtotime($notice['created_at'])); ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
