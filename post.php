<?php
session_start();
require 'db.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$me = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $post_id = intval($_POST['delete_id']);
    // Only allow deleting own posts
    $stmt = $conn->prepare("DELETE FROM posts WHERE post_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $post_id, $me);
    $stmt->execute();
    $stmt->close();
}

$redirect = $_POST['redirect'] ?? 'index.php';
header("Location: " . $redirect);
exit;
?>
