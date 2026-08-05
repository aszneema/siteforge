<?php
declare(strict_types=1);

namespace SiteForge;

use Anthropic\Client;
use Anthropic\Messages\RawContentBlockDeltaEvent;
use Anthropic\Messages\TextDelta;
use RuntimeException;
use Throwable;

/**
 * Builds sites by asking Claude to write the files.
 *
 * Files come back between plain-text delimiters rather than as JSON: HTML
 * escapes badly inside JSON strings and burns a lot of tokens doing it.
 */
final class ClaudeGenerator implements Generator
{
    private const MAX_TOKENS = 32000;

    /** Cap the file context sent back on an edit so long sites still fit. */
    private const MAX_CONTEXT_CHARS = 220000;

    public function __construct(
        private string $apiKey,
        private string $model = 'claude-opus-5',
    ) {
    }

    public function id(): string
    {
        return 'claude';
    }

    public function label(): string
    {
        return 'Claude (' . $this->model . ')';
    }

    public function create(array $ctx): Result
    {
        $progress = $ctx['progress'];
        $progress(15, 'Sending your brief to Claude');

        $prompt = "Build a complete website.\n\n"
            . "Site name: {$ctx['name']}\n"
            . 'Output format: ' . $this->formatBrief($ctx['site_type']) . "\n\n"
            . "What the user asked for:\n\"\"\"\n{$ctx['brief']}\n\"\"\"\n"
            . $this->imageBrief($ctx['images']);

        $raw = $this->send($this->systemPrompt($ctx['site_type']), $prompt, $progress);

        $progress(85, 'Writing files');
        [$files, $summary] = $this->parse($raw);

        if ($files === []) {
            throw new RuntimeException($this->emptyResultMessage($raw));
        }

        foreach ($ctx['images'] as $image) {
            $files[$image['path']] = $image['contents'];
        }

        return new Result($files, $summary !== '' ? $summary : 'Built your site from the brief.');
    }

    public function edit(array $ctx): Result
    {
        $progress = $ctx['progress'];
        $progress(15, 'Sending your changes to Claude');

        $prompt = "Here is the current site. Apply the requested change.\n\n"
            . "Site name: {$ctx['name']}\n"
            . 'Output format: ' . $this->formatBrief($ctx['site_type']) . "\n\n"
            . "Requested change:\n\"\"\"\n{$ctx['instruction']}\n\"\"\"\n\n"
            . $this->imageBrief($ctx['images'])
            . "Current files:\n\n" . $this->filesForPrompt($ctx['files']);

        $raw = $this->send($this->systemPrompt($ctx['site_type'], true), $prompt, $progress);

        $progress(85, 'Applying changes');
        [$changed, $summary, $deleted] = $this->parse($raw, true);

        if ($changed === [] && $deleted === []) {
            throw new RuntimeException($this->emptyResultMessage($raw));
        }

        // Start from the existing site so untouched files survive the edit.
        $files = $ctx['files'];
        foreach ($deleted as $path) {
            unset($files[$path]);
        }
        foreach ($changed as $path => $contents) {
            $files[$path] = $contents;
        }
        foreach ($ctx['images'] as $image) {
            $files[$image['path']] = $image['contents'];
        }

        return new Result($files, $summary !== '' ? $summary : 'Applied your changes.');
    }

    // --- API ---------------------------------------------------------------

    /**
     * Streams the response so long generations do not hit an HTTP timeout.
     * Only model/maxTokens/system/messages are sent: on claude-opus-5 adaptive
     * thinking and high effort are already the defaults.
     */
    private function send(string $system, string $prompt, callable $progress): string
    {
        try {
            $client = new Client(apiKey: $this->apiKey);

            $stream = $client->messages->createStream(
                model: $this->model,
                maxTokens: self::MAX_TOKENS,
                system: $system,
                messages: [
                    ['role' => 'user', 'content' => $prompt],
                ],
            );

            $text = '';
            $ticks = 0;
            foreach ($stream as $event) {
                if ($event instanceof RawContentBlockDeltaEvent && $event->delta instanceof TextDelta) {
                    $text .= $event->delta->text;

                    // Nudge the progress bar as content arrives (25% → 80%).
                    if (++$ticks % 25 === 0) {
                        $progress(min(80, 25 + intdiv(strlen($text), 900)), 'Claude is writing your site');
                    }
                }
            }

            return $text;
        } catch (Throwable $e) {
            throw new RuntimeException($this->friendlyError($e), 0, $e);
        }
    }

    private function friendlyError(Throwable $e): string
    {
        $message = $e->getMessage();

        return match (true) {
            str_contains($message, '401'), stripos($message, 'authentication') !== false =>
                'Anthropic rejected the API key. Check ANTHROPIC_API_KEY and try again.',
            str_contains($message, '429'), stripos($message, 'rate_limit') !== false =>
                'Anthropic rate limit reached. Wait a moment and try again.',
            str_contains($message, '529'), stripos($message, 'overloaded') !== false =>
                'Anthropic is overloaded right now. Try again in a minute.',
            stripos($message, 'could not resolve') !== false, stripos($message, 'timed out') !== false =>
                'Could not reach the Anthropic API. Check this machine\'s internet connection.',
            default => 'Claude request failed: ' . mb_substr($message, 0, 300),
        };
    }

    private function emptyResultMessage(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return 'Claude returned nothing for this request — it may have declined it. '
                 . 'Try rewording your description.';
        }

        return 'Claude replied but did not return any files. It said: "'
             . mb_substr(preg_replace('/\s+/', ' ', $raw) ?? '', 0, 240) . '"';
    }

    // --- Prompting ---------------------------------------------------------

    private function systemPrompt(string $siteType, bool $editing = false): string
    {
        $rules = <<<'TXT'
        You are a senior front-end developer who builds complete, production-quality marketing
        websites. You write real, finished pages — never placeholders, lorem ipsum, TODOs or
        "add your content here".

        Design rules:
        - Write genuinely good-looking, modern, responsive design. Mobile-first, with sensible
          breakpoints. No generic AI-template look.
        - Use a deliberate colour palette (CSS custom properties at the top of the stylesheet),
          clear typographic hierarchy, generous spacing and considered visual rhythm.
        - Add tasteful motion: scroll reveals, hover states, smooth transitions. Nothing gaudy.
        - Do not link to external fonts, CDNs, images or scripts unless the output format below
          explicitly calls for it. Use system font stacks, CSS gradients and inline SVG instead,
          so the site works offline.
        - Write realistic copy tailored to the business described. Invent plausible details
          (services, hours, testimonials) rather than leaving blanks.
        - Semantic HTML, alt text on images, labelled form fields, visible focus styles.
        TXT;

        $output = $editing
            ? <<<'TXT'

            OUTPUT FORMAT — follow exactly:

            First a single line:
            SUMMARY: <one or two sentences describing what you changed>

            Then ONLY the files you created or modified, each as:
            === FILE: relative/path.html ===
            <the complete new contents of the file>
            === END FILE ===

            To delete a file, emit a line: === DELETE: relative/path.html ===

            Rules:
            - Emit the COMPLETE contents of every file you touch. Never emit a diff, a fragment
              or a comment like "unchanged".
            - Do not re-emit files you did not change.
            - No markdown code fences anywhere. No commentary outside the SUMMARY line.
            TXT
            : <<<'TXT'

            OUTPUT FORMAT — follow exactly:

            First a single line:
            SUMMARY: <one or two sentences describing the site you built>

            Then every file, each as:
            === FILE: relative/path.html ===
            <the complete contents of the file>
            === END FILE ===

            Rules:
            - Paths are relative, use forward slashes, and go at most 3 folders deep.
            - No markdown code fences anywhere. No commentary outside the SUMMARY line.
            TXT;

        return $rules . "\n\nTarget: " . $this->formatBrief($siteType) . "\n" . $output;
    }

    private function formatBrief(string $siteType): string
    {
        return match ($siteType) {
            'php' => 'A PHP site. Pages are .php files at the root (index.php, about.php, '
                   . 'services.php, contact.php). Shared chrome lives in includes/header.php and '
                   . 'includes/footer.php, pulled in with require __DIR__ . "/includes/header.php". '
                   . 'CSS in assets/css/styles.css, JS in assets/js/main.js. Links between pages are '
                   . 'bare filenames. Include a working contact form that posts to itself and '
                   . 'validates input server-side.',
            'landing' => 'A single-page site: one index.html containing every section, with the nav '
                   . 'linking to in-page anchors and smooth scrolling. CSS in css/styles.css, JS in '
                   . 'js/main.js. No other pages.',
            'bootstrap' => 'A static site built on Bootstrap 5, loaded from the official jsDelivr CDN '
                   . '(this is the one permitted external resource). Multi-page: index.html, '
                   . 'about.html, services.html, contact.html. Use Bootstrap grid, navbar, cards and '
                   . 'utilities properly, plus css/styles.css for your own overrides and js/main.js.',
            default => 'A static site: index.html, about.html, services.html and contact.html at the '
                   . 'root, css/styles.css and js/main.js. Plain HTML, CSS and vanilla JavaScript — '
                   . 'no build step, no frameworks. Links between pages are bare filenames.',
        };
    }

    private function imageBrief(array $images): string
    {
        if ($images === []) {
            return '';
        }

        $lines = "The user uploaded these images. Reference them at exactly these paths and write\n"
               . "descriptive alt text for each:\n";
        foreach ($images as $image) {
            $lines .= '  - ' . $image['path'] . '  (original name: ' . $image['name'] . ")\n";
        }

        return $lines . "\nDo not emit the image files themselves — they are already in place.\n\n";
    }

    /** @param array<string,string> $files */
    private function filesForPrompt(array $files): string
    {
        $out = '';
        $budget = self::MAX_CONTEXT_CHARS;

        foreach ($files as $path => $contents) {
            if (preg_match('/\.(png|jpe?g|gif|webp|ico)$/i', $path)) {
                $out .= "=== FILE: {$path} ===\n[binary image, " . strlen($contents) . " bytes]\n=== END FILE ===\n\n";
                continue;
            }
            if ($budget <= 0) {
                $out .= "=== FILE: {$path} ===\n[omitted — context limit reached]\n=== END FILE ===\n\n";
                continue;
            }
            if (strlen($contents) > $budget) {
                $contents = substr($contents, 0, $budget) . "\n[... truncated ...]";
            }
            $budget -= strlen($contents);
            $out .= "=== FILE: {$path} ===\n{$contents}\n=== END FILE ===\n\n";
        }

        return $out;
    }

    // --- Parsing -----------------------------------------------------------

    /**
     * @return array{0:array<string,string>,1:string,2:array<int,string>}
     */
    private function parse(string $raw, bool $allowDeletes = false): array
    {
        $summary = '';
        if (preg_match('/^\s*SUMMARY:\s*(.+)$/mi', $raw, $m)) {
            $summary = trim($m[1]);
        }

        $files = [];
        if (preg_match_all('/^===\s*FILE:\s*(.+?)\s*===\r?\n(.*?)\r?\n===\s*END FILE\s*===/ms', $raw, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $path = trim($match[1]);
                $body = $this->stripFences($match[2]);
                if ($path !== '') {
                    $files[$path] = $body;
                }
            }
        }

        $deleted = [];
        if ($allowDeletes && preg_match_all('/^===\s*DELETE:\s*(.+?)\s*===\s*$/mi', $raw, $matches)) {
            foreach ($matches[1] as $path) {
                $deleted[] = trim($path);
            }
        }

        return [$files, $summary, $deleted];
    }

    /** Remove a wrapping markdown fence if the model added one anyway. */
    private function stripFences(string $body): string
    {
        $trimmed = trim($body);
        if (preg_match('/^```[a-z0-9]*\r?\n(.*)\r?\n```$/is', $trimmed, $m)) {
            return $m[1];
        }
        return $body;
    }
}
