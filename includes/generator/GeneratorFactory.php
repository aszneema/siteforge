<?php
declare(strict_types=1);

namespace SiteForge;

use RuntimeException;

/** Chooses which engine builds a site, based on config and what's available. */
final class GeneratorFactory
{
    /**
     * @param string $mode 'auto' | 'claude' | 'local'
     */
    public static function make(string $mode, string $apiKey, string $model): Generator
    {
        $claudeUsable = $apiKey !== '' && class_exists(\Anthropic\Client::class);

        return match ($mode) {
            'claude' => $claudeUsable
                ? new ClaudeGenerator($apiKey, $model)
                : throw new RuntimeException(
                    $apiKey === ''
                        ? 'Generation is set to "Claude only" but no ANTHROPIC_API_KEY is configured.'
                        : 'The Anthropic PHP SDK is not installed. Run "composer install" in the SiteForge folder.'
                ),
            'local'  => new LocalGenerator(),
            default  => $claudeUsable ? new ClaudeGenerator($apiKey, $model) : new LocalGenerator(),
        };
    }

    /** Which engine 'auto' would pick right now, for display in the UI. */
    public static function describe(string $mode, string $apiKey): array
    {
        $hasSdk = class_exists(\Anthropic\Client::class);
        $claudeUsable = $apiKey !== '' && $hasSdk;

        if ($mode === 'local' || ($mode === 'auto' && !$claudeUsable)) {
            return [
                'engine' => 'local',
                'label'  => 'Offline builder',
                'note'   => $mode === 'local'
                    ? 'Forced to the offline builder in config.php.'
                    : ($hasSdk
                        ? 'No ANTHROPIC_API_KEY found, so sites are built from the offline template engine.'
                        : 'The Anthropic SDK is not installed, so sites are built from the offline template engine.'),
            ];
        }

        return [
            'engine' => 'claude',
            'label'  => 'Claude',
            'note'   => 'Sites are written by Claude from your description.',
        ];
    }
}
