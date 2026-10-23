<?php
require __DIR__ . '/lib.php';

if (isset($_GET['logout'])) {
    if (hash_equals(csrf(), $_GET['logout'])) {
        session_destroy();
    }
    redirect('index.php');
}
if (user()) {
    redirect('dashboard.php');
}

$mode = ($_POST['mode'] ?? $_GET['mode'] ?? '') === 'register' ? 'register' : 'login';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $name = trim($_POST['name'] ?? '');

    if ($mode === 'register') {
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter your name and a valid email.';
        } elseif (strlen($password) < 8) {
            $error = 'Use at least 8 characters for your password.';
        } elseif (q('SELECT 1 FROM users WHERE email = ?', [$email])->fetch()) {
            $error = 'That email already has an account. Log in instead.';
        } else {
            q('INSERT INTO users (name, email, password) VALUES (?, ?, ?)', [mb_substr($name, 0, 80), $email, password_hash($password, PASSWORD_DEFAULT)]);
            session_regenerate_id(true);
            $_SESSION['uid'] = (int) db()->lastInsertId();
            redirect('dashboard.php');
        }
    } else {
        $u = q('SELECT id, password FROM users WHERE email = ?', [$email])->fetch();
        if ($u && password_verify($password, $u['password'])) {
            session_regenerate_id(true);
            $_SESSION['uid'] = (int) $u['id'];
            redirect('dashboard.php');
        }
        $error = 'Wrong email or password.';
    }
}

head($mode === 'register' ? 'Create your account' : 'Log in', 'page-auth');
?>
<main class="auth">
  <form class="card auth-card" method="post">
    <h1><?= $mode === 'register' ? 'Create your free account' : 'Welcome back' ?></h1>
    <p class="muted"><?= $mode === 'register' ? 'Build your first website in a few minutes.' : 'Log in to keep building.' ?></p>
    <?php if ($error): ?><p class="alert"><?= e($error) ?></p><?php endif ?>
    <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
    <input type="hidden" name="mode" value="<?= $mode ?>">
    <?php if ($mode === 'register'): ?>
      <label>Your name<input name="name" required autocomplete="name" value="<?= e($_POST['name'] ?? '') ?>"></label>
    <?php endif ?>
    <label>Email<input type="email" name="email" required autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>"></label>
    <label>Password<input type="password" name="password" required minlength="<?= $mode === 'register' ? 8 : 1 ?>" autocomplete="<?= $mode === 'register' ? 'new-password' : 'current-password' ?>"></label>
    <button class="btn btn-block"><?= $mode === 'register' ? 'Create account' : 'Log in' ?></button>
    <p class="muted center">
      <?php if ($mode === 'register'): ?>Already have an account? <a href="auth.php">Log in</a>
      <?php else: ?>New here? <a href="auth.php?mode=register">Create an account</a><?php endif ?>
    </p>
  </form>
</main>
<?php foot();
