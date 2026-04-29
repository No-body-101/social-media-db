<?php
// includes/nav.php — include this at the top of every page
$current = basename($_SERVER['PHP_SELF']);
?>
<nav>
  <a class="nav-brand" href="index.php">Nexus</a>
  <div class="nav-links">
    <a href="index.php"         class="<?= $current === 'index.php'         ? 'active' : '' ?>">🏠 Feed</a>
    <a href="search.php"        class="<?= $current === 'search.php'        ? 'active' : '' ?>">🔍 Search</a>
    <a href="messages.php"      class="<?= $current === 'messages.php'      ? 'active' : '' ?>">💬 Messages</a>
    <a href="notifications.php" class="<?= $current === 'notifications.php' ? 'active' : '' ?>">🔔 Notifications</a>
    <a href="profile.php?id=<?= $_SESSION['user_id'] ?>">👤 Profile</a>
    <a href="logout.php" style="color:#f76a8f;">Logout</a>
  </div>
</nav>
