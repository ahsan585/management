<?php
require_once __DIR__ . '/config/init.php';

if (isLoggedIn()) redirectToDashboard();

$adminCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
if ($adminCount) {
    flash('error', 'Admin account already exists. Please login.');
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username        = sanitize($_POST['username']        ?? '');
    $password        = $_POST['password']                 ?? '';
    $confirmPassword = $_POST['confirm_password']         ?? '';

    if (!$username || !$password || !$confirmPassword) {
        $error = 'Please fill in all fields.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :username');
        $stmt->execute(['username' => $username]);
        if ($stmt->fetchColumn() > 0) {
            $error = 'Username already taken. Choose another.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare('INSERT INTO users (username, password, role) VALUES (:username, :password, :role)')
                ->execute(['username' => $username, 'password' => $hash, 'role' => 'admin']);
            flash('success', 'Admin account created successfully. Please login.');
            header('Location: ' . BASE_URL . '/index.php');
            exit;
        }
    }
}

$pageTitle = 'Setup Admin — Digital DSMS';
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
        <rect x="155" y="238" width="90" height="8" fill="rgba(255,255,255,0.12)" rx="2"/>
        <rect x="162" y="232" width="76" height="8" fill="rgba(255,255,255,0.1)" rx="2"/>
        <rect x="30" y="210" width="8" height="32" fill="rgba(255,255,255,0.15)" rx="2"/>
        <ellipse cx="34" cy="200" rx="20" ry="22" fill="rgba(34,197,94,0.12)"/>
        <rect x="362" y="210" width="8" height="32" fill="rgba(255,255,255,0.15)" rx="2"/>
        <ellipse cx="366" cy="200" rx="20" ry="22" fill="rgba(34,197,94,0.12)"/>
        <rect x="148" y="72" width="104" height="22" fill="rgba(255,255,255,0.1)" rx="4"/>
        <text x="200" y="87" text-anchor="middle" fill="rgba(255,255,255,0.5)" font-size="9" font-family="system-ui" font-weight="700" letter-spacing="1">DIGITAL DSMS</text>
      </svg>
    </div>

    <div class="auth-left-footer">
      <div class="auth-left-title">First Time Setup</div>
      <div class="auth-left-desc">Create your admin account to start managing your school with Digital DSMS.</div>
      <div class="auth-features">
        <span class="auth-feature-pill"><i class="fa-solid fa-shield-halved"></i> Secure</span>
        <span class="auth-feature-pill"><i class="fa-solid fa-bolt"></i> Quick Setup</span>
        <span class="auth-feature-pill"><i class="fa-solid fa-lock"></i> Admin Only</span>
      </div>
    </div>
  </div>

  <!-- ── RIGHT ── -->
  <div class="auth-right">
    <div class="auth-form-box">

      <div class="auth-form-header">
        <div class="auth-form-icon" style="background:#dcfce7;color:#16a34a;box-shadow:0 4px 12px rgba(22,163,74,.15);">
          <i class="fa-solid fa-user-shield"></i>
        </div>
        <h1 class="auth-form-title">Create Admin Account</h1>
        <p class="auth-form-sub">Set up the administrator account for your school</p>
      </div>

      <?php if ($error): ?>
        <div class="auth-alert error"><i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <form method="post" action="">

        <div class="input-group">
          <label for="username">Username</label>
          <div class="auth-input-wrap">
            <input id="username" name="username" type="text"
                   placeholder="Choose a username"
                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                   required autocomplete="username" />
          </div>
        </div>

        <div class="input-group">
          <label for="password">Password</label>
          <div class="auth-input-wrap">
            <input id="password" name="password" type="password"
                   placeholder="Create a strong password (min 6 chars)"
                   required autocomplete="new-password" />
            <button type="button" class="auth-pw-toggle" onclick="togglePw('password', this)">
              <i class="fa-solid fa-eye"></i>
            </button>
          </div>
        </div>

        <div class="input-group">
          <label for="confirm_password">Confirm Password</label>
          <div class="auth-input-wrap">
            <input id="confirm_password" name="confirm_password" type="password"
                   placeholder="Repeat your password"
                   required autocomplete="new-password" />
            <button type="button" class="auth-pw-toggle" onclick="togglePw('confirm_password', this)">
              <i class="fa-solid fa-eye"></i>
            </button>
          </div>
        </div>

        <!-- Password strength indicator -->
        <div id="pwStrengthWrap" style="margin-top:-10px;margin-bottom:16px;display:none;">
          <div style="height:4px;background:#e2e8f0;border-radius:4px;overflow:hidden;">
            <div id="pwStrengthBar" style="height:100%;border-radius:4px;width:0%;"></div>
          </div>
          <div id="pwStrengthLabel" style="font-size:0.72rem;margin-top:4px;color:#94a3b8;"></div>
        </div>

        <button type="submit" class="auth-submit green">
          <i class="fa-solid fa-user-plus"></i> Create Admin Account
        </button>
      </form>

      <div class="auth-form-footer">
        Already have an account? <a href="index.php">Sign in</a>
      </div>

    </div>
  </div>

</div>

<script>
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

// Password strength
document.getElementById('password').addEventListener('input', function() {
  var val = this.value;
  var wrap = document.getElementById('pwStrengthWrap');
  var bar  = document.getElementById('pwStrengthBar');
  var lbl  = document.getElementById('pwStrengthLabel');

  if (!val) { wrap.style.display = 'none'; return; }
  wrap.style.display = 'block';

  var score = 0;
  if (val.length >= 6)  score++;
  if (val.length >= 10) score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;

  var levels = [
    { w: '20%', color: '#ef4444', label: 'Very weak' },
    { w: '40%', color: '#f97316', label: 'Weak' },
    { w: '60%', color: '#eab308', label: 'Fair' },
    { w: '80%', color: '#22c55e', label: 'Strong' },
    { w: '100%', color: '#16a34a', label: 'Very strong' },
  ];

  var lvl = levels[Math.min(score, 4)];
  bar.style.width = lvl.w;
  bar.style.background = lvl.color;
  lbl.textContent = lvl.label;
  lbl.style.color = lvl.color;
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
