  <script src="<?php echo defined("BASE_URL") ? BASE_URL : ""; ?>/assets/app.js"></script>

<!-- ===== TOAST SYSTEM ===== -->
<style>
#toast-container {
  position: fixed;
  top: 20px;
  right: 20px;
  z-index: 99999;
  display: flex;
  flex-direction: column;
  gap: 10px;
  pointer-events: none;
}

.toast {
  position: relative;
  overflow: hidden;
  display: flex;
  align-items: flex-start;
  gap: 12px;
  min-width: 300px;
  max-width: 400px;
  padding: 14px 16px;
  border-radius: 10px;
  background: #fff;
  border: 1px solid #e2e8f0;
  box-shadow: 0 4px 20px rgba(0,0,0,.12);
  pointer-events: all;
}

.toast-icon {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.9rem;
  flex-shrink: 0;
}

.toast.success .toast-icon { background: #dcfce7; color: #16a34a; }
.toast.error   .toast-icon { background: #fee2e2; color: #dc2626; }
.toast.warning .toast-icon { background: #fef9c3; color: #ca8a04; }
.toast.info    .toast-icon { background: #dbeafe; color: #2563eb; }

.toast-body { flex: 1; }

.toast-title {
  font-size: 0.85rem;
  font-weight: 700;
  margin-bottom: 2px;
}

.toast.success .toast-title { color: #15803d; }
.toast.error   .toast-title { color: #dc2626; }
.toast.warning .toast-title { color: #ca8a04; }
.toast.info    .toast-title { color: #1d4ed8; }

.toast-msg {
  font-size: 0.83rem;
  color: #475569;
  line-height: 1.4;
}

.toast-close {
  background: none;
  border: none;
  cursor: pointer;
  color: #94a3b8;
  font-size: 0.85rem;
  padding: 0;
  line-height: 1;
  flex-shrink: 0;
}

.toast-close:hover { color: #475569; }

.toast-progress {
  position: absolute;
  bottom: 0;
  left: 0;
  height: 3px;
  border-radius: 0 0 10px 10px;
  width: 100%;
}

.toast.success .toast-progress { background: #16a34a; }
.toast.error   .toast-progress { background: #dc2626; }
.toast.warning .toast-progress { background: #ca8a04; }
.toast.info    .toast-progress { background: #2563eb; }
</style>

<div id="toast-container"></div>

<script>
var toastIcons  = { success:'fa-circle-check', error:'fa-circle-xmark', warning:'fa-triangle-exclamation', info:'fa-circle-info' };
var toastTitles = { success:'Success', error:'Error', warning:'Warning', info:'Info' };

function showToast(type, message, duration) {
  duration = duration || 4000;
  var container = document.getElementById('toast-container');
  var id = 'toast-' + Date.now() + Math.random();

  var toast = document.createElement('div');
  toast.className = 'toast ' + type;
  toast.id = id;
  toast.innerHTML =
    '<div class="toast-icon"><i class="fa-solid ' + toastIcons[type] + '"></i></div>' +
    '<div class="toast-body">' +
      '<div class="toast-title">' + toastTitles[type] + '</div>' +
      '<div class="toast-msg">' + escT(message) + '</div>' +
    '</div>' +
    '<button class="toast-close" onclick="removeToast(\'' + id + '\')"><i class="fa-solid fa-xmark"></i></button>' +
    '<div class="toast-progress" id="prog-' + id + '"></div>';

  container.appendChild(toast);

  var prog = document.getElementById('prog-' + id);
  if (prog) {
    prog.style.width = '100%';
    prog.style.transition = 'width ' + (duration / 1000) + 's linear';
    setTimeout(function() { prog.style.width = '0%'; }, 30);
  }

  setTimeout(function() { removeToast(id); }, duration);
}

function removeToast(id) {
  var el = document.getElementById(id);
  if (el) el.remove();
}

function escT(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

document.addEventListener('DOMContentLoaded', function() {
  if (window.__flashPopups && window.__flashPopups.length) {
    window.__flashPopups.forEach(function(p) { showToast(p.type, p.msg); });
  }

  // Sidebar toggle for mobile
  var toggle  = document.getElementById('sbToggle');
  var sidebar = document.getElementById('sidebar');
  var overlay = document.getElementById('sbOverlay');

  if (toggle && sidebar && overlay) {
    toggle.addEventListener('click', function() {
      sidebar.classList.toggle('open');
      overlay.classList.toggle('open');
    });
    overlay.addEventListener('click', function() {
      sidebar.classList.remove('open');
      overlay.classList.remove('open');
    });
    // Close on nav link click (mobile)
    sidebar.querySelectorAll('.sb-link').forEach(function(link) {
      link.addEventListener('click', function() {
        sidebar.classList.remove('open');
        overlay.classList.remove('open');
      });
    });
  }
});
</script>



</body>
</html>
