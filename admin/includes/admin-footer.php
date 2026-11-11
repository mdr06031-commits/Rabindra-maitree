<?php
/**
 * Admin Panel Footer
 */
?>
    </main>

    <footer style="padding: 1rem 1.25rem; background: #ffffff; border-top: 1px solid #e2e8f0; font-size: 0.82rem; color: #64748b; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.65rem;">
      <div>
        &copy; <?= date('Y') ?> Rabindra Maitree University &bull; Internal Administration Console
      </div>
      <div style="display:flex; align-items:center; gap:0.85rem; flex-wrap:wrap;">
        <button type="button" data-pwa-install-btn hidden style="display:none; align-items:center; gap:0.35rem; background:#0f2c59; color:#ffffff; border:none; border-radius:6px; padding:0.4rem 0.85rem; min-height:44px; font-size:0.82rem; font-weight:700; cursor:pointer;">
          <i class="fas fa-download" aria-hidden="true"></i> Install Admin App
        </button>
        <span style="display: inline-flex; align-items: center; gap: 0.35rem; color: #10b981;">
          <i class="fas fa-circle" style="font-size: 0.5rem;"></i> System Active
        </span>
      </div>
    </footer>
  </div>
  <script>
    function toggleAdminSidebar(forceState) {
      var sidebar = document.querySelector('.admin-sidebar');
      var backdrop = document.getElementById('adminSidebarBackdrop');
      if (!sidebar) return;
      var open = (typeof forceState === 'boolean') ? forceState : !sidebar.classList.contains('open');
      sidebar.classList.toggle('open', open);
      if (backdrop) backdrop.classList.toggle('show', open);
      if (document.body) document.body.classList.toggle('rmu-drawer-open', open);
    }
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        var sidebar = document.querySelector('.admin-sidebar');
        if (sidebar && sidebar.classList.contains('open')) {
          toggleAdminSidebar(false);
          var btn = document.querySelector('.admin-mobile-toggle');
          if (btn) btn.focus();
        }
      }
    });
    document.addEventListener('click', function(e) {
      if (window.innerWidth <= 991 && e.target.closest('.admin-sidebar .admin-nav-link')) {
        toggleAdminSidebar(false);
      }
    });
  </script>
  <script src="<?= e(rmu_asset_url('assets/js/pwa.js')) ?>"></script>
</body>
</html>

