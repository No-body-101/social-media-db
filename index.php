<?php
session_start();
require 'db.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$me = $_SESSION['user_id'];

// Handle new post submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content'])) {
    $content    = trim($_POST['content']);
    $visibility = $_POST['visibility'] ?? 'public';
    $media_url  = '';

    if (!empty($_FILES['media']['name'])) {
        $ext      = pathinfo($_FILES['media']['name'], PATHINFO_EXTENSION);
        $allowed  = ['jpg','jpeg','png','gif','webp'];
        if (in_array(strtolower($ext), $allowed)) {
            $filename  = uniqid('img_') . '.' . $ext;
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            move_uploaded_file($_FILES['media']['tmp_name'], $uploadDir . $filename);
            $media_url = $uploadDir . $filename;
        }
    }

    if ($content) {
        $stmt = $conn->prepare("INSERT INTO posts (user_id, content, visibility, media_url) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $me, $content, $visibility, $media_url);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: index.php");
    exit;
}

// Fetch posts from followed users + own posts
$sql = "
    SELECT p.post_id, p.content, p.media_url, p.visibility, p.created_at,
           u.user_id, u.username,
           (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.post_id) AS like_count,
           (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.post_id AND l.user_id = ?) AS user_liked,
           (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.post_id) AS comment_count
    FROM posts p
    JOIN user u ON p.user_id = u.user_id
    WHERE u.is_deleted = FALSE
      AND (p.user_id = ?
           OR p.user_id IN (SELECT following_id FROM follows WHERE follower_id = ?))
      AND (p.visibility = 'public' OR p.user_id = ?)
    ORDER BY p.created_at DESC
    LIMIT 50
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iiii", $me, $me, $me, $me);
$stmt->execute();
$posts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Suggested users (not followed, not self)
$sugg_sql = "
    SELECT u.user_id, u.username
    FROM user u
    WHERE u.user_id != ?
      AND u.is_deleted = FALSE
      AND u.user_id NOT IN (SELECT following_id FROM follows WHERE follower_id = ?)
    ORDER BY RAND()
    LIMIT 5
";
$stmt = $conn->prepare($sugg_sql);
$stmt->bind_param("ii", $me, $me);
$stmt->execute();
$suggestions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Feed — Nexus</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'includes/nav.php'; ?>

<div class="feed-layout">
  <!-- MAIN FEED -->
  <main>
    <!-- Create Post -->
    <div class="create-post">
      <form method="POST" enctype="multipart/form-data">
        <textarea name="content" placeholder="What's on your mind, <?= htmlspecialchars($_SESSION['username']) ?>?" required></textarea>
        <div class="create-post-actions">
          <label>
            📷 <input type="file" name="media" accept="image/*" style="display:none;" onchange="this.parentElement.textContent='📷 ' + this.files[0].name">
          </label>
          <select name="visibility" style="background:var(--surface2);border:1px solid var(--border);color:var(--text);border-radius:8px;padding:0.4rem 0.7rem;font-family:inherit;font-size:0.85rem;outline:none;">
            <option value="public">🌐 Public</option>
            <option value="private">🔒 Private</option>
          </select>
          <button type="submit" class="btn btn-primary btn-sm">Post</button>
        </div>
      </form>
    </div>

    <!-- Posts -->
    <?php if (empty($posts)): ?>
      <div class="empty">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10l6 6v10a2 2 0 01-2 2z"/></svg>
        <h3>Nothing here yet</h3>
        <p>Follow some people or make your first post!</p>
      </div>
    <?php else: ?>
      <?php foreach ($posts as $p): ?>
      <div class="post-card">
        <div class="post-header">
          <a href="profile.php?id=<?= $p['user_id'] ?>" class="avatar">
            <?= strtoupper(substr($p['username'], 0, 1)) ?>
          </a>
          <div class="post-meta">
            <a href="profile.php?id=<?= $p['user_id'] ?>" class="username"><?= htmlspecialchars($p['username']) ?></a>
            <div class="time"><?= date('M j, Y · g:i a', strtotime($p['created_at'])) ?>
              <?php if ($p['visibility'] === 'private'): ?>
                <span class="badge badge-private">Private</span>
              <?php endif; ?>
            </div>
          </div>
          <?php if ($p['user_id'] == $me): ?>
            <form method="POST" action="post.php" style="margin-left:auto;">
              <input type="hidden" name="delete_id" value="<?= $p['post_id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this post?')">Delete</button>
            </form>
          <?php endif; ?>
        </div>

        <div class="post-content"><?= nl2br(htmlspecialchars($p['content'])) ?></div>

        <?php if ($p['media_url']): ?>
          <img src="<?= htmlspecialchars($p['media_url']) ?>" class="post-image" alt="Post image">
        <?php endif; ?>

        <div class="post-actions">
          <!-- Like -->
          <form method="POST" action="like.php" style="margin:0;">
            <input type="hidden" name="post_id" value="<?= $p['post_id'] ?>">
            <input type="hidden" name="redirect" value="index.php">
            <button type="submit" class="action-btn <?= $p['user_liked'] ? 'liked' : '' ?>">
              <svg viewBox="0 0 24 24" fill="<?= $p['user_liked'] ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
              <?= $p['like_count'] ?> Likes
            </button>
          </form>
          <!-- Comment toggle -->
          <button class="action-btn" onclick="toggleComments(<?= $p['post_id'] ?>)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
            <?= $p['comment_count'] ?> Comments
          </button>
        </div>

        <!-- Comments Section -->
        <div class="comments-section" id="comments-<?= $p['post_id'] ?>" style="display:none;">
          <?php
            $cstmt = $conn->prepare("SELECT c.content, c.created_at, u.username, u.user_id FROM comments c JOIN user u ON c.user_id = u.user_id WHERE c.post_id = ? ORDER BY c.created_at ASC LIMIT 20");
            $cstmt->bind_param("i", $p['post_id']);
            $cstmt->execute();
            $comments = $cstmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $cstmt->close();
            foreach ($comments as $c):
          ?>
          <div class="comment">
            <a href="profile.php?id=<?= $c['user_id'] ?>" class="avatar"><?= strtoupper(substr($c['username'],0,1)) ?></a>
            <div class="comment-body">
              <span class="username"><?= htmlspecialchars($c['username']) ?></span>
              <p><?= nl2br(htmlspecialchars($c['content'])) ?></p>
            </div>
          </div>
          <?php endforeach; ?>

          <form method="POST" action="comment.php" class="comment-form">
            <input type="hidden" name="post_id" value="<?= $p['post_id'] ?>">
            <input type="hidden" name="redirect" value="index.php">
            <input type="text" name="content" placeholder="Write a comment..." required>
            <button type="submit" class="btn btn-primary btn-sm">Send</button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </main>

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <!-- My profile mini -->
    <div class="sidebar-card">
      <div style="display:flex;align-items:center;gap:0.8rem;">
        <a href="profile.php?id=<?= $me ?>" class="avatar"><?= strtoupper(substr($_SESSION['username'],0,1)) ?></a>
        <div>
          <div style="font-weight:600;"><?= htmlspecialchars($_SESSION['username']) ?></div>
          <a href="profile.php?id=<?= $me ?>" style="font-size:0.8rem;color:var(--accent);text-decoration:none;">View Profile</a>
        </div>
      </div>
    </div>

    <!-- Suggested users -->
    <?php if (!empty($suggestions)): ?>
    <div class="sidebar-card">
      <h3>People to follow</h3>
      <?php foreach ($suggestions as $s): ?>
      <div class="suggest-user">
        <a href="profile.php?id=<?= $s['user_id'] ?>" class="avatar"><?= strtoupper(substr($s['username'],0,1)) ?></a>
        <div class="info">
          <a href="profile.php?id=<?= $s['user_id'] ?>" class="name"><?= htmlspecialchars($s['username']) ?></a>
        </div>
        <form method="POST" action="follow.php">
          <input type="hidden" name="target_id" value="<?= $s['user_id'] ?>">
          <input type="hidden" name="redirect" value="index.php">
          <button type="submit" class="btn btn-primary btn-sm">Follow</button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </aside>
</div>

<script>
function toggleComments(postId) {
  const el = document.getElementById('comments-' + postId);
  el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
</script>
</body>
</html>
