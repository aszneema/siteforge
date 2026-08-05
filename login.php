<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once SF_ROOT . '/includes/layout.php';

if (current_user()) {
    redirect('library.php');
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $email = trim((string) ($_POST['email'] ?? ''));
    $result = auth_login($email, (string) ($_POST['password'] ?? ''));

    if ($result['ok']) {
        $next = $_SESSION['after_login'] ?? 'library.php';
        unset($_SESSION['after_login']);
        redirect(is_string($next) && str_starts_with($next, '/') === false ? $next : 'library.php');
    }
    $error = $result['error'];
}

layout_header(['title' => 'Sign in', 'active' => '']);
?>
<section class="auth">
    <div class="auth-card">
        <h1>Welcome back</h1>
        <p class="muted">Sign in to pick up where you left off.</p>

        <?php if ($error !== ''): ?>
            <p class="alert alert-bad" role="alert"><?= e($error) ?></p>
        <?php endif; ?>

        <form method="post" class="stack">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <label class="field">
                <span>Email</span>
                <input type="email" name="email" value="<?= e($email) ?>" required autocomplete="email" autofocus>
            </label>
            <label class="field">
                <span>Password</span>
                <input type="password" name="password" required autocomplete="current-password">
            </label>
            <button class="btn btn-primary btn-block" type="submit">Sign in</button>
        </form>

        <p class="auth-foot">New here? <a href="register.php">Create an account</a></p>
    </div>
</section>
<?php layout_footer(); ?>
