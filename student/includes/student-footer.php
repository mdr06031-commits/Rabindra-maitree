<?php
/**
 * Rabindra Maitree University - Student Portal Footer
 */
?>
  <footer style="background:#ffffff; border-top:1px solid #e2e8f0; padding:2rem 0; margin-top:auto;" class="no-print">
    <div style="max-width:1300px; margin:0 auto; padding:0 1.25rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; font-size:0.85rem; color:#64748b;">
      <div>
        &copy; <?= date('Y') ?> <strong><?= APP_NAME ?></strong>. Student Information &amp; Portal System.
      </div>
      <div style="display:flex; gap:0.85rem 1.25rem; align-items:center; flex-wrap:wrap;">
        <span><i class="fas fa-headset" style="color:var(--sp-primary);"></i> Student Helpdesk: <a href="tel:+8801302991188" style="color:#334155; font-weight:600; text-decoration:none;">+8801302991188</a></span>
        <span><i class="fas fa-envelope" style="color:var(--sp-primary);"></i> <a href="mailto:support@rmu.ac.bd" style="color:#334155; font-weight:600; text-decoration:none;">support@rmu.ac.bd</a></span>
        <button type="button" data-pwa-install-btn hidden style="display:none; align-items:center; gap:0.35rem; background:#0f2c59; color:#ffffff; border:none; border-radius:6px; padding:0.4rem 0.85rem; min-height:44px; font-size:0.82rem; font-weight:700; cursor:pointer;">
          <i class="fas fa-download" aria-hidden="true"></i> Install Portal App
        </button>
      </div>
    </div>
  </footer>

  <script src="<?= e(rmu_asset_url('assets/js/main.js')) ?>"></script>
  <script src="<?= e(rmu_asset_url('assets/js/pwa.js')) ?>"></script>
</body>
</html>

