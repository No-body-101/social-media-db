<?php
session_start();
require 'db.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$me = $_SESSION['user_id'];

// Mark all as read
$conn->query("UPDATE notifications SET is_read = TRUE WHERE user_id = $me");

// Fetch notifications
$stmt = $conn->prepare("
    SELECT n.notif_id, n.type, n.is_read, n.created_at,
           u.username AS actor, u.user_id AS actor_id
    FROM notifications n
    JOIN user u ON u.user_id = n.actor_id
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC
    LIMIT 50
");
$stmt->bind_param("i", $me);
$stmt->execute();
$notifs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function notif_message($type, $actor) {
    return match($type) {
        'like'    => "❤️ <strong>" . htmlspecialchars($actor) . "</strong> liked your post.",
        'comment' => "💬 <strong>" . htmlspecialchars($actor) . "</strong> commented on your post.",
        'follow'  => "👤 <strong>" . htmlspecialchars($actor) . "</strong> started following you.",
        default   => "🔔 <strong>" . htmlspecialchars($actor) . "</strong> did something.",
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Notifications — Nexus</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'includes/nav.php'; ?>
<div class="container">
  <h2 style="margin-bottom:1.5rem;font-family:'Playfair Display',serif;">Notifications</h2>

  <?php if (empty($notifs)): ?>
    <div class="empty">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0"/></svg>
      <h3>No notifications yet</h3>
      <p>When people like, comment or follow you, it'll show up here.</p>
    </div>
  <?php else: ?>
    <div class="card" style="padding:0;overflow:hidden;">
      <?php foreach ($notifs as $n): ?>
      <div class="notif-item <?= !$n['is_read'] ? 'unread' : '' ?>">
        <a href="profile.php?id=<?= $n['actor_id'] ?>" class="avatar" style="width:36px;height:36px;font-size:0.85rem;text-decoration:none;">
          <?= strtoupper(substr($n['actor'],0,1)) ?>
        </a>
        <div style="flex:1;">
          <?= notif_message($n['type'], $n['actor']) ?>
          <div style="font-size:0.75rem;color:var(--muted);margin-top:0.2rem;"><?= date('M j, Y · g:i a', strtotime($n['created_at'])) ?></div>
        </div>
        <?php if (!$n['is_read']): ?><div class="notif-dot"></div><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
