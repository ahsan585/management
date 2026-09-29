<?php
require_once __DIR__ . '/../config/init.php';
requireRole('teacher');

$pageTitle  = 'Teacher ID Card';
$activePage = 'idcard';
$pageCss    = ['teacher-idcard'];

$user = currentUser();
$stmt = $pdo->prepare('SELECT * FROM teachers WHERE id = :id');
$stmt->execute(['id' => $user['teacher_id']]);
$teacher = $stmt->fetch();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main teacher-idcard">
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
          <div class="idcard-role-badge">Teacher</div>
        </div>

        <!-- Divider -->
        <div class="idcard-divider"></div>

        <!-- Body -->
        <div class="idcard-body">
          <div class="idcard-photo-col">
            <img class="idcard-photo"
                 src="<?php echo !empty($teacher['photo']) ? BASE_URL.'/uploads/photos/'.htmlspecialchars($teacher['photo']) : BASE_URL.'/assets/default-avatar.svg'; ?>"
                 alt="Photo" />
            <div class="idcard-photo-label">Teacher Photo</div>
          </div>

          <div class="idcard-info-col">
            <div class="idcard-name"><?php echo htmlspecialchars($teacher['name']); ?></div>
            <div class="idcard-designation"><?php echo htmlspecialchars($teacher['subject'] ?? 'Teacher'); ?></div>

            <div class="idcard-rows">
              <div class="idcard-row">
                <span class="idcard-label">Teacher ID</span>
                <span class="idcard-value id-mono"><?php echo htmlspecialchars($teacher['teacher_key']); ?></span>
              </div>
              <div class="idcard-row">
                <span class="idcard-label">Subject</span>
                <span class="idcard-value"><?php echo htmlspecialchars($teacher['subject'] ?? '—'); ?></span>
              </div>
              <div class="idcard-row">
                <span class="idcard-label">Contact</span>
                <span class="idcard-value"><?php echo htmlspecialchars($teacher['contact'] ?? '—'); ?></span>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="idcard-footer">
          <div class="idcard-footer-left">
            <div class="idcard-barcode">
              <?php
              $key = $teacher['teacher_key'] ?? '000000';
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
            <div class="idcard-barcode-num"><?php echo htmlspecialchars($teacher['teacher_key']); ?></div>
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
