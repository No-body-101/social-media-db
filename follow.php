<?php
session_start();
require 'db.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$me = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['target_id'])) {
    $target = intval($_POST['target_id']);

    if ($target !== $me) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM follows WHERE follower_id = ? AND following_id = ?");
        $stmt->bind_param("ii", $me, $target);
        $stmt->execute();
        $stmt->bind_result($already);
        $stmt->fetch();
        $stmt->close();

        if ($already) {
            // Unfollow
            $del = $conn->prepare("DELETE FROM follows WHERE follower_id = ? AND following_id = ?");
            $del->bind_param("ii", $me, $target);
            $del->execute();
            $del->close();
        } else {
            // Follow
            $ins = $conn->prepare("INSERT INTO follows (follower_id, following_id) VALUES (?, ?)");
            $ins->bind_param("ii", $me, $target);
            $ins->execute();
            $ins->close();

            // Notify
            $type = 'follow';
            $notif = $conn->prepare("INSERT INTO notifications (user_id, actor_id, type) VALUES (?, ?, ?)");
            $notif->bind_param("iis", $target, $me, $type);
            $notif->execute();
            $notif->close();
        }
    }
}

$redirect = $_POST['redirect'] ?? 'index.php';
header("Location: " . $redirect);
exit;
?>
