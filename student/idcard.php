<?php
require_once __DIR__ . '/../config/init.php';
requireRole('student');

$pageTitle  = 'Student ID Card';
$activePage = 'idcard';
$pageCss    = ['student-idcard'];

$user = currentUser();
$stmt = $pdo->prepare('SELECT * FROM students WHERE id = :id');
$stmt->execute(['id' => $user['student_id']]);
$student = $stmt->fetch();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main student-idcard">
  <div class="topbar">
    <h1>My ID Card</h1>
    <button class="btn" onclick="window.print()">
      <i class="fa-solid fa-print"></i> Print
    </button>
  </div>

  <div class="idcard-page">
    <div class="idcard-wrap">

      <!-- Card -->
      <div class="idcard">

        <!-- Header -->
        <div class="idcard-top">
          <div class="idcard-school-logo">
            <i class="fa-solid fa-graduation-cap"></i>
          </div>
          <div class="idcard-school-info">
            <div class="idcard-school-name">Digital DSMS</div>
            <div class="idcard-school-sub">School Management System</div>
          </div>
          <div class="idcard-role-badge student-badge">Student</div>
        </div>

        <!-- Divider -->
        <div class="idcard-divider"></div>

        <!-- Body -->
        <div class="idcard-body">
          <div class="idcard-photo-col">
            <img class="idcard-photo"
                 src="<?php echo !empty($student['photo']) ? BASE_URL.'/uploads/photos/'.htmlspecialchars($student['photo']) : BASE_URL.'/assets/default-avatar.svg'; ?>"
                 alt="Photo" />
            <div class="idcard-photo-label">Student Photo</div>
          </div>

          <div class="idcard-info-col">
            <div class="idcard-name"><?php echo htmlspecialchars($student['name']); ?></div>
            <div class="idcard-designation">Class <?php echo htmlspecialchars($student['class']); ?></div>

            <div class="idcard-rows">
              <div class="idcard-row">
                <span class="idcard-label">Student ID</span>
                <span class="idcard-value id-mono"><?php echo htmlspecialchars($student['student_key']); ?></span>
              </div>
              <div class="idcard-row">
                <span class="idcard-label">Class</span>
                <span class="idcard-value"><?php echo htmlspecialchars($student['class']); ?></span>
              </div>
              <div class="idcard-row">
                <span class="idcard-label">Contact</span>
                <span class="idcard-value"><?php echo htmlspecialchars($student['contact'] ?? '—'); ?></span>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="idcard-footer">
          <div class="idcard-footer-left">
            <div class="idcard-barcode">
              <?php
              $key = $student['student_key'] ?? '000000';
              for ($i = 0; $i < strlen($key); $i++) {
                  $w = (ord($key[$i]) % 3) + 1;
                  echo '<span style="width:' . $w . 'px;"></span>';
              }
              for ($i = 0; $i < 20; $i++) {
                  $w = ($i % 3) + 1;
                  echo '<span style="width:' . $w . 'px;"></span>';
              }
              ?>
            </div>
            <div class="idcard-barcode-num"><?php echo htmlspecialchars($student['student_key']); ?></div>
          </div>
          <div class="idcard-footer-right">
            <div class="idcard-issued">Issued by Digital DSMS</div>
            <div class="idcard-valid">Valid: <?php echo date('Y'); ?> – <?php echo date('Y') + 1; ?></div>
          </div>
        </div>

      </div>
      <!-- End Card -->

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
