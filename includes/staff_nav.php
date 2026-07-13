<?php
// Shared top nav for admin/staff pages (dashboard, requests, approve).
// Include AFTER require_login() has set $user, from inside <div class="wrap">.
// Paths are root-relative so this renders correctly whether the including
// page lives in /admin/ or /requests/.
?>
<nav>
  <div><strong>🩸 Blood Bank</strong></div>
  <div>
    <a href="/admin/dashboard.php">Dashboard</a>
    <a href="/requests/request_form.php">New Request</a>
    <a href="/requests/status.php">Request Status</a>
    <a href="/logout.php">Logout (<?= htmlspecialchars($user['username']) ?>)</a>
  </div>
</nav>
