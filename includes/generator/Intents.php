<?php
declare(strict_types=1);

namespace SiteForge;

/**
 * Turns a free-form instruction into concrete changes to a site spec.
 *
 * Rather than matching a fixed list of phrasings, every capability declares a
 * broad set of trigger words. The matcher normalises the instruction, scores
 * each capability, understands negation ("remove the slider", "less motion")
 * and tolerates typos, so unseen wordings still land on the right handler.
 *
 * Several intents can fire from one sentence — "make it dark and add a
 * slider" applies both.
 */
final class Intents
{
    /** Words that flip an intent into its "remove/less" form. */
    private const NEGATIONS = [
        'no', 'not', 'none', 'never', 'remove', 'removing', 'delete', 'drop', 'hide',
        'without', 'lose', 'kill', 'stop', 'disable', 'turn off', 'get rid of',
        'take out', 'take off', 'less', 'fewer', 'reduce', 'minus', 'dont', 'do not',
        'cut', 'strip', 'clear',
    ];

    /**
     * Capability table. Each entry: triggers => the words that select it.
     * Handling lives in applyIntent() so the logic stays readable.
     *
     * @return array<string, array<int,string>>
     */
    private static function table(): array
    {
        return [

        // --- Motion & interactivity -----------------------------------------
        'motion.up' => ['dynamic', 'animated', 'animation', 'animations', 'motion',
            'lively', 'interactive', 'movement', 'moving', 'engaging', 'eye catching',
            'eyecatching', 'flashy', 'fancy', 'alive', 'wow factor', 'punchier',
            'more life', 'liven', 'spice', 'jazz', 'transitions', 'effects', 'snappy',
            'micro interactions', 'microinteractions'],
        'motion.down' => ['static', 'still', 'calm down', 'calmer', 'no motion',
            'motionless', 'plain', 'boring', 'settle down', 'tone it down', 'tone down',
            'quiet', 'quieter', 'less busy', 'too busy', 'distracting'],

        // --- Components -----------------------------------------------------
        'comp.slider'     => ['slider', 'carousel', 'slideshow', 'slide show', 'rotator', 'swiper'],
        'comp.marquee'    => ['marquee', 'ticker', 'scrolling text', 'scrolling banner', 'running text'],
        'comp.parallax'   => ['parallax', 'depth effect', 'layered scroll'],
        'comp.sticky_cta' => ['sticky button', 'floating button', 'sticky cta', 'floating cta',
            'sticky call to action', 'floating call to action', 'always visible button'],
        'comp.back_to_top'=> ['back to top', 'scroll to top', 'top button', 'return to top'],
        'comp.theme_toggle' => ['dark mode toggle', 'theme toggle', 'theme switch', 'dark mode switch',
            'light dark toggle', 'mode switcher', 'toggle dark'],
        'comp.newsletter' => ['newsletter', 'subscribe', 'mailing list', 'email signup',
            'email sign up', 'sign up form', 'signup form'],
        'comp.social'     => ['social links', 'social media', 'social icons', 'socials',
            'instagram', 'facebook', 'twitter', 'linkedin', 'tiktok', 'youtube'],
        'comp.map'        => ['map', 'google map', 'location map', 'directions'],
        'comp.hours'      => ['opening hours', 'opening times', 'business hours', 'trading hours',
            'when we are open', 'hours section'],
        'comp.logos'      => ['client logos', 'logo strip', 'logos', 'brands we work with',
            'as seen in', 'trusted by', 'partner logos', 'partners strip'],
        'comp.process'    => ['how it works', 'process', 'steps', 'our process', 'the steps',
            'step by step', 'workflow'],
        'comp.timeline'   => ['timeline', 'milestones', 'our history', 'our journey', 'history section'],
        'comp.search'     => ['search bar', 'search box', 'search field'],
        'comp.breadcrumbs'=> ['breadcrumb', 'breadcrumbs'],
        'comp.cookie'     => ['cookie banner', 'cookie notice', 'cookie consent', 'gdpr banner'],
        'comp.chat'       => ['whatsapp button', 'chat button', 'live chat', 'chat bubble', 'whatsapp'],

        // --- Sections (rendered by the existing section renderers) -----------
        'sec.gallery'      => ['gallery', 'photo grid', 'photos section', 'image grid', 'lookbook', 'portfolio grid'],
        'sec.pricing'      => ['pricing', 'prices', 'price list', 'packages', 'plans', 'rates', 'price table'],
        'sec.testimonials' => ['testimonial', 'testimonials', 'reviews', 'what clients say', 'feedback section', 'quotes'],
        'sec.faq'          => ['faq', 'faqs', 'questions', 'q and a', 'common questions'],
        'sec.team'         => ['team', 'staff', 'our people', 'meet the team', 'team section', 'bios'],
        'sec.stats'        => ['stats', 'statistics', 'numbers', 'figures', 'key numbers', 'achievements'],
        'sec.cta'          => ['call to action', 'cta band', 'cta section'],
        'sec.about'        => ['about section', 'about us section', 'our story'],
        'sec.contact'      => ['contact section', 'contact form', 'enquiry form', 'get in touch section'],

        // --- Theme ----------------------------------------------------------
        'theme.dark'  => ['dark mode', 'dark theme', 'make it dark', 'darker', 'night mode',
            'black background', 'dark background', 'go dark'],
        'theme.light' => ['light mode', 'light theme', 'make it light', 'lighter', 'brighter',
            'white background', 'light background', 'go light'],

        // --- Layout ---------------------------------------------------------
        'hero.centred' => ['centred hero', 'centered hero', 'center the hero', 'centre the hero',
            'stack the hero', 'stacked hero', 'image below the text', 'image underneath',
            'hero in the middle', 'centred header', 'centered banner'],
        'hero.full'    => ['full screen hero', 'fullscreen hero', 'full width hero', 'big hero',
            'hero image background', 'full bleed hero', 'giant hero'],
        'hero.split'   => ['split hero', 'image beside', 'image next to', 'side by side hero'],
        'hero.flip'    => ['flip the hero', 'swap the hero', 'image on the left', 'image on the right',
            'other side', 'mirror the hero'],

        'width.wide'   => ['wider', 'wider layout', 'full width', 'more width', 'stretch it', 'edge to edge'],
        'width.narrow' => ['narrower', 'narrow layout', 'less width', 'tighter layout', 'slimmer'],

        'density.compact' => ['compact', 'tighter', 'denser', 'less spacing', 'less padding',
            'less white space', 'less whitespace', 'condensed spacing', 'closer together', 'too much space'],
        'density.airy'    => ['airy', 'more spacing', 'more padding', 'roomier', 'breathing room',
            'more white space', 'more whitespace', 'spacious', 'spread out'],

        'radius.sharp'   => ['sharp corners', 'square corners', 'straight corners', 'no rounding',
            'square buttons', 'squared buttons', 'square the buttons', 'squarer',
            'sharper', 'boxy', 'angular'],
        'radius.rounded' => ['rounded corners', 'rounder', 'softer corners', 'round the corners', 'soft corners'],
        'radius.pill'    => ['pill buttons', 'pill shaped', 'fully rounded', 'capsule buttons'],

        // --- Typography -----------------------------------------------------
        'type.serif'     => ['serif', 'serif font', 'times', 'georgia', 'classic font', 'editorial font'],
        'type.sans'      => ['sans serif', 'sans-serif', 'clean font', 'modern font', 'helvetica', 'arial'],
        'type.mono'      => ['monospace', 'mono font', 'code font', 'typewriter'],
        // 'rounder font' has to be listed in full: bare 'rounder' belongs to
        // radius.rounded, and the longer phrase is what outranks it.
        'type.rounded'   => ['rounded font', 'friendly font', 'soft font',
            'rounder font', 'rounder type', 'rounder typeface'],
        'type.condensed' => ['condensed font', 'narrow font', 'tall font', 'compressed type',
            'condensed type', 'condensed', 'narrow type'],
        'type.bigger'    => ['bigger text', 'larger text', 'bigger headings', 'larger headings',
            'bigger type', 'increase font', 'bigger font'],
        'type.smaller'   => ['smaller text', 'smaller headings', 'smaller type', 'reduce font', 'smaller font'],

        // --- Buttons --------------------------------------------------------
        'btn.outline' => ['outline buttons', 'outlined buttons', 'ghost buttons', 'hollow buttons', 'bordered buttons'],
        'btn.solid'   => ['solid buttons', 'filled buttons', 'block buttons'],
        'btn.gradient'=> ['gradient buttons', 'gradient', 'gradients', 'colourful buttons', 'colorful buttons'],

        // --- Palette intensity ----------------------------------------------
        // Named colours ("make it green") are handled as literals; these are
        // the requests that say how strong the colour should be, not which.
        'palette.vivid' => ['more colourful', 'more colorful', 'more color', 'more colour',
            'brighter colours', 'brighter colors', 'more vibrant', 'vibrant', 'punchier colours',
            'punchier colors', 'more saturated', 'less dull', 'not so dull', 'add some colour',
            'add some color', 'needs colour', 'needs color'],
        'palette.muted' => ['less colourful', 'less colorful', 'muted colours', 'muted colors',
            'more muted', 'tone the colours down', 'tone the colors down', 'less saturated',
            'softer colours', 'softer colors', 'too colourful', 'too colorful'],

        // --- Style ----------------------------------------------------------
        'style.minimal' => ['minimal', 'minimalist', 'clean', 'simple', 'understated', 'stripped back', 'plainer'],
        'style.elegant' => ['elegant', 'luxury', 'luxurious', 'classy', 'sophisticated', 'refined', 'upscale', 'premium', 'high end'],
        'style.bold'    => ['bold', 'striking', 'loud', 'strong', 'impactful', 'confident', 'heavy'],
        'style.playful' => ['playful', 'fun', 'friendly', 'quirky', 'cheerful', 'casual', 'informal'],
        'style.modern'  => ['modern', 'sleek', 'contemporary', 'current', 'up to date', 'fresh',
            'modernise', 'modernize', 'update the look', 'bring it up to date'],

        // --- Tone of the copy ------------------------------------------------
        'tone.friendly' => ['friendlier', 'warmer', 'friendly tone', 'warm tone', 'conversational',
            'approachable', 'less formal', 'chattier'],
        'tone.formal'   => ['more formal', 'formal tone', 'professional tone', 'corporate tone',
            'serious tone', 'business like'],
        'tone.punchy'   => ['punchier copy', 'shorter copy', 'snappier', 'more direct', 'to the point', 'concise'],
        'tone.detailed' => ['more detail', 'longer copy', 'more text', 'more information', 'expand the copy', 'wordier'],

        // --- Whole-site refresh ---------------------------------------------
        'general.better' => ['better', 'nicer', 'improve', 'improved', 'polish', 'nicer looking',
            'more professional', 'more attractive', 'prettier', 'smarter', 'upgrade the design',
            'redesign', 'refresh', 'more appealing', 'good looking', 'more impressive',
            'pop', 'make it pop', 'stand out', 'more exciting', 'spice it up', 'jazz it up'],
        ];
    }

    /**
     * @param bool $skipGeneral when reading an initial brief, ignore the catch-all
     *                          "make it better" intent — words like "professional"
     *                          are describing the business, not requesting a redesign.
     * @return array{0:array,1:array<int,string>,2:bool} spec, human-readable changes, matchedAnything
     */
    public static function apply(array $spec, string $instruction, bool $skipGeneral = false): array
    {
        $spec = self::withDefaults($spec);
        $raw = trim($instruction);
        $norm = self::normalise($raw);
        $changes = [];

        // 1. Precise, value-carrying instructions first.
        $changes = array_merge($changes, self::applyLiterals($spec, $raw, $norm));

        // 2. Collect every capability the wording could refer to.
        $candidates = [];
        foreach (self::table() as $id => $triggers) {
            if ($skipGeneral && str_starts_with($id, 'general.')) {
                continue;
            }
            $hit = self::bestTrigger($norm, $triggers);
            if ($hit !== null) {
                $candidates[] = $hit + ['id' => $id];
            }
        }

        // 3. Longest phrase wins where two capabilities overlap, so
        //    "dark mode toggle" adds the toggle instead of going dark.
        usort($candidates, static fn ($a, $b) => $b['rank'] <=> $a['rank']);

        $taken = [];
        $accepted = [];
        foreach ($candidates as $candidate) {
            $from = $candidate['at'];
            $to = $from + $candidate['len'];

            foreach ($taken as [$tf, $tt]) {
                if ($from < $tt && $to > $tf) {
                    continue 2; // overlaps a longer, already-accepted phrase
                }
            }
            $taken[] = [$from, $to];
            $accepted[] = $candidate;
        }

        // 4. Nothing landed at all? Only now risk a fuzzy guess, so a precise
        //    instruction is never polluted by a near-miss on another word.
        if ($accepted === [] && $changes === []) {
            $guess = self::fuzzyPass($norm, $skipGeneral);
            if ($guess !== null) {
                $accepted[] = $guess;
            }
        }

        foreach ($accepted as $candidate) {
            $negated = self::isNegated($norm, $candidate['at']);
            $note = self::applyIntent($spec, $candidate['id'], $negated, $norm);
            if ($note !== null) {
                $changes[] = $note;
            }
        }

        // 5. Section ordering ("put testimonials first", "move faq above pricing").
        $ordering = self::applyOrdering($spec, $norm);
        $changes = array_merge($changes, $ordering);

        // The third value says whether we *understood* the instruction, which
        // is different from whether anything changed: "remove the slider" on a
        // site with no slider is understood, but is a no-op.
        $understood = $accepted !== [] || $changes !== [] || $ordering !== [];

        return [$spec, array_values(array_filter($changes)), $understood];
    }

    /** Every dimension an edit may touch, with a safe default for older specs. */
    public static function withDefaults(array $spec): array
    {
        return $spec + [
            'motion'      => 'subtle',
            'density'     => 'comfortable',
            'radius'      => 'default',
            'width'       => 'default',
            'typeface'    => 'auto',
            'type_scale'  => 1.0,
            'button_shape'=> 'default',
            'hero_layout' => 'split',
            'hero_flip'   => false,
            'tone'        => 'neutral',
            'components'  => [],
        ];
    }

    // --- Normalising & matching --------------------------------------------

    private static function normalise(string $text): string
    {
        $text = mb_strtolower($text);
        $text = str_replace(['’', '‘', '“', '”', '–', '—'], ["'", "'", '"', '"', '-', '-'], $text);
        $text = str_replace(["don't", "doesn't", "can't", "won't", "isn't"], ['dont', 'doesnt', 'cant', 'wont', 'isnt'], $text);
        $text = preg_replace('/[^a-z0-9#\'\s-]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return ' ' . trim($text) . ' ';
    }

    /**
     * The longest trigger this instruction contains, with where it sits.
     *
     * @return array{phrase:string,at:int,len:int}|null
     */
    private static function bestTrigger(string $norm, array $triggers): ?array
    {
        $best = null;

        foreach ($triggers as $trigger) {
            $hit = self::matchTrigger($norm, $trigger);
            if ($hit !== null && ($best === null || $hit["rank"] > $best["rank"])) {
                $best = $hit;
            }
        }

        return $best;
    }

    /**
     * Match one trigger phrase. Exact wins; failing that, a multi-word trigger
     * still matches when all its words appear close together in any order —
     * "the buttons should be outlined" finds "outlined buttons".
     *
     * @return array{phrase:string,at:int,len:int}|null
     */
    private static function matchTrigger(string $norm, string $trigger): ?array
    {
        // Exact phrase, tolerating a plural/verb ending on the last word.
        // 'len' is the span the phrase actually occupies — it must not include
        // the surrounding spaces, or two adjacent phrases look like they
        // overlap and the second one gets thrown away.
        // 'er'/'r' catch the comparatives people actually type — "bolder",
        // "cleaner", "simpler" — without needing a row per adjective.
        foreach ([' ', 's ', 'es ', 'd ', 'ed ', 'ing ', 'er ', 'r '] as $suffix) {
            $at = strpos($norm, ' ' . $trigger . $suffix);
            if ($at !== false) {
                return [
                    'phrase' => $trigger,
                    'at'     => $at + 1,
                    'len'    => strlen($trigger),
                    'rank'   => strlen($trigger) + 2, // exact beats a same-length loose match
                ];
            }
        }

        if (!str_contains($trigger, ' ')) {
            return null;
        }

        // Same words, any order, within a short span.
        $words = explode(' ', $trigger);
        $first = null;
        $last = null;

        foreach ($words as $word) {
            $at = false;
            foreach ([' ', 's ', 'es ', 'ed ', 'ing '] as $suffix) {
                $at = strpos($norm, ' ' . $word . $suffix);
                if ($at !== false) {
                    break;
                }
            }
            if ($at === false) {
                return null;
            }
            $first = $first === null ? $at : min($first, $at);
            $last = $last === null ? $at + strlen($word) : max($last, $at + strlen($word));
        }

        // Reject "add a slider" matching "image on the left" style coincidences.
        if ($last - $first > strlen($trigger) + 24) {
            return null;
        }

        return [
            'phrase' => $trigger,
            'at'     => $first + 1,
            'len'    => max(1, $last - $first),
            'rank'   => strlen($trigger),
        ];
    }

    /**
     * Last resort for typos — "annimation", "gallry", "testimonails".
     * Deliberately strict, and only reached when nothing else matched.
     *
     * @return array{phrase:string,at:int,len:int,id:string}|null
     */
    private static function fuzzyPass(string $norm, bool $skipGeneral): ?array
    {
        $words = array_values(array_filter(
            preg_split('/\s+/', trim($norm)) ?: [],
            static fn ($w) => mb_strlen($w) >= 5
        ));
        if ($words === []) {
            return null;
        }

        foreach (self::table() as $id => $triggers) {
            if ($skipGeneral && str_starts_with($id, 'general.')) {
                continue;
            }
            foreach ($triggers as $trigger) {
                if (str_contains($trigger, ' ') || mb_strlen($trigger) < 6) {
                    continue;
                }
                foreach ($words as $word) {
                    $tolerance = mb_strlen($trigger) >= 8 ? 2 : 1;
                    if (abs(mb_strlen($word) - mb_strlen($trigger)) > $tolerance) {
                        continue;
                    }
                    if (levenshtein($word, $trigger) <= $tolerance) {
                        $at = strpos($norm, ' ' . $word . ' ');
                        return [
                            'phrase' => $trigger,
                            'at' => $at === false ? 1 : $at + 1,
                            'len' => strlen($trigger), 'rank' => strlen($trigger),
                            'id' => $id,
                        ];
                    }
                }
            }
        }

        return null;
    }

    /** Is the trigger preceded by a negation within the same clause? */
    private static function isNegated(string $norm, int $at): bool
    {
        $before = substr($norm, max(0, $at - 42), min(42, $at));

        // A new clause resets the context: "add a slider, no gallery".
        $clause = strrchr($before, ',');
        if ($clause !== false) {
            $before = $clause;
        }

        foreach (self::NEGATIONS as $word) {
            if (str_contains($before, ' ' . $word . ' ')) {
                return true;
            }
        }

        return false;
    }

    // --- Handlers -----------------------------------------------------------

    private static function applyIntent(array &$spec, string $id, bool $negated, string $norm): ?string
    {
        [$group, $key] = explode('.', $id, 2);

        switch ($group) {
            case 'motion':
                $want = ($key === 'up') !== $negated ? 'dynamic' : 'none';
                if ($spec['motion'] === $want) {
                    return null;
                }
                $spec['motion'] = $want;
                return $want === 'dynamic'
                    ? 'made it more dynamic — scroll animations, hover motion, parallax and animated counters'
                    : 'stripped the animations back to a static page';

            case 'comp':
                $note = self::toggleList($spec, 'components', $key, $negated, self::componentLabel($key));
                // Parallax is pointless on a page with motion switched off.
                if ($note !== null && $key === 'parallax' && !$negated && ($spec['motion'] ?? '') === 'none') {
                    $spec['motion'] = 'subtle';
                }
                return $note;

            case 'sec':
                return self::toggleList($spec, 'sections', $key, $negated, $key . ' section');

            case 'theme':
                $dark = ($key === 'dark') !== $negated;
                if (($spec['dark'] ?? false) === $dark) {
                    return null;
                }
                $spec['dark'] = $dark;
                return $dark ? 'switched to a dark theme' : 'switched to a light theme';

            case 'hero':
                if ($key === 'flip') {
                    $spec['hero_flip'] = !($spec['hero_flip'] ?? false);
                    return 'flipped the hero image to the other side';
                }
                if ($negated) {
                    return null;
                }
                if (($spec['hero_layout'] ?? 'split') === $key) {
                    return null;
                }
                $spec['hero_layout'] = $key;
                return match ($key) {
                    'centred' => 'centred the hero',
                    'full'    => 'made the hero a full-height banner',
                    default   => 'set the hero back to a split layout',
                };

            case 'width':
                $want = $negated ? 'default' : $key;
                if (($spec['width'] ?? 'default') === $want) {
                    return null;
                }
                $spec['width'] = $want;
                return match ($want) {
                    'wide'   => 'widened the layout',
                    'narrow' => 'narrowed the layout',
                    default  => 'reset the layout to the standard width',
                };

            case 'density':
                $want = $negated ? 'comfortable' : $key;
                if (($spec['density'] ?? 'comfortable') === $want) {
                    return null;
                }
                $spec['density'] = $want;
                return match ($want) {
                    'compact' => 'tightened up the spacing',
                    'airy'    => 'opened up the spacing',
                    default   => 'reset the spacing',
                };

            case 'radius':
                $want = $negated ? 'default' : $key;
                if (($spec['radius'] ?? 'default') === $want) {
                    return null;
                }
                $spec['radius'] = $want;
                return match ($want) {
                    'sharp'   => 'squared off the corners',
                    'rounded' => 'rounded the corners',
                    'pill'    => 'made buttons and cards pill-shaped',
                    default   => 'reset the corner radius',
                };

            case 'type':
                if ($key === 'bigger' || $key === 'smaller') {
                    $step = ($key === 'bigger') !== $negated ? 0.12 : -0.12;
                    $spec['type_scale'] = max(0.8, min(1.4, (float) ($spec['type_scale'] ?? 1.0) + $step));
                    return $step > 0 ? 'increased the type size' : 'reduced the type size';
                }
                if ($negated) {
                    return null;
                }
                if (($spec['typeface'] ?? 'auto') === $key) {
                    return null;
                }
                $spec['typeface'] = $key;
                return 'switched to a ' . $key . ' typeface';

            case 'btn':
                $want = $negated ? 'default' : $key;
                if (($spec['button_shape'] ?? 'default') === $want) {
                    return null;
                }
                $spec['button_shape'] = $want;
                return match ($want) {
                    'outline'  => 'made the buttons outlined',
                    'solid'    => 'made the buttons solid',
                    'gradient' => 'gave the buttons a gradient',
                    default    => 'reset the button style',
                };

            case 'palette':
                // There's no saturation dial in the renderer — intensity is
                // expressed by which palette we land on, so move to one that
                // reads the way they asked and isn't the one they already have.
                $vivid = ['violet', 'crimson', 'ember', 'rose', 'teal'];
                $muted = ['slate', 'sand', 'forest', 'ocean'];
                if ($negated) {
                    return null;
                }
                $wanted = $key === 'vivid' ? $vivid : $muted;
                $current = (string) ($spec['palette'] ?? '');
                if (in_array($current, $wanted, true)) {
                    return null;   // already reads that way; don't churn it
                }
                $spec['palette'] = $wanted[0];
                unset($spec['custom_primary']);   // a hand-picked colour would override the swap
                return $key === 'vivid'
                    ? 'brightened the palette to ' . $wanted[0]
                    : 'toned the palette down to ' . $wanted[0];

            case 'style':
                if ($negated || ($spec['style'] ?? '') === $key) {
                    return null;
                }
                $spec['style'] = $key;
                return 'restyled it to feel more ' . $key;

            case 'tone':
                if ($negated || ($spec['tone'] ?? 'neutral') === $key) {
                    return null;
                }
                $spec['tone'] = $key;
                return 'rewrote the copy in a ' . $key . ' tone';

            case 'general':
                // "make it better" — nudge everything a notch rather than refuse.
                $spec['motion'] = 'dynamic';
                $notes = ['turned the motion up'];
                if (!in_array('logos', $spec['components'] ?? [], true)) {
                    $spec['components'][] = 'logos';
                    $notes[] = 'added a trust strip';
                }
                if (!in_array('testimonials', $spec['sections'] ?? [], true)) {
                    $spec['sections'][] = 'testimonials';
                    $notes[] = 'added testimonials';
                }
                if (($spec['density'] ?? '') !== 'airy') {
                    $spec['density'] = 'airy';
                    $notes[] = 'gave the sections more room';
                }
                return 'polished the design — ' . implode(', ', $notes);
        }

        return null;
    }

    /** Add or remove a key from one of the spec's list fields. */
    private static function toggleList(array &$spec, string $field, string $key, bool $remove, string $label): ?string
    {
        $list = $spec[$field] ?? [];

        if ($remove) {
            if (!in_array($key, $list, true)) {
                return null;
            }
            $spec[$field] = array_values(array_diff($list, [$key]));
            return 'removed the ' . $label;
        }

        if (in_array($key, $list, true)) {
            return null;
        }
        $list[] = $key;
        $spec[$field] = array_values(array_unique($list));

        // "added social links", not "added a social links"; and quotes must not
        // be mistaken for a vowel in `"how it works" section`.
        $clean = trim($label, '"\'');
        $plural = str_ends_with($clean, 's') && !str_ends_with($clean, 'ss');
        $article = $plural ? '' : (preg_match('/^[aeiou]/i', $clean) ? 'an ' : 'a ');

        return 'added ' . $article . $label;
    }

    private static function componentLabel(string $key): string
    {
        return match ($key) {
            'slider'      => 'image slider',
            'marquee'     => 'scrolling ticker',
            'parallax'    => 'parallax effect',
            'sticky_cta'  => 'floating call-to-action button',
            'back_to_top' => 'back-to-top button',
            'theme_toggle'=> 'dark-mode toggle',
            'newsletter'  => 'newsletter signup',
            'social'      => 'social links',
            'map'         => 'location map',
            'hours'       => 'opening hours panel',
            'logos'       => 'client logo strip',
            'process'     => '"how it works" section',
            'timeline'    => 'timeline',
            'search'      => 'search box',
            'breadcrumbs' => 'breadcrumb trail',
            'cookie'      => 'cookie notice',
            'chat'        => 'chat button',
            default       => str_replace('_', ' ', $key),
        };
    }

    // --- Literal, value-carrying instructions -------------------------------

    /** @return array<int,string> */
    private static function applyLiterals(array &$spec, string $raw, string $norm): array
    {
        $changes = [];

        if (preg_match('/(?:rename(?:\s+(?:it|this|the site|the business))?\s+to|call it|change the (?:name|title|brand)(?:\s+of the site)?\s+to)\s+["\x{201C}]?([^"\x{201D}.\n]{2,50})["\x{201D}]?/iu', $raw, $m)) {
            $new = trim($m[1]);
            if ($new !== '') {
                $spec['name'] = $new;
                $spec['contact']['email'] = 'hello@' . preg_replace('/[^a-z0-9]/', '', mb_strtolower($new)) . '.com';
                $changes[] = 'renamed the site to "' . $new . '"';
            }
        }

        if (preg_match('/(?:headline|hero (?:title|heading)|main heading|h1|title)\s*(?:should (?:be|say)|to say|to|:)\s*["\x{201C}]?([^"\x{201D}\n]{3,120})["\x{201D}]?/iu', $raw, $m)) {
            $spec['hero']['title'] = trim($m[1], " \t.\"'");
            $changes[] = 'changed the hero headline';
        }

        if (preg_match('/(?:tagline|subtitle|subheading|strapline|sub heading)\s*(?:should (?:be|say)|to say|to|:)\s*["\x{201C}]?([^"\x{201D}\n]{3,160})["\x{201D}]?/iu', $raw, $m)) {
            $spec['hero']['sub'] = trim($m[1], " \t\"'");
            $changes[] = 'changed the hero subheading';
        }

        if (preg_match('/(?:button|cta|call to action)\s*(?:should (?:be|say)|to say|text to|label to|to)\s*["\x{201C}]?([^"\x{201D}\n]{2,40})["\x{201D}]?/iu', $raw, $m)) {
            $spec['hero']['cta'] = trim($m[1], " \t.\"'");
            $changes[] = 'changed the button text';
        }

        if (preg_match('/replace\s+["\x{201C}]([^"\x{201D}]+)["\x{201D}]\s+with\s+["\x{201C}]([^"\x{201D}]+)["\x{201D}]/iu', $raw, $m)) {
            array_walk_recursive($spec, static function (&$v) use ($m): void {
                if (is_string($v)) {
                    $v = str_ireplace($m[1], $m[2], $v);
                }
            });
            $changes[] = 'replaced "' . $m[1] . '" with "' . $m[2] . '"';
        }

        if (preg_match('/#([0-9a-f]{6})\b/i', $raw, $m)) {
            $spec['custom_primary'] = '#' . strtolower($m[1]);
            $changes[] = 'set the primary colour to #' . strtolower($m[1]);
        } else {
            $palette = Blueprint::detectPalette($norm, '');
            if ($palette !== '' && $palette !== ($spec['palette'] ?? '')) {
                $spec['palette'] = $palette;
                $changes[] = 'switched the palette to ' . $palette;
            }
        }

        if (preg_match('/\b([\w.+-]+@[\w-]+\.[\w.-]+)\b/', $raw, $m)) {
            $spec['contact']['email'] = $m[1];
            $changes[] = 'updated the contact email';
        }
        if (preg_match('/(?:phone|tel|call us|number)\D{0,12}([+\d][\d\s().-]{6,20})/i', $raw, $m)) {
            $spec['contact']['phone'] = trim($m[1]);
            $changes[] = 'updated the phone number';
        }
        if (preg_match('/(?:address|located at|find us at|based (?:in|at))\s*(?:is|:)?\s*([^.\n]{6,120})/i', $raw, $m)) {
            $spec['contact']['address'] = trim($m[1]);
            $changes[] = 'updated the address';
        }

        return $changes;
    }

    // --- Section ordering ----------------------------------------------------

    /** @return array<int,string> */
    private static function applyOrdering(array &$spec, string $norm): array
    {
        $names = [
            'hero' => 'hero', 'about' => 'about', 'services' => 'services', 'gallery' => 'gallery',
            'pricing' => 'pricing', 'testimonials' => 'testimonials', 'reviews' => 'testimonials',
            'faq' => 'faq', 'team' => 'team', 'stats' => 'stats', 'contact' => 'contact',
        ];
        $alt = implode('|', array_keys($names));

        // "move X above/below Y"
        if (preg_match('/(?:move|put|place)\s+(?:the\s+)?(' . $alt . ')\s+(above|below|before|after|under|over)\s+(?:the\s+)?(' . $alt . ')/', $norm, $m)) {
            $from = $names[$m[1]];
            $target = $names[$m[3]];
            $after = in_array($m[2], ['below', 'after', 'under'], true);

            $order = array_values(array_diff($spec['sections'] ?? [], [$from]));
            $at = array_search($target, $order, true);
            if ($at !== false) {
                array_splice($order, $after ? $at + 1 : $at, 0, [$from]);
                $spec['sections'] = $order;
                return ['moved ' . $from . ' ' . ($after ? 'below ' : 'above ') . $target];
            }
        }

        // "put X first / at the top", "X last / at the bottom"
        if (preg_match('/(?:move|put|place)?\s*(?:the\s+)?(' . $alt . ')\s+(?:section\s+)?(first|last|at the top|at the bottom|to the top|to the bottom)/', $norm, $m)) {
            $key = $names[$m[1]];
            $toTop = in_array($m[2], ['first', 'at the top', 'to the top'], true);

            $order = array_values(array_diff($spec['sections'] ?? [], [$key, 'hero']));
            $order = $toTop ? array_merge([$key], $order) : array_merge($order, [$key]);
            $spec['sections'] = array_merge(['hero'], $order);

            return ['moved ' . $key . ' to the ' . ($toTop ? 'top' : 'bottom')];
        }

        return [];
    }
}
