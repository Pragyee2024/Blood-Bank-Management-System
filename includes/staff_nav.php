<nav>
  <div><strong>🩸 Blood Bank</strong></div>
  <div>
    <a href="<?= BASE_URL ?>admin/dashboard.php">Dashboard</a>
    <a href="<?= BASE_URL ?>requests/request_form.php">New Request</a>
    <a href="<?= BASE_URL ?>requests/status.php">Request Status</a>
    <a href="<?= BASE_URL ?>inventory/stock.php">Inventory Stock</a>
    <a href="<?= BASE_URL ?>inventory/add_stock.php">Add Stock</a>
    <a href="<?= BASE_URL ?>inventory/expiry.php">Expiry List</a>
    <a href="<?= BASE_URL ?>logout.php">Logout (<?= htmlspecialchars($user['username']) ?>)</a>
  </div>
</nav>

