<?php
session_start();
require 'db.php';
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}
$me       = $_SESSION['user_id'];
$username = $_SESSION['username'];
$is_ajax  = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

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
            $type  = 'comment';
            $notif = $conn->prepare("INSERT INTO notifications (user_id, actor_id, type) VALUES (?, ?, ?)");
            $notif->bind_param("iis", $owner_id, $me, $type);
            $notif->execute();
            $notif->close();
        }

        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success'  => true,
                'content'  => htmlspecialchars($content),
                'username' => $username,
                'user_id'  => $me
            ]);
            exit;
        }
    } else {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Empty comment']);
            exit;
        }
    }
}

$redirect = $_POST['redirect'] ?? 'index.php';
header("Location: " . $redirect);
exit;
?>