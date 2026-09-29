<?php
require_once __DIR__ . '/../config/init.php';
requireRole('student');

$pageTitle = 'Student Dashboard';
$activePage = 'dashboard';
$pageCss = ['student-dashboard'];

$user = currentUser();
$student = $pdo->prepare('SELECT * FROM students WHERE id = :id');
$student->execute(['id' => $user['student_id']]);
$student = $student->fetch();

$totalAttendance = $pdo->prepare('SELECT COUNT(*) FROM attendance WHERE student_id = :id');
$totalAttendance->execute(['id' => $student['id']]);
$totalAttendance = $totalAttendance->fetchColumn();

$presentCount = $pdo->prepare('SELECT COUNT(*) FROM attendance WHERE student_id = :id AND status = :status');
$presentCount->execute(['id' => $student['id'], 'status' => 'present']);
$presentCount = $presentCount->fetchColumn();

// Notice popup removed — handled via notice_reads system

// Best exam result: exam where student scored highest overall percentage
// Calculated from SUM(marks)/SUM(total_marks) across all subjects — no stored percentage column
$topResult = $pdo->prepare("
    SELECT
        r.exam_name,
        SUM(r.marks)                                                             AS total_obtained,
        SUM(r.total_marks)                                                       AS total_possible,
        ROUND((SUM(r.marks) / NULLIF(SUM(r.total_marks), 0) * 100)::numeric, 2) AS percentage,
        CASE
            WHEN (SUM(r.marks) / NULLIF(SUM(r.total_marks), 0) * 100) >= 80 THEN 'A+'
            WHEN (SUM(r.marks) / NULLIF(SUM(r.total_marks), 0) * 100) >= 70 THEN 'A'
            WHEN (SUM(r.marks) / NULLIF(SUM(r.total_marks), 0) * 100) >= 60 THEN 'B'
            WHEN (SUM(r.marks) / NULLIF(SUM(r.total_marks), 0) * 100) >= 50 THEN 'C'
            WHEN (SUM(r.marks) / NULLIF(SUM(r.total_marks), 0) * 100) >= 45 THEN 'D'
            ELSE 'F'
        END AS grade
    FROM results r
    WHERE r.student_id = :id
    GROUP BY r.exam_name, r.exam_class
    ORDER BY percentage DESC
    LIMIT 1
");
$topResult->execute(['id' => $student['id']]);
$topResult = $topResult->fetch();

// Chart data (past 6 months)
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $months[] = date('M Y', strtotime("-{$i} months"));
}

$attendanceByMonth = $pdo->prepare("SELECT to_char(created_at, 'Mon YYYY') AS month, COUNT(*) AS cnt FROM attendance WHERE student_id = :id AND created_at >= now() - interval '5 months' GROUP BY month ORDER BY min(created_at)");
$attendanceByMonth->execute(['id' => $student['id']]);
$attendanceByMonth = $attendanceByMonth->fetchAll(PDO::FETCH_KEY_PAIR);

$resultsByMonth = $pdo->prepare("SELECT to_char(created_at, 'Mon YYYY') AS month, COUNT(*) AS cnt FROM results WHERE student_id = :id AND created_at >= now() - interval '5 months' GROUP BY month ORDER BY min(created_at)");
$resultsByMonth->execute(['id' => $student['id']]);
$resultsByMonth = $resultsByMonth->fetchAll(PDO::FETCH_KEY_PAIR);

$attendanceData = array_map(fn($m) => $attendanceByMonth[$m] ?? 0, $months);
$resultsData = array_map(fn($m) => $resultsByMonth[$m] ?? 0, $months);

require_once __DIR__ . '/../includes/header.php';
?>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>



<div class="main student-dashboard">
  <div class="topbar">
    <h1>Welcome, <?php echo htmlspecialchars($student['name']); ?></h1>
  </div>

  <div class="card" style="margin-bottom:20px;">
    <h3 style="margin:0 0 16px;font-size:1rem;font-weight:600;">Quick Stats</h3>
    <div class="stat-cards">
      <div class="stat-card attendance">
        <div class="stat-icon"><i class="fa-solid fa-calendar-check"></i></div>
        <strong><?php echo $totalAttendance; ?></strong>
        <div class="stat-label">Total Records</div>
      </div>
      <div class="stat-card present">
        <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
        <strong><?php echo $presentCount; ?></strong>
        <div class="stat-label">Times Present</div>
      </div>
      <?php if ($topResult): ?>
        <div class="stat-card grade">
          <div class="stat-icon"><i class="fa-solid fa-trophy"></i></div>
          <strong><?php echo htmlspecialchars($topResult['grade']); ?></strong>
          <div class="stat-label">Best Grade — <?php echo htmlspecialchars($topResult['percentage']); ?>%</div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card" style="margin-bottom:20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <div>
        <h3 style="margin:0;font-size:1rem;font-weight:600;">Performance Overview</h3>
        <div style="font-size:0.75rem;color:#94a3b8;margin-top:2px;">Last 6 months</div>
      </div>
    </div>
    <div style="position:relative;height:280px;">
      <canvas id="studentChart"></canvas>
    </div>
  </div>

  <div class="card">
    <div class="sp-head">
      <span class="sp-head-icon"><i class="fa-solid fa-user"></i></span>
      <h3 style="margin:0;font-size:1rem;font-weight:600;">Student Profile</h3>
    </div>

    <div class="sp-body">
      <!-- Photo + name -->
      <div class="sp-photo-col">
        <img class="sp-avatar"
             src="<?php echo htmlspecialchars($student['photo'] ? BASE_URL.'/uploads/photos/'.$student['photo'] : BASE_URL.'/assets/default-avatar.svg'); ?>"
             alt="photo" />
        <div class="sp-name"><?php echo htmlspecialchars($student['name']); ?></div>
        <span class="sp-class-badge">Class <?php echo htmlspecialchars($student['class']); ?></span>
      </div>

      <!-- Info rows -->
      <div class="sp-info-col">
        <div class="sp-row">
          <span class="sp-label"><i class="fa-solid fa-id-card"></i> Student ID</span>
          <code class="sp-value-code"><?php echo htmlspecialchars($student['student_key']); ?></code>
        </div>
        <div class="sp-row">
          <span class="sp-label"><i class="fa-solid fa-graduation-cap"></i> Class</span>
          <span class="sp-value"><?php echo htmlspecialchars($student['class']); ?></span>
        </div>
        <div class="sp-row">
          <span class="sp-label"><i class="fa-solid fa-phone"></i> Contact</span>
          <span class="sp-value"><?php echo htmlspecialchars($student['contact'] ?? '—'); ?></span>
        </div>
        <div class="sp-row">
          <span class="sp-label"><i class="fa-solid fa-calendar"></i> Joined</span>
          <span class="sp-value"><?php echo date('d M Y', strtotime($student['created_at'])); ?></span>
        </div>

        <div class="sp-actions">
          <a href="<?php echo BASE_URL; ?>/student/idcard.php" class="sp-btn-idcard">
            <i class="fa-solid fa-id-card"></i> View ID Card
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
window.addEventListener('load', function () {
  const ctx = document.getElementById('studentChart');
  if (!ctx || typeof Chart === 'undefined') return;

  const proFont = { family: "system-ui, -apple-system, sans-serif" };

  new Chart(ctx, {
    type: 'line',
    data: {
      labels: <?php echo json_encode($months); ?>,
      datasets: [
        {
          label: 'Attendance',
          data: <?php echo json_encode($attendanceData); ?>,
          borderColor: '#22c55e',
          backgroundColor: 'rgba(34,197,94,0.08)',
          borderWidth: 2,
          pointBackgroundColor: '#22c55e',
          pointBorderColor: '#fff',
          pointBorderWidth: 2,
          pointRadius: 4,
          pointHoverRadius: 6,
          fill: true,
          tension: 0.4,
        },
        {
          label: 'Results',
          data: <?php echo json_encode($resultsData); ?>,
          borderColor: '#3b82f6',
          backgroundColor: 'rgba(59,130,246,0.08)',
          borderWidth: 2,
          pointBackgroundColor: '#3b82f6',
          pointBorderColor: '#fff',
          pointBorderWidth: 2,
          pointRadius: 4,
          pointHoverRadius: 6,
          fill: true,
          tension: 0.4,
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: false,
      plugins: {
        legend: {
          position: 'top',
          align: 'end',
          labels: {
            boxWidth: 10, boxHeight: 10,
            borderRadius: 3, useBorderRadius: true,
            padding: 16,
            font: { ...proFont, size: 12 },
            color: '#64748b',
          }
        },
        tooltip: {
          mode: 'index',
          intersect: false,
          backgroundColor: '#fff',
          titleColor: '#1f2a37',
          bodyColor: '#475569',
          borderColor: '#e2e8f0',
          borderWidth: 1,
          padding: 12,
          titleFont: { ...proFont, size: 13, weight: '600' },
          bodyFont: { ...proFont, size: 12 },
          boxWidth: 8, boxHeight: 8,
          usePointStyle: true,
        }
      },
      interaction: { mode: 'index', intersect: false },
      scales: {
        x: {
          grid: { display: false },
          border: { display: false },
          ticks: { color: '#94a3b8', font: { ...proFont, size: 11 } }
        },
        y: {
          beginAtZero: true,
          border: { display: false, dash: [4,4] },
          grid: { color: '#f1f5f9' },
          ticks: { precision: 0, color: '#94a3b8', font: { ...proFont, size: 11 }, padding: 8 }
        }
      }
    }
  });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php';
