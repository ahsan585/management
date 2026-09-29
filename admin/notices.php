<?php
require_once __DIR__ . '/../config/init.php';
requireRole('admin');

$pageTitle = 'Manage Notices';
$activePage = 'notices';
$pageCss    = ['admin-notices'];

$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($action === 'delete' && $id) {
    $pdo->prepare('DELETE FROM notices WHERE id = :id')->execute(['id' => $id]);
    flash('success', 'Notice deleted.');
    header('Location: ' . BASE_URL . '/admin/notices.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title   = sanitize($_POST['title']   ?? '');
    $message = sanitize($_POST['message'] ?? '');
    $target  = sanitize($_POST['target']  ?? 'all');

    if (!$title || !$message) {
        $errors[] = 'Title and message are required.';
    }

    if (empty($errors)) {
        $pdo->prepare('INSERT INTO notices (title, message, target, created_by) VALUES (:title, :message, :target, :created_by)')
            ->execute(['title' => $title, 'message' => $message, 'target' => $target, 'created_by' => $_SESSION['user_id']]);
        flash('success', 'Notice posted.');
        header('Location: ' . BASE_URL . '/admin/notices.php');
        exit;
    }
    foreach ($errors as $_e) { flash('error', $_e); }
    header('Location: ' . BASE_URL . '/admin/notices.php');
    exit;
}

// Fetch notices with read counts
// "seen by all" = every teacher/student who is a target has read it
$notices = $pdo->query("
    SELECT
        n.*,
        u.username AS author,
        -- total users who should see this notice
        (
            SELECT COUNT(*) FROM users uu
            WHERE uu.role IN ('teacher','student')
              AND (n.target = 'all'
                   OR (n.target = 'teachers' AND uu.role = 'teacher')
                   OR (n.target = 'students' AND uu.role = 'student'))
        ) AS target_count,
        -- how many have read it
        (
            SELECT COUNT(*) FROM notice_reads nr
            JOIN users uu ON uu.id = nr.user_id
            WHERE nr.notice_id = n.id
              AND uu.role IN ('teacher','student')
        ) AS read_count
    FROM notices n
    LEFT JOIN users u ON u.id = n.created_by
    ORDER BY n.created_at DESC
")->fetchAll();

$success = flash('success');

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main admin-notices">

  <div class="an-topbar">
    <div class="an-topbar-left">
      <h1 class="an-title">Notices</h1>
      <span class="an-count-badge"><?php echo count($notices); ?></span>
    </div>
  </div>

  <div class="an-layout">

    <!-- LEFT: Post form -->
    <div class="an-form-col">
      <div class="card an-form-card">
        <div class="an-form-head">
          <span class="an-form-icon"><i class="fa-solid fa-paper-plane"></i></span>
          <div>
            <div class="an-form-title">Post Notice</div>
            <div class="an-form-sub">Broadcast to teachers or students</div>
          </div>
        </div>

        <form method="post">
          <div class="input-group">
            <label>Title</label>
            <input name="title" placeholder="e.g. School closed on Friday" required />
          </div>
          <div class="input-group">
            <label>Message</label>
            <textarea name="message" rows="5" placeholder="Write your notice here..." required></textarea>
          </div>
          <div class="input-group">
            <label>Target Audience</label>
            <select name="target">
              <option value="all">Everyone</option>
              <option value="students">Students Only</option>
              <option value="teachers">Teachers Only</option>
            </select>
          </div>
          <button type="submit" class="an-publish-btn">
            <i class="fa-solid fa-paper-plane"></i> Publish Notice
          </button>
        </form>
      </div>
    </div>

    <!-- RIGHT: Notices list -->
    <div class="an-list-col">
      <div class="card an-list-card">
        <div class="an-list-head">
          <span class="an-list-icon"><i class="fa-solid fa-list-ul"></i></span>
          <span class="an-list-title">All Notices</span>
        </div>

        <?php if (empty($notices)): ?>
          <div class="an-empty">
            <i class="fa-regular fa-bell-slash"></i>
            <p>No notices posted yet.</p>
          </div>
        <?php else: ?>
          <div class="an-cards">
            <?php foreach ($notices as $notice):
              $allRead = ($notice['target_count'] > 0 && $notice['read_count'] >= $notice['target_count']);
            ?>
              <div class="an-card <?php echo $allRead ? 'an-card--read' : ''; ?>">

                <?php
                  // Tick state: 0 = no one read, 1 = some read, 2 = all read
                  if ($notice['target_count'] == 0)       $tickState = 0;
                  elseif ($allRead)                        $tickState = 2;
                  elseif ($notice['read_count'] > 0)       $tickState = 1;
                  else                                     $tickState = 0;

                  $targetLabels = ['all' => 'Everyone', 'students' => 'Students', 'teachers' => 'Teachers'];
                  $targetLabel  = $targetLabels[$notice['target']] ?? $notice['target'];
                ?>

                <div class="an-card-header">
                  <div class="an-card-title-row">
                    <span class="an-card-title"><?php echo htmlspecialchars($notice['title']); ?></span>
                    <span class="an-target-badge an-target-<?php echo htmlspecialchars($notice['target']); ?>">
                      <?php echo $targetLabel; ?>
                    </span>
                  </div>
                </div>

                <p class="an-card-msg"><?php echo htmlspecialchars($notice['message']); ?></p>

                <div class="an-card-footer">
                  <div class="an-meta">
                    <span><i class="fa-regular fa-user"></i> <?php echo htmlspecialchars($notice['author'] ?? 'Admin'); ?></span>
                    <span><i class="fa-regular fa-calendar"></i> <?php echo date('d M Y', strtotime($notice['created_at'])); ?></span>
                  </div>

                  <div class="an-footer-right">
                    <!-- WhatsApp-style double tick -->
                    <span class="an-ticks an-ticks--<?php echo $tickState; ?>"
                          title="<?php echo (int)$notice['read_count']; ?>/<?php echo (int)$notice['target_count']; ?> read">
                      <?php if ($tickState === 0): ?>
                        <!-- single grey tick: sent, no one read -->
                        <svg class="an-tick-svg" viewBox="0 0 16 11" fill="none">
                          <path d="M1 5.5L5.5 10L15 1" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                      <?php elseif ($tickState === 1): ?>
                        <!-- double grey tick: some read -->
                        <svg class="an-tick-svg" viewBox="0 0 22 11" fill="none">
                          <path d="M1 5.5L5.5 10L15 1" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                          <path d="M7 5.5L11.5 10L21 1" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                      <?php else: ?>
                        <!-- double blue tick: all read -->
                        <svg class="an-tick-svg" viewBox="0 0 22 11" fill="none">
                          <path d="M1 5.5L5.5 10L15 1" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                          <path d="M7 5.5L11.5 10L21 1" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                      <?php endif; ?>
                      <span class="an-read-count"><?php echo (int)$notice['read_count']; ?>/<?php echo (int)$notice['target_count']; ?></span>
                    </span>

                    <a class="an-delete-btn"
                       href="<?php echo BASE_URL; ?>/admin/notices.php?action=delete&id=<?php echo (int)$notice['id']; ?>"
                       onclick="return confirm('Delete this notice?')">
                      <i class="fa-solid fa-trash"></i>
                    </a>
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
