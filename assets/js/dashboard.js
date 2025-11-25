// assets/js/dashboard.js
// JS for sidebar toggle and user dropdown. Comments in English.

document.addEventListener('DOMContentLoaded', function () {
  const btn = document.getElementById('btnToggleSidebar');
  const app = document.querySelector('.app');
  const sidebar = document.querySelector('.sidebar');
  const userBtn = document.getElementById('userBtn');
  const userMenu = document.getElementById('userMenuDropdown');

  // Toggle sidebar behavior (desktop: collapse, mobile: slide-in)
  if (btn) {
    btn.addEventListener('click', () => {
      if (window.innerWidth > 980) {
        app.classList.toggle('collapsed');
      } else {
        sidebar.classList.toggle('open');
        app.classList.toggle('dimmed');
      }
    });
  }

  // Close mobile sidebar when clicking outside
  document.addEventListener('click', (e) => {
    if (window.innerWidth <= 980 && sidebar.classList.contains('open')) {
      if (!sidebar.contains(e.target) && e.target !== btn) {
        sidebar.classList.remove('open');
        app.classList.remove('dimmed');
      }
    }
  });

  // User dropdown toggle
  if (userBtn && userMenu) {
    userBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      const isOpen = userMenu.style.display === 'block';
      userMenu.style.display = isOpen ? 'none' : 'block';
      userBtn.setAttribute('aria-expanded', !isOpen);
    });

    // close dropdown clicking outside
    document.addEventListener('click', (e) => {
      if (!userBtn.contains(e.target) && !userMenu.contains(e.target)) {
        userMenu.style.display = 'none';
        userBtn.setAttribute('aria-expanded', 'false');
      }
    });
  }

  // Improve accessibility: close dropdown with ESC
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      if (userMenu) userMenu.style.display = 'none';
      if (sidebar && sidebar.classList.contains('open')) {
        sidebar.classList.remove('open');
        app.classList.remove('dimmed');
      }
      if (app.classList.contains('collapsed') && window.innerWidth > 980) {
        // do nothing — keep collapsed state
      }
    }
  });

  // When resizing from small to large, ensure sidebar state resets
  window.addEventListener('resize', () => {
    if (window.innerWidth > 980) {
      sidebar.classList.remove('open');
      app.classList.remove('dimmed');
    } else {
      // allow mobile behaviors
    }
  });
});
