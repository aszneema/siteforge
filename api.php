<?php
require __DIR__ . '/lib.php';

use Anthropic\Beta\Messages\BetaRawContentBlockDeltaEvent;
use Anthropic\Beta\Messages\BetaRawContentBlockStartEvent;
use Anthropic\Beta\Messages\BetaRawMessageDeltaEvent;
use Anthropic\Beta\Messages\BetaRawMessageStartEvent;
use Anthropic\Beta\Messages\BetaTextDelta;
use Anthropic\Core\Exceptions\AnthropicException;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;

// JSON endpoints used by the dashboard and the editor. Every call needs a login;
// everything except the ZIP download also needs the CSRF header.

$action = $_GET['action'] ?? '';
if (!user()) {
    fail(401, 'Please log in again.');
}
if ($action !== 'export') {
    check_csrf();
}
$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
header('Content-Type: application/json');

function out(array $data): never
{
    echo json_encode($data);
    exit;
}

const IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];

switch ($action) {
    case 'create':
        $name = mb_substr(trim($_POST['name'] ?? '') ?: 'My website', 0, 60);
        q('INSERT INTO sites (user_id, name, slug) VALUES (?, ?, ?)', [user()['id'], $name, unique_slug($name)]);
        out(['id' => (int) db()->lastInsertId()]);

    case 'save':
        own_site($id);
        $project = $_POST['project'] ?? '';
        $export = json_decode($_POST['export'] ?? '', true);
        if (!is_array(json_decode($project, true)) || !is_array($export['pages'] ?? null)) {
            fail(400, 'Could not save: the editor sent incomplete data.');
        }
        q('UPDATE sites SET project = ?, export = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?', [$project, json_encode($export), $id]);
        out(['ok' => true]);

    case 'rename':
        own_site($id);
        $name = mb_substr(trim($_POST['name'] ?? ''), 0, 60) ?: fail(400, 'Give your site a name.');
        q('UPDATE sites SET name = ? WHERE id = ?', [$name, $id]);
        out(['name' => $name]);

    case 'publish':
        own_site($id);
        q('UPDATE sites SET published = ? WHERE id = ?', [empty($_POST['published']) ? 0 : 1, $id]);
        out(['published' => !empty($_POST['published'])]);

    case 'delete':
        own_site($id);
        delete_site_files($id);
        q('DELETE FROM sites WHERE id = ?', [$id]);
        out(['ok' => true]);

    case 'upload':
        own_site($id);
        $f = $_FILES['files'] ?? fail(400, 'No file received.');
        $urls = [];
        foreach ((array) $f['tmp_name'] as $i => $tmp) {
            $err = ((array) $f['error'])[$i];
            if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE || ((array) $f['size'])[$i] > 10_000_000) {
                fail(400, 'That image is too big. The limit is 10 MB.');
            }
            $ext = $err === UPLOAD_ERR_OK ? (IMAGE_TYPES[(new finfo(FILEINFO_MIME_TYPE))->file($tmp)] ?? null) : null;
            if (!$ext) {
                fail(400, 'Only JPG, PNG, GIF and WebP images can be uploaded.');
            }
            $name = bin2hex(random_bytes(10)) . '.' . $ext;
            move_uploaded_file($tmp, __DIR__ . '/uploads/' . $name) || fail(500, 'Could not store the image.');
            q('INSERT INTO assets (site_id, file) VALUES (?, ?)', [$id, $name]);
            $urls[] = base() . '/uploads/' . $name;
        }
        out(['data' => $urls]);

    case 'export':
        export_zip(own_site($id));

    case 'chat':
        chat(own_site($id));

    default:
        fail(404, 'Unknown action.');
}

/** Download the site as a ZIP of plain HTML files that work anywhere. */
function export_zip(array $site): never
{
    $export = json_decode($site['export'] ?? '', true);
    if (empty($export['pages'])) {
        fail(400, 'Nothing to download yet. Build your site first.');
    }
    // Uploaded images move into images/; forms keep posting to the live SiteForge inbox.
    $uploads = '~(?<=["\'(=\s])' . preg_quote(base() . '/uploads/', '~') . '~';
    $live = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . '://' . $_SERVER['HTTP_HOST'] . base() . '/site.php/' . $site['slug'] . '/';
    $css = preg_replace($uploads, 'images/', $export['css'] ?? '');
    $used = $css;

    $tmp = tempnam(sys_get_temp_dir(), 'sf');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);
    $zip->addFromString('style.css', $css);
    foreach ($export['pages'] as $page) {
        $file = slugify($page['id']) . '.html';
        $page['html'] = route_forms(preg_replace($uploads, 'images/', $page['html']), $live . $file);
        $used .= $page['html'];
        $zip->addFromString($file, page_document($site, $export, $page, '<link rel="stylesheet" href="style.css">'));
    }
    preg_match_all('~images/([a-f0-9]+\.(?:jpg|png|gif|webp))~', $used, $m);
    foreach (array_unique($m[1]) as $f) {
        if (is_file(__DIR__ . "/uploads/$f")) {
            $zip->addFile(__DIR__ . "/uploads/$f", "images/$f");
        }
    }
    $zip->close();

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $site['slug'] . '.zip"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    unlink($tmp);
    exit;
}

/** Stream one chat turn: Claude reads the current site + request and answers with changed pages. */
function chat(array $site): never
{
    $id = (int) $site['id'];
    $message = mb_substr(trim($_POST['message'] ?? ''), 0, 8000);
    if ($message === '') {
        fail(400, 'Type what you would like first.');
    }
    $current = json_decode($_POST['site'] ?? '', true) ?: [];
    $images = array_slice((array) json_decode($_POST['images'] ?? '[]', true), 0, 6);
    $focus = json_decode($_POST['focus'] ?? '', true) ?: [];

    $me = user();
    // A build can take minutes: release the session lock so autosave keeps working,
    // and stream events so neither the browser nor the host times out.
    session_write_close();
    set_time_limit(0);
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    @ini_set('zlib.output_compression', '0');
    while (ob_get_level()) {
        ob_end_flush();
    }
    $send = function (array $event): void {
        echo 'data: ' . json_encode($event) . "\n\n";
        flush();
    };
    echo ':' . str_repeat(' ', 2048) . "\n\n"; // pushes past proxy buffers
    $send(['t' => 'status', 'text' => 'Reading your request…']);

    if (!cfg('anthropic_api_key')) {
        $send(['t' => 'error', 'message' => "The AI assistant isn't switched on for this SiteForge yet. The site owner needs to add an Anthropic API key in config.php. You can still build everything by hand."]);
        exit;
    }
    if (setting('ai_enabled') !== '1') {
        $send(['t' => 'error', 'message' => 'The AI assistant has been paused by the admin. You can still build everything by hand.']);
        exit;
    }
    $limit = (int) setting('ai_daily_limit');
    if ($limit > 0 && !$me['is_admin']) {
        $used = (int) q("SELECT COUNT(*) FROM messages m JOIN sites s ON s.id = m.site_id
                         WHERE s.user_id = ? AND m.role = 'assistant' AND m.created_at >= date('now')", [$me['id']])->fetchColumn();
        if ($used >= $limit) {
            $send(['t' => 'error', 'message' => "You've reached today's limit of $limit AI requests. It resets at midnight (UTC); you can keep editing by hand meanwhile."]);
            exit;
        }
    }
    if (!is_file(__DIR__ . '/vendor/autoload.php')) {
        $send(['t' => 'error', 'message' => 'The AI library is missing on the server. Run "composer install" in the SiteForge folder.']);
        exit;
    }

    $history = array_reverse(q('SELECT role, body FROM messages WHERE site_id = ? ORDER BY id DESC LIMIT 12', [$id])->fetchAll());
    while ($history && $history[0]['role'] !== 'user') {
        array_shift($history);
    }
    $messages = array_map(fn ($m) => ['role' => $m['role'], 'content' => $m['body']], $history);
    $messages[] = ['role' => 'user', 'content' => request_content($site, $message, $current, $images, $focus)];

    $text = '';
    $stop = null;
    $tokensIn = $tokensOut = 0;
    try {
        require_once __DIR__ . '/vendor/autoload.php';
        $client = new Anthropic\Client(apiKey: cfg('anthropic_api_key'));
        $stream = $client->beta->messages->createStream(
            model: cfg('model'),
            maxTokens: 64000,
            system: [['type' => 'text', 'text' => system_prompt(), 'cacheControl' => ['type' => 'ephemeral']]],
            messages: $messages,
            thinking: ['type' => 'adaptive'],
            outputConfig: ['effort' => cfg('effort')],
            fallbacks: 'default',
            betas: ['server-side-fallback-2026-07-01'],
        );
        foreach ($stream as $event) {
            if ($event instanceof BetaRawMessageStartEvent) {
                $u = $event->message->usage;
                $tokensIn = $u->inputTokens + ($u->cacheCreationInputTokens ?? 0) + ($u->cacheReadInputTokens ?? 0);
            } elseif ($event instanceof BetaRawContentBlockStartEvent && $event->contentBlock->type === 'thinking') {
                $send(['t' => 'status', 'text' => 'Planning your design…']);
            } elseif ($event instanceof BetaRawContentBlockDeltaEvent && $event->delta instanceof BetaTextDelta) {
                $text .= $event->delta->text;
                $send(['t' => 'delta', 'text' => $event->delta->text]);
            } elseif ($event instanceof BetaRawMessageDeltaEvent) {
                $stop = $event->delta->stopReason ?? $stop;
                $tokensOut = $event->usage->outputTokens;
            }
            if (connection_aborted()) {
                exit;
            }
        }
    } catch (AuthenticationException) {
        $send(['t' => 'error', 'message' => 'The AI key in config.php was rejected. Check that it is correct.']);
        exit;
    } catch (RateLimitException) {
        $send(['t' => 'error', 'message' => 'The AI is busy right now. Wait a minute and send your message again.']);
        exit;
    } catch (APIStatusException $e) {
        $send(['t' => 'error', 'message' => 'The AI service returned an error (' . $e->status . '). Please try again.']);
        exit;
    } catch (APIConnectionException) {
        $send(['t' => 'error', 'message' => "Couldn't reach the AI service. Check the server's internet connection and try again."]);
        exit;
    } catch (AnthropicException $e) {
        $send(['t' => 'error', 'message' => 'Something went wrong talking to the AI: ' . $e->getMessage()]);
        exit;
    }

    if ($stop === 'refusal') {
        $send(['t' => 'error', 'message' => "The assistant can't help with that request. Try describing it differently."]);
        exit;
    }
    if ($stop === 'max_tokens') {
        $send(['t' => 'error', 'message' => 'That was too much to build in one go. Ask for fewer pages or changes at a time.']);
        exit;
    }

    $send(['t' => 'status', 'text' => 'Checking the photos…']);
    $text = fix_photos($text);
    $reply = preg_match('~<reply>(.*?)</reply>~s', $text, $m) ? trim($m[1]) : (str_contains($text, '<page') ? 'Done!' : trim($text));
    q('INSERT INTO messages (site_id, role, body, tokens_in, tokens_out) VALUES (?, ?, ?, 0, 0), (?, ?, ?, ?, ?)', [$id, 'user', $message, $id, 'assistant', $reply ?: 'Done!', $tokensIn, $tokensOut]);
    $send(['t' => 'done', 'text' => $text, 'reply' => $reply]);
    exit;
}

/** Swap any Unsplash photo that doesn't exist for a working placeholder of the same size. */
function fix_photos(string $text): string
{
    preg_match_all('~https://images\.unsplash\.com/photo-[\w-]+~', $text, $m);
    $handles = [];
    $multi = curl_multi_init();
    foreach (array_unique($m[0]) as $url) {
        $h = curl_init($url . '?w=10&q=1');
        curl_setopt_array($h, [CURLOPT_NOBODY => true, CURLOPT_TIMEOUT => 8, CURLOPT_RETURNTRANSFER => true]);
        curl_multi_add_handle($multi, $h);
        $handles[$url] = $h;
    }
    do {
        curl_multi_exec($multi, $running);
        curl_multi_select($multi);
    } while ($running);

    foreach ($handles as $url => $h) {
        // Only a definite "not found" counts; a slow or failed check keeps the photo.
        if (curl_getinfo($h, CURLINFO_HTTP_CODE) === 404) {
            $text = preg_replace_callback('~' . preg_quote($url, '~') . '(\?[^"\'\s)]*)?~', function ($x) use ($url) {
                parse_str(html_entity_decode(ltrim($x[1] ?? '', '?')), $q);
                return sprintf('https://picsum.photos/seed/%s/%d/%d', substr(md5($url), 0, 10), $q['w'] ?? 1200, $q['h'] ?? 800);
            }, $text);
        }
        curl_multi_remove_handle($multi, $h);
    }
    return $text;
}

function request_content(array $site, string $message, array $current, array $images, array $focus): array
{
    $content = [];
    $attached = [];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    foreach ($images as $url) {
        $file = basename((string) $url);
        $path = __DIR__ . '/uploads/' . $file;
        if (!q('SELECT 1 FROM assets WHERE site_id = ? AND file = ?', [$site['id'], $file])->fetch() || !is_file($path)) {
            continue;
        }
        $attached[] = base() . '/uploads/' . $file;
        if (filesize($path) <= 3_700_000) { // the API takes images up to 5 MB once base64-encoded
            $content[] = ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => $finfo->file($path), 'data' => base64_encode(file_get_contents($path))]];
        }
    }

    $pages = '';
    foreach ($current['pages'] ?? [] as $p) {
        $pages .= sprintf("<page id=\"%s\" name=\"%s\">\n%s\n</page>\n", e($p['id'] ?? ''), e($p['name'] ?? ''), $p['html'] ?? '');
    }
    $uploads = array_map(fn ($f) => '- ' . base() . '/uploads/' . $f, q('SELECT file FROM assets WHERE site_id = ? ORDER BY id', [$site['id']])->fetchAll(PDO::FETCH_COLUMN));

    $empty = trim(strip_tags(implode('', array_column($current['pages'] ?? [], 'html')), '<img>')) === '';
    $text = 'Website name: ' . $site['name'] . "\n\n<current_site>\n"
        . ($empty ? "The site is empty. Nothing has been built yet.\n" : "<css>\n" . ($current['css'] ?? '') . "\n</css>\n" . $pages)
        . "</current_site>\n\n";
    if ($uploads) {
        $text .= "Images the user uploaded (use these paths in src or url()):\n" . implode("\n", $uploads) . "\n\n";
    }
    if ($attached) {
        $text .= 'The user attached ' . count($attached) . " image(s) to this message (shown above), at:\n- " . implode("\n- ", $attached) . "\n\n";
    }
    if (!empty($focus['page'])) {
        $text .= 'The user is looking at the page with id "' . $focus['page'] . '".';
        if (!empty($focus['selected'])) {
            $text .= " They selected this element, so words like \"this\" or \"here\" refer to it:\n<selected>\n" . mb_substr($focus['selected'], 0, 6000) . "\n</selected>";
        }
        $text .= "\n\n";
    }
    $content[] = ['type' => 'text', 'text' => $text . "<request>\n" . $message . "\n</request>"];
    return $content;
}

function system_prompt(): string
{
    $fonts = implode(', ', array_keys(FONTS));
    return <<<PROMPT
You are the website builder inside SiteForge. The people you work with usually can't code: they describe the website they want and you build it, or change it, exactly the way they ask. They see your work immediately in a visual editor, where they can keep editing by hand or ask you for more changes.

How to work
- Do exactly what was asked, completely. If they name a feature, layout, section, page, colour, style or wording, build that, not something generic. If they describe an app-like idea (for example "a site where I add games and rate them"), build a real working page for it with the interactivity it needs.
- When the request is vague ("make it nicer", "a site for my bakery"), make confident, tasteful choices yourself rather than asking questions.
- When editing an existing site, change only what the request touches. Keep everything else exactly as it is, including the user's own manual edits, element ids and class names.
- Write real, specific content for their business or topic. Never use lorem ipsum. Write the site and your reply in the language the user writes in unless they ask otherwise. For Arabic or another right-to-left language, add direction: rtl to the body rule.
- Make it look professionally designed: strong visual hierarchy, generous whitespace, consistent buttons and cards, subtle hover states, and a layout that works from 360px phones to wide desktops.

Answer format: only these tags, nothing outside them.
<reply>One to three short, friendly sentences telling the user what you did, in plain words without code.</reply>
<css>
The complete stylesheet for the whole site (every page shares it). Leave this tag out if no styles need to change.
</css>
<page id="index" name="Home">
The full body content of the page (no html, head or body tags).
</page>
<remove page="old-page-id"/>

- Start with <reply>. Then include a <page> tag only for each page you create or change, each with its complete content. Pages you leave out stay as they are.
- The home page id is always "index". Other page ids are short, lowercase, words separated by dashes.
- If the user only asks a question, or you truly cannot build anything without one answer from them, send only <reply>.

Technical rules
- The stylesheet starts with a :root rule defining these theme variables, and the rest of the CSS uses them. The user's Theme panel edits them, so never hard-code the main colours or fonts elsewhere:
  --primary, --accent (colours), --on-primary (text colour on primary buttons), --bg (page background), --surface (cards and alternate sections), --text, --muted (secondary text), --font-heading, --font-body, --radius (corner radius), --base-size (body font size), --space (vertical section padding), --max-width (content width).
- Fonts: only these Google Fonts are loaded: {$fonts}. Use them by name, for example --font-heading: 'Playfair Display', serif. Do not add @import or link tags.
- Link between pages with href="page-id.html" (home is href="index.html"). Link to a section with href="#section-id". Every page shares the same header with navigation to all pages, and the same footer.
- Images: use the user's uploaded images wherever they fit. Otherwise use real Unsplash photos that match the content, as https://images.unsplash.com/photo-ID?auto=format&fit=crop&w=WIDTH&h=HEIGHT&q=80 with photo IDs you are confident exist and show the right subject. Every img gets a meaningful alt. For icons, use inline SVG or emoji.
- Plain HTML and CSS only: no frameworks, no external scripts or stylesheets, no inline style attributes (put all styles in the stylesheet with class names).
- Interactivity (mobile menu, tabs, sliders, filters, ratings, calculators, quizzes and so on) is welcome: put a single <script> at the end of the page that uses addEventListener, never inline on... attributes. Scripts only run on the live site, not inside the editor, so the page must look complete before any script runs. Scripts cannot use localStorage, cookies or network requests, so keep data in variables or in the page itself.
- Forms: a <form> without an action attribute delivers its answers to the site owner's SiteForge inbox. Give every field a name and a label, and use a submit button.
PROMPT;
}
