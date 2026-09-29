<?php
require_once __DIR__ . '/../config/init.php';
requireRole('admin');

$pageTitle = 'Admin Dashboard';
$activePage = 'dashboard';
$pageCss = ['admin-buttons', 'admin-dashboard'];

// Fetch counts
$totalStudents = $pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();
$totalTeachers = $pdo->query('SELECT COUNT(*) FROM teachers')->fetchColumn();
$totalNotices = $pdo->query('SELECT COUNT(*) FROM notices')->fetchColumn();
$totalNotes = $pdo->query('SELECT COUNT(*) FROM notes')->fetchColumn();

// Chart data (last 6 months)
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $months[] = date('M Y', strtotime("-{$i} months"));
}

$studentsByMonth = $pdo->query("SELECT to_char(created_at, 'Mon YYYY') AS month, COUNT(*) AS cnt FROM students WHERE created_at >= now() - interval '5 months' GROUP BY month ORDER BY min(created_at)")->fetchAll(PDO::FETCH_KEY_PAIR);
$teachersByMonth = $pdo->query("SELECT to_char(created_at, 'Mon YYYY') AS month, COUNT(*) AS cnt FROM teachers WHERE created_at >= now() - interval '5 months' GROUP BY month ORDER BY min(created_at)")->fetchAll(PDO::FETCH_KEY_PAIR);
$noticesByMonth = $pdo->query("SELECT to_char(created_at, 'Mon YYYY') AS month, COUNT(*) AS cnt FROM notices WHERE created_at >= now() - interval '5 months' GROUP BY month ORDER BY min(created_at)")->fetchAll(PDO::FETCH_KEY_PAIR);
$notesByMonth = $pdo->query("SELECT to_char(created_at, 'Mon YYYY') AS month, COUNT(*) AS cnt FROM notes WHERE created_at >= now() - interval '5 months' GROUP BY month ORDER BY min(created_at)")->fetchAll(PDO::FETCH_KEY_PAIR);

$studentsData = array_map(fn($m) => $studentsByMonth[$m] ?? 0, $months);
$teachersData = array_map(fn($m) => $teachersByMonth[$m] ?? 0, $months);
$noticesData = array_map(fn($m) => $noticesByMonth[$m] ?? 0, $months);
$notesData = array_map(fn($m) => $notesByMonth[$m] ?? 0, $months);

// Notice popup removed — handled via notice_reads system in notices panel

// Top students per class (by total marks percentage across all subjects)
$topStudents = $pdo->query("
    WITH agg AS (
        SELECT r.student_id,
               ROUND((SUM(r.marks) / NULLIF(SUM(r.total_marks), 0) * 100)::numeric, 2) AS avg_percentage
        FROM results r
        GROUP BY r.student_id
    ),
    ranked AS (
        SELECT s.id, s.name, s.student_key, s.photo, s.class,
               agg.avg_percentage,
               CASE
                   WHEN agg.avg_percentage >= 90 THEN 'A+'
                   WHEN agg.avg_percentage >= 80 THEN 'A'
                   WHEN agg.avg_percentage >= 70 THEN 'B'
                   WHEN agg.avg_percentage >= 60 THEN 'C'
                   WHEN agg.avg_percentage >= 50 THEN 'D'
                   ELSE 'F'
               END AS grade,
               RANK() OVER (PARTITION BY s.class ORDER BY agg.avg_percentage DESC) AS rnk
        FROM students s
        JOIN agg ON agg.student_id = s.id
    )
    SELECT * FROM ranked WHERE rnk = 1 ORDER BY class LIMIT 6
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>



<div class="main admin-dashboard">

  <div class="card" style="margin-bottom:20px;">
    <h3 style="margin:0 0 16px;font-size:1rem;font-weight:600;">Quick Stats</h3>
    <div class="stat-cards">
        <div class="stat-card students">
          <div class="stat-icon"><i class="fa-solid fa-user-graduate"></i></div>
          <strong><?php echo $totalStudents; ?></strong>
          <div class="stat-label">Students</div>
        </div>
        <div class="stat-card teachers">
          <div class="stat-icon"><i class="fa-solid fa-chalkboard-user"></i></div>
          <strong><?php echo $totalTeachers; ?></strong>
          <div class="stat-label">Teachers</div>
        </div>
        <div class="stat-card notices">
          <div class="stat-icon"><i class="fa-solid fa-bullhorn"></i></div>
          <strong><?php echo $totalNotices; ?></strong>
          <div class="stat-label">Notices</div>
        </div>
        <div class="stat-card notes">
          <div class="stat-icon"><i class="fa-solid fa-file-lines"></i></div>
          <strong><?php echo $totalNotes; ?></strong>
          <div class="stat-label">Notes</div>
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
      <canvas id="adminChart"></canvas>
    </div>
  </div>

  <div class="card">
    <div class="ts-head">
      <div class="ts-head-left">
        <span class="ts-head-icon"><i class="fa-solid fa-trophy"></i></span>
        <div>
          <h3 style="margin:0;font-size:1rem;font-weight:600;">Top Students</h3>
          <div style="font-size:0.78rem;color:#64748b;margin-top:2px;">Best performer per class</div>
        </div>
      </div>
      <a href="<?php echo BASE_URL; ?>/admin/top_students.php" class="ts-view-all">
        View All <i class="fa-solid fa-arrow-right"></i>
      </a>
    </div>

    <?php if (empty($topStudents)): ?>
      <div class="ts-empty">
        <i class="fa-solid fa-chart-bar"></i>
        <p>No results yet. Upload results to see top students.</p>
      </div>
    <?php else: ?>
      <div class="ts-list">
        <?php
        $gradeColors = [
          'A+' => ['bg'=>'#dcfce7','color'=>'#16a34a'],
          'A'  => ['bg'=>'#dbeafe','color'=>'#2563eb'],
          'B'  => ['bg'=>'#e0f2fe','color'=>'#0369a1'],
          'C'  => ['bg'=>'#fef9c3','color'=>'#ca8a04'],
          'D'  => ['bg'=>'#ffedd5','color'=>'#ea580c'],
          'F'  => ['bg'=>'#fee2e2','color'=>'#dc2626'],
        ];
        foreach ($topStudents as $i => $student):
          $gc = $gradeColors[$student['grade']] ?? $gradeColors['F'];
        ?>
          <div class="ts-row">
            <div class="ts-rank"><?php echo $i + 1; ?></div>
            <img class="ts-avatar"
                 src="<?php echo $student['photo'] ? BASE_URL.'/uploads/photos/'.htmlspecialchars($student['photo']) : BASE_URL.'/assets/default-avatar.svg'; ?>"
                 alt="" />
            <div class="ts-info">
              <div class="ts-name"><?php echo htmlspecialchars($student['name']); ?></div>
              <div class="ts-meta">
                <span><i class="fa-solid fa-graduation-cap"></i> Class <?php echo htmlspecialchars($student['class']); ?></span>
                <span><i class="fa-solid fa-id-card"></i> <?php echo htmlspecialchars($student['student_key']); ?></span>
              </div>
            </div>
            <div class="ts-right">
              <span class="ts-grade" style="background:<?php echo $gc['bg']; ?>;color:<?php echo $gc['color']; ?>;">
                <?php echo htmlspecialchars($student['grade']); ?>
              </span>
              <div class="ts-pct"><?php echo htmlspecialchars($student['avg_percentage']); ?>%</div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
window.addEventListener('load', function () {
  const ctx = document.getElementById('adminChart');
  if (!ctx || typeof Chart === 'undefined') return;

  const proFont = { family: "system-ui, -apple-system, sans-serif" };

  new Chart(ctx, {
    type: 'line',
    data: {
      labels: <?php echo json_encode($months); ?>,
      datasets: [
        {
          label: 'Students',
          data: <?php echo json_encode($studentsData); ?>,
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
        },
        {
          label: 'Teachers',
          data: <?php echo json_encode($teachersData); ?>,
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
          label: 'Notices',
          data: <?php echo json_encode($noticesData); ?>,
          borderColor: '#a855f7',
          backgroundColor: 'rgba(168,85,247,0.08)',
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
          label: 'Notes',
          data: <?php echo json_encode($notesData); ?>,
          borderColor: '#f97316',
          backgroundColor: 'rgba(249,115,22,0.08)',
          borderWidth: 2,
          pointBackgroundColor: '#f97316',
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
            boxWidth: 10,
            boxHeight: 10,
            borderRadius: 3,
            useBorderRadius: true,
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
          boxWidth: 8,
          boxHeight: 8,
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
          grid: { color: '#f1f5f9', drawBorder: false },
          ticks: { precision: 0, color: '#94a3b8', font: { ...proFont, size: 11 }, padding: 8 }
        }
      }
    }
  });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php';
