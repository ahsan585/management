<?php
require_once __DIR__ . '/../config/init.php';
requireRole('teacher');

$pageTitle = 'Teacher Dashboard';
$activePage = 'dashboard';
$pageCss = ['teacher-dashboard'];

// Stats
$totalClasses = $pdo->query('SELECT COUNT(DISTINCT class) FROM students')->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM notes WHERE uploaded_by = :uid');
$stmt->execute([':uid' => $_SESSION['user_id']]);
$totalNotes = $stmt->fetchColumn();

$totalNotices = $pdo->query('SELECT COUNT(*) FROM notices')->fetchColumn();

$stmt2 = $pdo->prepare('SELECT COUNT(DISTINCT exam_name || class_id) FROM exam_timetable');
$stmt2->execute();
$totalExams = $stmt2->fetchColumn();

// Chart data (past 6 months)
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $months[] = date('M Y', strtotime("-{$i} months"));
}

$notesByMonth = $pdo->prepare("SELECT to_char(created_at, 'Mon YYYY') AS month, COUNT(*) AS cnt FROM notes WHERE uploaded_by = :uid AND created_at >= now() - interval '5 months' GROUP BY month ORDER BY min(created_at)");
$notesByMonth->execute([':uid' => $_SESSION['user_id']]);
$notesByMonth = $notesByMonth->fetchAll(PDO::FETCH_KEY_PAIR);

$attendanceByMonth = $pdo->prepare("SELECT to_char(created_at, 'Mon YYYY') AS month, COUNT(*) AS cnt FROM attendance WHERE created_by = :uid AND created_at >= now() - interval '5 months' GROUP BY month ORDER BY min(created_at)");
$attendanceByMonth->execute([':uid' => $_SESSION['user_id']]);
$attendanceByMonth = $attendanceByMonth->fetchAll(PDO::FETCH_KEY_PAIR);

$examsByMonth = $pdo->prepare("SELECT to_char(created_at, 'Mon YYYY') AS month, COUNT(*) AS cnt FROM exam_timetable WHERE created_at >= now() - interval '5 months' GROUP BY month ORDER BY min(created_at)");
$examsByMonth->execute();
$examsByMonth = $examsByMonth->fetchAll(PDO::FETCH_KEY_PAIR);

$notesData      = array_map(fn($m) => (int)($notesByMonth[$m]      ?? 0), $months);
$attendanceData = array_map(fn($m) => (int)($attendanceByMonth[$m] ?? 0), $months);
$examsData      = array_map(fn($m) => (int)($examsByMonth[$m]      ?? 0), $months);
// Notice popup removed — handled via notice_reads system

require_once __DIR__ . '/../includes/header.php';
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>



<div class="main teacher-dashboard">
  <div class="topbar">
    <h1>Teacher Dashboard</h1>
  </div>

  <div class="card" style="margin-bottom:20px;">
    <h3 style="margin:0 0 16px;font-size:1rem;font-weight:600;">Quick Stats</h3>
    <div class="stat-cards">
      <div class="stat-card classes">
        <div class="stat-icon"><i class="fa-solid fa-chalkboard"></i></div>
        <strong><?php echo $totalClasses ?: 0; ?></strong>
        <div class="stat-label">Classes</div>
      </div>
      <div class="stat-card notes">
        <div class="stat-icon"><i class="fa-solid fa-file-lines"></i></div>
        <strong><?php echo $totalNotes ?: 0; ?></strong>
        <div class="stat-label">Notes Uploaded</div>
      </div>
      <div class="stat-card exams">
        <div class="stat-icon"><i class="fa-solid fa-pen-to-square"></i></div>
        <strong><?php echo $totalExams ?: 0; ?></strong>
        <div class="stat-label">Exams Created</div>
      </div>
      <div class="stat-card notices">
        <div class="stat-icon"><i class="fa-solid fa-bullhorn"></i></div>
        <strong><?php echo $totalNotices ?: 0; ?></strong>
        <div class="stat-label">Notices</div>
      </div>
    </div>
  </div>

  <div class="card" style="margin-bottom:20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <div>
        <h3 style="margin:0;font-size:1rem;font-weight:600;">Activity Overview</h3>
        <div style="font-size:0.75rem;color:#94a3b8;margin-top:2px;">Last 6 months</div>
      </div>
    </div>
    <div style="position:relative;height:280px;">
      <canvas id="teacherChart"></canvas>
    </div>
  </div>
</div>

<script>
window.addEventListener('load', function () {
  const ctx = document.getElementById('teacherChart');
  if (!ctx || typeof Chart === 'undefined') return;

  const pf = { family: "system-ui, -apple-system, sans-serif" };

  new Chart(ctx, {
    type: 'line',
    data: {
      labels: <?php echo json_encode($months); ?>,
      datasets: [
        {
          label: 'Attendance',
          data: <?php echo json_encode($attendanceData); ?>,
          borderColor: '#22c55e',
          backgroundColor: 'rgba(34,197,94,0.07)',
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
          label: 'Notes',
          data: <?php echo json_encode($notesData); ?>,
          borderColor: '#a855f7',
          backgroundColor: 'rgba(168,85,247,0.07)',
          borderWidth: 2,
          pointBackgroundColor: '#a855f7',
          pointBorderColor: '#fff',
          pointBorderWidth: 2,
          pointRadius: 4,
          pointHoverRadius: 6,
          fill: true,
          tension: 0.4,
        },
        {
          label: 'Exams',
          data: <?php echo json_encode($examsData); ?>,
          borderColor: '#f59e0b',
          backgroundColor: 'rgba(245,158,11,0.07)',
          borderWidth: 2,
          pointBackgroundColor: '#f59e0b',
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
            font: { ...pf, size: 12 },
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
          titleFont: { ...pf, size: 13, weight: '600' },
          bodyFont: { ...pf, size: 12 },
          boxWidth: 8, boxHeight: 8,
          usePointStyle: true,
        }
      },
      interaction: { mode: 'index', intersect: false },
      scales: {
        x: {
          grid: { display: false },
          border: { display: false },
          ticks: { color: '#94a3b8', font: { ...pf, size: 11 } }
        },
        y: {
          beginAtZero: true,
          border: { display: false, dash: [4, 4] },
          grid: { color: '#f1f5f9' },
          ticks: { precision: 0, color: '#94a3b8', font: { ...pf, size: 11 }, padding: 8 }
        }
      }
    }
  });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>