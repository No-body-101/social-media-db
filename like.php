<?php
session_start();
require 'db.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$me = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_id'])) {
    $post_id = intval($_POST['post_id']);

    // Toggle like
    $stmt = $conn->prepare("SELECT like_id FROM likes WHERE user_id = ? AND post_id = ?");
    $stmt->bind_param("ii", $me, $post_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->close();
        $del = $conn->prepare("DELETE FROM likes WHERE user_id = ? AND post_id = ?");
        $del->bind_param("ii", $me, $post_id);
        $del->execute();
        $del->close();
    } else {
        $stmt->close();
        $ins = $conn->prepare("INSERT INTO likes (user_id, post_id) VALUES (?, ?)");
        $ins->bind_param("ii", $me, $post_id);
        $ins->execute();
        $ins->close();

        // Notify post owner (if not own post)
        $owner = $conn->prepare("SELECT user_id FROM posts WHERE post_id = ?");
        $owner->bind_param("i", $post_id);
        $owner->execute();
        $owner->bind_result($owner_id);
        $owner->fetch();
        $owner->close();

        if ($owner_id && $owner_id != $me) {
            $type = 'like';
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
