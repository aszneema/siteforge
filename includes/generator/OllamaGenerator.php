<?php
declare(strict_types=1);

namespace SiteForge;

use RuntimeException;

/**
 * Builds sites with a language model running locally through Ollama — no API
 * key, no cost, no internet.
 *
 * Design note: this does NOT ask the model to emit raw HTML. A 7–8B model on a
 * laptop is slow and unreliable at producing six consistent files, and the
 * result is usually worse markup than the renderer already produces. Instead
 * the model does the one thing templates cannot — write copy that is actually
 * about *your* business — and returns it as structured data. The existing
 * Renderer then builds the site from that.
 *
 * The upshot: your specifics land in the copy, and every other feature
 * (palettes, motion, components, all four output formats, section ordering)
 * keeps working exactly as before.
 */
final class OllamaGenerator implements Generator
{
    /** Preferred models, best first. General instruct models beat coder models for prose. */
    private const PREFERRED = [
        'llama3.1', 'llama3.3', 'qwen2.5', 'mistral-nemo', 'gemma2',
        'llama3.2', 'mistral', 'phi3', 'qwen2.5-coder', 'deepseek-r1',
    ];

    public function __construct(
        private string $url = 'http://localhost:11434',
        private string $model = '',
    ) {
        $this->url = rtrim($this->url, '/');
    }

    public function id(): string
    {
        return 'ollama';
    }

    public function label(): string
    {
        return 'Local AI' . ($this->model !== '' ? ' (' . $this->model . ')' : '');
    }

    // --- Availability -------------------------------------------------------

    /** Is an Ollama server responding, and does it have at least one model? */
    public static function isAvailable(string $url = 'http://localhost:11434'): bool
    {
        return self::models($url) !== [];
    }

    /**
     * Installed model names. Cached per request — this is called from page
     * headers, so it must stay cheap.
     *
     * @return array<int,string>
     */
    public static function models(string $url = 'http://localhost:11434'): array
    {
        static $cache = [];
        $url = rtrim($url, '/');

        if (array_key_exists($url, $cache)) {
            return $cache[$url];
        }

        $raw = self::httpGet($url . '/api/tags', 700);
        $names = [];

        if ($raw !== null) {
            $data = json_decode($raw, true);
            foreach ($data['models'] ?? [] as $entry) {
                if (!empty($entry['name'])) {
                    $names[] = (string) $entry['name'];
                }
            }
        }

        return $cache[$url] = $names;
    }

    /** Pick the best installed model, honouring an explicit config choice. */
    public static function chooseModel(string $url, string $configured = ''): string
    {
        $installed = self::models($url);
        if ($installed === []) {
            return '';
        }

        if ($configured !== '') {
            foreach ($installed as $name) {
                if ($name === $configured || str_starts_with($name, $configured . ':')) {
                    return $name;
                }
            }
        }

        foreach (self::PREFERRED as $wanted) {
            foreach ($installed as $name) {
                if ($name === $wanted || str_starts_with($name, $wanted . ':')) {
                    return $name;
                }
            }
        }

        return $installed[0];
    }

    // --- Generation ---------------------------------------------------------

    public function create(array $ctx): Result
    {
        $progress = $ctx['progress'];

        // Start from the template spec: it gives us a complete, valid site
        // (palette, sections, components, layout) that the model then rewrites
        // the copy of. Anything the model omits keeps a sensible default.
        $progress(12, 'Reading your brief');
        $spec = Blueprint::analyse($ctx['brief'], $ctx['site_type'], $ctx['name'], $ctx['images']);

        $progress(25, 'Writing your content with ' . $this->model);
        $written = $this->ask($this->createPrompt($ctx['brief']), $this->contentSystem());

        $progress(70, 'Building pages');
        $spec = $this->mergeContent($spec, $written);

        $files = $this->render($spec, $ctx['site_type']);
        foreach ($ctx['images'] as $image) {
            $files[$image['path']] = $image['contents'];
        }

        $progress(90, 'Finishing up');

        return new Result(
            $files,
            'Wrote your site from the brief with ' . $this->model . ' — copy, services and details all taken from what you described.',
            $spec
        );
    }

    public function edit(array $ctx): Result
    {
        $progress = $ctx['progress'];

        $spec = $ctx['spec'];
        if (empty($spec)) {
            throw new RuntimeException(
                'This project was built by a different engine and has no editable structure. '
                . 'Rebuild it from the Create page to edit it with the local model.'
            );
        }

        // Structural and visual changes are deterministic and instant — no
        // reason to spend a model call on "make it dark".
        $progress(20, 'Applying changes');
        [$spec, $changes] = Blueprint::applyEdit($spec, $ctx['instruction']);

        // Then let the model handle anything about the words themselves.
        $progress(40, 'Rewriting copy with ' . $this->model);
        $patch = $this->ask(
            $this->editPrompt($ctx['instruction'], $spec),
            $this->contentSystem(true)
        );

        $before = $spec;
        $spec = $this->mergeContent($spec, $patch);

        if ($this->copyChanged($before, $spec)) {
            $changes[] = 'rewrote the wording to match your request';
        }

        if ($changes === []) {
            throw new RuntimeException(
                'Nothing changed — the model did not find anything in "'
                . mb_substr(trim($ctx['instruction']), 0, 80)
                . '" that applies to this site. Try naming the part you want different.'
            );
        }

        $progress(75, 'Rebuilding pages');
        $spec['images'] = array_values(array_unique(array_merge(
            $spec['images'] ?? [],
            array_map(static fn ($i) => $i['path'], $ctx['images'])
        )));

        $files = $this->render($spec, $ctx['site_type']);

        // Keep any images already in the project.
        foreach ($ctx['files'] as $path => $contents) {
            if (preg_match('/\.(png|jpe?g|gif|webp|ico|svg)$/i', $path) && !isset($files[$path])) {
                $files[$path] = $contents;
            }
        }
        foreach ($ctx['images'] as $image) {
            $files[$image['path']] = $image['contents'];
        }

        return new Result($files, 'Updated your site: ' . $this->joinList($changes) . '.', $spec);
    }

    /** @return array<string,string> */
    private function render(array $spec, string $siteType): array
    {
        return $siteType === 'bootstrap'
            ? (new BootstrapRenderer($spec))->render()
            : (new Renderer($spec, $siteType))->render();
    }

    // --- Prompting ----------------------------------------------------------

    private function contentSystem(bool $editing = false): string
    {
        $task = $editing
            ? 'You revise website copy. Return ONLY the fields that need to change.'
            : 'You write website copy.';

        return <<<TXT
        {$task}

        Reply with a single JSON object and nothing else — no explanation, no
        markdown fences, no commentary.

        Shape (include only keys you have something for):
        {
          "name": "the business name",
          "tagline": "short strapline, under 60 characters",
          "hero": {
            "eyebrow": "small line above the headline, under 40 chars",
            "title": "headline, under 60 chars",
            "sub": "one or two sentences, under 220 chars",
            "cta": "button text, 1-3 words",
            "cta2": "second button text, 1-3 words"
          },
          "about": {
            "title": "section heading",
            "lead": "one strong sentence",
            "body": ["paragraph", "paragraph"],
            "points": ["short point", "short point", "short point"]
          },
          "services": {
            "title": "section heading",
            "sub": "one sentence",
            "items": [{"title": "name", "text": "one or two sentences"}]
          },
          "stats": [{"value": "12", "label": "short label"}],
          "testimonials": [{"quote": "what they said", "name": "Full Name", "role": "who they are"}],
          "faq": [{"q": "question?", "a": "answer"}],
          "contact": {"email": "", "phone": "", "address": "", "hours": ""}
        }

        Rules:
        - Use every concrete detail the user gives you: names, dates, family
          history, specific products, prices, locations, opening times. These are
          the whole point — do not replace them with generic filler.
        - Where the user gives no detail, invent something plausible and specific
          for that kind of business. Never write placeholder text, never write
          "lorem ipsum", never write "[your text here]".
        - British English. Plain, concrete, human. No marketing waffle, no
          exclamation marks, no "unlock", "elevate", "seamless" or "passionate".
        - 4 to 6 services items, 2 to 3 testimonials, 3 to 4 FAQ entries,
          3 stats — unless the user asked for something different.
        - Plain text only inside the strings. No HTML.
        TXT;
    }

    private function createPrompt(string $brief): string
    {
        return "Write the copy for this website.\n\nThe user's description:\n\"\"\"\n"
            . trim($brief) . "\n\"\"\"\n\nReturn the JSON object now.";
    }

    private function editPrompt(string $instruction, array $spec): string
    {
        $current = json_encode([
            'name' => $spec['name'] ?? '',
            'tagline' => $spec['tagline'] ?? '',
            'hero' => $spec['hero'] ?? [],
            'about' => $spec['about'] ?? [],
            'services' => $spec['services'] ?? [],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return "Here is the current copy of a website:\n\n{$current}\n\n"
            . "The user asked for this change:\n\"\"\"\n" . trim($instruction) . "\n\"\"\"\n\n"
            . "If the request is about the wording or content, return a JSON object with only the "
            . "fields that should change. If it is purely about colours, layout, spacing or adding "
            . "a section — nothing to do with the words — return exactly {}.\n\nReturn the JSON now.";
    }

    // --- Merging ------------------------------------------------------------

    /**
     * Overlay model-written content onto the spec. Only known keys are taken and
     * every value is coerced to the shape the renderer expects, so a malformed
     * or partial reply degrades to "fewer things changed" rather than a broken site.
     */
    private function mergeContent(array $spec, array $new): array
    {
        if ($new === []) {
            return $spec;
        }

        foreach (['name', 'tagline'] as $key) {
            if (!empty($new[$key]) && is_string($new[$key])) {
                $spec[$key] = $this->clean($new[$key], 90);
            }
        }

        foreach (['eyebrow' => 60, 'title' => 120, 'sub' => 300, 'cta' => 40, 'cta2' => 40] as $key => $max) {
            if (!empty($new['hero'][$key]) && is_string($new['hero'][$key])) {
                $spec['hero'][$key] = $this->clean($new['hero'][$key], $max);
            }
        }

        foreach (['title' => 120, 'lead' => 260] as $key => $max) {
            if (!empty($new['about'][$key]) && is_string($new['about'][$key])) {
                $spec['about'][$key] = $this->clean($new['about'][$key], $max);
            }
        }
        if (!empty($new['about']['body']) && is_array($new['about']['body'])) {
            $body = [];
            foreach (array_slice($new['about']['body'], 0, 3) as $paragraph) {
                if (is_string($paragraph) && trim($paragraph) !== '') {
                    $body[] = $this->clean($paragraph, 700);
                }
            }
            if ($body !== []) {
                $spec['about']['body'] = $body;
            }
        }
        if (!empty($new['about']['points']) && is_array($new['about']['points'])) {
            $points = [];
            foreach (array_slice($new['about']['points'], 0, 5) as $point) {
                if (is_string($point) && trim($point) !== '') {
                    $points[] = $this->clean($point, 120);
                }
            }
            if ($points !== []) {
                $spec['about']['points'] = $points;
            }
        }

        foreach (['title' => 120, 'sub' => 240] as $key => $max) {
            if (!empty($new['services'][$key]) && is_string($new['services'][$key])) {
                $spec['services'][$key] = $this->clean($new['services'][$key], $max);
            }
        }
        $items = $this->pairs($new['services']['items'] ?? null, 'title', 'text', 8, 120, 400);
        if ($items !== []) {
            $spec['services']['items'] = $items;
        }

        $stats = $this->pairs($new['stats'] ?? null, 'value', 'label', 4, 12, 60);
        if ($stats !== []) {
            $spec['stats'] = $stats;
        }

        $faq = $this->pairs($new['faq'] ?? null, 'q', 'a', 6, 200, 700);
        if ($faq !== []) {
            $spec['faq'] = $faq;
        }

        if (!empty($new['testimonials']) && is_array($new['testimonials'])) {
            $quotes = [];
            foreach (array_slice($new['testimonials'], 0, 4) as $entry) {
                if (!is_array($entry) || empty($entry['quote']) || !is_string($entry['quote'])) {
                    continue;
                }
                $quotes[] = [
                    'quote' => $this->clean($entry['quote'], 400),
                    'name'  => $this->clean((string) ($entry['name'] ?? 'Customer'), 60),
                    'role'  => $this->clean((string) ($entry['role'] ?? 'Client'), 60),
                ];
            }
            if ($quotes !== []) {
                $spec['testimonials'] = $quotes;
            }
        }

        foreach (['email', 'phone', 'address', 'hours'] as $key) {
            if (!empty($new['contact'][$key]) && is_string($new['contact'][$key])) {
                $spec['contact'][$key] = $this->clean($new['contact'][$key], 160);
            }
        }

        return $spec;
    }

    /**
     * Normalise a list of two-field objects, tolerating the common variations a
     * small model produces (a bare list of strings, or differently named keys).
     */
    private function pairs(mixed $raw, string $keyA, string $keyB, int $limit, int $maxA, int $maxB): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach (array_slice($raw, 0, $limit) as $entry) {
            if (is_string($entry)) {
                $out[] = [$keyA => $this->clean($entry, $maxA), $keyB => ''];
                continue;
            }
            if (!is_array($entry)) {
                continue;
            }

            $a = $entry[$keyA] ?? $entry['name'] ?? $entry['question'] ?? $entry['label'] ?? null;
            $b = $entry[$keyB] ?? $entry['description'] ?? $entry['answer'] ?? $entry['value'] ?? null;

            if (!is_string($a) || trim($a) === '') {
                continue;
            }
            $out[] = [
                $keyA => $this->clean($a, $maxA),
                $keyB => is_string($b) ? $this->clean($b, $maxB) : '',
            ];
        }

        return $out;
    }

    private function clean(string $text, int $max): string
    {
        $text = strip_tags(trim($text));
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return mb_substr($text, 0, $max);
    }

    /** Did the model actually alter any of the visible copy? */
    private function copyChanged(array $before, array $after): bool
    {
        foreach (['name', 'tagline', 'hero', 'about', 'services', 'stats', 'testimonials', 'faq', 'contact'] as $key) {
            if (($before[$key] ?? null) !== ($after[$key] ?? null)) {
                return true;
            }
        }

        return false;
    }

    // --- Talking to Ollama --------------------------------------------------

    /**
     * One request/response. Returns the decoded JSON object the model produced.
     *
     * @return array<string,mixed>
     */
    private function ask(string $prompt, string $system): array
    {
        if ($this->model === '') {
            throw new RuntimeException('No Ollama model is installed. Run: ollama pull llama3.1');
        }

        $payload = json_encode([
            'model'  => $this->model,
            'prompt' => $prompt,
            'system' => $system,
            'stream' => false,
            'format' => 'json',   // Ollama constrains the output to valid JSON
            'options' => [
                'temperature' => 0.7,
                'num_predict' => 2048,
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $raw = $this->httpPost($this->url . '/api/generate', (string) $payload);
        $envelope = json_decode($raw, true);

        if (!is_array($envelope) || !isset($envelope['response'])) {
            throw new RuntimeException(
                'Ollama sent back something unexpected. Check the model is working: '
                . 'ollama run ' . $this->model
            );
        }

        $decoded = $this->extractJson((string) $envelope['response']);
        if ($decoded === null) {
            throw new RuntimeException(
                $this->model . ' did not return usable JSON. Smaller models sometimes struggle '
                . 'with this — try a larger one (ollama pull llama3.1) and generate again.'
            );
        }

        return $decoded;
    }

    /** Pull the JSON object out of a reply, even if it is fenced or padded with prose. */
    private function extractJson(string $text): ?array
    {
        $text = trim($text);

        if (preg_match('/```(?:json)?\s*(.+?)```/is', $text, $m)) {
            $text = trim($m[1]);
        }

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Fall back to the outermost brace pair.
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private static function httpGet(string $url, int $timeoutMs): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS     => $timeoutMs,
            CURLOPT_CONNECTTIMEOUT_MS => $timeoutMs,
        ]);
        $body = curl_exec($ch);
        $ok = $body !== false && curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 200;
        curl_close($ch);

        return $ok ? (string) $body : null;
    }

    private function httpPost(string $url, string $json): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            // Generation on CPU can genuinely take minutes.
            CURLOPT_TIMEOUT        => 600,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException(
                str_contains($error, 'Connection refused') || str_contains($error, 'connect')
                    ? 'Could not reach Ollama at ' . $this->url . '. Start it with: ollama serve'
                    : 'Ollama request failed: ' . $error
            );
        }
        if ($status !== 200) {
            throw new RuntimeException('Ollama returned HTTP ' . $status . '. ' . mb_substr((string) $body, 0, 200));
        }

        return (string) $body;
    }

    private function joinList(array $items): string
    {
        $items = array_values(array_filter($items));
        if ($items === []) {
            return 'nothing';
        }
        if (count($items) === 1) {
            return $items[0];
        }
        $last = array_pop($items);

        return implode(', ', $items) . ' and ' . $last;
    }
}
