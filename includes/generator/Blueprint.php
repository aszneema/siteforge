<?php
declare(strict_types=1);

namespace SiteForge;

/**
 * Turns a plain-English brief into a structured site description ("spec"),
 * and applies plain-English edits to that spec.
 *
 * The spec is what the offline builder renders from, and it is stored with
 * every version, so edits compose instead of fighting each other.
 */
final class Blueprint
{
    // --- Colour palettes ---------------------------------------------------

    public const PALETTES = [
        'indigo'  => ['primary' => '#4f46e5', 'dark' => '#3730a3', 'accent' => '#06b6d4'],
        'ocean'   => ['primary' => '#0284c7', 'dark' => '#075985', 'accent' => '#22d3ee'],
        'teal'    => ['primary' => '#0d9488', 'dark' => '#115e59', 'accent' => '#2dd4bf'],
        'emerald' => ['primary' => '#059669', 'dark' => '#047857', 'accent' => '#34d399'],
        'forest'  => ['primary' => '#15803d', 'dark' => '#14532d', 'accent' => '#84cc16'],
        'ember'   => ['primary' => '#c2410c', 'dark' => '#7c2d12', 'accent' => '#f59e0b'],
        'crimson' => ['primary' => '#dc2626', 'dark' => '#991b1b', 'accent' => '#fb923c'],
        'rose'    => ['primary' => '#e11d48', 'dark' => '#9f1239', 'accent' => '#fb7185'],
        'violet'  => ['primary' => '#7c3aed', 'dark' => '#5b21b6', 'accent' => '#a78bfa'],
        'gold'    => ['primary' => '#b45309', 'dark' => '#78350f', 'accent' => '#eab308'],
        'sand'    => ['primary' => '#a16207', 'dark' => '#713f12', 'accent' => '#d97706'],
        'slate'   => ['primary' => '#334155', 'dark' => '#0f172a', 'accent' => '#64748b'],
    ];

    private const COLOUR_WORDS = [
        'indigo' => 'indigo', 'blue' => 'ocean', 'navy' => 'ocean', 'sky' => 'ocean',
        'cyan' => 'teal', 'turquoise' => 'teal', 'teal' => 'teal', 'aqua' => 'teal',
        'green' => 'emerald', 'mint' => 'emerald', 'forest' => 'forest', 'olive' => 'forest',
        'orange' => 'ember', 'amber' => 'ember', 'terracotta' => 'ember', 'rust' => 'ember',
        'red' => 'crimson', 'maroon' => 'crimson', 'burgundy' => 'crimson',
        'pink' => 'rose', 'rose' => 'rose', 'magenta' => 'rose',
        'purple' => 'violet', 'violet' => 'violet', 'lavender' => 'violet',
        'gold' => 'gold', 'yellow' => 'gold', 'luxury' => 'gold', 'premium' => 'gold',
        'beige' => 'sand', 'cream' => 'sand', 'warm' => 'sand', 'earthy' => 'sand',
        'grey' => 'slate', 'gray' => 'slate', 'monochrome' => 'slate', 'black' => 'slate',
    ];

    private const STYLE_WORDS = [
        'minimal' => 'minimal', 'minimalist' => 'minimal', 'clean' => 'minimal',
        'simple' => 'minimal', 'understated' => 'minimal',
        'elegant' => 'elegant', 'luxury' => 'elegant', 'classy' => 'elegant',
        'sophisticated' => 'elegant', 'refined' => 'elegant', 'upscale' => 'elegant',
        'bold' => 'bold', 'striking' => 'bold', 'punchy' => 'bold', 'loud' => 'bold',
        'playful' => 'playful', 'fun' => 'playful', 'friendly' => 'playful',
        'quirky' => 'playful', 'colourful' => 'playful', 'colorful' => 'playful',
        'modern' => 'modern', 'sleek' => 'modern', 'contemporary' => 'modern',
    ];

    /** Section keys the user can name directly in a brief or an edit. */
    private const SECTION_WORDS = [
        'gallery' => ['gallery', 'photos', 'images', 'portfolio grid', 'lookbook'],
        'pricing' => ['pricing', 'prices', 'packages', 'plans', 'rates', 'tariff'],
        'testimonials' => ['testimonial', 'reviews', 'what clients say', 'feedback'],
        'faq' => ['faq', 'questions', 'q&a'],
        'team' => ['team', 'staff', 'our people', 'meet the'],
        'stats' => ['stats', 'numbers', 'statistics', 'achievements'],
        'cta' => ['call to action', 'cta', 'newsletter', 'signup', 'sign up'],
    ];

    // --- Analysis ----------------------------------------------------------

    /**
     * @param array $images uploaded images: [['path'=>..,'name'=>..], ...]
     */
    public static function analyse(string $brief, string $siteType, string $projectName, array $images = []): array
    {
        $text = mb_strtolower($brief);

        $industryKey = self::detectIndustry($text);
        $bank = Content::industry($industryKey);

        $name = self::detectName($brief, $projectName, $bank['name']);
        $paletteKey = self::detectPalette($text, $bank['palette']);
        $style = self::detectStyle($text, $bank['style']);
        $dark = self::wants($text, ['dark mode', 'dark theme', 'dark background', 'black background', 'night mode']);

        $sections = $bank['sections'];
        foreach (self::SECTION_WORDS as $section => $words) {
            if (self::wants($text, $words)) {
                $sections[] = $section;
            }
        }
        $sections = array_values(array_unique($sections));

        $spec = [
            'name'      => $name,
            'industry'  => $industryKey,
            'tagline'   => $bank['tagline'],
            'style'     => $style,
            'palette'   => $paletteKey,
            'dark'      => $dark,
            'sections'  => $sections,
            'hero'      => $bank['hero'],
            'about'     => $bank['about'],
            'services'  => $bank['services'],
            'stats'     => $bank['stats'],
            'testimonials' => $bank['testimonials'],
            'faq'       => $bank['faq'],
            'pricing'   => $bank['pricing'],
            'team'      => $bank['team'],
            'contact'   => $bank['contact'],
            'images'    => array_map(static fn ($i) => $i['path'], $images),
            'site_type' => $siteType,
        ];

        // Weave the business name into the copy so it does not read as a template.
        $spec['hero']['title'] = str_replace('{name}', $name, $spec['hero']['title']);
        $spec['hero']['sub'] = str_replace('{name}', $name, $spec['hero']['sub']);
        $spec['about']['body'] = array_map(
            static fn ($p) => str_replace('{name}', $name, $p),
            $spec['about']['body']
        );
        $spec['contact']['email'] = 'hello@' . self::domainFrom($name);

        // Let the brief request the same things an edit can — "modern and
        // dynamic, with a slider and a pricing table" works on the first build.
        [$spec] = Intents::apply($spec, $brief, true);

        return $spec;
    }

    private static function detectIndustry(string $text): string
    {
        $best = 'generic';
        $bestScore = 0;
        $bestOffset = PHP_INT_MAX;

        foreach (Content::all() as $key => $bank) {
            $score = 0;
            $offset = PHP_INT_MAX;

            foreach ($bank['match'] as $word) {
                $at = mb_strpos($text, $word);
                if ($at === false) {
                    continue;
                }
                // Longer keywords are more specific, so weight them higher.
                $score += mb_strlen($word) > 6 ? 3 : 2;
                $offset = min($offset, $at);
            }

            // On a tie, trust whichever keyword the writer mentioned first —
            // "a bakery … show the menu" is a bakery, not a restaurant.
            if ($score > $bestScore || ($score === $bestScore && $score > 0 && $offset < $bestOffset)) {
                $bestScore = $score;
                $bestOffset = $offset;
                $best = $key;
            }
        }

        return $best;
    }

    /** Pull a business name out of the brief, falling back to the project name. */
    public static function detectName(string $brief, string $projectName, string $fallback): string
    {
        // A name is a run of capitalised words, optionally joined by a lowercase
        // connector — "Field and Frame", "House of Ash", "Hartley & Crowe".
        $word = '[A-Z][\w&\'’.-]*';
        $more = '(?:\s+(?:and|of|the|for|&|\+)\s+' . $word . '|\s+' . $word . ')';

        $patterns = [
            '/(?:called|named)\s+["\x{201C}]([^"\x{201D}]{2,50})["\x{201D}]/iu',
            '/(?:called|named)\s+(' . $word . $more . '{0,3})/u',
            '/["\x{201C}]([^"\x{201D}]{2,50})["\x{201D}]/u',
            '/(?:business is|company is|brand is)\s+(' . $word . $more . '{0,2})/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $brief, $m)) {
                // "called Riverside Dental. Calm and trustworthy…" must yield
                // "Riverside Dental", not everything up to the next full stop.
                $candidate = preg_split('/(?<=\.)\s+/', $m[1])[0] ?? $m[1];
                $candidate = trim($candidate, " \t\n\r.,;:'\"");

                if ($candidate !== '' && mb_strlen($candidate) <= 50 && !self::isCommonWord($candidate)) {
                    return $candidate;
                }
            }
        }

        $projectName = trim($projectName);
        if ($projectName !== '' && strcasecmp($projectName, 'Untitled site') !== 0) {
            return $projectName;
        }

        return $fallback;
    }

    private static function isCommonWord(string $candidate): bool
    {
        $blocked = ['a', 'an', 'the', 'me', 'my', 'us', 'it', 'this', 'that', 'website', 'site', 'page', 'business'];
        return in_array(mb_strtolower($candidate), $blocked, true);
    }

    public static function detectPalette(string $text, string $fallback): string
    {
        foreach (self::COLOUR_WORDS as $word => $palette) {
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/', $text)) {
                return $palette;
            }
        }
        return $fallback;
    }

    public static function detectStyle(string $text, string $fallback): string
    {
        foreach (self::STYLE_WORDS as $word => $style) {
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/', $text)) {
                return $style;
            }
        }
        return $fallback;
    }

    private static function wants(string $text, array $words): bool
    {
        foreach ($words as $word) {
            if (str_contains($text, $word)) {
                return true;
            }
        }
        return false;
    }

    private static function domainFrom(string $name): string
    {
        $slug = preg_replace('/[^a-z0-9]/', '', mb_strtolower($name)) ?: 'example';
        return mb_substr($slug, 0, 24) . '.com';
    }

    // --- Editing -----------------------------------------------------------
    /**
     * Apply a plain-English change to a spec.
     *
     * The matching lives in Intents, which understands a broad vocabulary,
     * negation and typos, and can apply several changes from one sentence.
     *
     * @return array{0:array,1:array<int,string>,2:bool} spec, changes made, whether
     *                                                   the instruction was understood
     */
    public static function applyEdit(array $spec, string $instruction): array
    {
        return Intents::apply($spec, $instruction);
    }

    // --- Derived theme -------------------------------------------------------

    /** Resolve palette + style into the CSS custom properties the renderer uses. */
    public static function theme(array $spec): array
    {
        $palette = self::PALETTES[$spec['palette']] ?? self::PALETTES['indigo'];
        $primary = $spec['custom_primary'] ?? $palette['primary'];
        $dark = (bool) ($spec['dark'] ?? false);

        $style = match ($spec['style'] ?? 'modern') {
            'minimal' => ['radius' => '4px', 'radiusLg' => '8px', 'weight' => '600', 'track' => '-0.01em',
                          'heading' => '"Helvetica Neue", Helvetica, Arial, system-ui, sans-serif',
                          'body' => '"Helvetica Neue", Helvetica, Arial, system-ui, sans-serif',
                          'hero' => 'clamp(2.4rem, 5vw, 3.8rem)', 'pad' => '84px'],
            'elegant' => ['radius' => '2px', 'radiusLg' => '4px', 'weight' => '600', 'track' => '0.01em',
                          'heading' => 'Georgia, "Iowan Old Style", "Times New Roman", serif',
                          'body' => 'Georgia, "Iowan Old Style", "Times New Roman", serif',
                          'hero' => 'clamp(2.6rem, 5.4vw, 4.2rem)', 'pad' => '100px'],
            'bold'    => ['radius' => '18px', 'radiusLg' => '28px', 'weight' => '800', 'track' => '-0.035em',
                          'heading' => 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
                          'body' => 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
                          'hero' => 'clamp(2.9rem, 6.4vw, 5rem)', 'pad' => '92px'],
            'playful' => ['radius' => '22px', 'radiusLg' => '34px', 'weight' => '700', 'track' => '-0.01em',
                          'heading' => '"Trebuchet MS", "Segoe UI", system-ui, sans-serif',
                          'body' => '"Trebuchet MS", "Segoe UI", system-ui, sans-serif',
                          'hero' => 'clamp(2.5rem, 5.6vw, 4rem)', 'pad' => '88px'],
            default   => ['radius' => '14px', 'radiusLg' => '22px', 'weight' => '700', 'track' => '-0.02em',
                          'heading' => 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", sans-serif',
                          'body' => 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", sans-serif',
                          'hero' => 'clamp(2.6rem, 5.6vw, 4.4rem)', 'pad' => '90px'],
        };

        // --- Per-dimension overrides on top of the style preset ------------

        $typeface = $spec['typeface'] ?? 'auto';
        if ($typeface !== 'auto') {
            $stack = match ($typeface) {
                'serif'     => 'Georgia, "Iowan Old Style", "Times New Roman", serif',
                'mono'      => 'ui-monospace, "SF Mono", Menlo, Consolas, "Courier New", monospace',
                'rounded'   => '"Trebuchet MS", "Segoe UI", Verdana, system-ui, sans-serif',
                'condensed' => '"Arial Narrow", "Helvetica Neue Condensed", Impact, system-ui, sans-serif',
                default     => '"Helvetica Neue", Helvetica, Arial, system-ui, sans-serif',
            };
            $style['heading'] = $stack;
            $style['body'] = $typeface === 'condensed'
                ? 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif'   // keep body readable
                : $stack;
        }

        $scale = max(0.8, min(1.4, (float) ($spec['type_scale'] ?? 1.0)));
        if (abs($scale - 1.0) > 0.01) {
            $style['hero'] = sprintf(
                'clamp(%.2frem, %.2fvw, %.2frem)',
                2.6 * $scale,
                5.6 * $scale,
                4.4 * $scale
            );
        }

        $style['pad'] = match ($spec['density'] ?? 'comfortable') {
            'compact' => (int) round((int) rtrim($style['pad'], 'px') * 0.62) . 'px',
            'airy'    => (int) round((int) rtrim($style['pad'], 'px') * 1.35) . 'px',
            default   => $style['pad'],
        };

        [$style['radius'], $style['radiusLg']] = match ($spec['radius'] ?? 'default') {
            'sharp'   => ['0px', '0px'],
            'rounded' => ['18px', '30px'],
            'pill'    => ['999px', '32px'],
            default   => [$style['radius'], $style['radiusLg']],
        };

        $maxWidth = match ($spec['width'] ?? 'default') {
            'narrow' => '980px',
            'wide'   => '1400px',
            default  => '1160px',
        };

        return array_merge($style, [
            'primary'     => $primary,
            'primaryDark' => $palette['dark'],
            'accent'      => $palette['accent'],
            'bg'          => $dark ? '#0b1020' : '#ffffff',
            'surface'     => $dark ? '#141c33' : '#f7f9fc',
            'surfaceAlt'  => $dark ? '#1b2543' : '#eef2f9',
            'border'      => $dark ? '#26304f' : '#e3e9f2',
            'ink'         => $dark ? '#f8fafc' : '#0f172a',
            'text'        => $dark ? '#aab6d4' : '#4a5568',
            'onPrimary'   => '#ffffff',
            'footerBg'    => $dark ? '#070b16' : '#0f172a',
            'maxWidth'    => $maxWidth,
            'motion'      => $spec['motion'] ?? 'subtle',
            'buttonShape' => $spec['button_shape'] ?? 'default',
        ]);
    }
}
