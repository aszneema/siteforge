<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once SF_ROOT . '/includes/layout.php';

if (current_user()) {
    redirect('library.php');
}

$error = '';
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));

    $result = auth_register($name, $email, (string) ($_POST['password'] ?? ''));
    if ($result['ok']) {
        redirect('create.php');
    }
    $error = $result['error'];
}

layout_header(['title' => 'Create account', 'active' => '']);
?>
<section class="auth">
    <div class="auth-card">
        <h1>Create your account</h1>
        <p class="muted">Free, and takes about twenty seconds.</p>

        <?php if ($error !== ''): ?>
            <p class="alert alert-bad" role="alert"><?= e($error) ?></p>
        <?php endif; ?>

        <form method="post" class="stack">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <label class="field">
                <span>Your name</span>
                <input type="text" name="name" value="<?= e($name) ?>" required maxlength="60" autocomplete="name" autofocus>
            </label>
            <label class="field">
                <span>Email</span>
                <input type="email" name="email" value="<?= e($email) ?>" required autocomplete="email">
            </label>
            <label class="field">
                <span>Password</span>
                <input type="password" name="password" required minlength="8" autocomplete="new-password">
                <small class="hint">At least 8 characters.</small>
            </label>
            <button class="btn btn-primary btn-block" type="submit">Create account</button>
        </form>

        <p class="auth-foot">Already have an account? <a href="login.php">Sign in</a></p>
    </div>
</section>
<?php layout_footer(); ?>
