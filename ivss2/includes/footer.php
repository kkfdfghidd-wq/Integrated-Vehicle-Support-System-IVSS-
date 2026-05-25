<?php // includes/footer.php 
?>
<footer>
  <div class="logo" style="justify-content:center; display:flex; gap:10px; margin-bottom:20px;">
    <div class="logo-icon" style="width:36px;height:36px;border-radius:8px;overflow:hidden;">
      <img src="<?= SITE_URL ?>/images/ivss.jpg"
        alt="IVSS"
        style="width:100%;height:100%;object-fit:contain;">
    </div>
    <span style="color:var(--white);font-size:17px;font-weight:700;"><span style="color:var(--gold);">IV</span>SS</span>
  </div>
  <div class="footer-links">
    <a href="<?= SITE_URL ?>/index.php">Home</a>
    <a href="<?= SITE_URL ?>/pages/garages.php">Find Garage</a>
    <a href="<?= SITE_URL ?>/pages/register.php">Join as Driver</a>
    <a href="<?= SITE_URL ?>/pages/garage_register.php">Join as Garage</a>
    <a href="<?= SITE_URL ?>/pages/login.php">Login</a>
  </div>
  <p>© <?= date('Y') ?> IVSS — Integrated Vehicle Support System &nbsp;🇴🇲&nbsp; Oman</p>
  <p style="margin-top:6px;font-size:12px;">Aligned with Oman Vision 2040 &nbsp;|&nbsp; CICS Project</p>
</footer>
</body>

</html>