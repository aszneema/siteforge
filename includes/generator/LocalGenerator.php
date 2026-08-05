<?php
declare(strict_types=1);

namespace SiteForge;

use RuntimeException;

/**
 * Builds sites without an API key.
 *
 * It reads the brief, works out what kind of business it is, picks a palette
 * and a set of sections, and renders a complete site from the copy bank. Edits
 * are applied to the stored spec and the whole site is re-rendered, so changes
 * compose instead of stacking up as patches.
 */
final class LocalGenerator implements Generator
{
    public function id(): string
    {
        return 'local';
    }

    public function label(): string
    {
        return 'Offline builder';
    }

    public function create(array $ctx): Result
    {
        $progress = $ctx['progress'];

        $progress(20, 'Reading your brief');
        $spec = Blueprint::analyse($ctx['brief'], $ctx['site_type'], $ctx['name'], $ctx['images']);

        $progress(45, 'Choosing a palette and layout');
        $progress(65, 'Writing pages');
        $files = $this->renderFiles($spec, $ctx['site_type']);

        $progress(85, 'Adding styles and scripts');
        foreach ($ctx['images'] as $image) {
            $files[$image['path']] = $image['contents'];
        }

        return new Result($files, $this->describe($spec, $ctx['site_type']), $spec);
    }

    public function edit(array $ctx): Result
    {
        $progress = $ctx['progress'];
        $progress(25, 'Reading your change');

        // A site built by Claude has no spec, so there is nothing to re-render
        // from. Fall back to what can be done safely on the raw files.
        if (empty($ctx['spec'])) {
            return $this->editRawFiles($ctx);
        }

        [$spec, $changes, $understood] = Blueprint::applyEdit($ctx['spec'], $ctx['instruction']);

        if ($changes === []) {
            $current = Intents::withDefaults($ctx['spec']);

            throw new RuntimeException($understood
                ? 'That is already how this site is set up, so there was nothing to change. '
                  . 'Try something else, or tell me what you would like different.'
                : $this->cannotUnderstand($ctx['instruction'], $current));
        }

        $progress(60, 'Rebuilding the site');
        $spec['images'] = array_values(array_unique(array_merge(
            $spec['images'] ?? [],
            array_map(static fn ($i) => $i['path'], $ctx['images'])
        )));

        $files = $this->renderFiles($spec, $ctx['site_type']);

        $progress(85, 'Writing files');
        foreach ($ctx['files'] as $path => $contents) {
            // Keep any images already in the project.
            if (preg_match('/\.(png|jpe?g|gif|webp|ico|svg)$/i', $path) && !isset($files[$path])) {
                $files[$path] = $contents;
            }
        }
        foreach ($ctx['images'] as $image) {
            $files[$image['path']] = $image['contents'];
        }

        $summary = 'Updated your site: ' . $this->joinChanges($changes) . '.';

        return new Result($files, $summary, $spec);
    }

    /** @return array<string,string> */
    private function renderFiles(array $spec, string $siteType): array
    {
        return $siteType === 'bootstrap'
            ? (new BootstrapRenderer($spec))->render()
            : (new Renderer($spec, $siteType))->render();
    }

    private function describe(array $spec, string $siteType): string
    {
        $sections = array_values(array_diff($spec['sections'], ['hero']));
        $list = $this->joinChanges(array_map(
            static fn ($s) => match ($s) {
                'cta' => 'call-to-action band',
                'faq' => 'FAQ',
                default => $s,
            },
            $sections
        ));

        return sprintf(
            'Built a %s %s site for "%s" in a %s %s palette, with a hero plus %s.',
            $spec['style'],
            site_type_label($siteType),
            $spec['name'],
            $spec['dark'] ? 'dark' : 'light',
            $spec['palette'],
            $list
        );
    }

    private function joinChanges(array $items): string
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

    /**
     * Nothing matched. Rather than list every supported phrase, ask a short
     * question and offer three things that would actually change *this* site.
     */
    private function cannotUnderstand(string $instruction, array $spec): string
    {
        $ideas = [];

        if (($spec['motion'] ?? 'subtle') !== 'dynamic') {
            $ideas[] = '"make it more dynamic"';
        }
        if (empty($spec['dark'])) {
            $ideas[] = '"switch to a dark theme"';
        } else {
            $ideas[] = '"switch to a light theme"';
        }
        foreach (['gallery' => 'a gallery', 'pricing' => 'a pricing table', 'faq' => 'an FAQ', 'team' => 'a team section'] as $key => $label) {
            if (!in_array($key, $spec['sections'] ?? [], true)) {
                $ideas[] = '"add ' . $label . '"';
            }
        }
        if (!in_array('slider', $spec['components'] ?? [], true)) {
            $ideas[] = '"add an image slider"';
        }
        $ideas[] = '"use a green palette"';

        $pick = array_slice(array_values(array_unique($ideas)), 0, 3);

        return 'I couldn\'t tell what to change from "' . mb_substr(trim($instruction), 0, 90) . '". '
            . 'Could you say it a different way — which part of the site, and what should be different?'
            . "\n\nThings that would work right now: " . implode(', ', $pick) . '.';
    }

    /**
     * Sites generated by Claude have no spec to re-render, so only direct
     * text substitutions are possible here.
     */
    private function editRawFiles(array $ctx): Result
    {
        $instruction = $ctx['instruction'];
        $files = $ctx['files'];
        $changes = [];

        if (preg_match('/replace\s+["\x{201C}]([^"\x{201D}]+)["\x{201D}]\s+with\s+["\x{201C}]([^"\x{201D}]+)["\x{201D}]/iu', $instruction, $m)) {
            $count = 0;
            foreach ($files as $path => $contents) {
                if (preg_match('/\.(html?|php|css|js|txt|md)$/i', $path)) {
                    $replaced = str_replace($m[1], $m[2], $contents, $n);
                    if ($n > 0) {
                        $files[$path] = $replaced;
                        $count += $n;
                    }
                }
            }
            if ($count > 0) {
                $changes[] = sprintf('replaced "%s" with "%s" in %d place%s', $m[1], $m[2], $count, $count === 1 ? '' : 's');
            }
        }

        // Swap one hex colour for another across the stylesheets.
        if (preg_match('/#([0-9a-f]{6})\b.*?#([0-9a-f]{6})\b/is', $instruction, $m)) {
            $count = 0;
            foreach ($files as $path => $contents) {
                if (preg_match('/\.(css|html?|php)$/i', $path)) {
                    $replaced = str_ireplace('#' . $m[1], '#' . $m[2], $contents, $n);
                    if ($n > 0) {
                        $files[$path] = $replaced;
                        $count += $n;
                    }
                }
            }
            if ($count > 0) {
                $changes[] = 'changed #' . strtolower($m[1]) . ' to #' . strtolower($m[2]);
            }
        }

        if ($changes === []) {
            throw new RuntimeException(
                "This site was written by Claude, so the offline builder can't rebuild it from a description.\n\n"
                . "Without an API key it can only do direct substitutions here, for example:\n"
                . "  • replace \"Old headline\" with \"New headline\"\n"
                . "  • change #4f46e5 to #0f766e\n\n"
                . 'Set an ANTHROPIC_API_KEY to make free-form edits to this project again.'
            );
        }

        $ctx['progress'](80, 'Applying changes');

        return new Result($files, 'Updated your site: ' . $this->joinChanges($changes) . '.', null);
    }
}
