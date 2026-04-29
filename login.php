<?php
session_start();
require 'db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = "Please fill in all fields.";
    } else {
        $stmt = $conn->prepare("SELECT user_id, username, password, is_deleted, status FROM user WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->bind_result($user_id, $username, $hashed, $is_deleted, $status);
        $stmt->fetch();
        $stmt->close();

        if (!$user_id) {
            $error = "No account found with that email.";
        } elseif ($is_deleted) {
            $error = "This account has been deactivated.";
        } elseif (!password_verify($password, $hashed)) {
            $error = "Incorrect password.";
        } else {
            $_SESSION['user_id']  = $user_id;
            $_SESSION['username'] = $username;
            header("Location: index.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — Nexus</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="auth-wrapper">
  <div class="auth-card card">
    <h1>Welcome back</h1>
    <p class="subtitle">Sign in to your Nexus account</p>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" placeholder="you@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" placeholder="Your password" required>
      </div>
      <button type="submit" class="btn btn-primary btn-full">Sign In</button>
    </form>

    <p class="auth-footer">Don't have an account? <a href="register.php">Create one</a></p>
  </div>
</div>
</body>
</html>
