<?php $__current = basename($_SERVER['SCRIPT_NAME']); ?>
<aside class="sidebar">
  <div class="brand">&#129656; HemoLink</div>
  <a class="side-link <?= $__current === 'dashboard.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>admin/dashboard.php"><span class="ico">&#128202;</span> Dashboard</a>
  <a class="side-link <?= $__current === 'request_form.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>requests/request_form.php"><span class="ico">&#10133;</span> New Request</a>
  <a class="side-link <?= $__current === 'status.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>requests/status.php"><span class="ico">&#128203;</span> Request Status</a>
  <a class="side-link <?= $__current === 'stock.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>inventory/stock.php"><span class="ico">&#129666;</span> Inventory Stock</a>
  <a class="side-link <?= $__current === 'add_stock.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>inventory/add_stock.php"><span class="ico">&#128230;</span> Add Stock</a>
  <a class="side-link <?= $__current === 'expiry.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>inventory/expiry.php"><span class="ico">&#8987;</span> Expiry List</a>
  <div class="side-section">
    <div class="side-user"><?= htmlspecialchars($user['username']) ?></div>
    <a class="side-link side-logout" href="<?= BASE_URL ?>logout.php">Logout</a>
  </div>
</aside>
