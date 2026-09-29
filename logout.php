<?php
require_once __DIR__ . '/config/init.php';

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}
session_destroy();

$pageTitle = 'Logged Out — Digital DSMS';
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
      <div class="auth-left-title">See you soon!</div>
      <div class="auth-left-desc">You have been securely signed out. Your session data has been cleared.</div>
      <div class="auth-features">
        <span class="auth-feature-pill"><i class="fa-solid fa-shield-halved"></i> Session Cleared</span>
        <span class="auth-feature-pill"><i class="fa-solid fa-lock"></i> Secure Logout</span>
      </div>
    </div>
  </div>

  <!-- ── RIGHT ── -->
  <div class="auth-right">
    <div class="auth-form-box">
      <div class="auth-logout-box">

        <div class="auth-logout-icon">
          <i class="fa-solid fa-circle-check"></i>
        </div>

        <div class="auth-logout-title">Logged Out Successfully</div>
        <div class="auth-logout-msg">
          Your session has ended and you have been securely signed out of Digital DSMS.
        </div>

        <a href="<?php echo BASE_URL; ?>/index.php" class="auth-logout-btn">
          <i class="fa-solid fa-right-to-bracket"></i> Back to Login
        </a>

        <div class="auth-redirect">
          Redirecting in <span id="countdown">5</span> seconds...
        </div>

      </div>
    </div>
  </div>

</div>

<script>
var n = 5;
var el = document.getElementById('countdown');
var t = setInterval(function() {
  n--;
  if (el) el.textContent = n;
  if (n <= 0) {
    clearInterval(t);
    window.location.href = '<?php echo BASE_URL; ?>/index.php';
  }
}, 1000);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
