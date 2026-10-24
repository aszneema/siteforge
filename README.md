# SITEFORGE

**Build. Design. Create.** A website builder for people who don't code.

Two ways to build, on the same page, at any time:

- **AI builder**: describe the site in your own words (any language, photos and screenshots welcome). Claude builds the pages, then keeps editing them whenever you ask: "add a pricing section", "make it darker", "put my logo in the header". Every AI change has an Undo button.
- **Visual editor**: start from a template and a style, drag in ready-made sections (searchable, with live previews in your site's colours), click anything to edit it. The **Design** tab shows plain-language controls that fit what you clicked: text, links (pages, sections, email, phone, WhatsApp), button looks, images, layout, backgrounds and photos, spacing, corners and shadows, appear-on-scroll effects and show/hide per screen size. An **Advanced** tab keeps every CSS option. Plus a site-wide Theme panel (including right-to-left), multiple pages with Google title and description, desktop/tablet/phone views, a 1-minute guided tour and a help panel.

Then publish with one click (forms on the site land in your SiteForge inbox) or download the site as plain HTML files.

## How it works

| Part | What it is |
|---|---|
| Editor | [GrapesJS](https://grapesjs.com) 0.23 from a CDN, with our own panels around it |
| AI | Claude (`claude-opus-5-5`) through the official Anthropic PHP SDK, streamed to the chat |
| Storage | SQLite in `data/` (no database to set up) |
| Server | Plain PHP 8.1+, no framework |

The AI and the visual editor work on the same thing: pages of HTML plus one stylesheet whose `:root` theme variables (`--primary`, `--font-heading`, `--radius`, …) drive the Theme panel. So you can switch between asking and editing at any point.

| File | Job |
|---|---|
| `index.php` | Landing page |
| `auth.php` | Sign up, log in, log out |
| `dashboard.php` | Your websites, the new-website dialog, form inboxes |
| `editor.php` + `assets/editor.js` | The builder, the AI chat, pages, theme, tour and help |
| `assets/design.js` | The Design tab: simple controls for the selected element |
| `assets/blocks.js` | Sections, style presets and starter templates |
| `api.php` | Save, upload, publish, download ZIP, AI chat |
| `site.php` | Serves published sites at `site.php/<slug>/` and receives their forms |
| `admin.php` | Admin area: overview, users, websites, settings |
| `lib.php` | Settings, database, login and shared helpers |

## Run it locally

```bash
git clone https://github.com/aszneema/siteforge.git && cd siteforge
composer install
PHP_CLI_SERVER_WORKERS=6 php -S localhost:8080
```

Open <http://localhost:8080> and create an account. (`composer install` fetches the Anthropic SDK into `vendor/`, which isn't kept in git.)

## Switch on the AI

The AI needs one Anthropic API key for the whole site; the people using SiteForge never need their own. Create `config.php` (it's kept out of git so the key never gets published):

```php
<?php
return ['anthropic_api_key' => 'sk-ant-…'];
```

Optional settings in the same array: `'model'` (default `claude-opus-5-5`) and `'effort'` (`low`, `medium` or `high`; default `medium`).

Without a key everything else works and the chat explains that the AI is off.

## Admin

The first account created on a fresh install is the admin (an **Admin** link appears in the top bar). Admins can make other users admins.

- **Overview**: users, websites, AI requests and estimated AI cost, form messages, image storage, top AI users.
- **Users**: search, make or remove admin, suspend (logs them out and takes their websites offline), restore, delete.
- **Websites**: search, view any site (drafts too), publish or take offline, delete.
- **Settings**: open or close sign-ups, pause the AI for everyone, and set how many AI requests each user gets per day (default 50; admins are unlimited).

## Put it online (cPanel)

1. Upload `siteforge-upload.tar.gz` in File Manager and **Extract** it (a `.zip` with JavaScript inside gets blocked by the host's virus scanner, a `.tar.gz` does not).
2. Edit `config.php` there and paste the API key.
3. Make sure `data/` and `uploads/` are writable (755 is usually enough).
4. Pick PHP 8.1 or newer in "Select PHP Version".

`.htaccess` keeps `data/`, `vendor/`, `config.php` and `lib.php` private.

## Check

```bash
php selfcheck.php
```
