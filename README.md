# WP Anime Index

**Version:** 1.3.0  
**Author:** Rexdevai  
**Stack:** PHP · WordPress · Vanilla JavaScript / jQuery · CSS · AniList GraphQL · Jikan API · Kitsu API · Google Gemini · LiteSpeed · Cloudflare

WP Anime Index is a WordPress plugin for creating, importing, organizing, and displaying anime content through a dedicated Custom Post Type, external anime data providers, automated imports, editorial content generation, frontend search, rankings, trailers, theme controls, cache management, and SEO integration.

---

## Key features

- Dedicated `anime` Custom Post Type.
- Anime genre, studio, year, status, and season taxonomies.
- AniList GraphQL integration.
- Jikan API integration.
- Kitsu API integration.
- Google Gemini editorial content generation.
- Manual anime importing.
- Scheduled automatic importing.
- Batch import processing.
- Pending import management.
- AniList and MAL duplicate detection.
- External anime cover handling.
- Cover migration and retry tools.
- Anime directory shortcode.
- Dynamic anime homepage shortcode.
- Frontend anime search.
- Directory filters and pagination.
- Top 5 anime ranking.
- Trailer of the day.
- YouTube trailer lightbox.
- Configurable light and dark theme colors.
- Full-site light/dark theme control including header, footer, blog, related posts, comments, and widgets.
- Mobile-specific theme adjustments for Astra header, off-canvas drawer, widgets, and archive cards.
- Object cache integration (Redis/Memcached) when available.
- Total cache purge across LiteSpeed, Cloudflare, transients, WP Rocket, W3 Total Cache, WP Super Cache, WP Fastest Cache, Nginx Helper, Autoptimize, Breeze, SiteGround, Kinsta, WP Engine, Hummingbird, NitroPack, Comet Cache, OPcache, and object cache.
- Targeted URL cache purge from the admin bar.
- Cloudflare API integration for cache purging.
- Unified log module with tabs for activity, errors, and combined view.
- Menu badge indicating unread error count.
- Yoast SEO integration.
- Anime JSON-LD structured data.
- Dynamic SEO titles, descriptions, and canonicals.
- `noindex` handling for filtered directory URLs.
- API connectivity status checks.
- Performance monitoring panel (object cache, LiteSpeed, Cloudflare status).
- WordPress AJAX integration.
- WordPress Cron integration.

---

## Installation

1. Upload the `wp-anime-index` plugin directory to `/wp-content/plugins/`, or install the plugin ZIP through WordPress.
2. Activate **WP Anime Index** from the WordPress Plugins screen.
3. Open **Anime Index → Configuración**.
4. Configure the API and import settings.
5. Configure the homepage and appearance settings under **Anime Index → Apariencia**.
6. Configure the Cloudflare Zone ID and API Token under **Anime Index → Configuración → Cloudflare** (optional, required for Cloudflare cache purging).
7. Create a WordPress page for the anime directory.
8. Add the `[anime_index]` shortcode to the directory page.
9. Add `[anime_home]` to the page intended to display the anime homepage.

After activation, the plugin registers its Custom Post Type and taxonomies and initializes its scheduled import system.

---

## Compatibility

WP Anime Index is built around native WordPress APIs and functionality.

The plugin integrates with:

- WordPress Custom Post Types.
- WordPress taxonomies.
- WordPress options.
- WordPress transients.
- WordPress Cron.
- WordPress AJAX.
- WordPress Media Library.
- WordPress HTTP API.
- WordPress REST-enabled post types.
- Yoast SEO when installed and active.
- LiteSpeed Cache when installed and active.
- Cloudflare CDN when configured.
- Redis / Memcached object cache when available.
- WP Rocket, W3 Total Cache, WP Super Cache, WP Fastest Cache, Nginx Helper, Autoptimize, Breeze, SiteGround, Kinsta, WP Engine, Hummingbird, NitroPack, Comet Cache.
- AniList GraphQL API.
- Jikan API.
- Kitsu API.
- Google Gemini API.

---

## Architecture overview

The plugin uses WordPress's native infrastructure throughout. The main components are separated by responsibility:

- **CPT layer** — registers the anime post type and taxonomies.
- **API clients** — retrieve and normalize external anime information.
- **Importer** — converts external anime data into WordPress content.
- **Media layer** — manages anime cover sources and media handling.
- **Homepage** — builds the dynamic anime homepage and its cached sections.
- **Shortcodes** — exposes the frontend directory, homepage, and anime cards.
- **AJAX layer** — handles frontend and administrative asynchronous operations.
- **Cron layer** — manages scheduled importing and ranking updates.
- **Admin layer** — provides settings, import tools, migration tools, logs, and API status.
- **Cache layer** — handles cache purging across external systems and internal transients.
- **Theme layer** — manages configurable frontend colors and light/dark presentation.
- **SEO layer** — integrates with Yoast and generates anime structured data.

The plugin stores anime information using WordPress posts, post metadata, and taxonomies rather than a separate database schema.

---

## File map

```text
wp-anime-index/
├── wp-anime-index.php
├── includes/
│   ├── class-cpt.php
│   ├── class-api-anilist.php
│   ├── class-api-jikan.php
│   ├── class-api-kitsu.php
│   ├── class-api-gemini.php
│   ├── class-media.php
│   ├── class-importer.php
│   ├── class-admin.php
│   ├── class-cache.php
│   ├── class-cron.php
│   ├── class-shortcodes.php
│   ├── class-ajax.php
│   ├── class-home.php
│   ├── class-seo.php
│   └── class-theme.php
├── templates/
│   ├── index.php
│   ├── index-grid.php
│   ├── home.php
│   ├── card.php
│   └── single-anime.php
└── assets/
    ├── css/
    │   ├── front.css
    │   ├── home.css
    │   ├── theme.css
    │   └── admin.css
    └── js/
        ├── home.js
        ├── theme.js
        ├── index-pagination.js
        ├── admin-search.js
        ├── admin-migrate.js
        ├── admin-logs.js
        └── cpt-list.js
```

---

## Custom post types and taxonomies

Registered by `class-cpt.php` on `init`.

### CPT: `anime`

- Public.
- Archive enabled.
- REST enabled.
- Rewrite slug: `anime`.
- Supports `title`, `editor`, `thumbnail`, and `excerpt`.

### Taxonomy: `anime_genre`

Rewrite:

```text
genero
```

Used for anime genres.

### Taxonomy: `anime_studio`

Rewrite:

```text
estudio
```

Used for anime studios.

### Taxonomy: `anime_year`

Rewrite:

```text
anio
```

Used for anime release years.

### Taxonomy: `anime_status`

Rewrite:

```text
estado
```

Used for anime status values.

### Taxonomy: `anime_season`

Rewrite:

```text
temporada
```

Used for anime seasons.

All five taxonomies are public, non-hierarchical, and REST-enabled.

---

## Post meta schema

### On `anime`

| Meta key | Type | Description |
|----------|------|-------------|
| `wai_anilist_id` | int | AniList anime identifier |
| `wai_mal_id` | int | MyAnimeList identifier |
| `wai_import_source` | string | Source used during import |
| `wai_title_romaji` | string | Romaji title |
| `wai_title_native` | string | Native-language title |
| `wai_episodes` | int | Episode count |
| `wai_duration` | int | Episode duration |
| `wai_score` | float | Normalized anime score |
| `wai_popularity` | int | Anime popularity value |
| `wai_format` | string | Anime format |
| `wai_status` | string | Anime release status |
| `wai_season` | string | Anime season |
| `wai_season_year` | int | Season year |
| `wai_trailer_id` | string | Trailer identifier |
| `wai_trailer_site` | string | Trailer platform |
| `wai_banner` | string | Banner image URL |
| `wai_franchise` | string | Detected franchise name |
| `wai_pending_fields` | array/string | Stored pending import information |
| `wai_cover_url` | string | External anime cover URL (extraLarge) |
| `wai_cover_url_large` | string | External anime cover URL (large, used for `srcset`) |

---

## Shortcodes

### `[anime_home]`

Renders the complete dynamic anime homepage.

The homepage includes:

- Search.
- Hero slideshow.
- Anime filters.
- Latest anime.
- Top 5 ranking.
- Trailer of the day.
- YouTube trailer lightbox.
- Theme toggle.

### `[anime_index]`

Renders the anime directory.

Supported attributes:

| Attribute | Default | Description |
|-----------|---------|-------------|
| `genre` | `""` | Genre filter |
| `year` | `""` | Year filter |
| `status` | `""` | Status filter |
| `per_page` | `20` | Number of anime per page |
| `orderby` | `meta_value_num` | Ordering field |
| `order` | `DESC` | Ordering direction |

Example:

```text
[anime_index]
```

Example with filters:

```text
[anime_index genre="Action" year="2024" status="RELEASING"]
```

The directory also reads the following GET parameters:

```text
wai_genre
wai_year
wai_status
wai_search
```

When the default ordering is used, the directory orders anime using the `wai_score` metadata field.

### `[anime_card]`

Renders an individual anime card using the anime post data.

---

## Homepage flow

The homepage is implemented by `class-home.php`.

```text
[anime_home]
      │
      ├── Search
      ├── Hero
      ├── Filters
      ├── Latest
      ├── Top 5
      └── Trailer of the day
```

### Hero

The hero displays five anime slides.

Each card can contain:

- Title.
- Cover.
- Banner.
- Score.
- Anime information.
- Trailer information.

### Latest anime

The latest section retrieves five published anime ordered by publication date.

### Top 5 ranking

The homepage ranking uses `wai_score`.

The generated ranking is cached using:

```text
wai_top5_rankings
```

### Trailer of the day

The trailer section selects a published anime with trailer information.

The result is cached using:

```text
wai_trailer_of_day
```

for one day.

---

## Homepage search

Homepage search is handled through the public AJAX action:

```text
wai_home_search
```

The search returns up to eight published anime.

Each result contains:

- Post ID.
- Title.
- URL.
- Cover.
- Score.
- Year.

---

## Anime card data

The homepage card builder provides:

- ID.
- Title.
- URL.
- Cover.
- Banner.
- Score.
- Episodes.
- Format.
- Status.
- Year.
- Trailer ID.
- Trailer site.
- Excerpt.
- Genres.
- Studio.

The generated excerpt is limited to approximately 20 words.

---

## AniList API

AniList is the primary external anime data source.

Endpoint:

```text
https://graphql.anilist.co
```

The AniList client uses GraphQL queries and requests:

```text
isAdult: false
```

The HTTP timeout is 20 seconds.

HTTP 429 responses are handled using retry processing with `Retry-After` support and progressive backoff.

Up to three retry attempts can be performed for rate-limited requests.

---

## Jikan API

Jikan provides supplementary anime information and cover retrieval.

Endpoint:

```text
https://api.jikan.moe/v4
```

Jikan data is used for:

- Cover retrieval.
- MAL-related information.
- Supplementary anime information.
- Status mapping.
- Format mapping.
- Score normalization.

Jikan scores use a 0–10 scale and are normalized to the internal 0–100 scale.

---

## Kitsu API

Kitsu provides an additional anime cover source and MAL-based lookup support.

Endpoint:

```text
https://kitsu.io/api/edge
```

Retrieved cover information can be cached through WordPress transients.

---

## Google Gemini

Gemini integration is implemented by `class-api-gemini.php`.

Default configuration:

| Setting | Default |
|---------|---------|
| API version | `v1beta` |
| Model | `gemini-3.6-flash` |
| Timeout | `120` |
| Temperature | `0.7` |
| Max output tokens | `4096` |
| Thinking | `low` |

Endpoint format:

```text
https://generativelanguage.googleapis.com/{api_version}/models/{model}:generateContent
```

The API key is stored in:

```text
wai_gemini_key
```

The administration interface exposes the API key field as a password input.

---

## Gemini content generation

Gemini is used to generate editorial anime content during importing.

The generation prompt requests Latin American Spanish editorial content with a target length of approximately 250–800 words.

The generated article is expected to:

- Provide original editorial text.
- Avoid copying the official synopsis.
- Avoid generic introductory phrases.
- Use an accessible editorial tone.
- Explain the intended audience.
- Provide relevant cultural or contextual information.
- Avoid invented facts.
- End with `¿Para quién es?`.
- Include exactly three bullet points in the final section.
- Produce a complete article.

The response is validated before it is stored.

A response with `MAX_TOKENS` is treated as incomplete.

A valid generated response requires a `STOP` finish reason and non-empty text.

---

## Importer

The importer is implemented by `class-importer.php`.

Automatic importing uses three phases:

```text
1. Popular
2. Top-rated
3. Alphabetical by year
```

The alphabetical phase begins at 1960.

The default automatic batch size is 50.

The publication status can be configured as:

```text
draft
publish
```

After each import, the plugin:

- Invalidates the homepage cached data.
- Purges the specific anime permalink, the homepage, and the directory URL across LiteSpeed, Cloudflare, and WP Rocket (when available).

---

## Import flow

```text
External anime data
        ↓
Data normalization
        ↓
Duplicate detection
        ↓
Supplementary API data
        ↓
Gemini editorial content
        ↓
Anime post creation
        ↓
Post metadata
        ↓
Taxonomies
        ↓
Media
        ↓
Cache invalidation
        ↓
Import log
```

Duplicate detection uses AniList and MAL identifiers before creating a new anime post.

---

## Importer methods

The importer provides methods for:

- `import_single()`
- `revert()`
- `revert_incomplete_batch()`
- `complete_pending()`
- `complete_pending_batch()`
- `process()`
- `add_to_pending()`
- `remove_from_pending()`
- `reimport()`
- `exists()`
- `exists_by_mal()`

---

## Franchise detection

`detect_franchise()` identifies a common franchise name from an anime title.

The normalization process recognizes continuation and release indicators including:

- Seasons.
- Parts.
- Roman numerals.
- Final Season.
- Movies.
- OVAs.
- Specials.

The resulting value is stored in:

```text
wai_franchise
```

---

## Pending imports

The importer can store pending information using:

```text
wai_pending_fields
```

Pending records can then be processed through the administration interface.

Available operations include:

- Add to pending.
- Remove from pending.
- Complete pending.
- Complete pending batch.

---

## Media system

Media functionality is implemented by `class-media.php`.

The primary cover resolution order is:

```text
wai_cover_url
      ↓
Local featured image
      ↓
Placeholder
```

When an external cover URL is not available, the media system can query Jikan and Kitsu using the stored MAL ID.

The `cover_img()` method renders external covers with `srcset` support when the `wai_cover_url_large` metadata is available, allowing mobile browsers to download a smaller variant when appropriate.

The legacy `sideload()` method can download remote images into the WordPress Media Library.

Supported sideload formats:

- JPG.
- JPEG.
- PNG.
- WebP.

---

## Cache system

Cache management is implemented by `class-cache.php` through the `WAI_Cache` class.

The cache layer provides:

- **Total cache purge** — clears all supported cache layers across the entire site.
- **Targeted URL purge** — clears only a specific URL across supported cache layers.
- **Cloudflare API integration** — purges via `purge_everything` or by explicit file list.
- **LiteSpeed filter exclusion** — prevents LiteSpeed from caching filtered directory URLs (`?wai_genre=`, `?wai_search=`, `?wai_year=`, `?wai_status=`).
- **Object cache integration** — automatically uses `wp_cache_*` when Redis or Memcached is available, otherwise falls back to transients.

The cache purge supports, in order:

```text
LiteSpeed
Cloudflare
WordPress transients
Object cache (Redis / Memcached)
OPcache
WP Rocket
W3 Total Cache
WP Super Cache
WP Fastest Cache
Nginx Helper
Autoptimize
Breeze
SiteGround Optimizer
Kinsta
WP Engine
Hummingbird
NitroPack
Comet Cache
```

Each layer is detected at runtime and skipped if unavailable.

---

## Cache administration

The administration interface and the WordPress admin bar provide cache purging controls.

### Admin bar

The admin bar exposes a dedicated cache menu:

```text
🧹 Caché WAI
   ├── 🧹 Purgar esta URL
   └── ☢️ Purga Total
```

- **Purgar esta URL** — purges only the currently viewed URL across supported cache layers.
- **Purga Total** — purges the entire site cache with a confirmation dialog warning about the impact on high-traffic sites.

### Settings page

**Anime Index → Configuración** provides:

- A **Cloudflare** configuration section (Zone ID + API Token).
- A **Purga Total** section with a manual trigger button.
- A **Rendimiento** panel that reports object cache, LiteSpeed, and Cloudflare status.

---

## Cron

Scheduled functionality is implemented by `class-cron.php`.

Custom schedules include:

```text
10 minutes
30 minutes
```

Main scheduled hooks:

```text
wai_cron_import
wai_refresh_rankings
```

Activation registers the anime post type and taxonomies, flushes rewrite rules, and initializes scheduled importing.

Deactivation removes the import schedule and flushes rewrite rules.

---

## Ranking refresh

The daily ranking process retrieves top anime information from AniList.

Matching anime posts are updated with their current scores.

The Top 5 ranking is stored temporarily using:

```text
wai_top5_rankings
```

The trailer of the day uses:

```text
wai_trailer_of_day
```

Ranking caches have a 24-hour TTL. Homepage hero and latest caches have a 6-hour TTL. When an import occurs, all homepage caches are invalidated immediately.

---

## wp_options

| Option | Description |
|--------|-------------|
| `wai_gemini_key` | Google Gemini API key |
| `wai_gemini_api_version` | Gemini API version |
| `wai_gemini_model` | Gemini model |
| `wai_gemini_timeout` | Gemini request timeout |
| `wai_gemini_temperature` | Gemini temperature |
| `wai_gemini_max_output_tokens` | Gemini maximum output tokens |
| `wai_gemini_thinking_level` | Gemini thinking level |
| `wai_post_status` | Imported post publication status |
| `wai_cron_freq` | Import cron frequency |
| `wai_import_mode` | Automatic import mode |
| `wai_import_batch` | Import batch size |
| `wai_import_phase` | Current import phase |
| `wai_import_offset` | Current import offset |
| `wai_import_year` | Current import year (alphabetical phase) |
| `wai_imported_ids` | Registered imported AniList IDs |
| `wai_pending_reprocess` | Pending post IDs |
| `wai_cover_migration_state` | Cover migration batch state |
| `wai_home_page_id` | Configured anime homepage |
| `wai_theme_colors` | Light and dark theme configurations |
| `wai_cloudflare_config` | Cloudflare Zone ID and API Token |
| `wai_import_log` | Activity log |
| `wai_error_log` | Error log |

---

## Logs

Logs are managed through a unified administration module accessible from:

**Anime Index → Registros**

The module presents a tabbed interface:

| Tab | Storage | Purpose |
|-----|---------|---------|
| Todo | Combined | Unified timeline of all entries |
| Actividad | `wai_import_log` | Imports, cron phases, migrations, cache purges |
| Errores | `wai_error_log` | API failures from AniList, Jikan, Kitsu, and import exceptions |

The module provides:

- Live text search across messages.
- Level filter (Error, Warning, Success, Info).
- Unified table with date, level, source, and message columns.
- Clear button that operates on the active tab.
- A red badge on the admin menu that shows the current error count.

Storage limits:

| Log | Max entries |
|-----|-------------|
| `wai_import_log` | 200 |
| `wai_error_log` | 300 |

---

## AJAX endpoints

| Action | File | Auth | Description |
|--------|------|------|-------------|
| `wai_search_anime` | `class-admin.php` | Admin + nonce | Search anime from the administration interface |
| `wai_import_one` | `class-ajax.php` | Admin + nonce | Import one anime |
| `wai_import_single` | `class-admin.php` | Admin + nonce | Import a single anime |
| `wai_migrate_covers` | `class-admin.php` | Admin + nonce | Migrate anime covers |
| `wai_retry_covers` | `class-admin.php` | Admin + nonce | Retry cover retrieval |
| `wai_api_status` | `class-admin.php` | Admin + nonce | Check external API status |
| `wai_revert_anime` | `class-admin.php` | Admin + nonce | Revert one anime |
| `wai_revert_bulk` | `class-admin.php` | Admin + nonce | Revert multiple anime |
| `wai_complete_pending` | `class-admin.php` | Admin + nonce | Complete pending imports |
| `wai_purge_all_cache` | `class-cache.php` | Admin + nonce | Purge all cache layers |
| `wai_purge_single_url` | `class-cache.php` | Admin + nonce | Purge a specific URL |
| `wai_home_search` | `class-home.php` | Public | Homepage anime search |
| `wai_index_page` | `class-shortcodes.php` | Public | Directory pagination |

Administrative AJAX operations use WordPress capability and nonce validation.

---

## Cover migration

The administration interface provides a cover migration workflow for converting existing local cover references into external AniList cover URLs.

Migration processing is performed in batches.

The migration process stores both the `extraLarge` and `large` cover variants so the media layer can render `srcset` attributes.

The migration interface also provides:

- Cover retry processing.
- API status checks.
- Individual anime revert.
- Bulk anime revert.
- Pending import completion.

---

## Color system

Theme configuration is implemented by `class-theme.php`.

The plugin supports separate light and dark color configurations.

The theme system:

- Applies its color variables across the entire site, not only the plugin pages, including blog posts, archives, related posts, comments, and widgets.
- Provides mobile-specific overrides for Astra's mobile header, off-canvas menu drawer, widgets, and archive cards.
- Maps Astra's global color slots to the plugin's semantic palette to keep Astra-driven components consistent across light and dark modes.
- Handles FOUC (flash of unstyled content) by applying the stored theme before page paint.
- Uses `filemtime()` on asset files to automatically version CSS and JavaScript, forcing browsers to fetch updated files when the source changes.

The configured values are used by the frontend theme system and can be customized from:

**Anime Index → Apariencia**

The theme JavaScript is implemented by:

```text
assets/js/theme.js
```

---

## JavaScript

### `home.js`

Frontend homepage functionality.

Handles:

- Homepage search.
- Dynamic search results.
- Homepage interactions.
- Trailer lightbox.
- Homepage UI interactions.

### `index-pagination.js`

Handles anime directory pagination and asynchronous directory navigation.

### `theme.js`

Handles frontend theme switching between light and dark presentation.

### `admin-search.js`

Provides administration-side anime search interactions.

### `admin-migrate.js`

Handles administration-side cover migration and related migration controls.

### `admin-logs.js`

Provides tab switching and live filtering for the unified Logs module.

### `cpt-list.js`

Provides Custom Post Type administration list interactions.

---

## SEO

SEO functionality is implemented by `class-seo.php`.

When Yoast SEO is active, WP Anime Index integrates with Yoast filters.

When Yoast is not active, the plugin provides its own basic SEO output.

The SEO layer manages:

- Anime titles.
- Meta descriptions.
- Canonical URLs.
- Robots directives.
- Anime structured data.
- AniList resource preconnect.
- Hero image preloading via `<link rel="preload" as="image">` with `fetchpriority="high"` for LCP optimization.

---

## Structured data

Anime pages can generate JSON-LD structured data.

Supported schema types include:

```text
TVSeries
Movie
AggregateRating
VideoObject
BreadcrumbList
```

Anime structured data can use:

- Name.
- Image.
- Genres.
- Studio.
- Episodes.
- Release year.
- Score.
- Popularity.
- Trailer information.

---

## SEO filters

The SEO component integrates with WordPress and Yoast title and metadata processing.

Directory URLs containing the plugin's filtering parameters can receive `noindex` handling.

Relevant filter parameters include:

```text
wai_genre
wai_year
wai_status
wai_search
```

The plugin also adds a preconnect resource hint for:

```text
s4.anilist.co
```

when applicable to pages using the anime homepage.

The first hero image receives:

```text
fetchpriority="high"
```

---

## Performance

The plugin includes several frontend performance optimizations:

- Object cache support — homepage data is served from Redis/Memcached when available.
- Hero preloading via `<link rel="preload">` in `<head>`.
- `srcset` support for external covers, allowing mobile devices to fetch smaller images.
- Homepage cache invalidation triggered only when content actually changes.
- Targeted cache purge — imports purge only the affected URL, homepage, and directory, not the entire site.
- LiteSpeed filter exclusion for filtered directory URLs to prevent cache fragmentation.
- Automatic asset versioning using `filemtime()`.

---

## Templates

### `templates/index.php`

Main anime directory template wrapper.

### `templates/index-grid.php`

Renders the anime directory grid.

### `templates/home.php`

Renders the anime homepage structure.

### `templates/card.php`

Reusable anime card template.

### `templates/single-anime.php`

Renders individual anime content.

---

## Security

Administrative operations use standard WordPress controls including:

- `manage_options` capability checks.
- WordPress nonces.
- Input sanitization.
- Output escaping.
- WordPress HTTP APIs.
- WordPress database APIs.
- WordPress post and metadata APIs.

External API credentials are handled by the server-side plugin configuration.

---

## Development notes

The plugin follows a component-based PHP structure rather than using a framework or build system.

The main development boundaries are:

```text
CPT
API clients
Importer
Media
Homepage
Shortcodes
AJAX
Cron
Admin
Cache
Theme
SEO
```

The API classes are responsible for external data access and normalization.

The importer is responsible for converting that normalized information into WordPress content.

The templates and shortcode layer consume the resulting WordPress data for frontend presentation.

---

## Data flow

```text
AniList
   │
   ├── Anime information
   ├── Genres
   ├── Studio
   ├── Score
   ├── Popularity
   ├── Episodes
   ├── Format
   ├── Status
   ├── Season
   └── Trailer
          │
          ▼
      WAI Importer
          │
          ├── Jikan
          ├── Kitsu
          └── Gemini
          │
          ▼
     WordPress `anime`
          │
          ├── Post metadata
          ├── Taxonomies
          ├── Media
          └── Editorial content
          │
          ▼
     Cache invalidation
          │
          ▼
       Frontend
```

---

## Activation and deactivation

### Activation

The activation process:

- Registers the anime CPT.
- Registers anime taxonomies.
- Flushes rewrite rules.
- Initializes scheduled importing.

### Deactivation

The deactivation process:

- Removes the scheduled import event.
- Flushes rewrite rules.

---

## Version history

| Version | Notes |
|---------|-------|
| 1.3.0 | Unified cache management layer (LiteSpeed, Cloudflare, WP Rocket, W3TC, and 17+ systems), admin bar cache controls, object cache integration for the homepage, targeted URL cache purging, unified logs module with tabs and menu badge, complete light/dark theme coverage including mobile and Astra-specific components, automatic asset versioning via `filemtime()`, hero preload for LCP, and `srcset` support for external covers. |
| 1.2.5 | Anime directory, homepage, external API integrations, Gemini content generation, importing, media, cron, AJAX, theme, and SEO systems. |

---

*[ES] Versión en español disponible en [README.es.md](./README.es.md)*