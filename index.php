<?php
require_once __DIR__ . '/config/init.php';

if (isLoggedIn()) redirectToDashboard();

$adminCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$error   = flash('error') ?: '';
$success = flash('success') ?: '';

$selectedRole = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = sanitize($_POST['role'] ?? '');
    $selectedRole = $role;

    if (!$username || !$password || !$role) {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :username AND role = :role');
        $stmt->execute(['username' => $username, 'role' => $role]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role']    = $user['role'];
            redirectToDashboard();
        }
        $error = 'Invalid credentials. Please check your ID and password.';
    }
}

$pageTitle = 'Login — Digital DSMS';
$pageCss   = ['login'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-page">

  <!-- ── LEFT ── -->
  <div class="auth-left">
    <div class="auth-brand">
      <div class="auth-brand-logo"><i class="fa-solid fa-graduation-cap"></i></div>
      <div>
        <div class="auth-brand-name">Digital DSMS</div>
        <div class="auth-brand-sub">School Management System</div>
      </div>
    </div>

    <div class="auth-illustration">
      <svg class="auth-school-svg" viewBox="0 0 400 300" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect width="400" height="300" fill="rgba(255,255,255,0.03)" rx="16"/>
        <rect x="0" y="240" width="400" height="60" fill="rgba(255,255,255,0.05)"/>
        <rect x="80" y="100" width="240" height="145" fill="rgba(255,255,255,0.08)" rx="4"/>
        <polygon points="60,100 200,40 340,100" fill="rgba(255,255,255,0.12)"/>
        <line x1="200" y1="40" x2="200" y2="10" stroke="rgba(255,255,255,0.4)" stroke-width="2"/>
        <rect x="200" y="10" width="24" height="14" fill="rgba(255,200,50,0.7)" rx="2"/>
        <rect x="170" y="185" width="60" height="60" fill="rgba(255,255,255,0.15)" rx="4"/>
        <circle cx="222" cy="215" r="3" fill="rgba(255,255,255,0.5)"/>
        <rect x="100" y="120" width="44" height="36" fill="rgba(99,179,237,0.2)" rx="3"/>
        <rect x="256" y="120" width="44" height="36" fill="rgba(99,179,237,0.2)" rx="3"/>
        <line x1="122" y1="120" x2="122" y2="156" stroke="rgba(255,255,255,0.2)" stroke-width="1"/>
        <line x1="100" y1="138" x2="144" y2="138" stroke="rgba(255,255,255,0.2)" stroke-width="1"/>
        <line x1="278" y1="120" x2="278" y2="156" stroke="rgba(255,255,255,0.2)" stroke-width="1"/>
        <line x1="256" y1="138" x2="300" y2="138" stroke="rgba(255,255,255,0.2)" stroke-width="1"/>
        <rect x="100" y="168" width="44" height="30" fill="rgba(99,179,237,0.15)" rx="3"/>
        <rect x="256" y="168" width="44" height="30" fill="rgba(99,179,237,0.15)" rx="3"/>
        <rect x="155" y="238" width="90" height="8" fill="rgba(255,255,255,0.12)" rx="2"/>
        <rect x="162" y="232" width="76" height="8" fill="rgba(255,255,255,0.1)" rx="2"/>
        <rect x="30" y="210" width="8" height="32" fill="rgba(255,255,255,0.15)" rx="2"/>
        <ellipse cx="34" cy="200" rx="20" ry="22" fill="rgba(34,197,94,0.12)"/>
        <rect x="362" y="210" width="8" height="32" fill="rgba(255,255,255,0.15)" rx="2"/>
        <ellipse cx="366" cy="200" rx="20" ry="22" fill="rgba(34,197,94,0.12)"/>
        <circle cx="340" cy="58" r="18" fill="rgba(251,191,36,0.15)"/>
        <circle cx="340" cy="58" r="10" fill="rgba(251,191,36,0.2)"/>
        <rect x="148" y="72" width="104" height="22" fill="rgba(255,255,255,0.1)" rx="4"/>
        <text x="200" y="87" text-anchor="middle" fill="rgba(255,255,255,0.5)" font-size="9" font-family="system-ui" font-weight="700" letter-spacing="1">DIGITAL DSMS</text>
      </svg>
    </div>

    <div class="auth-left-footer">
      <div class="auth-left-title">Welcome Back!</div>
      <div class="auth-left-desc">Manage students, teachers, attendance, results and more — all in one place.</div>
      <div class="auth-features">
        <span class="auth-feature-pill"><i class="fa-solid fa-users"></i> Students</span>
        <span class="auth-feature-pill"><i class="fa-solid fa-chalkboard-user"></i> Teachers</span>
        <span class="auth-feature-pill"><i class="fa-solid fa-chart-line"></i> Results</span>
        <span class="auth-feature-pill"><i class="fa-solid fa-calendar-check"></i> Attendance</span>
      </div>
    </div>
  </div>

  <!-- ── RIGHT ── -->
  <div class="auth-right">
    <div class="auth-form-box">

      <div class="auth-form-header">
        <div class="auth-form-icon"><i class="fa-solid fa-right-to-bracket"></i></div>
        <h1 class="auth-form-title">Sign in to your account</h1>
        <p class="auth-form-sub">Select your role and enter your credentials</p>
      </div>

      <?php if ($success): ?>
        <div class="auth-alert success"><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="auth-alert error"><i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <form method="post" action="" id="loginForm">

        <!-- Role selector cards -->
        <div class="input-group">
          <label>Select Role</label>
          <div class="auth-role-cards">
            <div class="auth-role-card <?php echo $selectedRole === 'admin' ? 'selected' : ''; ?>"
                 onclick="selectRole('admin')">
              <i class="fa-solid fa-shield-halved" style="color:#f59e0b;"></i>
              Admin
            </div>
            <div class="auth-role-card <?php echo $selectedRole === 'teacher' ? 'selected' : ''; ?>"
                 onclick="selectRole('teacher')">
              <i class="fa-solid fa-chalkboard-user" style="color:#34d399;"></i>
              Teacher
            </div>
            <div class="auth-role-card <?php echo $selectedRole === 'student' ? 'selected' : ''; ?>"
                 onclick="selectRole('student')">
              <i class="fa-solid fa-user-graduate" style="color:#60a5fa;"></i>
              Student
            </div>
          </div>
          <input type="hidden" name="role" id="roleInput" value="<?php echo htmlspecialchars($selectedRole); ?>" required />
        </div>

        <div class="input-group">
          <label for="username">ID / Username</label>
          <div class="auth-input-wrap">
            <input id="username" name="username" type="text"
                   placeholder="Enter your ID or username"
                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                   required autocomplete="username" />
          </div>
        </div>

        <div class="input-group">
          <label for="password">Password</label>
          <div class="auth-input-wrap">
            <input id="password" name="password" type="password"
                   placeholder="Enter your password"
                   required autocomplete="current-password" />
            <button type="button" class="auth-pw-toggle" onclick="togglePw('password', this)">
              <i class="fa-solid fa-eye"></i>
            </button>
          </div>
          <div class="auth-input-hint">
            <i class="fa-solid fa-circle-info"></i>
            Students &amp; Teachers: use your Login ID as password on first login
          </div>
        </div>

        <button type="submit" class="auth-submit">
          <i class="fa-solid fa-right-to-bracket"></i> Sign In
        </button>
      </form>

      <?php if ($adminCount == 0): ?>
        <div class="auth-form-footer">
          No admin account yet? <a href="signup.php">Create one</a>
        </div>
      <?php endif; ?>

    </div>
  </div>

</div>

<script>
function selectRole(role) {
  document.getElementById('roleInput').value = role;
  document.querySelectorAll('.auth-role-card').forEach(function(c) {
    c.classList.remove('selected');
  });
  event.currentTarget.classList.add('selected');
}

function togglePw(id, btn) {
  var inp = document.getElementById(id);
  var icon = btn.querySelector('i');
  if (inp.type === 'password') {
    inp.type = 'text';
    icon.className = 'fa-solid fa-eye-slash';
  } else {
    inp.type = 'password';
    icon.className = 'fa-solid fa-eye';
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
