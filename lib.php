<?php
declare(strict_types=1);

// Shared by every page: settings, database, login, and the HTML shell of the app pages.

/** Google Fonts the builder offers (name => css2 family query). The AI is told to use only these. */
const FONTS = [
    'Inter' => 'Inter:wght@400;500;600;700;800',
    'Poppins' => 'Poppins:wght@400;500;600;700;800',
    'Montserrat' => 'Montserrat:wght@400;500;600;700;800',
    'DM Sans' => 'DM+Sans:wght@400;500;700',
    'Space Grotesk' => 'Space+Grotesk:wght@400;500;700',
    'Nunito' => 'Nunito:wght@400;600;700;800',
    'Raleway' => 'Raleway:wght@400;500;700;800',
    'Lato' => 'Lato:wght@400;700;900',
    'Playfair Display' => 'Playfair+Display:wght@400;600;700;800',
    'Lora' => 'Lora:wght@400;600;700',
    'Merriweather' => 'Merriweather:wght@400;700;900',
    'Cormorant Garamond' => 'Cormorant+Garamond:wght@400;500;600;700',
    'Oswald' => 'Oswald:wght@400;500;600;700',
    'Bebas Neue' => 'Bebas+Neue',
    'Pacifico' => 'Pacifico',
    'Caveat' => 'Caveat:wght@400;700',
    'Cairo' => 'Cairo:wght@400;600;700;800',
    'Tajawal' => 'Tajawal:wght@400;500;700;800',
];

function cfg(string $key): mixed
{
    static $cfg = null;
    $cfg ??= (is_file(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : []) + [
        'anthropic_api_key' => getenv('ANTHROPIC_API_KEY') ?: '',
        'model' => 'claude-opus-5-5',
        'effort' => 'medium',
    ];
    return $cfg[$key] ?? null;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }
    $pdo = new PDO('sqlite:' . __DIR__ . '/data/siteforge.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec(<<<'SQL'
        PRAGMA foreign_keys = ON;
        PRAGMA journal_mode = WAL;
        PRAGMA busy_timeout = 5000;
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE COLLATE NOCASE,
            password TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS sites (
            id INTEGER PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            name TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            project TEXT,            -- editor state (GrapesJS project JSON)
            export TEXT,             -- rendered site: {"css": "...", "pages": [{"id","name","html"}]}
            published INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY,
            site_id INTEGER NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
            role TEXT NOT NULL,
            body TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS assets (
            id INTEGER PRIMARY KEY,
            site_id INTEGER NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
            file TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS submissions (
            id INTEGER PRIMARY KEY,
            site_id INTEGER NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
            page TEXT NOT NULL,
            data TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        SQL);
    return $pdo;
}

function q(string $sql, array $args = []): PDOStatement
{
    $s = db()->prepare($sql);
    $s->execute($args);
    return $s;
}

// --- Session & login ---------------------------------------------------------

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('siteforge');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
    session_start();
}

function user(): ?array
{
    start_session();
    if (empty($_SESSION['uid'])) {
        return null;
    }
    return q('SELECT id, name, email FROM users WHERE id = ?', [$_SESSION['uid']])->fetch() ?: null;
}

function require_user(): array
{
    return user() ?? redirect('auth.php');
}

function csrf(): string
{
    start_session();
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
}

function check_csrf(): void
{
    if (!hash_equals(csrf(), $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '')) {
        fail(403, 'Your session expired. Reload the page and try again.');
    }
}

/** The signed-in user's site, or a 404. */
function own_site(int $id): array
{
    return q('SELECT * FROM sites WHERE id = ? AND user_id = ?', [$id, user()['id'] ?? 0])->fetch()
        ?: fail(404, 'Site not found.');
}

// --- Small helpers -----------------------------------------------------------

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function fail(int $code, string $message): never
{
    http_response_code($code);
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'json') || str_ends_with($_SERVER['SCRIPT_NAME'], 'api.php')) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $message]);
    } else {
        echo e($message);
    }
    exit;
}

/** URL path of the app folder, e.g. "" at the domain root or "/siteforge" in a subfolder. */
function base(): string
{
    return rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
}

function slugify(string $s): string
{
    $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-'));
    return $s !== '' ? substr($s, 0, 40) : 'site';
}

function unique_slug(string $name): string
{
    $slug = slugify($name);
    for ($try = $slug, $n = 2; q('SELECT 1 FROM sites WHERE slug = ?', [$try])->fetch(); $n++) {
        $try = "$slug-$n";
    }
    return $try;
}

/** Google Fonts stylesheet for the fonts a stylesheet mentions (all fonts when $css is null). */
function fonts_url(?string $css = null): string
{
    $families = [];
    foreach (FONTS as $name => $query) {
        if ($css === null || stripos($css, $name) !== false) {
            $families[] = 'family=' . $query;
        }
    }
    return $families ? 'https://fonts.googleapis.com/css2?' . implode('&', $families) . '&display=swap' : '';
}

/** Point every form that has no action of its own at the site's inbox (the given URL, or the page itself). */
function route_forms(string $html, string $action = ''): string
{
    return preg_replace_callback('/<form\b(?![^>]*\baction=)([^>]*)>/i', fn ($m) => '<form method="post"'
        . ($action !== '' ? ' action="' . e($action) . '"' : '')
        . preg_replace('/\smethod=("[^"]*"|\'[^\']*\'|\S+)/i', '', $m[1]) . '>', $html);
}

/** A full HTML document for one page of a built site. */
function page_document(array $site, array $export, array $page, string $styleTag): string
{
    $fonts = fonts_url($export['css'] ?? '');
    return "<!doctype html>\n<html lang=\"en\">\n<head>\n<meta charset=\"utf-8\">\n"
        . "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n"
        . '<title>' . e(($page['id'] === 'index' ? '' : $page['name'] . ' · ') . $site['name']) . "</title>\n"
        . ($fonts ? "<link rel=\"stylesheet\" href=\"" . e($fonts) . "\">\n" : '')
        . $styleTag . "\n</head>\n<body>\n" . $page['html'] . "\n</body>\n</html>\n";
}

// --- App page shell ----------------------------------------------------------

function brand_mark(): string
{
    return '<svg class="mark" viewBox="0 0 120 132" aria-hidden="true">'
        . '<path class="mk-teal-s" d="M12 72 L38 124 L60 80" fill="none" stroke-width="18"/>'
        . '<path class="mk-navy-s" d="M60 80 L82 124 L108 72" fill="none" stroke-width="18"/>'
        . '<path class="mk-teal" d="M60 6 L44 22 L44 62 L60 76 Z"/>'
        . '<path class="mk-navy" d="M60 6 L76 22 L76 62 L60 76 Z"/>'
        . '<path fill="#fff" d="M52 26 L52 49.8 L57.9 44.2 L61.8 52.6 L66 50.8 L62.1 42.5 L68.8 42.1 Z"/></svg>';
}

function head(string $title, string $bodyClass = ''): void
{
    $u = user(); ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · SiteForge</title>
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="assets/app.css">
</head>
<body class="<?= e($bodyClass) ?>">
<header class="topnav">
  <div class="wrap topnav-in">
    <a class="brand" href="<?= $u ? 'dashboard.php' : 'index.php' ?>"><?= brand_mark() ?><span>SITE<b>FORGE</b></span></a>
    <nav>
      <?php if ($u): ?>
        <a href="dashboard.php">My websites</a>
        <span class="who"><?= e($u['name']) ?></span>
        <a href="auth.php?logout=<?= e(csrf()) ?>">Log out</a>
      <?php else: ?>
        <a href="auth.php">Log in</a>
        <a class="btn btn-sm" href="auth.php?mode=register">Start free</a>
      <?php endif ?>
    </nav>
  </div>
</header>
<?php
}

function foot(): void
{ ?>
<footer class="footer"><div class="wrap">© <?= date('Y') ?> SiteForge · Build. Design. Create.</div></footer>
</body>
</html>
<?php
}
