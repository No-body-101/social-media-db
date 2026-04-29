<?php
session_start();
require 'db.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$me = $_SESSION['user_id'];

$query   = trim($_GET['q'] ?? '');
$results = [];

if ($query) {
    $like = "%$query%";
    $stmt = $conn->prepare("
        SELECT u.user_id, u.username, u.bio,
               (SELECT COUNT(*) FROM follows WHERE following_id = u.user_id) AS followers,
               (SELECT COUNT(*) FROM follows WHERE follower_id = ? AND following_id = u.user_id) AS i_follow
        FROM user u
        WHERE (u.username LIKE ? OR u.bio LIKE ?)
          AND u.is_deleted = FALSE
          AND u.user_id != ?
        LIMIT 20
    ");
    $stmt->bind_param("issi", $me, $like, $like, $me);
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Search — Nexus</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'includes/nav.php'; ?>
<div class="container">
  <h2 style="margin-bottom:1.2rem;font-family:'Playfair Display',serif;">Search People</h2>

  <form method="GET" class="search-bar">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
    <input type="text" name="q" placeholder="Search by username or bio..." value="<?= htmlspecialchars($query) ?>" autofocus>
  </form>

  <?php if ($query && empty($results)): ?>
    <div class="empty">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <h3>No results found</h3>
      <p>Try a different search term.</p>
    </div>
  <?php endif; ?>

  <?php foreach ($results as $u): ?>
  <div class="card" style="display:flex;align-items:center;gap:1rem;margin-bottom:1rem;">
    <a href="profile.php?id=<?= $u['user_id'] ?>" class="avatar avatar-lg"><?= strtoupper(substr($u['username'],0,1)) ?></a>
    <div style="flex:1;">
      <a href="profile.php?id=<?= $u['user_id'] ?>" style="font-weight:600;font-size:1rem;color:var(--text);text-decoration:none;"><?= htmlspecialchars($u['username']) ?></a>
      <p style="color:var(--muted);font-size:0.85rem;margin:0.2rem 0;"><?= htmlspecialchars($u['bio'] ?? 'No bio') ?></p>
      <p style="color:var(--muted);font-size:0.78rem;"><?= $u['followers'] ?> followers</p>
    </div>
    <form method="POST" action="follow.php">
      <input type="hidden" name="target_id" value="<?= $u['user_id'] ?>">
      <input type="hidden" name="redirect" value="search.php?q=<?= urlencode($query) ?>">
      <button type="submit" class="btn <?= $u['i_follow'] ? 'btn-secondary' : 'btn-primary' ?> btn-sm">
        <?= $u['i_follow'] ? 'Unfollow' : 'Follow' ?>
      </button>
    </form>
  </div>
  <?php endforeach; ?>
</div>
</body>
</html>
