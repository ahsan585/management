<?php
$active = $activePage ?? '';
$role   = $_SESSION['role'] ?? '';
$user   = currentUser();

$roleLabels = ['admin' => 'Administrator', 'teacher' => 'Teacher', 'student' => 'Student'];
$roleLabel  = $roleLabels[$role] ?? ucfirst($role);

$roleColors = ['admin' => '#f59e0b', 'teacher' => '#34d399', 'student' => '#60a5fa'];
$roleColor  = $roleColors[$role] ?? '#94a3b8';

// Unread notices count for teacher/student
$unreadNotices = 0;
if ($user && in_array($role, ['teacher', 'student'])) {
    $uid = $user['id'];
    // Target filter: notices meant for this role or 'all'
    $targetRole = ($role === 'teacher') ? 'teachers' : 'students';
    $unreadStmt = $pdo->prepare("
        SELECT COUNT(*) FROM notices n
        WHERE (n.target = 'all' OR n.target = :target)
          AND (n.class_id IS NULL OR n.class_id = (
              SELECT s.class FROM students s
              JOIN users u2 ON u2.student_id = s.id
              WHERE u2.id = :uid LIMIT 1
          ))
          AND n.id NOT IN (
              SELECT notice_id FROM notice_reads WHERE user_id = :uid2
          )
    ");
    $unreadStmt->execute(['target' => $targetRole, 'uid' => $uid, 'uid2' => $uid]);
    $unreadNotices = (int)$unreadStmt->fetchColumn();
}

// Unread notes count for student only
$unreadNotes = 0;
if ($user && $role === 'student') {
    $uid        = $user['id'];
    $stuStmt    = $pdo->prepare('SELECT class FROM students WHERE id = :sid');
    $stuStmt->execute(['sid' => $user['student_id']]);
    $stuClass   = $stuStmt->fetchColumn();
    if ($stuClass) {
        $unreadNotesStmt = $pdo->prepare("
            SELECT COUNT(*) FROM notes n
            WHERE n.class = :class
              AND n.id NOT IN (
                  SELECT note_id FROM note_reads WHERE user_id = :uid
              )
        ");
        $unreadNotesStmt->execute(['class' => $stuClass, 'uid' => $uid]);
        $unreadNotes = (int)$unreadNotesStmt->fetchColumn();
    }
}
?>

<!-- Mobile toggle button -->
<button class="sb-toggle" id="sbToggle" aria-label="Menu">
  <i class="fa-solid fa-bars"></i>
</button>

<!-- Overlay -->
<div class="sb-overlay" id="sbOverlay"></div>

<aside class="sidebar" id="sidebar">

  <!-- Brand -->
  <div class="sb-brand">
    <div class="sb-logo">
      <i class="fa-solid fa-graduation-cap"></i>
    </div>
    <div class="sb-brand-text">
      <div class="sb-brand-name">Digital DSMS</div>
      <div class="sb-brand-sub">School Management</div>
    </div>
  </div>

  <!-- User info -->
  <?php if ($user): ?>
  <div class="sb-user">
    <div class="sb-user-avatar">
      <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
    </div>
    <div class="sb-user-info">
      <div class="sb-user-name"><?php echo htmlspecialchars($user['username']); ?></div>
      <span class="sb-role-badge" style="background:<?php echo $roleColor; ?>22;color:<?php echo $roleColor; ?>;">
        <?php echo $roleLabel; ?>
      </span>
    </div>
  </div>
  <?php endif; ?>

  <!-- Divider -->
  <div class="sb-divider"></div>

  <!-- Nav -->
  <nav class="sb-nav">

    <?php if ($role === 'admin'): ?>

      <div class="sb-section-label">Main</div>
      <a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/admin/dashboard.php" class="sb-link <?php echo $active === 'dashboard' ? 'active' : ''; ?>">
        <span class="sb-icon"><i class="fa-solid fa-gauge-high"></i></span>
        <span>Dashboard</span>
      </a>

      <div class="sb-section-label">Setup</div>
      <a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/admin/classes.php" class="sb-link <?php echo $active === 'classes' ? 'active' : ''; ?>">
        <span class="sb-icon"><i class="fa-solid fa-chalkboard"></i></span>
        <span>Classes</span>
      </a>
      <a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/admin/subjects.php" class="sb-link <?php echo $active === 'subjects' ? 'active' : ''; ?>">
        <span class="sb-icon"><i class="fa-solid fa-book"></i></span>
        <span>Subjects</span>
      </a>

      <div class="sb-section-label">People</div>
      <a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/admin/students.php" class="sb-link <?php echo $active === 'students' ? 'active' : ''; ?>">
        <span class="sb-icon"><i class="fa-solid fa-user-graduate"></i></span>
        <span>Students</span>
      </a>
      <a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/admin/teachers.php" class="sb-link <?php echo $active === 'teachers' ? 'active' : ''; ?>">
        <span class="sb-icon"><i class="fa-solid fa-chalkboard-user"></i></span>
        <span>Teachers</span>
      </a>

      <div class="sb-section-label">Academic</div>
      <a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/admin/exams.php" class="sb-link <?php echo $active === 'exams' ? 'active' : ''; ?>">
        <span class="sb-icon"><i class="fa-solid fa-calendar-check"></i></span>
        <span>Exam Timetable</span>
      </a>
      <a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/admin/results.php" class="sb-link <?php echo $active === 'results' ? 'active' : ''; ?>">
        <span class="sb-icon"><i class="fa-solid fa-chart-line"></i></span>
        <span>Results</span>
      </a>
      <a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/admin/attendance.php" class="sb-link <?php echo $active === 'attendance' ? 'active' : ''; ?>">
        <span class="sb-icon"><i class="fa-solid fa-clipboard-check"></i></span>
        <span>Attendance</span>
      </a>
      <a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/admin/timetable.php" class="sb-link <?php echo $active === 'timetable' ? 'active' : ''; ?>">
        <span class="sb-icon"><i class="fa-solid fa-calendar-week"></i></span>
        <span>Timetable</span>
      </a>

      <div class="sb-section-label">Content</div>
      <a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/admin/notes.php" class="sb-link <?php echo $active === 'notes' ? 'active' : ''; ?>">
        <span class="sb-icon"><i class="fa-solid fa-file-lines"></i></span>
        <span>Notes</span>
      </a>
      <a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/admin/notices.php" class="sb-link <?php echo $active === 'notices' ? 'active' : ''; ?>">
        <span class="sb-icon"><i class="fa-solid fa-bell"></i></span>
        <span>Notices</span>
      </a>
      <a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/admin/top_students.php" class="sb-link <?php echo $active === 'top' ? 'active' : ''; ?>">
        <span class="sb-icon"><i class="fa-solid fa-trophy"></i></span>
        <span>Top Students</span>
      </a>

    <?php elseif ($role === 'teacher'): ?>

      <div class="sb-section-label">Main</div>
      <a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/teacher/dashboard.php" class="sb-link <?php echo $active === 'dashboard' ? 'active' : ''; ?>">
        <span class="sb-icon"><i class="fa-solid fa-gauge-high"></i></span>
        <span>Dashboard</span>
      </a>

      <div class="sb-section-label">Academic</div>
      <a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/teacher/attendance.php" class="sb-link <?php echo $active === 'attendance' ? 'active' : ''; ?>">
        <span class="sb-icon"><i class="fa-solid fa-clipboard-check"></i></span>
        <span>Attendance</span>
      </a>
      <a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/teacher/exams.php" class="sb-link <?php echo $active === 'exams' ? 'active' : ''; ?>">
        <span class="sb-icon"><i class="fa-solid fa-calendar-check"></i></span>
        <span>Exam Timetable</span>
      </a>
      <a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/teacher/results.php" class="sb-link <?php echo $active === 'results' ? 'active' : ''; ?>">
        <span class="sb-icon"><i class="fa-solid fa-chart-line"></i></span>
        <span>Results</span>
      </a>
      <a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/teacher/timetable.php" class="sb-link <?php echo $active === 'timetable' ? 'active' : ''; ?>">
        <span class="sb-icon"><i class="fa-solid fa-calendar-week"></i></span>
        <span>Timetable</span>
      </a>

      <div class="sb-section-label">Content</div>
      <a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/teacher/notes.php" class="sb-link <?php echo $active === 'notes' ? 'active' : ''; ?>">
        <span class="sb-icon"><i class="fa-solid fa-file-lines"></i></span>
        <span>Upload Notes</span>
      </a>
      <a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/teacher/notices.php" class="sb-link <?php echo $active === 'notices' ? 'active' : ''; ?>">
        <span class="sb-icon"><i class="fa-solid fa-bell"></i></span>
        <span>Notices</span>
        <?php if ($unreadNotices > 0): ?>
          <span class="sb-unread-dot"><?php echo $unreadNotices; ?></span>
        <?php endif; ?>
      </a>

      <div class="sb-section-label">Profile</div>
      <a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/teacher/idcard.php" class="sb-link <?php echo $active === 'idcard' ? 'active' : ''; ?>">
        <span class="sb-icon"><i class="fa-solid fa-id-card"></i></span>
        <span>ID Card</span>
      </a>

    <?php elseif ($role === 'student'): ?>

      <div class="sb-section-label">Main</div>
      <a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/student/dashboard.php" class="sb-link <?php echo $active === 'dashboard' ? 'active' : ''; ?>">
        <span class="sb-icon"><i class="fa-solid fa-gauge-high"></i></span>
        <span>Dashboard</span>
      </a>

      <div class="sb-section-label">Academic</div>
      <a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/student/attendance.php" class="sb-link <?php echo $active === 'attendance' ? 'active' : ''; ?>">
        <span class="sb-icon"><i class="fa-solid fa-clipboard-check"></i></span>
        <span>Attendance</span>
      </a>
      <a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/student/results.php" class="sb-link <?php echo $active === 'results' ? 'active' : ''; ?>">
        <span class="sb-icon"><i class="fa-solid fa-chart-line"></i></span>
        <span>Results</span>
      </a>
      <a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/student/timetable.php" class="sb-link <?php echo $active === 'timetable' ? 'active' : ''; ?>">
        <span class="sb-icon"><i class="fa-solid fa-calendar-week"></i></span>
        <span>Timetable</span>
      </a>

      <div class="sb-section-label">Content</div>
      <a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/student/notes.php" class="sb-link <?php echo $active === 'notes' ? 'active' : ''; ?>">
        <span class="sb-icon"><i class="fa-solid fa-file-lines"></i></span>
        <span>Notes</span>
        <?php if ($unreadNotes > 0): ?>
          <span class="sb-unread-dot"><?php echo $unreadNotes; ?></span>
        <?php endif; ?>
      </a>
      <a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/student/notices.php" class="sb-link <?php echo $active === 'notices' ? 'active' : ''; ?>">
        <span class="sb-icon"><i class="fa-solid fa-bell"></i></span>
        <span>Notices</span>
        <?php if ($unreadNotices > 0): ?>
          <span class="sb-unread-dot"><?php echo $unreadNotices; ?></span>
        <?php endif; ?>
      </a>

      <div class="sb-section-label">Profile</div>
      <a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/student/idcard.php" class="sb-link <?php echo $active === 'idcard' ? 'active' : ''; ?>">
        <span class="sb-icon"><i class="fa-solid fa-id-card"></i></span>
        <span>ID Card</span>
      </a>

    <?php endif; ?>

  </nav>

  <!-- Logout -->
  <div class="sb-footer">
    <a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/logout.php" class="sb-logout">
      <i class="fa-solid fa-right-from-bracket"></i>
      <span>Logout</span>
    </a>
  </div>

</aside>
