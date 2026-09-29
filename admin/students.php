<?php
require_once __DIR__ . '/../config/init.php';
requireRole('admin');

$pageTitle  = 'Manage Students';
$activePage = 'students';
$pageCss    = ['admin-buttons', 'admin-students'];

$action = $_GET['action'] ?? '';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($action === 'delete' && $id) {
    $pdo->prepare('DELETE FROM students WHERE id = :id')->execute(['id' => $id]);
    $pdo->prepare('DELETE FROM users WHERE student_id = :id')->execute(['id' => $id]);
    flash('success', 'Student removed.');
    header('Location: students.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']    ?? '');
    $class   = trim($_POST['class']   ?? '');
    $contact = trim($_POST['contact'] ?? '');

    if (!$name || !$class) {
        flash('error', 'Name and class are required.');
        header('Location: students.php'); exit;
    }

    $photoFile = null;
    if (!empty($_FILES['photo']['name'])) {
        $ext       = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $photoFile = uniqid('student_') . '.' . $ext;
        move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__ . '/../uploads/photos/' . $photoFile);
    }

    if (!empty($_POST['id'])) {
        $sid    = (int)$_POST['id'];
        $sql    = 'UPDATE students SET name=?, class=?, contact=?';
        $params = [$name, $class, $contact];
        if ($photoFile) { $sql .= ', photo=?'; $params[] = $photoFile; }
        $sql .= ' WHERE id=?'; $params[] = $sid;
        $pdo->prepare($sql)->execute($params);
        flash('success', 'Student updated.');
    } else {
        $key = generateUniqueKey($pdo, 'students', 'student_key', 6);
        $pdo->prepare('INSERT INTO students (name,class,contact,photo,student_key) VALUES (?,?,?,?,?)')
            ->execute([$name, $class, $contact, $photoFile, $key]);
        $sid = $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO users (username,password,role,student_id) VALUES (?,?,?,?)')
            ->execute([$key, password_hash($key, PASSWORD_DEFAULT), 'student', $sid]);
        flash('success', 'Student added. Login ID & Password: ' . $key);
    }
    header('Location: students.php'); exit;
}

$editStudent = null;
if ($action === 'edit' && $id) {
    $stmt = $pdo->prepare('SELECT * FROM students WHERE id=?');
    $stmt->execute([$id]);
    $editStudent = $stmt->fetch();
}

$students  = $pdo->query('SELECT * FROM students ORDER BY id DESC')->fetchAll();
$classList = $pdo->query('SELECT name FROM classes ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
$noClasses = empty($classList);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="main admin-students">
  <div class="topbar">
    <h1><?php echo $editStudent ? 'Edit Student' : 'Manage Students'; ?></h1>
    <span class="count-badge"><?php echo count($students); ?> Students</span>
  </div>
  <div class="as-layout">
    <div class="as-form-col">
      <div class="card">
        <div class="as-card-head">
          <span class="as-head-icon <?php echo $editStudent ? 'icon-edit' : 'icon-add'; ?>">
            <i class="fa-solid <?php echo $editStudent ? 'fa-user-pen' : 'fa-user-plus'; ?>"></i>
          </span>
          <h3><?php echo $editStudent ? 'Edit Student' : 'Add New Student'; ?></h3>
        </div>
        <?php if ($noClasses): ?>
          <div class="as-no-class-warn">
            <i class="fa-solid fa-triangle-exclamation"></i>
            First add <a href="classes.php">Classes</a> and <a href="subjects.php">Subjects</a>.
          </div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data">
          <?php if ($editStudent): ?>
            <input type="hidden" name="id" value="<?php echo $editStudent['id']; ?>">
          <?php endif; ?>
          <div class="as-form-row">
            <div class="input-group">
              <label>Full Name</label>
              <input name="name" placeholder="e.g. Ali Ahmed"
                     value="<?php echo htmlspecialchars($editStudent['name'] ?? ''); ?>" required />
            </div>
            <div class="input-group">
              <label>Class</label>
              <select name="class" required>
                <option value="">— Select class —</option>
                <?php foreach ($classList as $cls): ?>
                  <option value="<?php echo htmlspecialchars($cls); ?>"
                    <?php echo ($editStudent['class'] ?? '') === $cls ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cls); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="input-group">
            <label>Contact</label>
            <input name="contact" placeholder="Phone number"
                   value="<?php echo htmlspecialchars($editStudent['contact'] ?? ''); ?>" />
          </div>
          <div class="input-group">
            <div class="as-info-note">
              <i class="fa-solid fa-circle-info"></i>
              <?php echo $editStudent ? 'Login ID unchanged unless reset.' : 'Login ID & password = auto-generated student ID.'; ?>
            </div>
          </div>
          <div class="input-group">
            <label>Photo</label>
            <label class="as-file-label" for="photoInput">
              <i class="fa-solid fa-cloud-arrow-up"></i>
              <span>Click to upload photo</span>
              <input type="file" name="photo" accept="image/*" id="photoInput" onchange="asPreview(this)" />
            </label>
            <?php if ($editStudent && $editStudent['photo']): ?>
              <img id="asPhotoPreview"
                   src="<?php echo BASE_URL; ?>/uploads/photos/<?php echo htmlspecialchars($editStudent['photo']); ?>"
                   class="as-preview-img" />
            <?php else: ?>
              <img id="asPhotoPreview" class="as-preview-img" style="display:none;" />
            <?php endif; ?>
          </div>
          <div class="as-form-actions">
            <button type="submit" class="btn as-btn-submit">
              <i class="fa-solid <?php echo $editStudent ? 'fa-floppy-disk' : 'fa-plus'; ?>"></i>
              <?php echo $editStudent ? 'Update Student' : 'Add Student'; ?>
            </button>
            <?php if ($editStudent): ?>
              <a href="students.php" class="btn as-btn-cancel">
                <i class="fa-solid fa-xmark"></i> Cancel
              </a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
    <div class="as-table-col">
      <div class="card">
        <div class="as-card-head">
          <span class="as-head-icon icon-list"><i class="fa-solid fa-users"></i></span>
          <h3>All Students</h3>
        </div>
        <?php if (empty($students)): ?>
          <div class="as-empty">
            <i class="fa-solid fa-user-slash"></i><p>No students added yet.</p>
          </div>
        <?php else: ?>
          <div class="as-table-wrap">
            <table class="table">
              <thead>
                <tr><th>Student</th><th>Class</th><th>Login ID</th><th>Contact</th><th></th></tr>
              </thead>
              <tbody>
                <?php foreach ($students as $s): ?>
                  <tr>
                    <td>
                      <div class="as-student-cell">
                        <img class="as-row-avatar"
                             src="<?php echo $s['photo'] ? BASE_URL.'/uploads/photos/'.htmlspecialchars($s['photo']) : BASE_URL.'/assets/default-avatar.svg'; ?>"
                             alt="" />
                        <div>
                          <div class="as-row-name"><?php echo htmlspecialchars($s['name']); ?></div>
                          <div class="as-row-sub">#<?php echo $s['id']; ?></div>
                        </div>
                      </div>
                    </td>
                    <td><span class="as-class-badge"><?php echo htmlspecialchars($s['class']); ?></span></td>
                    <td><code class="as-key-code"><?php echo htmlspecialchars($s['student_key']); ?></code></td>
                    <td class="small"><?php echo htmlspecialchars($s['contact'] ?? '—'); ?></td>
                    <td>
                      <div class="as-row-actions">
                        <a class="as-btn-icon as-edit" href="?action=edit&id=<?php echo $s['id']; ?>">
                          <i class="fa-solid fa-pen"></i>
                        </a>
                        <a class="as-btn-icon as-delete"
                           href="?action=delete&id=<?php echo $s['id']; ?>"
                           onclick="return confirm('Delete this student?')">
                          <i class="fa-solid fa-trash"></i>
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<script>
function asPreview(input) {
  var p = document.getElementById('asPhotoPreview');
  if (input.files && input.files[0]) {
    var r = new FileReader();
    r.onload = function(e) { p.src = e.target.result; p.style.display = 'block'; };
    r.readAsDataURL(input.files[0]);
  }
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
