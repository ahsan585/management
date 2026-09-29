// assets/app.js

window.addEventListener('DOMContentLoaded', () => {
  const notice = document.querySelector('[data-notice]');
  if (notice) {
    const msg = notice.getAttribute('data-notice');
    if (msg) {
      window.alert(msg);
    }
  }
});
