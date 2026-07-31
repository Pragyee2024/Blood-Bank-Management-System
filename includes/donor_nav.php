<?php
$__current = basename($_SERVER['SCRIPT_NAME']);
?>
<aside class="sidebar">
  <div class="brand">&#129656; HemoLink</div>
  <a class="side-link <?= $__current === 'profile.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>donor/profile.php"><span class="ico">&#128100;</span> Profile</a>
  <a class="side-link <?= $__current === 'history.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>donor/history.php"><span class="ico">&#128202;</span> Donation History</a>
  <div class="side-section">
    <a class="side-link side-logout" href="<?= BASE_URL ?>logout.php">Logout</a>
  </div>
</aside>
 