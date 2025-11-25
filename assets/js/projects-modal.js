document.addEventListener('DOMContentLoaded', function () {
  const modal = document.getElementById('createModal');
  const openBtn = document.getElementById('btnOpenCreate');         // header button
  const openBtnFirst = document.getElementById('btnOpenCreateFirst'); // empty-state button

  if (!modal) return;

  const overlay = modal.querySelector('.modal-overlay');
  const closeButtons = modal.querySelectorAll('[data-close]');
  const firstFocusable = modal.querySelector('input, select, textarea, button');

  // Function to open modal
  function openModal() {
    modal.setAttribute('aria-hidden', 'false');
    document.documentElement.style.overflow = 'hidden';
    document.body.style.overflow = 'hidden';
    modal.classList.add('open');
    if (firstFocusable) firstFocusable.focus();

    // Remove ?new=1 from URL if present
    if (window.history && window.location.search.includes('new=1')) {
      try {
        const url = new URL(window.location);
        url.searchParams.delete('new');
        window.history.replaceState({}, '', url.pathname + url.search + url.hash);
      } catch (e) { /* ignore */ }
    }
  }

  // Function to close modal
  function closeModal() {
    modal.setAttribute('aria-hidden', 'true');
    document.documentElement.style.overflow = '';
    document.body.style.overflow = '';
    modal.classList.remove('open');
    // Return focus to the button that opened the modal
    if (openBtn) openBtn.focus();
  }

  // Wire up page buttons to open the modal
  if (openBtn) openBtn.addEventListener('click', function (e) { e.preventDefault(); openModal(); });
  if (openBtnFirst) openBtnFirst.addEventListener('click', function (e) { e.preventDefault(); openModal(); });

  // Close handlers
  closeButtons.forEach(btn => btn.addEventListener('click', function (e) { e.preventDefault(); closeModal(); }));
  if (overlay) overlay.addEventListener('click', function (e) { e.preventDefault(); closeModal(); });

  // Close modal with Escape key
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal.classList.contains('open')) closeModal();
  });

  // Auto open modal if ?new=1 or #new is in the URL
  try {
    const params = new URLSearchParams(window.location.search);
    const hash = window.location.hash || '';
    if (params.get('new') === '1' || hash === '#new') {
      openModal();
    }
  } catch (err) { /* ignore */ }

  // Intercept clicks on links that point to proyectos.php?new=1
  document.addEventListener('click', function (e) {
    const a = e.target.closest && e.target.closest('a');
    if (!a || !a.getAttribute) return;
    const href = a.getAttribute('href') || '';
    if (href.indexOf('proyectos.php') !== -1 && href.indexOf('new=1') !== -1) {
      const path = window.location.pathname || '';
      if (path.endsWith('/proyectos.php') || path.endsWith('/pages/proyectos.php') || path.endsWith('proyectos.php')) {
        e.preventDefault();
        openModal();
      }
    }
  });

  // Prevent scroll bleed on resize when modal is open
  window.addEventListener('resize', function () {
    if (modal.classList.contains('open')) {
      document.documentElement.style.overflow = 'hidden';
      document.body.style.overflow = 'hidden';
    }
  });
});
