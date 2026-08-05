<?php
declare(strict_types=1);

namespace SiteForge;

use RuntimeException;

/**
 * Chooses which engine builds a site.
 *
 * On 'auto' the order is: Claude if a key is set, else a local model through
 * Ollama if one is running, else the offline template builder. That way the app
 * always works, and it uses the best writer available without configuration.
 */
final class GeneratorFactory
{
    /**
     * @param string $mode 'auto' | 'claude' | 'ollama' | 'local'
     */
    public static function make(string $mode, string $apiKey, string $model): Generator
    {
        $claudeUsable = $apiKey !== '' && class_exists(\Anthropic\Client::class);
        $ollamaUrl = (string) config('ollama_url', 'http://localhost:11434');
        $ollamaModel = OllamaGenerator::chooseModel($ollamaUrl, (string) config('ollama_model', ''));

        return match ($mode) {
            'claude' => $claudeUsable
                ? new ClaudeGenerator($apiKey, $model)
                : throw new RuntimeException(
                    $apiKey === ''
                        ? 'Generation is set to "Claude only" but no ANTHROPIC_API_KEY is configured.'
                        : 'The Anthropic PHP SDK is not installed. Run "composer install" in the SiteForge folder.'
                ),

            'ollama' => $ollamaModel !== ''
                ? new OllamaGenerator($ollamaUrl, $ollamaModel)
                : throw new RuntimeException(
                    'Generation is set to "local model" but Ollama is not reachable at ' . $ollamaUrl
                    . '. Start it with "ollama serve", then install a model with "ollama pull llama3.1".'
                ),

            'local' => new LocalGenerator(),

            default => match (true) {
                $claudeUsable       => new ClaudeGenerator($apiKey, $model),
                $ollamaModel !== '' => new OllamaGenerator($ollamaUrl, $ollamaModel),
                default             => new LocalGenerator(),
            },
        };
    }

    /** Which engine is active right now, for display in the UI. */
    public static function describe(string $mode, string $apiKey): array
    {
        $hasSdk = class_exists(\Anthropic\Client::class);
        $claudeUsable = $apiKey !== '' && $hasSdk;

        $ollamaUrl = (string) config('ollama_url', 'http://localhost:11434');
        $ollamaModel = OllamaGenerator::chooseModel($ollamaUrl, (string) config('ollama_model', ''));

        $claude = [
            'engine' => 'claude',
            'label'  => 'Claude',
            'note'   => 'Sites are written by Claude from your description — the best quality available.',
        ];
        $ollama = [
            'engine' => 'ollama',
            'label'  => 'Local AI (' . $ollamaModel . ')',
            'note'   => 'A model running on this machine writes your copy — free, offline, no API key.',
        ];
        $local = [
            'engine' => 'local',
            'label'  => 'Template builder',
            'note'   => 'No AI available, so sites are assembled from built-in templates. '
                      . 'The layout and styling follow your brief, but the wording is pre-written.',
        ];

        return match ($mode) {
            'claude' => $claudeUsable ? $claude : ['engine' => 'claude', 'label' => 'Claude — unavailable',
                'note' => $apiKey === ''
                    ? 'Forced to Claude in config.php, but no ANTHROPIC_API_KEY is set. Builds will fail.'
                    : 'Forced to Claude in config.php, but the Anthropic SDK is missing. Run "composer install".'],

            'ollama' => $ollamaModel !== '' ? $ollama : ['engine' => 'ollama', 'label' => 'Local AI — unavailable',
                'note' => 'Forced to the local model in config.php, but Ollama is not responding at '
                        . $ollamaUrl . '. Builds will fail.'],

            'local' => $local + ['note' => 'Forced to the template builder in config.php.'],

            default => match (true) {
                $claudeUsable       => $claude,
                $ollamaModel !== '' => $ollama,
                default             => $local,
            },
        };
    }

    /**
     * Everything the Settings page needs to explain the current setup.
     */
    public static function status(string $apiKey): array
    {
        $ollamaUrl = (string) config('ollama_url', 'http://localhost:11434');
        $installed = OllamaGenerator::models($ollamaUrl);

        return [
            'sdk'           => class_exists(\Anthropic\Client::class),
            'key'           => $apiKey !== '',
            'ollama_url'    => $ollamaUrl,
            'ollama_up'     => $installed !== [],
            'ollama_models' => $installed,
            'ollama_model'  => OllamaGenerator::chooseModel($ollamaUrl, (string) config('ollama_model', '')),
        ];
    }
}
