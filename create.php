<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once SF_ROOT . '/includes/layout.php';
require_once SF_ROOT . '/includes/projects.php';

$user = require_login();

$engine = SiteForge\GeneratorFactory::describe(
    (string) config('generator', 'auto'),
    (string) config('anthropic_api_key', '')
);

$examples = [
    'A cosy neighbourhood bakery called Marlow & Bean. Warm colours, show the menu and opening hours.',
    'A dental practice site — calm and trustworthy, blue, with a team section and an FAQ.',
    'A portfolio for a freelance illustrator. Dark, bold, big gallery, minimal text.',
    'A landing page for a project management app. Pricing table, testimonials, sign-up call to action.',
];

layout_header(['title' => 'Create a website', 'active' => 'create']);
?>

<section class="build" id="build"
         data-engine="<?= e($engine['engine']) ?>"
         data-csrf="<?= e(csrf_token()) ?>">

    <div class="shell build-shell">

        <header class="build-head" data-reveal>
            <span class="pill"><span class="pill-dot"></span><?= e($engine['label']) ?></span>
            <h1>What are we building?</h1>
            <p class="muted"><?= e($engine['note']) ?></p>
        </header>

        <!-- Conversation ------------------------------------------------->
        <div class="chat" id="chat" aria-live="polite">
            <div class="msg msg-bot">
                <span class="msg-avatar" aria-hidden="true">W</span>
                <div class="msg-body">
                    <?php /* One line: .msg-body p uses pre-wrap, so source indentation would show. */ ?>
                    <p>Tell me about the website you want. The more you say about the business, the sections you need and the feel you're after, the closer the first build lands.</p>
                    <div class="chips">
                        <?php foreach ($examples as $example): ?>
                            <button type="button" class="chip js-example" data-text="<?= e($example) ?>">
                                <?= e(mb_substr($example, 0, 46)) ?>&hellip;
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Live build progress ------------------------------------------>
        <div class="progress-panel" id="progressPanel" hidden>
            <div class="progress-top">
                <strong id="progressStage">Starting build</strong>
                <span id="progressPercent">0%</span>
            </div>
            <div class="progress-track"><div class="progress-bar" id="progressBar"></div></div>
            <p class="muted small" id="progressHint">This usually takes a few seconds. You can watch it in the Library too.</p>
        </div>

        <!-- Result -------------------------------------------------------->
        <div class="result-panel" id="resultPanel" hidden>
            <div class="result-head">
                <div>
                    <h2 id="resultTitle">Your site is ready</h2>
                    <p class="muted" id="resultSummary"></p>
                </div>
                <div class="result-actions">
                    <a class="btn btn-ghost" id="resultOpen" href="#" target="_blank" rel="noopener">Open full size</a>
                    <a class="btn btn-primary" id="resultProject" href="#">Edit this site</a>
                </div>
            </div>
            <div class="preview-frame">
                <iframe id="resultFrame" title="Preview of your generated site" loading="lazy"></iframe>
            </div>
        </div>

        <!-- Composer ------------------------------------------------------>
        <form class="composer" id="composer" autocomplete="off">
            <label class="sr-only" for="brief">Describe your website</label>
            <textarea id="brief" name="brief" rows="4" maxlength="4000"
                      placeholder="e.g. A site for a small law firm called Hartley &amp; Crowe. Professional, understated, with services, team bios and a contact form."></textarea>

            <div class="composer-error" id="composerError" role="alert" hidden></div>

            <div class="composer-tools">

                <!-- Images -->
                <div class="tool tool-images">
                    <span class="tool-label">Images</span>
                    <div class="uploads" id="uploads">
                        <label class="upload-add">
                            <input type="file" id="fileInput" accept="image/png,image/jpeg,image/gif,image/webp" multiple hidden>
                            <span aria-hidden="true">+</span>
                            <span class="sr-only">Add images</span>
                        </label>
                    </div>
                    <p class="tool-hint">PNG, JPG, GIF or WebP &middot; up to 5&nbsp;MB each</p>
                </div>

                <!-- Site type -->
                <div class="tool tool-type">
                    <span class="tool-label">Output format</span>
                    <div class="type-picker" role="radiogroup" aria-label="Output format">
                        <?php $first = true; foreach (site_types() as $key => $type): ?>
                            <button type="button"
                                    class="type-option<?= $first ? ' is-selected' : '' ?>"
                                    role="radio"
                                    aria-checked="<?= $first ? 'true' : 'false' ?>"
                                    data-type="<?= e($key) ?>">
                                <span class="type-option-icon"><?= $type['icon'] ?></span>
                                <span class="type-option-label"><?= e($type['label']) ?></span>
                                <span class="type-option-hint"><?= e($type['hint']) ?></span>
                            </button>
                        <?php $first = false; endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="composer-foot">
                <span class="muted small" id="charCount">0 / 4000</span>
                <button class="btn btn-primary btn-lg" type="submit" id="buildBtn">
                    <span class="btn-label">Build my website</span>
                    <span class="spinner" aria-hidden="true"></span>
                </button>
            </div>
        </form>

    </div>
</section>

<?php layout_footer(['scripts' => ['assets/js/create.js']]); ?>
