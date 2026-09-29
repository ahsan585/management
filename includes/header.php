<?php
if (!isset($pageTitle)) $pageTitle = 'School Management';
$pageCss = $pageCss ?? [];

// Collect flash messages for toast
$_flashPopups = [];
foreach (['success', 'error', 'info', 'warning'] as $ftype) {
    $fmsg = flash($ftype);
    if ($fmsg) $_flashPopups[] = ['type' => $ftype, 'msg' => $fmsg];
}

// Cache-busting version
$v = '2.0.0';
$base = defined('BASE_URL') ? BASE_URL : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo htmlspecialchars($pageTitle); ?></title>

  <!-- Favicon -->
  <link rel="icon" type="image/svg+xml" href="<?php echo $base; ?>/assets/favicon.svg" />
  <link rel="shortcut icon" href="<?php echo $base; ?>/assets/favicon.svg" />

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

  <!-- Inter Font -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" />

  <!-- Base CSS -->
  <link rel="stylesheet" href="<?php echo $base; ?>/assets/css/base.css?v=<?php echo $v; ?>" />

  <!-- Page-specific CSS -->
  <?php foreach ((array)$pageCss as $css): ?>
  <link rel="stylesheet" href="<?php echo $base; ?>/assets/css/<?php echo htmlspecialchars($css); ?>.css?v=<?php echo $v; ?>" />
  <?php endforeach; ?>

  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>

<?php if (!empty($_flashPopups)): ?>
<script>window.__flashPopups = <?php echo json_encode($_flashPopups); ?>;</script>
<?php endif; ?>
