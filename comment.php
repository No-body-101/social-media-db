<?php
session_start();
require 'db.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$me = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_id'], $_POST['content'])) {
    $post_id = intval($_POST['post_id']);
    $content = trim($_POST['content']);

    if ($content) {
        $stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $post_id, $me, $content);
        $stmt->execute();
        $stmt->close();

        // Notify post owner
        $owner = $conn->prepare("SELECT user_id FROM posts WHERE post_id = ?");
        $owner->bind_param("i", $post_id);
        $owner->execute();
        $owner->bind_result($owner_id);
        $owner->fetch();
        $owner->close();

        if ($owner_id && $owner_id != $me) {
            $type = 'comment';
            $notif = $conn->prepare("INSERT INTO notifications (user_id, actor_id, type) VALUES (?, ?, ?)");
            $notif->bind_param("iis", $owner_id, $me, $type);
            $notif->execute();
            $notif->close();
        }
    }
}

$redirect = $_POST['redirect'] ?? 'index.php';
header("Location: " . $redirect);
exit;
?>
