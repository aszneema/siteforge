<?php
/**
 * SiteForge configuration.
 *
 * The Anthropic API key is read from the environment or from this file.
 * It is deliberately NOT settable through the web UI so the key never ends up
 * stored in the database or in a browser-submitted form.
 *
 * Set it in your shell / hosting panel:
 *     export ANTHROPIC_API_KEY="sk-ant-..."
 * or paste it into 'anthropic_api_key' below (keep this file out of version control).
 */

return [
    'app_name' => 'SiteForge',

    // --- AI generation -----------------------------------------------------
    'anthropic_api_key' => getenv('ANTHROPIC_API_KEY') ?: '',
    'model'             => 'claude-opus-5',

    // --- Local AI (Ollama) -------------------------------------------------
    // A model running on this machine writes your copy — free, offline, no key.
    //   1. Install Ollama:  https://ollama.com/download
    //   2. Pull a model:    ollama pull llama3.1
    // Leave 'ollama_model' empty to auto-pick the best model you have installed.
    'ollama_url'   => 'http://localhost:11434',
    'ollama_model' => '',

    // auto   = Claude if a key is set, else a local Ollama model, else templates
    // claude = always use Claude          (fails loudly if no key)
    // ollama = always use the local model (fails loudly if Ollama is not running)
    // local  = always use the offline template builder
    'generator' => 'auto',

    // --- Storage -----------------------------------------------------------
    'storage_path' => __DIR__ . '/storage',

    // --- Preview -----------------------------------------------------------
    // Generated PHP pages are executed by preview.php so PHP projects can be
    // previewed in the browser. Only the project's owner can trigger this.
    // Set to false to preview PHP projects as source code instead.
    'preview_execute_php' => true,
];
