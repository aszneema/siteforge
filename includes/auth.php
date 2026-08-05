<?php
declare(strict_types=1);

/** Reload the signed-in user for this request. */
function auth_touch(): void
{
    $GLOBALS['wm_user'] = null;
    $id = $_SESSION['uid'] ?? null;
    if (!$id) {
        return;
    }
    $stmt = db()->prepare('SELECT id, name, email, created_at FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if ($user) {
        $GLOBALS['wm_user'] = $user;
    } else {
        unset($_SESSION['uid']);
    }
}

function current_user(): ?array
{
    return $GLOBALS['wm_user'] ?? null;
}

function user_id(): ?int
{
    $u = current_user();
    return $u ? (int) $u['id'] : null;
}

/** Redirect guests to the login page, remembering where they were headed. */
function require_login(): array
{
    $user = current_user();
    if (!$user) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? 'library.php';
        redirect('login.php');
    }
    return $user;
}

/** Same as require_login() but answers JSON for API endpoints. */
function require_login_api(): array
{
    $user = current_user();
    if (!$user) {
        json_error('You need to sign in first.', 401);
    }
    return $user;
}

function auth_register(string $name, string $email, string $password): array
{
    $name = trim($name);
    $email = strtolower(trim($email));

    if ($name === '' || mb_strlen($name) > 60) {
        return ['ok' => false, 'error' => 'Please enter your name (up to 60 characters).'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'That email address does not look valid.'];
    }
    if (strlen($password) < 8) {
        return ['ok' => false, 'error' => 'Your password must be at least 8 characters.'];
    }

    $exists = db()->prepare('SELECT 1 FROM users WHERE email = ?');
    $exists->execute([$email]);
    if ($exists->fetchColumn()) {
        return ['ok' => false, 'error' => 'An account with that email already exists.'];
    }

    $stmt = db()->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
    $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);

    auth_start_session((int) db()->lastInsertId());
    return ['ok' => true];
}

function auth_login(string $email, string $password): array
{
    $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE email = ?');
    $stmt->execute([strtolower(trim($email))]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($password, $row['password_hash'])) {
        return ['ok' => false, 'error' => 'Email or password is incorrect.'];
    }

    auth_start_session((int) $row['id']);
    return ['ok' => true];
}

function auth_start_session(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = $userId;
    auth_touch();
}

function auth_logout(): void
{
    $_SESSION = [];
    session_destroy();
}
