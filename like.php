<?php
session_start();
require 'db.php';
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}
$me = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_id'])) {
    $post_id = intval($_POST['post_id']);

    $stmt = $conn->prepare("SELECT like_id FROM likes WHERE user_id = ? AND post_id = ?");
    $stmt->bind_param("ii", $me, $post_id);
    $stmt->execute();
    $stmt->store_result();
    $already_liked = $stmt->num_rows > 0;
    $stmt->close();

    if ($already_liked) {
        $del = $conn->prepare("DELETE FROM likes WHERE user_id = ? AND post_id = ?");
        $del->bind_param("ii", $me, $post_id);
        $del->execute();
        $del->close();
        $liked = false;
    } else {
        $ins = $conn->prepare("INSERT INTO likes (user_id, post_id) VALUES (?, ?)");
        $ins->bind_param("ii", $me, $post_id);
        $ins->execute();
        $ins->close();
        $liked = true;

        // Notify post owner
        $owner = $conn->prepare("SELECT user_id FROM posts WHERE post_id = ?");
        $owner->bind_param("i", $post_id);
        $owner->execute();
        $owner->bind_result($owner_id);
        $owner->fetch();
        $owner->close();

        if ($owner_id && $owner_id != $me) {
            $type  = 'like';
            $notif = $conn->prepare("INSERT INTO notifications (user_id, actor_id, type) VALUES (?, ?, ?)");
            $notif->bind_param("iis", $owner_id, $me, $type);
            $notif->execute();
            $notif->close();
        }
    }

    // Get updated like count
    $cnt = $conn->prepare("SELECT COUNT(*) FROM likes WHERE post_id = ?");
    $cnt->bind_param("i", $post_id);
    $cnt->execute();
    $cnt->bind_result($like_count);
    $cnt->fetch();
    $cnt->close();

    // If AJAX request return JSON, else redirect
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['liked' => $liked, 'like_count' => $like_count]);
        exit;
    }
}

$redirect = $_POST['redirect'] ?? 'index.php';
header("Location: " . $redirect);
exit;
?>