<?php
session_start();
require 'db.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$me = $_SESSION['user_id'];

$to      = intval($_GET['to'] ?? 0);
$chat_user = null;

if ($to) {
    $stmt = $conn->prepare("SELECT user_id, username FROM user WHERE user_id = ? AND is_deleted = FALSE");
    $stmt->bind_param("i", $to);
    $stmt->execute();
    $chat_user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Send message
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content'])) {
        $content = trim($_POST['content']);
        if ($content) {
            $stmt = $conn->prepare("INSERT INTO messages (sender_id, reciever_id, content) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $me, $to, $content);
            $stmt->execute();
            $stmt->close();
        }
        header("Location: messages.php?to=$to");
        exit;
    }

    // Mark as read
    $conn->query("UPDATE messages SET is_read = TRUE WHERE reciever_id = $me AND sender_id = $to");

    // Fetch conversation
    $stmt = $conn->prepare("
        SELECT m.*, u.username
        FROM messages m
        JOIN user u ON u.user_id = m.sender_id
        WHERE (m.sender_id = ? AND m.reciever_id = ?)
           OR (m.sender_id = ? AND m.reciever_id = ?)
        ORDER BY m.sent_at ASC
        LIMIT 100
    ");
    $stmt->bind_param("iiii", $me, $to, $to, $me);
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Inbox: people I've talked to
$inbox_sql = "
    SELECT u.user_id, u.username,
           (SELECT content FROM messages
            WHERE (sender_id = u.user_id AND reciever_id = ?)
               OR (sender_id = ? AND reciever_id = u.user_id)
            ORDER BY sent_at DESC LIMIT 1) AS last_msg,
           (SELECT COUNT(*) FROM messages WHERE sender_id = u.user_id AND reciever_id = ? AND is_read = FALSE) AS unread
    FROM user u
    WHERE u.is_deleted = FALSE AND u.user_id != ?
      AND (
        u.user_id IN (SELECT sender_id FROM messages WHERE reciever_id = ?)
        OR u.user_id IN (SELECT reciever_id FROM messages WHERE sender_id = ?)
      )
    ORDER BY (SELECT sent_at FROM messages
              WHERE (sender_id = u.user_id AND reciever_id = ?)
                 OR (sender_id = ? AND reciever_id = u.user_id)
              ORDER BY sent_at DESC LIMIT 1) DESC
";
$stmt = $conn->prepare($inbox_sql);
$stmt->bind_param("iiiiiiii", $me,$me,$me,$me,$me,$me,$me,$me);
$stmt->execute();
$inbox = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Messages — Nexus</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    .msg-layout { display:grid; grid-template-columns: 280px 1fr; gap:1.2rem; max-width:900px; margin:0 auto; padding:2rem 1rem; height:calc(100vh - 80px); }
    .inbox-panel { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); overflow-y:auto; }
    .inbox-header { padding:1rem 1.2rem; border-bottom:1px solid var(--border); font-weight:600; }
    .chat-panel { display:flex; flex-direction:column; background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
    .chat-header { padding:1rem 1.2rem; border-bottom:1px solid var(--border); font-weight:600; display:flex; align-items:center; gap:0.7rem; }
    .chat-messages { flex:1; overflow-y:auto; padding:1rem; display:flex; flex-direction:column; gap:0.6rem; }
    .msg-bubble { max-width:65%; padding:0.6rem 0.9rem; border-radius:12px; font-size:0.9rem; line-height:1.5; }
    .msg-bubble.mine { background:var(--accent); color:#fff; align-self:flex-end; border-bottom-right-radius:3px; }
    .msg-bubble.theirs { background:var(--surface2); color:var(--text); align-self:flex-start; border-bottom-left-radius:3px; }
    .msg-time { font-size:0.7rem; opacity:0.6; margin-top:0.2rem; }
    .chat-input { display:flex; gap:0.6rem; padding:0.8rem 1rem; border-top:1px solid var(--border); }
    .chat-input input { flex:1; background:var(--surface2); border:1px solid var(--border); border-radius:9px; padding:0.6rem 1rem; color:var(--text); font-family:inherit; font-size:0.9rem; outline:none; }
    .chat-input input:focus { border-color:var(--accent); }
    .no-chat { flex:1; display:flex; align-items:center; justify-content:center; color:var(--muted); flex-direction:column; gap:0.5rem; }
  </style>
</head>
<body>
<?php include 'includes/nav.php'; ?>

<div class="msg-layout">
  <!-- Inbox -->
  <div class="inbox-panel">
    <div class="inbox-header">💬 Messages</div>
    <?php if (empty($inbox)): ?>
      <p style="padding:1rem;color:var(--muted);font-size:0.85rem;">No conversations yet. Visit someone's profile to message them.</p>
    <?php endif; ?>
    <?php foreach ($inbox as $i): ?>
    <a href="messages.php?to=<?= $i['user_id'] ?>" class="message-item <?= ($to == $i['user_id']) ? 'active' : '' ?>"
       style="<?= ($to == $i['user_id']) ? 'background:var(--surface2);' : '' ?>">
      <div class="avatar" style="width:38px;height:38px;font-size:0.9rem;"><?= strtoupper(substr($i['username'],0,1)) ?></div>
      <div style="flex:1;min-width:0;">
        <div style="font-weight:600;font-size:0.9rem;"><?= htmlspecialchars($i['username']) ?>
          <?php if ($i['unread'] > 0): ?><span style="background:var(--accent);color:#fff;border-radius:10px;padding:0.1rem 0.45rem;font-size:0.7rem;margin-left:0.3rem;"><?= $i['unread'] ?></span><?php endif; ?>
        </div>
        <div class="preview <?= $i['unread'] > 0 ? 'unread' : '' ?>"><?= htmlspecialchars(substr($i['last_msg'] ?? '', 0, 40)) ?>...</div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Chat -->
  <div class="chat-panel">
    <?php if ($chat_user): ?>
      <div class="chat-header">
        <div class="avatar" style="width:34px;height:34px;font-size:0.85rem;"><?= strtoupper(substr($chat_user['username'],0,1)) ?></div>
        <a href="profile.php?id=<?= $chat_user['user_id'] ?>" style="color:var(--text);text-decoration:none;"><?= htmlspecialchars($chat_user['username']) ?></a>
      </div>
      <div class="chat-messages" id="chatBox">
        <?php foreach ($messages as $m): ?>
        <div style="display:flex;flex-direction:column;align-items:<?= $m['sender_id']==$me ? 'flex-end' : 'flex-start' ?>;">
          <div class="msg-bubble <?= $m['sender_id']==$me ? 'mine' : 'theirs' ?>">
            <?= nl2br(htmlspecialchars($m['content'])) ?>
          </div>
          <div class="msg-time" style="<?= $m['sender_id']==$me ? 'text-align:right' : '' ?>"><?= date('g:i a', strtotime($m['sent_at'])) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <form method="POST" class="chat-input">
        <input type="text" name="content" placeholder="Type a message..." autocomplete="off" autofocus required>
        <button type="submit" class="btn btn-primary btn-sm">Send</button>
      </form>
    <?php else: ?>
      <div class="no-chat">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity="0.4"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
        <p style="color:var(--muted);font-size:0.9rem;">Select a conversation or go to a profile to start one</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
  // Auto scroll to bottom of chat
  const box = document.getElementById('chatBox');
  if (box) box.scrollTop = box.scrollHeight;
</script>
</body>
</html>
