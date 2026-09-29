<?php
require_once __DIR__ . '/../config/init.php';
requireRole('admin');

$pageTitle  = 'Manage Teachers';
$activePage = 'teachers';
$pageCss    = ['admin-buttons', 'admin-students', 'admin-teachers'];

$action = $_GET['action'] ?? '';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// DELETE
if ($action === 'delete' && $id) {
    $pdo->prepare('DELETE FROM teachers WHERE id = :id')->execute(['id' => $id]);
    $pdo->prepare('DELETE FROM users WHERE teacher_id = :id')->execute(['id' => $id]);
    flash('success', 'Teacher removed.');
    header('Location: ' . BASE_URL . '/admin/teachers.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = sanitize($_POST['name']     ?? '');
    $subject  = sanitize($_POST['subject']  ?? '');
    $contact  = sanitize($_POST['contact']  ?? '');

    if (!$name) $errors[] = 'Name is required.';

    $photoFile = null;
    if (!empty($_FILES['photo']['name'])) {
        $ext       = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $photoFile = uniqid('teacher_') . '.' . $ext;
        move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__ . '/../uploads/photos/' . $photoFile);
    }

    if (empty($errors)) {
        if (!empty($_POST['id'])) {
            $teacherId = (int)$_POST['id'];
            $sql    = 'UPDATE teachers SET name=:name, subject=:subject, contact=:contact';
            $params = ['name' => $name, 'subject' => $subject, 'contact' => $contact];
            if ($photoFile) { $sql .= ', photo=:photo'; $params['photo'] = $photoFile; }
            $sql .= ' WHERE id=:id';
            $params['id'] = $teacherId;
            $pdo->prepare($sql)->execute($params);
            flash('success', 'Teacher updated.');
        } else {
            $key = generateUniqueKey($pdo, 'teachers', 'teacher_key', 6);
            $pdo->prepare('INSERT INTO teachers (name, subject, contact, teacher_key, photo) VALUES (:name, :subject, :contact, :key, :photo)')
                ->execute(['name' => $name, 'subject' => $subject, 'contact' => $contact, 'key' => $key, 'photo' => $photoFile]);
            $teacherId = $pdo->lastInsertId();
            // Default password = teacher_key (ID)
            $pdo->prepare('INSERT INTO users (username, password, role, teacher_id) VALUES (:u, :p, :r, :tid)')
                ->execute([
                    'u'   => $key,
                    'p'   => password_hash($key, PASSWORD_DEFAULT),
                    'r'   => 'teacher',
                    'tid' => $teacherId,
                ]);
            flash('success', 'Teacher added. Login ID & Password: ' . $key);
        }
        header('Location: ' . BASE_URL . '/admin/teachers.php');
        exit;
    }
    if (!empty($errors)) {
        foreach ($errors as $_e) { flash('error', $_e); }
        header('Location: ' . BASE_URL . '/admin/teachers.php');
        exit;
    }
}

$editTeacher = null;
if ($action === 'edit' && $id) {
    $stmt = $pdo->prepare('SELECT * FROM teachers WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $editTeacher = $stmt->fetch();
}

$teachers = $pdo->query('SELECT * FROM teachers ORDER BY id DESC')->fetchAll();
$success  = flash('success');

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="main admin-teachers">

  <div class="topbar">
    <h1><?php echo $editTeacher ? 'Edit Teacher' : 'Manage Teachers'; ?></h1>
    <span class="count-badge"><?php echo count($teachers); ?> Teachers</span>
  </div>

  <div class="as-layout">

    <!-- FORM COLUMN -->
    <div class="as-form-col">
      <div class="card">
        <div class="as-card-head">
          <span class="as-head-icon <?php echo $editTeacher ? 'icon-edit' : 'icon-add'; ?>">
            <i class="fa-solid <?php echo $editTeacher ? 'fa-user-pen' : 'fa-chalkboard-user'; ?>"></i>
          </span>
          <h3><?php echo $editTeacher ? 'Edit Teacher' : 'Add New Teacher'; ?></h3>
        </div>

        <form method="post" enctype="multipart/form-data">
          <?php if ($editTeacher): ?>
            <input type="hidden" name="id" value="<?php echo (int)$editTeacher['id']; ?>" />
          <?php endif; ?>

          <div class="as-form-row">
            <div class="input-group">
              <label>Full Name</label>
              <input name="name" placeholder="Full name"
                     value="<?php echo htmlspecialchars($editTeacher['name'] ?? ''); ?>" required />
            </div>
            <div class="input-group">
              <label>Subject</label>
              <input name="subject" placeholder="e.g. Mathematics"
                     value="<?php echo htmlspecialchars($editTeacher['subject'] ?? ''); ?>" />
            </div>
          </div>

          <div class="as-form-row">
            <div class="input-group">
              <label>Contact</label>
              <input name="contact" placeholder="Phone number"
                     value="<?php echo htmlspecialchars($editTeacher['contact'] ?? ''); ?>" />
            </div>
          </div>

          <div class="input-group">
            <div class="as-info-note">
              <i class="fa-solid fa-circle-info"></i>
              <?php if ($editTeacher): ?>
                Login ID & password remain unchanged unless reset manually.
              <?php else: ?>
                Login ID and password will both be set to the auto-generated teacher ID.
              <?php endif; ?>
            </div>
          </div>

          <div class="input-group">
            <label>Photo</label>
            <label class="as-file-label" for="photoInputT">
              <i class="fa-solid fa-cloud-arrow-up"></i>
              <span>Click to upload photo</span>
              <input type="file" name="photo" accept="image/*" id="photoInputT"
                     onchange="atPreview(this)"
                     <?php echo $editTeacher ? '' : 'required'; ?> />
            </label>
            <?php if ($editTeacher && $editTeacher['photo']): ?>
              <img id="atPhotoPreview"
                   src="<?php echo $editTeacher['photo'] ? BASE_URL.'/uploads/photos/'.htmlspecialchars($editTeacher['photo']) : ''; ?>"
                   class="as-preview-img" />
            <?php else: ?>
              <img id="atPhotoPreview" class="as-preview-img" style="display:none;" />
            <?php endif; ?>
          </div>

          <div class="as-form-actions">
            <button type="submit" class="btn as-btn-submit">
              <i class="fa-solid <?php echo $editTeacher ? 'fa-floppy-disk' : 'fa-plus'; ?>"></i>
              <?php echo $editTeacher ? 'Update Teacher' : 'Add Teacher'; ?>
            </button>
            <?php if ($editTeacher): ?>
              <a href="<?php echo BASE_URL; ?>/admin/teachers.php" class="btn as-btn-cancel">
                <i class="fa-solid fa-xmark"></i> Cancel
              </a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>

    <!-- TABLE COLUMN -->
    <div class="as-table-col">
      <div class="card">
        <div class="as-card-head">
          <span class="as-head-icon icon-list">
            <i class="fa-solid fa-chalkboard-user"></i>
          </span>
          <h3>All Teachers</h3>
        </div>

        <?php if (empty($teachers)): ?>
          <div class="as-empty">
            <i class="fa-solid fa-user-slash"></i>
            <p>No teachers added yet.</p>
          </div>
        <?php else: ?>
          <div class="as-table-wrap">
            <table class="table">
              <thead>
                <tr>
                  <th>Teacher</th>
                  <th>Subject</th>
                  <th>Login ID</th>
                  <th>Contact</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($teachers as $t): ?>
                  <tr>
                    <td>
                      <div class="as-student-cell">
                        <img class="as-row-avatar"
                             src="<?php echo $t['photo'] ? BASE_URL.'/uploads/photos/'.htmlspecialchars($t['photo']) : BASE_URL.'/assets/default-avatar.svg'; ?>"
                             alt="" />
                        <div>
                          <div class="as-row-name"><?php echo htmlspecialchars($t['name']); ?></div>
                          <div class="as-row-sub">#<?php echo $t['id']; ?></div>
                        </div>
                      </div>
                    </td>
                    <td class="small"><?php echo htmlspecialchars($t['subject'] ?? '—'); ?></td>
                    <td><code class="as-key-code"><?php echo htmlspecialchars($t['teacher_key']); ?></code></td>
                    <td class="small"><?php echo htmlspecialchars($t['contact'] ?? '—'); ?></td>
                    <td>
                      <div class="as-row-actions">
                        <a class="as-btn-icon as-edit"
                           href="<?php echo BASE_URL; ?>/admin/teachers.php?action=edit&id=<?php echo (int)$t['id']; ?>"
                           title="Edit">
                          <i class="fa-solid fa-pen"></i>
                        </a>
                        <a class="as-btn-icon as-delete"
                           href="<?php echo BASE_URL; ?>/admin/teachers.php?action=delete&id=<?php echo (int)$t['id']; ?>"
                           onclick="return confirm('Delete this teacher?')" title="Delete">
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
function atPreview(input) {
  var preview = document.getElementById('atPhotoPreview');
  if (input.files && input.files[0]) {
    var reader = new FileReader();
    reader.onload = function(e) {
      preview.src = e.target.result;
      preview.style.display = 'block';
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
