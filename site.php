<?php
require __DIR__ . '/lib.php';

// Serves published sites at site.php/<slug>/<page>.html and stores their form submissions.

[$slug, $file] = array_pad(explode('/', trim($_SERVER['PATH_INFO'] ?? '', '/'), 2), 2, '');
$site = $slug !== '' ? q('SELECT * FROM sites WHERE slug = ?', [$slug])->fetch() : null;
$isOwner = $site && isset($_COOKIE['siteforge']) && (int) (user()['id'] ?? 0) === (int) $site['user_id'];

if (!$site || (!$site['published'] && !$isOwner)) {
    http_response_code(404);
    exit('<!doctype html><meta charset="utf-8"><title>Not found</title><p style="font:16px system-ui;padding:40px;text-align:center">This website doesn\'t exist or isn\'t published yet.</p>');
}
if (!str_ends_with($_SERVER['PATH_INFO'], '/') && $file === '') {
    redirect($_SERVER['SCRIPT_NAME'] . '/' . $slug . '/');
}

$export = json_decode($site['export'] ?? '', true) ?: ['css' => '', 'pages' => []];
$id = preg_replace('/\.html$/', '', $file) ?: 'index';
$page = current(array_filter($export['pages'], fn ($p) => $p['id'] === $id));
if (!$page) {
    http_response_code(404);
    $page = ['id' => '404', 'name' => 'Page not found', 'html' => '<p style="padding:40px;text-align:center">This page doesn\'t exist. <a href="index.html">Go to the home page</a></p>'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $site['published']) {
    $data = [];
    foreach (array_slice($_POST, 0, 40, true) as $k => $v) {
        $data[mb_substr((string) $k, 0, 100)] = mb_substr(is_array($v) ? implode(', ', $v) : (string) $v, 0, 5000);
    }
    if ($data) {
        q('INSERT INTO submissions (site_id, page, data) VALUES (?, ?, ?)', [$site['id'], $page['id'], json_encode($data, JSON_UNESCAPED_UNICODE)]);
    }
    redirect(strtok($_SERVER['REQUEST_URI'], '?') . '?sent=1');
}

// Visitors' scripts must never act as a signed-in SiteForge user, so the page gets its own origin.
header('Content-Security-Policy: sandbox allow-scripts allow-forms allow-popups allow-popups-to-escape-sandbox allow-modals');

$page['html'] = route_forms($page['html']);
if (isset($_GET['sent'])) {
    $page['html'] .= '<div style="position:fixed;left:50%;bottom:24px;transform:translateX(-50%);background:#0f172a;color:#fff;padding:14px 22px;border-radius:12px;font:15px system-ui;box-shadow:0 10px 30px rgba(0,0,0,.25);z-index:99999">Thank you! Your message was sent.</div>';
}
if (!$site['published']) {
    $page['html'] .= '<div style="position:fixed;bottom:12px;left:12px;background:#f59e0b;color:#111;padding:6px 12px;border-radius:999px;font:600 12px system-ui;z-index:99999">Draft preview: only you can see this</div>';
}
echo page_document($site, $export, $page, '<style>' . ($export['css'] ?? '') . '</style>');
