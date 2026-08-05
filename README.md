# SITEFORGE

**Build. Design. Create.**

Describe a website in plain English, watch it get built, then change it by
describing the change. Every build and every edit is saved as a version you can
preview, restore or download as a ZIP.

Written in plain PHP with SQLite. No framework, no build step, no npm.

---

## Brand

The logo lives in `assets/img/` as SVG, and is also inlined by
`brand_mark()` in `includes/layout.php` so CSS can recolour it — the navy
facets flip to white on the navy navigation bar and footer.

| Asset | Use |
|---|---|
| `assets/img/logo.svg` | Full lockup: mark, SITEFORGE wordmark, tagline |
| `assets/img/favicon.svg` | Simplified mark, browser tab |
| `brand_mark()` | Inline, CSS-themeable mark used in the header and footer |

### Colour

| Token | Hex | Use |
|---|---|---|
| `--navy` | `#1A2B4C` | Navigation bar, footer, headings |
| `--teal` | `#008B99` | Primary CTAs, links, focus rings |
| `--teal-bright` | `#17A2B8` | Hover states, wordmark accent, highlights |
| `--bg` | `#F8FAFC` | Page background — easier on the eyes than pure white |
| `--text` | `#334155` | Body copy — never pure black |

Every colour is a CSS custom property at the top of `assets/css/app.css`.

### Type

| Family | Use |
|---|---|
| **Montserrat** (600/700/800) | Headings, wordmark, stat figures |
| **Inter** (400–700) | Body copy, forms, UI |
| **Fira Code** (400/500) | File paths, model IDs, shell commands, code |

Loaded from Google Fonts in `includes/layout.php`, each with a system-font
fallback so the app still reads correctly offline. To self-host them instead,
drop the woff2 files in `assets/fonts/` and swap the `<link>` for `@font-face`
rules.

> The palette above is SITEFORGE's own branding. It does **not** affect the
> sites it generates — those get their own palette chosen from the brief.

---

## Requirements

- PHP **8.1+** with `pdo_sqlite`, `zip` and (for Claude generation) `curl` + `openssl`
- A writable `storage/` directory
- [Composer](https://getcomposer.org) — only needed for Claude generation
- Optional: an Anthropic API key

## Running it

```bash
git clone https://github.com/aszneema/siteforge.git
cd siteforge
composer install
php -S localhost:8000
```

Then open <http://localhost:8000>.

`vendor/` is not committed, so a fresh clone has no Anthropic SDK until you run
`composer install`. The app still runs fine without it — it falls back to the
offline builder and says so on the Settings page — but the Claude option stays
unavailable until the SDK is present *and* a key is set.

> **Note on `php -S`:** the built-in server handles one request at a time, so
> the live progress bar can stall while a build is running. Start it with
> workers to avoid that:
> ```bash
> PHP_CLI_SERVER_WORKERS=6 php -S localhost:8000
> ```
> Apache, nginx and shared hosting (cPanel/LiteSpeed) handle this correctly
> without any extra flags.

Register an account on first run — accounts are local to this install.

## Three engines, picked automatically

SiteForge always works. On `'generator' => 'auto'` (the default) it uses the
best writer available:

| | Writes your copy? | Cost | Needs |
|---|---|---|---|
| **Claude** | Yes — best quality | a few cents per site | `ANTHROPIC_API_KEY` |
| **Local AI** | Yes — free and offline | none | [Ollama](https://ollama.com/download) + a model |
| **Template builder** | No — pre-written copy | none | nothing, always available |

The template builder reads your brief for *signals* — industry, colour, style,
which sections and components — and assembles copy from a built-in bank. It's
instant and needs nothing, but it will never mention your grandmother's
sourdough starter, because it doesn't write: it selects.

Both AI engines fix that. They read the brief properly and write copy about
*your* business, then the same renderer builds the site, so every other feature
(palettes, motion, components, all four formats, edits) works identically
whichever engine is active.

### Free AI with no API key

```bash
# install from https://ollama.com/download, then:
ollama pull llama3.1
```

That's it — SiteForge detects it automatically, no restart needed. Around 5 GB
once; generation then takes roughly 30–90 seconds per site depending on your
machine.

The local model isn't asked to write HTML. It writes your **content** as
structured data, and the renderer builds the pages from it — which is far more
reliable on a 7–8B model than asking for six consistent files, and produces
better markup. Set `ollama_model` in `config.php` to pin a specific model, or
leave it empty to auto-pick the best one installed.

## Turning on Claude generation

Without an API key SiteForge uses its **offline builder**: it reads the brief,
works out the kind of business, picks a palette and sections, and renders a
complete site from a built-in copy bank. Everything in the app works, and
builds take milliseconds.

With a key, sites are written by Claude instead, which handles free-form
briefs and free-form edits:

```bash
export ANTHROPIC_API_KEY="sk-ant-..."
php -S localhost:8000
```

The key is read from the environment or `config.php` only — never from a form
in the app, so it is never stored in the database. `Settings` shows which
engine is active.

Set `generator` in `config.php` to `auto` (default), `claude` or `local` to
override the choice.

## What it produces

| Format | Output |
|---|---|
| **Static HTML** | `index/about/services/contact.html`, `css/styles.css`, `js/main.js` |
| **PHP** | `.php` pages, `includes/header.php` + `footer.php`, working contact form with server-side validation |
| **Landing page** | One `index.html` with every section and in-page anchors |
| **Bootstrap 5** | Bootstrap markup + CDN, with a stylesheet that recolours it to your palette |

Generated sites are self-contained: no external fonts, no CDNs (except
Bootstrap, where that is the point), no build step. Placeholder imagery is
generated as CSS gradients, so nothing ever shows a broken image.

## Editing

On a project page, describe the change in your own words. **No API key is
needed for any of this** — it all runs in `includes/generator/Intents.php`,
which normalises the instruction, matches it against a broad vocabulary,
understands negation and tolerates typos. One sentence can trigger several
changes: *"make it dark and add a slider"* does both.

| Ask for | Examples |
|---|---|
| **Motion** | make it dynamic · more animated · add parallax · less motion · make it static |
| **Components** | slider / carousel · marquee · client logos · how it works · timeline · newsletter · opening hours · map · social links · search box · back-to-top · floating CTA · dark-mode toggle · cookie banner · chat button |
| **Sections** | add a pricing table · remove the testimonials · add an FAQ · no gallery |
| **Order** | move testimonials above services · put the gallery first |
| **Theme** | make it dark · go light · use a green palette · primary colour #0f766e |
| **Layout** | centre the hero · full screen hero · flip the hero · wider · narrower |
| **Spacing** | too much space · tighter · more breathing room |
| **Shape** | sharp corners · rounded corners · pill buttons · outlined buttons · gradient buttons |
| **Type** | use a serif font · monospace · condensed · bigger headings · smaller text |
| **Style/tone** | more minimal / elegant / bold / playful · friendlier copy · more formal |
| **Content** | rename it to Riverside Dental · change the headline to "…" · replace "x" with "y" · the email is hi@example.com |
| **Catch-all** | make it better / nicer / more professional |

Everything above works on all four output formats.

If it genuinely can't tell what you meant, it asks a short question and
suggests three things that would change *this* site — it never claims to have
done something it didn't. With an API key set, Claude handles the edit instead
and there's no vocabulary limit at all.

## Layout

```
index.php        Home
create.php       Chat builder — brief, images, output format
library.php      All projects, with live build progress
project.php      Preview, edit chat, version history, file list
preview.php      Serves a generated site (executes PHP projects)
download.php     ZIP export
settings.php     Engine status and account

includes/
  bootstrap.php  Config, session, DB, autoloader
  db.php         Schema and migrations
  auth.php       Registration and login
  projects.php   Projects, versions, files, chat log
  pipeline.php   Runs one generation end to end
  generator/     Generator, ClaudeGenerator, LocalGenerator,
                 Blueprint (brief → spec), Content (copy bank),
                 Renderer + BootstrapRenderer, CssKit

api/             generate, projects, status, upload, asset
storage/         SQLite database, generated sites, uploads (not web-servable)
```

## How a build works

1. `api/projects.php` (`start`) creates the project row and returns its id.
2. The browser calls `api/generate.php` and polls `api/status.php` at the same
   time — the generation request closes its session lock first, so polling is
   never queued behind it.
3. The generator returns a set of files; `version_store()` writes them to
   `storage/projects/<id>/v<n>/` and records the version.
4. Edits repeat the same path with the previous version's files (and, for the
   offline builder, its stored spec) as input.

## Security notes

- Passwords are hashed with `password_hash()`; sessions are HttpOnly + SameSite.
- All state-changing endpoints require a CSRF token.
- Uploads are accepted only if `getimagesize()` confirms PNG/JPEG/GIF/WebP.
  SVG is rejected: it can carry script, and these files get embedded in
  generated pages.
- Generator-supplied paths are normalised and extension-checked before writing;
  preview paths are resolved with `realpath()` and confined to the version
  directory.
- `preview.php` **executes** generated PHP so PHP projects can be previewed.
  It runs only for the project's owner, inside an isolated function scope. Set
  `preview_execute_php` to `false` in `config.php` to show source instead.
- `storage/` ships with an `.htaccess` deny rule in case it sits inside a web
  root; on nginx, block it in your server config.
