<?php
session_start();
require 'db.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$me = $_SESSION['user_id'];

$profile_id = intval($_GET['id'] ?? $me);

// Fetch user
$stmt = $conn->prepare("SELECT user_id, username, email, bio, status, created_at, profile_pic, banner_pic FROM user WHERE user_id = ? AND is_deleted = FALSE");
$stmt->bind_param("i", $profile_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) { echo "<p style='color:white;padding:2rem;'>User not found.</p>"; exit; }

// Counts
$stmt = $conn->prepare("SELECT COUNT(*) FROM posts WHERE user_id = ?");
$stmt->bind_param("i", $profile_id);
$stmt->execute();
$stmt->bind_result($post_count); $stmt->fetch(); $stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) FROM follows WHERE follower_id = ?");
$stmt->bind_param("i", $profile_id);
$stmt->execute();
$stmt->bind_result($following_count); $stmt->fetch(); $stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) FROM follows WHERE following_id = ?");
$stmt->bind_param("i", $profile_id);
$stmt->execute();
$stmt->bind_result($followers_count); $stmt->fetch(); $stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) FROM follows WHERE follower_id = ? AND following_id = ?");
$stmt->bind_param("ii", $me, $profile_id);
$stmt->execute();
$stmt->bind_result($is_following); $stmt->fetch(); $stmt->close();

// Helper: upload image
function uploadImage($fileKey, $prefix) {
    if (empty($_FILES[$fileKey]['name']) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) return null;
    $ext     = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif','webp'];
    if (!in_array($ext, $allowed)) return null;
    if ($_FILES[$fileKey]['size'] > 10 * 1024 * 1024) return null;
    $filename  = uniqid($prefix) . '.' . $ext;
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $uploadDir . $filename)) {
        return 'uploads/' . $filename;
    }
    return null;
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $profile_id == $me) {
    $fields = [];
    $params = [];
    $types  = '';

    if (isset($_POST['bio'])) {
        $fields[] = "bio = ?";
        $params[] = trim($_POST['bio']);
        $types   .= 's';
    }

    $new_pfp = uploadImage('profile_pic', 'pfp_');
    if ($new_pfp) {
        $fields[] = "profile_pic = ?";
        $params[] = $new_pfp;
        $types   .= 's';
    }

    $new_banner = uploadImage('banner_pic', 'banner_');
    if ($new_banner) {
        $fields[] = "banner_pic = ?";
        $params[] = $new_banner;
        $types   .= 's';
    }

    if (!empty($fields)) {
        $params[] = $me;
        $types   .= 'i';
        $sql  = "UPDATE user SET " . implode(', ', $fields) . " WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: profile.php?id=$me");
    exit;
}

// Posts
$show_private = ($profile_id == $me);
$vis_clause   = $show_private ? "" : "AND p.visibility = 'public'";
$sql = "
    SELECT p.post_id, p.content, p.media_url, p.visibility, p.created_at,
           (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.post_id) AS like_count,
           (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.post_id AND l.user_id = ?) AS user_liked,
           (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.post_id) AS comment_count
    FROM posts p
    WHERE p.user_id = ? $vis_clause
    ORDER BY p.created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $me, $profile_id);
$stmt->execute();
$posts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($user['username']) ?> — Nexus</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    .profile-banner {
      height: 200px;
      background: <?= $user['banner_pic']
        ? "url('" . htmlspecialchars($user['banner_pic']) . "') center/cover no-repeat"
        : "linear-gradient(135deg, var(--accent) 0%, var(--accent2) 100%)" ?>;
      border-radius: var(--radius) var(--radius) 0 0;
      position: relative;
    }
    .pfp-img { width:72px;height:72px;border-radius:50%;object-fit:cover;border:4px solid var(--surface);box-shadow:0 0 0 3px var(--accent),0 0 0 6px var(--surface);position:relative;z-index:10; }
    .edit-section { background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius);padding:1.2rem;margin-bottom:1.5rem;display:none; }
    .edit-section h3 { font-size:0.85rem;color:var(--muted);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:1rem; }
    .upload-row { display:flex;align-items:center;gap:1rem;margin-bottom:0.8rem;flex-wrap:wrap; }
    .upload-preview { width:60px;height:60px;border-radius:8px;object-fit:cover;border:1px solid var(--border);display:none; }
    .banner-preview { width:120px;height:50px;border-radius:8px;object-fit:cover;border:1px solid var(--border);display:none; }
  </style>
</head>
<body>
<?php include 'includes/nav.php'; ?>

<div class="container">

  <!-- Banner -->
  <div class="profile-banner" id="bannerDiv"></div>

  <div class="profile-info">
    <div class="profile-avatar-wrap" style="margin-top:-36px;margin-bottom:0.8rem;position:relative;z-index:10;">
      <?php if ($user['profile_pic']): ?>
        <img src="<?= htmlspecialchars($user['profile_pic']) ?>" class="pfp-img" alt="Profile pic">
      <?php else: ?>
        <div class="avatar avatar-lg" style="box-shadow:0 0 0 3px var(--accent),0 0 0 6px var(--surface);"><?= strtoupper(substr($user['username'],0,1)) ?></div>
      <?php endif; ?>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:0.8rem;">
      <div>
        <div class="profile-name"><?= htmlspecialchars($user['username']) ?></div>
        <div class="profile-bio"><?= htmlspecialchars($user['bio'] ?? 'No bio yet.') ?></div>
        <div class="profile-stats">
          <span><strong><?= $post_count ?></strong> Posts</span>
          <span><strong><?= $followers_count ?></strong> Followers</span>
          <span><strong><?= $following_count ?></strong> Following</span>
        </div>
      </div>
      <div style="display:flex;gap:0.6rem;">
        <?php if ($profile_id == $me): ?>
          <button type="button" class="btn btn-secondary" onclick="toggleEdit()">✏️ Edit Profile</button>
        <?php else: ?>
          <form method="POST" action="follow.php">
            <input type="hidden" name="target_id" value="<?= $profile_id ?>">
            <input type="hidden" name="redirect" value="profile.php?id=<?= $profile_id ?>">
            <button type="submit" class="btn <?= $is_following ? 'btn-secondary' : 'btn-primary' ?>">
              <?= $is_following ? 'Unfollow' : 'Follow' ?>
            </button>
          </form>
          <a href="messages.php?to=<?= $profile_id ?>" class="btn btn-secondary">Message</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if ($profile_id == $me): ?>
  <!-- Edit Profile Section -->
  <div class="edit-section" id="editSection">
    <h3>✏️ Edit Profile</h3>
    <form method="POST" enctype="multipart/form-data">

      <!-- Bio -->
      <div class="form-group">
        <label>Bio</label>
        <input type="text" name="bio" placeholder="Write something about yourself..." value="<?= htmlspecialchars($user['bio'] ?? '') ?>">
      </div>

      <!-- Profile Picture -->
      <div class="upload-row">
        <div>
          <label style="display:block;font-size:0.85rem;color:var(--muted);margin-bottom:0.4rem;">Profile Picture</label>
          <input type="file" name="profile_pic" accept="image/jpeg,image/png,image/gif,image/webp"
            onchange="previewImg(this, 'pfpPrev')">
        </div>
        <img id="pfpPrev" class="upload-preview" alt="Preview">
      </div>

      <!-- Banner -->
      <div class="upload-row">
        <div>
          <label style="display:block;font-size:0.85rem;color:var(--muted);margin-bottom:0.4rem;">Banner Image</label>
          <input type="file" name="banner_pic" accept="image/jpeg,image/png,image/gif,image/webp"
            onchange="previewImg(this, 'bannerPrev'); previewBannerTop(this)">
        </div>
        <img id="bannerPrev" class="banner-preview" alt="Preview">
      </div>

      <button type="submit" class="btn btn-primary">Save Changes</button>
        <button type="button" class="btn btn-secondary" onclick="toggleEdit()">Cancel</button>
    </form>
  </div>
  <?php endif; ?>

  <!-- Posts -->
  <?php if (empty($posts)): ?>
    <div class="empty">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
      <h3>No posts yet</h3>
      <p><?= $profile_id == $me ? "Share your first post on the feed!" : "This user hasn't posted yet." ?></p>
    </div>
  <?php else: ?>
    <?php foreach ($posts as $p): ?>
    <div class="post-card">
      <div class="post-header">
        <?php if ($user['profile_pic']): ?>
          <img src="<?= htmlspecialchars($user['profile_pic']) ?>" class="avatar" style="object-fit:cover;" alt="">
        <?php else: ?>
          <div class="avatar"><?= strtoupper(substr($user['username'],0,1)) ?></div>
        <?php endif; ?>
        <div class="post-meta">
          <span class="username"><?= htmlspecialchars($user['username']) ?></span>
          <div class="time"><?= date('M j, Y · g:i a', strtotime($p['created_at'])) ?>
            <?php if ($p['visibility'] === 'private'): ?>
              <span class="badge badge-private">Private</span>
            <?php endif; ?>
          </div>
        </div>
        <?php if ($profile_id == $me): ?>
          <form method="POST" action="post.php" style="margin-left:auto;">
            <input type="hidden" name="delete_id" value="<?= $p['post_id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete?')">Delete</button>
          </form>
        <?php endif; ?>
      </div>

      <div class="post-content"><?= nl2br(htmlspecialchars($p['content'])) ?></div>

      <?php if ($p['media_url']): ?>
        <?php $ext = strtolower(pathinfo($p['media_url'], PATHINFO_EXTENSION)); ?>
        <?php if (in_array($ext, ['mp4','webm'])): ?>
          <video controls class="post-image" style="background:#000;">
            <source src="<?= htmlspecialchars($p['media_url']) ?>" type="video/<?= $ext ?>">
          </video>
        <?php else: ?>
          <img src="<?= htmlspecialchars($p['media_url']) ?>" class="post-image" alt="Post image">
        <?php endif; ?>
      <?php endif; ?>

      <div class="post-actions">
        <form method="POST" action="like.php" style="margin:0;">
          <input type="hidden" name="post_id" value="<?= $p['post_id'] ?>">
          <input type="hidden" name="redirect" value="profile.php?id=<?= $profile_id ?>">
          <button type="submit" class="action-btn <?= $p['user_liked'] ? 'liked' : '' ?>">
            <svg viewBox="0 0 24 24" fill="<?= $p['user_liked'] ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
            <?= $p['like_count'] ?> Likes
          </button>
        </form>
        <button class="action-btn" onclick="toggleComments(<?= $p['post_id'] ?>)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
          <?= $p['comment_count'] ?> Comments
        </button>
      </div>

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
          <input type="hidden" name="redirect" value="profile.php?id=<?= $profile_id ?>">
          <input type="text" name="content" placeholder="Write a comment..." required>
          <button type="submit" class="btn btn-primary btn-sm">Send</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<script>
function toggleEdit() {
  const el = document.getElementById('editSection');
  el.style.display = el.style.display === 'none' ? 'block' : 'none';
  el.scrollIntoView({behavior: 'smooth', block: 'start'});
}

function toggleComments(postId) {
  const el = document.getElementById('comments-' + postId);
  el.style.display = el.style.display === 'none' ? 'block' : 'none';
}

function previewImg(input, previewId) {
  if (!input.files[0]) return;
  const img = document.getElementById(previewId);
  img.src = URL.createObjectURL(input.files[0]);
  img.style.display = 'block';
}

function previewBannerTop(input) {
  if (!input.files[0]) return;
  const url = URL.createObjectURL(input.files[0]);
  document.getElementById('bannerDiv').style.background = `url('${url}') center/cover no-repeat`;
}
</script>
</body>
</html>