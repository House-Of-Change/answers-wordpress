# info.link/answers – Be visible in ChatGPT and Google AI (GEO, AEO) — WordPress/WooCommerce Integration

A WordPress plugin that renders FAQ content server-side for SEO and LLM crawlability. Includes a Docker-based local development environment with WooCommerce, sample products, and sample FAQ data.

## Quick Start

```bash
docker compose up
```

On first run, the `wpcli` service installs WordPress, WooCommerce, the Storefront theme, and seeds sample products and pages. Once you see `Setup complete!` in the logs, visit:

- **Store**: http://localhost:8080
- **Admin**: http://localhost:8080/wp-admin/ (admin / admin)

### Sample Content

**Products** (FAQs render in a WooCommerce product tab):
- http://localhost:8080/product/blue-snowboard/
- http://localhost:8080/product/red-snowboard/
- http://localhost:8080/product/green-jacket/

**Pages** (FAQs auto-injected after content or via shortcode):
- http://localhost:8080/shipping-returns/
- http://localhost:8080/about-our-store/

### Reset Everything

```bash
docker compose down -v
docker compose up
```

The `-v` flag removes the database and WordPress volumes, so the seed script runs again from scratch.

## Plugin: `answers`

The plugin lives in `plugin/answers/` and is volume-mounted into the WordPress container. Edits to plugin files are reflected immediately (no rebuild needed).

### How It Works

1. **Each page/product gets a Feed ID** via a meta box in the WordPress editor sidebar (stored as `_answers_feed_id` post meta).
2. **The data provider** fetches the rendered HTML for that feed from the publish API, falling back to a local JSON sample (`data/sample-faqs.json`) when no API URL is set or the request fails.
3. **FAQs render server-side** as HTML in the initial response (no client-side JS fetching), so content is visible to `curl`, Googlebot, and LLM crawlers.

### SSR Rendering

All FAQ content is rendered in PHP before the response is sent:

- **Product pages**: FAQs appear as a WooCommerce product tab via the `woocommerce_product_tabs` filter
- **Pages/posts**: FAQs are appended after `the_content` automatically when a feed ID is assigned
- **Shortcode**: `[answers_faq id="<feed-uuid>"]` for manual placement anywhere

### Shortcode

Place a feed anywhere with the `[answers_faq]` shortcode. Only `id` is required — every other attribute maps 1:1 to a publish-feed query parameter and is **optional**. When an attribute is omitted, the API serves the **default preset the publisher chose when the snapshot was captured**, so a bare `[answers_faq id="…"]` renders exactly what the publisher intended. Supplying an attribute overrides that preset for this placement only.

```
[answers_faq id="8da215bd-62a2-45e7-b6b2-70dafae4b57f"]
[answers_faq id="8da215bd-…" styling="scoped" heading_level="2" hide="verificationSources,authorshipCredentials"]
```

| Attribute | API param | Values | Effect |
|---|---|---|---|
| `id` *(required)* | path | feed UUID | Which published feed to render. Falls back to the **Default Feed ID** setting if omitted. |
| `slug` | `slug` | entity slug | Pick one entity from a multi-entity feed. Defaults to the feed's primary (first) entity. |
| `variant` | `variant` | `embedded` *(default)*, `standalone` | The plugin always requests `embedded` — an injectable HTML fragment (no `<html>`/`<head>`). `standalone` returns a **complete HTML document** — only use it for a dedicated page, never inside existing content. |
| `styling` | `styling` | `scoped`, `full`, `none` | `scoped` ships only structural, `.ep-`-prefixed CSS so your theme's typography flows through (recommended for embedding). `full` ships the complete stylesheet — ⚠️ it contains global selectors (`*`, `body`, `h2`, …) that **restyle the host page**, so reserve it for `standalone`. `none` ships no CSS. |
| `heading_level` | `headingLevel` | `1`, `2`, `3` | Top heading level for the rendered block — set `2` or `3` to nest correctly under the page's existing `<h1>`. |
| `hide` | `hide` | comma-separated section keys | Hide sections from this placement. Can only **hide** sections the publisher included — it can never reveal content the publisher excluded. |

**Section keys for `hide`:** `pageHeader`, `pageIndex`, `faqs`, `relatedEntities`, `authorshipCredentials`, `verificationSources`, `humanNotice`, `identity`, `identityDescription`, `hierarchy`, `company`, `webPresence`, `classification`, `certifications`, `specifications`, `howItDiffers`, `differentiators`, `entityPositioning`, `notIdenticalWith`, `structuredData`, `provProvenance`, `gs1Identifiers`.

> Invalid values (e.g. `styling="bogus"`) are dropped rather than sent, so a typo falls back to the publisher's preset instead of erroring.

### WPBakery Page Builder

When [WPBakery Page Builder](https://wpbakery.com/) is active, the plugin registers `[answers_faq]` as a native element so editors place a feed without typing shortcode syntax. In the editor, click **Add Element → Content → info.link/answers** and fill the form:

- **Feed ID** — leave empty to use the **Default Feed ID** setting.
- **Variant**, **Styling**, **Heading level** — dropdowns whose values match the [shortcode attributes](#shortcode); each defaults to "Default (publisher preset)", which omits the attribute so the publisher's snapshot preset wins.
- **Slug**, **Hide** — text fields.

The mapping (`includes/class-answers-wpbakery.php`) only builds the WPBakery form — output is still produced by the same `[answers_faq]` render callback, so behavior matches the shortcode exactly. It's a no-op when WPBakery isn't installed.

> **Not using a page builder?** Use a **Text Block** (or the classic editor), which runs shortcodes. Avoid WPBakery's **Raw HTML** element — it never executes shortcodes, so a pasted `[answers_faq]` renders nothing there.

### Elementor

When [Elementor](https://elementor.com/) is active, the plugin registers an **info.link/answers** widget (found under the **General** category, or by searching "FAQ"/"Answers"). Drop it onto the canvas and fill the panel:

- **Feed ID** — leave empty to use the **Default Feed ID** setting.
- **Variant**, **Styling**, **Heading level** — dropdowns matching the [shortcode attributes](#shortcode); each defaults to "Default (publisher preset)", which omits the attribute so the publisher's snapshot preset wins.
- **Slug**, **Hide** — text fields.

The widget (`includes/class-answers-elementor-widget.php`) only builds the Elementor panel — its `render()` defers to the same `[answers_faq]` shortcode callback, so behavior matches the shortcode exactly. Registration is deliberately defensive (`includes/class-answers-elementor.php`): the widget class loads only after Elementor is confirmed present, so it's a clean no-op when Elementor isn't installed.

> **No widget?** Elementor's **built-in Shortcode widget** runs `[answers_faq …]` reliably and is always available as a fallback — unlike a rich-text editor, it won't mangle quotes.

### Structured Data

[FAQPage JSON-LD](https://schema.org/FAQPage) is emitted for rich results in Google and machine-readability for AI assistants. The source depends on the mode:

- **API mode**: the rendered feed embeds its own JSON-LD `<script>` (when the publisher enables the `structuredData` section), so the plugin injects it as part of the HTML and **skips** the separate `<head>` injection to avoid duplicate schema.
- **Local-sample mode** (no API URL): the plugin builds the JSON-LD in PHP and injects it into `<head>`.

Verify with: `curl -s http://localhost:8080/product/blue-snowboard/ | grep 'application/ld+json'`

### Plugin File Structure

```
plugin/answers/
├── answers.php                         # Bootstrap, constants, hook registration
├── includes/
│   ├── class-answers-data-provider.php  # Fetches rendered HTML from the publish feed; local JSON fallback
│   ├── class-answers-renderer.php       # Local-sample HTML output + JSON-LD (fallback only)
│   ├── class-answers-hooks.php          # Auto-injection via WordPress/WooCommerce hooks
│   ├── class-answers-shortcode.php      # [answers_faq] shortcode
│   ├── class-answers-wpbakery.php       # Maps [answers_faq] as a WPBakery element (no-op without WPBakery)
│   ├── class-answers-elementor.php          # Registers the Elementor widget (defensive; no-op without Elementor)
│   ├── class-answers-elementor-widget.php   # Elementor widget — shortcode passthrough
│   ├── class-answers-admin.php          # Settings page (Settings > info.link/answers)
│   └── class-answers-meta-box.php       # Per-post/product feed ID selector
├── data/
│   └── sample-faqs.json               # Local sample feeds keyed by ID (offline fallback)
└── assets/css/
    └── answers.css                     # FAQ accordion styles
```

### Connecting to the Verified Answers API

1. Go to **Settings > info.link/answers** in WP Admin
2. Enter the **API URL** (the publish base, e.g. `https://answers.info.link/api/publish`) and **API Key**
3. The data provider fetches each feed from `{API URL}/{id}?format=html&variant=embedded` and injects the returned HTML, with transient caching (configurable TTL)

The plugin always requests `format=html&variant=embedded` (the injectable fragment); presentation is otherwise driven by the publisher's default preset unless a [shortcode option](#shortcode) overrides it. The local JSON sample serves as a fallback if the API is unreachable.

**Cache Duration (TTL):** rendered HTML is cached in a transient keyed by the full request URL. Set the TTL to **`0` to disable caching** (every view fetches fresh — useful while iterating on published content); any value `> 0` caches for that many seconds. Note that re-publishing a feed won't be reflected until the cached entry expires, so lower the TTL (or set `0`) when testing changes.

## Releasing & Client Distribution

Every PR merged to `main` automatically triggers a GitHub Actions workflow that:

1. Increments the patch version (e.g. `v1.0.0` → `v1.0.1`)
2. Updates the version in the plugin PHP file
3. Zips `plugin/answers/` into `answers.zip`
4. Creates a tagged GitHub Release with the zip attached

### Installing a Release (Client Instructions)

**Download the latest release zip:**

```bash
curl -L \
  "$(curl -s 'https://api.github.com/repos/benatwork/answers-wordpress/releases/latest' \
    | grep '"browser_download_url"' | cut -d'"' -f4)" \
  -o answers.zip
```

Or download directly from the [Releases page](https://github.com/benatwork/answers-wordpress/releases).

**Install in WordPress:**

1. In WP Admin, go to **Plugins → Add New → Upload Plugin**
2. Choose `answers.zip` and click **Install Now**
3. Activate the plugin

### Auto-updates

The plugin checks for new releases automatically. When a new version is published, a standard WordPress update notice appears in **Plugins → Installed Plugins** — no configuration required.

## Architecture

```
docker-compose.yml
├── db          — MySQL 8.0 (persistent volume)
├── wordpress   — WordPress + PHP 8.2 + Apache (port 8080)
│                 └── plugin volume-mounted from ./plugin/
└── wpcli       — WP-CLI (runs seed/setup.sh on first boot)
```

The `seed/setup.sh` script handles all first-run configuration: WordPress install, WooCommerce setup, theme activation, product/page creation, and feed ID assignment via post meta.
