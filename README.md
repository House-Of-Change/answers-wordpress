# Answers – AI Search GEO AEO Plugin — WordPress/WooCommerce Integration

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

1. **Each page/product gets a FAQ Set ID** via a meta box in the WordPress editor sidebar (stored as `_answers_set_id` post meta).
2. **The data provider** loads FAQs for that set ID — currently from a local JSON file (`data/sample-faqs.json`), designed to switch to the Verified Answers REST API later.
3. **FAQs render server-side** as HTML in the initial response (no client-side JS fetching), so content is visible to `curl`, Googlebot, and LLM crawlers.

### SSR Rendering

All FAQ content is rendered in PHP before the response is sent:

- **Product pages**: FAQs appear as a WooCommerce product tab via the `woocommerce_product_tabs` filter
- **Pages/posts**: FAQs are appended after `the_content` automatically when a FAQ set ID is assigned
- **Shortcode**: `[answers set="general-store-faqs" heading="Store FAQ"]` for manual placement anywhere

### Structured Data

The plugin injects [FAQPage JSON-LD](https://schema.org/FAQPage) into `<head>` on any page with FAQs. This enables rich results in Google and makes the content machine-readable for AI assistants.

Verify with: `curl -s http://localhost:8080/product/blue-snowboard/ | grep 'application/ld+json'`

### Plugin File Structure

```
plugin/answers/
├── answers.php                         # Bootstrap, constants, hook registration
├── includes/
│   ├── class-answers-data-provider.php  # Data layer (local JSON now, REST API later)
│   ├── class-answers-renderer.php       # HTML output + JSON-LD structured data
│   ├── class-answers-hooks.php          # Auto-injection via WordPress/WooCommerce hooks
│   ├── class-answers-shortcode.php      # [answers] shortcode
│   ├── class-answers-admin.php          # Settings page (Settings > Answers by info.link)
│   └── class-answers-meta-box.php       # Per-post/product FAQ set ID selector
├── data/
│   └── sample-faqs.json               # Hardcoded FAQ sets keyed by set ID
└── assets/css/
    └── answers.css                     # FAQ accordion styles
```

### Connecting to the Verified Answers API

When the platform's publish REST API is ready, the switch requires no code changes:

1. Go to **Settings > Answers by info.link** in WP Admin
2. Enter the **API URL** and **API Key**
3. The data provider automatically fetches from the API instead of the local JSON file, with transient caching (configurable TTL)

The local JSON file serves as a fallback if the API is unreachable.

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

The `seed/setup.sh` script handles all first-run configuration: WordPress install, WooCommerce setup, theme activation, product/page creation, and FAQ set assignment via post meta.
