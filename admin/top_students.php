<?php
require_once __DIR__ . '/../config/init.php';
requireRole('admin');

$pageTitle  = 'Top Students';
$activePage = 'top';
$pageCss    = ['admin-buttons', 'admin-top-students'];

$topStudents = $pdo->query("
    WITH agg AS (
        SELECT r.student_id,
               ROUND((SUM(r.marks) / NULLIF(SUM(r.total_marks), 0) * 100)::numeric, 2) AS avg_percentage
        FROM results r GROUP BY r.student_id
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
        FROM students s JOIN agg ON agg.student_id = s.id
    )
    SELECT * FROM ranked WHERE rnk = 1 ORDER BY class
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="main admin-top">

  <div class="topbar">
    <h1>Top Students</h1>
    <span class="pro-count-badge"><?php echo count($topStudents); ?> Classes</span>
  </div>

  <div class="card">
    <div class="pro-card-head">
      <span class="pro-head-icon" style="background:#fef9c3;color:#ca8a04;"><i class="fa-solid fa-trophy"></i></span>
      <h3>Best Student Per Class</h3>
    </div>

    <?php if (empty($topStudents)): ?>
      <div class="pro-empty">
        <i class="fa-solid fa-chart-bar"></i>
        <p>No results yet. Upload results to see top students.</p>
      </div>
    <?php else: ?>
      <div class="top-grid">
        <?php foreach ($topStudents as $i => $s):
          $gradeColors = [
            'A+' => ['bg'=>'#dcfce7','color'=>'#16a34a'],
            'A'  => ['bg'=>'#dbeafe','color'=>'#2563eb'],
            'B'  => ['bg'=>'#e0f2fe','color'=>'#0369a1'],
            'C'  => ['bg'=>'#fef9c3','color'=>'#ca8a04'],
            'D'  => ['bg'=>'#ffedd5','color'=>'#ea580c'],
            'F'  => ['bg'=>'#fee2e2','color'=>'#dc2626'],
          ];
          $gc = $gradeColors[$s['grade']] ?? $gradeColors['F'];
        ?>
          <div class="top-card">
            <div class="top-rank"><?php echo $i + 1; ?></div>
            <img class="top-avatar"
                 src="<?php echo $s['photo'] ? BASE_URL.'/uploads/photos/'.htmlspecialchars($s['photo']) : BASE_URL.'/assets/default-avatar.svg'; ?>"
                 alt="" />
            <div class="top-info">
              <div class="top-name"><?php echo htmlspecialchars($s['name']); ?></div>
              <div class="top-meta">
                <span><i class="fa-solid fa-graduation-cap"></i> Class <?php echo htmlspecialchars($s['class']); ?></span>
                <span><i class="fa-solid fa-id-card"></i> <?php echo htmlspecialchars($s['student_key']); ?></span>
              </div>
            </div>
            <div class="top-right">
              <span class="top-grade" style="background:<?php echo $gc['bg']; ?>;color:<?php echo $gc['color']; ?>;">
                <?php echo htmlspecialchars($s['grade']); ?>
              </span>
              <div class="top-pct"><?php echo htmlspecialchars($s['avg_percentage']); ?>%</div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
