# WP Anime Index — Developer Documentation

**Version:** 1.3.0  
**Author:** Rexdevai

This document describes the internal architecture, data model, component responsibilities, processing flows, WordPress integration points, AJAX actions, scheduled tasks, API integrations, templates, and frontend assets used by WP Anime Index.

---

## Architecture overview

WP Anime Index follows a modular PHP architecture based on dedicated classes for each major subsystem.

```text
wp-anime-index.php
        │
        ├── WAI_CPT
        ├── WAI_AniList
        ├── WAI_Jikan
        ├── WAI_Kitsu
        ├── WAI_Gemini
        ├── WAI_Media
        ├── WAI_Importer
        ├── WAI_Admin
        ├── WAI_Cache
        ├── WAI_Cron
        ├── WAI_Shortcodes
        ├── WAI_Ajax
        ├── WAI_Home
        ├── WAI_SEO
        └── WAI_Theme
```

Each component has a defined responsibility while sharing WordPress's native APIs for storage, HTTP requests, scheduled tasks, AJAX, metadata, taxonomies, and rendering.

---

## Plugin bootstrap

The main plugin file is:

```text
wp-anime-index.php
```

It defines the plugin constants and loads the component classes.

Primary constants include:

| Constant | Purpose |
|----------|---------|
| `WAI_VERSION` | Plugin version |
| `WAI_DIR` | Plugin filesystem directory |
| `WAI_URL` | Plugin URL |
| `WAI_LOG_OPT` | Import log option identifier |

The current plugin version is:

```text
1.3.0
```

The bootstrap initializes the plugin components through WordPress hooks.

The plugin also contains Gemini model migration handling so an older stored default model can be migrated to the current default model when applicable.

---

## File structure

```text
wp-anime-index/
│
├── wp-anime-index.php
│
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
│
├── templates/
│   ├── index.php
│   ├── index-grid.php
│   ├── home.php
│   ├── card.php
│   └── single-anime.php
│
└── assets/
    ├── css/
    │   ├── front.css
    │   ├── home.css
    │   ├── theme.css
    │   └── admin.css
    │
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

## Component map

| Component | File | Responsibility |
|-----------|------|----------------|
| CPT | `class-cpt.php` | Anime CPT and taxonomies |
| AniList | `class-api-anilist.php` | Primary anime data source |
| Jikan | `class-api-jikan.php` | MAL/Jikan supplementary data |
| Kitsu | `class-api-kitsu.php` | Additional cover/data source |
| Gemini | `class-api-gemini.php` | Editorial content generation |
| Media | `class-media.php` | Cover URLs and media handling |
| Importer | `class-importer.php` | Anime import pipeline |
| Admin | `class-admin.php` | Administration UI and operations |
| Cache | `class-cache.php` | Cache purging and invalidation |
| Cron | `class-cron.php` | Scheduled processing |
| Shortcodes | `class-shortcodes.php` | Frontend shortcode rendering |
| AJAX | `class-ajax.php` | Administrative AJAX operations |
| Home | `class-home.php` | Homepage data and rendering |
| SEO | `class-seo.php` | SEO and structured data |
| Theme | `class-theme.php` | Theme color configuration |

---

## Custom Post Type

The plugin registers:

```text
anime
```

The CPT is:

- Public.
- Archive-enabled.
- REST-enabled.
- Rewritten using the `anime` slug.

Supported features:

```text
title
editor
thumbnail
excerpt
```

---

## Taxonomies

The plugin registers five anime taxonomies.

| Internal name | Rewrite | Purpose |
|---------------|---------|---------|
| `anime_genre` | `genero` | Anime genres |
| `anime_studio` | `estudio` | Anime studios |
| `anime_year` | `anio` | Release years |
| `anime_status` | `estado` | Anime status |
| `anime_season` | `temporada` | Anime seasons |

All five taxonomies are public, non-hierarchical, and REST-enabled.

---

## Data model

The plugin uses the WordPress `anime` post type as its primary content model.

Anime information is distributed across:

```text
WordPress Post
    │
    ├── Post title
    ├── Content
    ├── Excerpt
    ├── Featured image
    │
    ├── Post Meta
    │
    └── Taxonomies
```

External API data is normalized before being stored in this model.

---

## Post meta

The primary anime metadata fields are:

| Meta key | Description |
|----------|-------------|
| `wai_anilist_id` | AniList ID |
| `wai_mal_id` | MyAnimeList ID |
| `wai_import_source` | Import source |
| `wai_title_romaji` | Romaji title |
| `wai_title_native` | Native title |
| `wai_episodes` | Episode count |
| `wai_duration` | Episode duration |
| `wai_score` | Internal anime score |
| `wai_popularity` | Popularity value |
| `wai_format` | Anime format |
| `wai_status` | Anime status |
| `wai_season` | Anime season |
| `wai_season_year` | Season year |
| `wai_trailer_id` | Trailer ID |
| `wai_trailer_site` | Trailer platform |
| `wai_banner` | Banner URL |
| `wai_franchise` | Franchise name |
| `wai_pending_fields` | Pending import information |
| `wai_cover_url` | External cover URL (extraLarge) |
| `wai_cover_url_large` | External cover URL (large, used for `srcset`) |

---

## Option storage

Plugin configuration is stored using WordPress options.

| Option | Purpose |
|--------|---------|
| `wai_gemini_key` | Gemini API key |
| `wai_gemini_api_version` | Gemini API version |
| `wai_gemini_model` | Gemini model |
| `wai_gemini_timeout` | Gemini timeout |
| `wai_gemini_temperature` | Gemini temperature |
| `wai_gemini_max_output_tokens` | Maximum Gemini output tokens |
| `wai_gemini_thinking_level` | Gemini thinking level |
| `wai_post_status` | Imported post status |
| `wai_cron_freq` | Import frequency |
| `wai_import_mode` | Import mode |
| `wai_import_batch` | Import batch size |
| `wai_import_phase` | Current import phase |
| `wai_import_offset` | Current import offset |
| `wai_import_year` | Alphabetical phase current year |
| `wai_imported_ids` | Registered imported AniList IDs |
| `wai_pending_reprocess` | Pending post IDs |
| `wai_cover_migration_state` | Cover migration batch state |
| `wai_home_page_id` | Configured homepage ID |
| `wai_theme_colors` | Light and dark theme colors |
| `wai_cloudflare_config` | Cloudflare Zone ID and API Token |
| `wai_import_log` | Import log |
| `wai_error_log` | Error log |

---

## Transient storage

Temporary information is stored through WordPress transients.

Main transient values include:

```text
wai_top5_rankings
wai_trailer_of_day
wai_heroes_5
wai_latest_5
```

When a persistent object cache (Redis or Memcached) is available, the homepage data layer switches to `wp_cache_*` calls under the `wai_home` group instead of transients, with the same TTL behavior. The homepage cache layer provides a `cache_get`, `cache_set`, and `cache_delete` abstraction that hides this distinction from consumers.

API-derived cover information can also be cached through transients.

---

## API layer

The plugin separates external services into dedicated API classes.

```text
class-api-anilist.php
class-api-jikan.php
class-api-kitsu.php
class-api-gemini.php
```

Each API client is responsible for communication with its respective service and for normalizing returned information where required.

---

## AniList

AniList is the primary anime data provider.

Endpoint:

```text
https://graphql.anilist.co
```

Communication uses GraphQL.

Anime queries request:

```text
isAdult: false
```

The HTTP timeout is 20 seconds.

Rate-limit responses (`429`) are handled through retry processing using `Retry-After` when available and progressive backoff.

Up to three retry attempts are supported for rate-limited requests.

---

## Jikan

Jikan is used as a supplementary anime and cover source.

Endpoint:

```text
https://api.jikan.moe/v4
```

The Jikan integration handles:

- Anime data.
- MAL identifiers.
- Cover retrieval.
- Status mapping.
- Format mapping.
- Score normalization.

Jikan scores are normalized from 0–10 to the internal 0–100 representation.

---

## Kitsu

Kitsu provides additional cover and anime lookup functionality.

Endpoint:

```text
https://kitsu.io/api/edge
```

The Kitsu client can retrieve cover information through MAL-related mappings and cache retrieved cover information.

---

## Gemini API

Gemini integration is implemented in:

```text
includes/class-api-gemini.php
```

Default configuration:

| Parameter | Value |
|-----------|-------|
| API version | `v1beta` |
| Model | `gemini-3.6-flash` |
| Timeout | `120` |
| Temperature | `0.7` |
| Max output tokens | `4096` |
| Thinking | `low` |

Endpoint:

```text
https://generativelanguage.googleapis.com/{api_version}/models/{model}:generateContent
```

The API key is read from:

```text
wai_gemini_key
```

The key is used server-side by the API client.

---

## Gemini generation flow

Gemini is called during the anime import process when editorial content needs to be generated.

```text
Anime data
    ↓
Prompt construction
    ↓
Gemini API
    ↓
Response validation
    ↓
Generated editorial content
    ↓
WordPress post content
```

The generation configuration requests Latin American Spanish editorial content of approximately 250–800 words.

The expected response:

- Uses original editorial writing.
- Does not reproduce the official synopsis.
- Avoids generic phrases.
- Uses an accessible tone.
- Explains audience and context.
- Avoids invented information.
- Ends with `¿Para quién es?`.
- Contains exactly three final bullet points.

Response validation checks the finish state and generated text.

`MAX_TOKENS` responses are treated as incomplete.

A usable response requires a `STOP` finish reason and non-empty text.

---

## Importer architecture

The importer is implemented by:

```text
includes/class-importer.php
```

Its primary responsibilities are:

- Selecting anime.
- Checking existing posts.
- Normalizing imported information.
- Calling supplementary APIs.
- Generating editorial content.
- Creating posts.
- Saving metadata.
- Saving taxonomies.
- Managing media.
- Tracking pending data.
- Recording import activity.
- Invalidating homepage cache and purging affected URLs.

---

## Import modes

Automatic import processing uses three phases:

```text
Popular
   ↓
Top-rated
   ↓
Alphabetical by year
```

The alphabetical phase starts from 1960.

The automatic batch size is 50.

Imported posts can use either:

```text
draft
publish
```

as their configured publication status.

---

## Import pipeline

```text
AniList
   │
   ▼
Normalize anime data
   │
   ▼
Check AniList/MAL duplicates
   │
   ├── Existing → existing import flow
   │
   └── New
        │
        ▼
     Jikan/Kitsu
        │
        ▼
      Gemini
        │
        ▼
    Create `anime`
        │
        ├── Metadata
        ├── Taxonomies
        ├── Media
        └── Content
        │
        ▼
    Cache invalidation
        │
        ▼
   Targeted URL purge
```

After successfully importing a post, the importer calls:

```text
WAI_Home::invalidate_all()
WAI_Cache::purge_urls([permalink, home, directory])
```

This ensures homepage data, hero, latest, Top 5, and trailer caches are cleared and the specific URLs are purged across LiteSpeed, Cloudflare, and WP Rocket (when available).

---

## Duplicate detection

The importer provides:

```text
exists()
exists_by_mal()
```

AniList IDs and MAL IDs are used to identify existing anime before a new post is created.

---

## Importer methods

The importer exposes functionality through methods including:

```text
import_single()
revert()
revert_incomplete_batch()
complete_pending()
complete_pending_batch()
process()
add_to_pending()
remove_from_pending()
reimport()
exists()
exists_by_mal()
```

---

## Pending import system

Pending information is stored through:

```text
wai_pending_fields
```

The pending workflow allows incomplete import information to remain associated with the anime and be completed through later processing.

Primary operations:

```text
add_to_pending()
remove_from_pending()
complete_pending()
complete_pending_batch()
```

---

## Franchise detection

The importer uses:

```text
detect_franchise()
```

to derive a franchise name from an anime title.

The title normalization process recognizes common continuation indicators including:

- Season.
- Part.
- Roman numerals.
- Final Season.
- Movie.
- OVA.
- Special.

The resulting franchise is stored in:

```text
wai_franchise
```

---

## Revert flow

The importer provides:

```text
revert(post_id)
```

The operation removes the corresponding anime post and thumbnail.

Bulk revert functionality is exposed through the administration interface.

---

## Media architecture

Media functionality is implemented by:

```text
includes/class-media.php
```

Cover resolution follows:

```text
wai_cover_url
      ↓
Local featured image
      ↓
Placeholder
```

If an external cover URL is unavailable, the media layer can retrieve cover information from Jikan or Kitsu using the anime's MAL ID.

When `wai_cover_url_large` is available, `cover_img()` renders the external cover with a `srcset` attribute exposing both variants:

```text
wai_cover_url_large   1x
wai_cover_url         2x
```

This allows mobile browsers to download a smaller variant when the device pixel ratio permits.

---

## Sideloading

The media class also contains the legacy:

```text
sideload()
```

method.

Supported image formats:

```text
jpg
jpeg
png
webp
```

Remote images can be downloaded into the WordPress Media Library through the WordPress media APIs.

---

## Cache architecture

Cache functionality is implemented by:

```text
includes/class-cache.php
```

The `WAI_Cache` class provides:

- `purge_all()` — purges all supported cache layers.
- `purge_urls()` — purges a specific list of URLs.
- `exclude_from_cache()` — marks filtered directory URLs as non-cacheable for LiteSpeed.
- `ajax_purge_all()` — administrative AJAX handler.
- `ajax_purge_single_url()` — administrative AJAX handler.

Supported cache layers are detected at runtime:

```text
LiteSpeed Cache
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

### Cloudflare integration

Cloudflare purging uses the `wai_cloudflare_config` option, which stores:

```text
zone_id
api_token
```

The token must have permission `Zone → Cache Purge` for the configured zone.

Total purge uses `purge_everything: true`.

Targeted purge uses the `files` array, limited to 30 URLs per request.

### Object cache integration

The homepage data layer uses `wp_using_ext_object_cache()` to detect whether a persistent object cache is available.

When available:

```text
wp_cache_get($key, 'wai_home')
wp_cache_set($key, $value, 'wai_home', $ttl)
wp_cache_delete($key, 'wai_home')
```

Otherwise, the layer falls back to transients.

Homepage cache TTLs:

| Cache | TTL |
|-------|-----|
| `heroes_5` | 6 hours |
| `latest_5` | 6 hours |
| `top5_rankings` | 24 hours |
| `trailer_of_day` | 24 hours |

---

## Shortcode architecture

Shortcodes are implemented by:

```text
includes/class-shortcodes.php
```

Available shortcodes:

```text
[anime_home]
[anime_index]
[anime_card]
```

---

## Anime directory

`[anime_index]` provides the public anime directory.

Default attributes:

```text
genre=""
year=""
status=""
per_page="20"
orderby="meta_value_num"
order="DESC"
```

The directory supports filtering through:

```text
wai_genre
wai_year
wai_status
wai_search
```

When using the default ordering, `wai_score` is used as the numeric ordering meta field.

The AJAX pagination layer limits requested page sizes to the supported range of 1–50 items.

---

## Homepage architecture

Homepage functionality is implemented by:

```text
includes/class-home.php
```

The shortcode:

```text
[anime_home]
```

builds the main frontend anime experience.

The homepage consists of:

```text
Search
Hero
Filters
Latest
Top 5
Trailer of the day
YouTube lightbox
Theme toggle
```

Homepage data access is routed through the object cache abstraction layer. When a persistent object cache is available, queries are avoided on cache hits.

---

## Homepage data

The homepage card builder exposes:

```text
id
title
url
cover
banner
score
episodes
format
status
year
trailer_id
trailer_site
excerpt
genres
studio
```

The homepage uses five hero items and five latest published anime.

---

## Homepage ranking

The ranking system uses `wai_score`.

The Top 5 result is cached using:

```text
wai_top5_rankings
```

The daily ranking process retrieves top anime information from AniList and updates matching anime scores.

---

## Trailer of the day

The homepage can select a published anime containing trailer information.

The result is cached using:

```text
wai_trailer_of_day
```

for one day.

Trailer information is used by the homepage YouTube lightbox.

---

## Homepage cache invalidation

The homepage exposes a public invalidation method:

```text
WAI_Home::invalidate_all()
```

It clears:

```text
heroes_5
latest_5
top5_rankings
trailer_of_day
```

The importer calls this method after every successful import, revert, or pending completion so the homepage reflects fresh data immediately.

---

## AJAX architecture

WP Anime Index uses WordPress AJAX actions for frontend and administration operations.

### Administration AJAX

| Action | Handler |
|--------|---------|
| `wai_search_anime` | `class-admin.php` |
| `wai_import_single` | `class-admin.php` |
| `wai_migrate_covers` | `class-admin.php` |
| `wai_retry_covers` | `class-admin.php` |
| `wai_api_status` | `class-admin.php` |
| `wai_revert_anime` | `class-admin.php` |
| `wai_revert_bulk` | `class-admin.php` |
| `wai_complete_pending` | `class-admin.php` |
| `wai_purge_all_cache` | `class-cache.php` |
| `wai_purge_single_url` | `class-cache.php` |

### General AJAX

| Action | Handler |
|--------|---------|
| `wai_search` | `class-ajax.php` |
| `wai_import_one` | `class-ajax.php` |

### Frontend AJAX

| Action | Handler |
|--------|---------|
| `wai_home_search` | `class-home.php` |
| `wai_index_page` | `class-shortcodes.php` |

Administrative actions use WordPress nonces and capability checks.

---

## Administration

Administration functionality is implemented by:

```text
includes/class-admin.php
```

The main menu is:

```text
Anime Index
```

Sections include:

```text
Configuración
Importar manual
Apariencia
Pendientes
Registros
```

Administrative functionality requires:

```text
manage_options
```

---

## Admin bar

The admin bar exposes a cache control menu for administrators:

```text
🧹 Caché WAI
   ├── 🧹 Purgar esta URL
   └── ☢️ Purga Total
```

The admin bar script is printed through both `wp_footer` and `admin_footer` so it is available on the frontend and on the backend, but only when the admin bar is being displayed and the current user has the `manage_options` capability.

`Purgar esta URL` uses `window.location.href` so it purges the currently viewed URL regardless of which page the administrator is on.

`Purga Total` triggers the same total purge as the settings page and shows a confirmation dialog.

---

## Import administration

The administration interface provides:

- Manual anime importing.
- Batch configuration.
- Publication status configuration.
- Import mode configuration.
- Scheduled import configuration.
- Pending import processing.
- Individual and bulk revert operations.

---

## Cover administration

The administration interface provides:

```text
wai_migrate_covers
wai_retry_covers
```

for cover migration and retry processing.

Migration processing handles ten items per batch.

Cover retry processing handles five items per batch with a one-second interval between items.

Both processes also store the `large` cover variant in `wai_cover_url_large` to enable `srcset` output in the media layer.

---

## API status

The administration interface provides:

```text
wai_api_status
```

for checking connectivity with:

- AniList.
- Jikan.
- Kitsu.

---

## Performance status

The administration interface provides a performance panel that reports:

- Persistent object cache availability (Redis / Memcached).
- LiteSpeed detection.
- Cloudflare configuration status.

The panel also suggests installing a persistent object cache when none is detected.

---

## Cache administration

The administration interface provides:

- Cloudflare Zone ID and API Token configuration.
- Manual total cache purge button.
- Purge result panel that reports the outcome for each detected cache layer.

The cache layer is exposed through the following AJAX actions:

```text
wai_purge_all_cache
wai_purge_single_url
```

Both actions require `manage_options` and a valid nonce.

---

## Logs

Logs are managed through the unified module:

```text
Anime Index → Registros
```

The module presents three views backed by two WordPress options:

| Tab | Storage | Purpose |
|-----|---------|---------|
| Todo | Combined | Unified timeline of all entries |
| Actividad | `wai_import_log` | Import activity, cron phases, migrations, cache purges |
| Errores | `wai_error_log` | API failures and import exceptions |

Storage limits:

| Log | Max entries |
|-----|-------------|
| `wai_import_log` | 200 |
| `wai_error_log` | 300 |

The activity log stores plain strings.

The error log stores structured entries with `ts`, `level`, `source`, and `message` fields.

The unified view parses activity strings into the same structured format used by the error log so both can be rendered in a single table.

The admin menu displays a red badge with the current error count when the error log is not empty.

---

## Cron architecture

Scheduled functionality is implemented by:

```text
includes/class-cron.php
```

Custom intervals:

```text
10 minutes
30 minutes
```

Main hooks:

```text
wai_cron_import
wai_refresh_rankings
```

---

## Cron activation flow

During activation:

```text
Register CPT
    ↓
Register taxonomies
    ↓
Flush rewrite rules
    ↓
Schedule import
```

During deactivation:

```text
Unschedule import
    ↓
Flush rewrite rules
```

---

## Daily ranking flow

```text
AniList top anime
       ↓
Match existing anime
       ↓
Update `wai_score`
       ↓
Generate Top 5 cache
       ↓
Clear trailer-of-day cache
```

The ranking refresh uses the scheduled:

```text
wai_refresh_rankings
```

hook.

---

## Theme architecture

Theme configuration is implemented by:

```text
includes/class-theme.php
```

The plugin stores independent light and dark theme color configurations.

Configuration is available from the Anime Index Appearance administration screen.

Frontend theme switching is handled by:

```text
assets/js/theme.js
```

---

## Theme scope

The theme system applies to the entire WordPress site, not only the plugin pages.

The `body_class` filter adds the `wai-theme-enabled` class on every page so that all theme CSS rules apply consistently across:

- Blog posts and archives.
- Category, tag, author, and date archives.
- Single posts.
- Comments and comment forms.
- Sidebar widgets and widget blocks.
- Related posts (Astra native, YARPP, Jetpack, Contextual Related Posts, and similar plugins).
- Blog pagination.
- Breadcrumbs.
- Astra separate container layout.

The theme system also maps Astra's global color slots to the plugin's semantic palette:

```text
--ast-global-color-0   → --wai-text
--ast-global-color-1   → --wai-accent
--ast-global-color-2   → --wai-link
--ast-global-color-3   → --wai-link
--ast-global-color-4   → --wai-surface
--ast-global-color-5   → --wai-surface
--ast-global-color-6   → --wai-surface2
--ast-global-color-7   → --wai-text-muted
--ast-global-color-8   → --wai-border
```

This mapping keeps Astra-driven components (blog cards, archive cards, meta links) consistent across light and dark modes.

---

## Mobile theme adjustments

The theme CSS includes mobile-specific overrides that target elements Astra renders differently on small screens:

- `#ast-mobile-header` and its internal wrappers.
- The mobile menu trigger and its icon.
- The off-canvas menu drawer (`#ast-mobile-popup-wrap` and its children).
- The inline mobile menu (`.main-header-menu li.menu-item` and their anchors).
- Mobile widgets and widget blocks.
- Astra archive cards and related posts on mobile.
- iOS input autofill and default `appearance` behavior.
- Safe area insets for devices with notches and dynamic islands.

---

## Theme persistence

The active theme is stored in `localStorage` under the key:

```text
wai-theme-v2
```

The `wp_head` bootstrap script reads this key before the page paints to avoid a flash of unstyled content.

The theme JavaScript sets both `data-wai-theme` and `data-theme` attributes on `<html>` and `<body>`.

---

## Asset versioning

CSS and JavaScript assets are versioned using `filemtime()` on the source file when available:

```php
$css_ver = file_exists($css_path) ? filemtime($css_path) : WAI_VERSION;
```

This forces browsers and CDN edge caches to fetch updated assets whenever the source file changes, without requiring the plugin version constant to be manually bumped.

---

## Frontend assets

### CSS

| File | Purpose |
|------|---------|
| `front.css` | General frontend anime styles |
| `home.css` | Homepage styles |
| `theme.css` | Theme and color system |
| `admin.css` | Administration interface styles |

### JavaScript

| File | Purpose |
|------|---------|
| `home.js` | Homepage interactions and search |
| `theme.js` | Theme switching |
| `index-pagination.js` | Directory pagination |
| `admin-search.js` | Administration anime search |
| `admin-migrate.js` | Cover migration controls |
| `admin-logs.js` | Tabs and filters for the unified Logs module |
| `cpt-list.js` | Anime CPT list interactions |

---

## Template architecture

The plugin uses dedicated PHP templates for frontend rendering.

| Template | Responsibility |
|----------|----------------|
| `index.php` | Directory wrapper |
| `index-grid.php` | Anime directory grid |
| `home.php` | Homepage layout |
| `card.php` | Anime card |
| `single-anime.php` | Single anime page |

The shortcode and homepage components provide the data consumed by these templates.

---

## SEO architecture

SEO functionality is implemented by:

```text
includes/class-seo.php
```

The SEO component integrates with Yoast SEO when Yoast is active.

Without Yoast, the plugin provides its own basic SEO output.

The SEO layer manages:

- Titles.
- Descriptions.
- Canonical URLs.
- Robots directives.
- Structured data.
- Resource hints.
- Hero image loading priority.

---

## Anime Schema

Anime pages can generate JSON-LD using:

```text
TVSeries
Movie
AggregateRating
VideoObject
BreadcrumbList
```

Schema information can include:

```text
name
image
genre
studio
episodes
year
score
popularity
trailer
```

The rating data uses the anime score and popularity metadata available to the plugin.

---

## SEO URL handling

Directory filtering parameters include:

```text
wai_genre
wai_year
wai_status
wai_search
```

Filtered directory URLs can receive `noindex` handling.

The SEO layer also adds a preconnect resource hint for:

```text
s4.anilist.co
```

when applicable to `[anime_home]`.

The first hero image is preloaded through `<link rel="preload" as="image">` emitted from `wp_head` with `fetchpriority="high"` for LCP optimization.

---

## WordPress hooks

The plugin integrates with WordPress through standard hooks including:

```text
plugins_loaded
init
wp_enqueue_scripts
admin_menu
admin_init
admin_bar_menu
wp_footer
admin_footer
wp_ajax_*
wp_ajax_nopriv_*
cron_schedules
```

The exact hook usage is distributed across the relevant component classes.

---

## Security model

Administrative operations use WordPress's standard security model.

The implementation uses:

```text
manage_options
```

for administrative access control and WordPress nonces for AJAX and administrative actions.

Input processing uses WordPress sanitization functions and output is escaped according to its rendering context.

External requests use WordPress HTTP APIs.

Post, metadata, options, and taxonomy operations use WordPress APIs.

Cloudflare API credentials are stored in `wai_cloudflare_config` and are only used server-side.

---

## Data flow

The complete content flow is:

```text
                    AniList
                       │
          ┌────────────┴────────────┐
          │                         │
      Anime data                Ranking data
          │
          ▼
     WAI Importer
          │
     ┌────┼────┐
     │    │    │
 Jikan Kitsu Gemini
     │    │    │
     └────┼────┘
          │
          ▼
    WordPress `anime`
          │
     ┌────┼─────────────┐
     │    │             │
  Meta Terms         Media
     │    │             │
     └────┼─────────────┘
          │
          ▼
  Cache invalidation
          │
          ▼
       Frontend
          │
     ┌────┼─────────────┐
     │    │             │
 Directory Homepage  Single
```

---

## Development workflow

The main development areas are:

```text
CPT
API clients
Importer
Media
Admin
Cache
Cron
Shortcodes
AJAX
Homepage
Theme
SEO
Templates
Assets
```

Changes to external API behavior should remain within the corresponding API client whenever possible.

Import-specific processing belongs in the importer.

Frontend presentation belongs in templates, CSS, and frontend JavaScript.

Administrative operations belong in the administration layer and its associated AJAX handlers.

Scheduled operations belong in the Cron component.

SEO behavior belongs in the SEO component.

Cache behavior belongs in the Cache component.

---

## Extension points

The architecture allows additional functionality to be implemented by extending the relevant component boundary.

Examples include:

```text
New data provider
    → New API client

New import source
    → Importer integration

New frontend view
    → Template / shortcode

New scheduled task
    → Cron component

New administrative tool
    → Admin + AJAX

New SEO output
    → SEO component

New cache layer
    → WAI_Cache::purge_all() / purge_urls()
```

This keeps external services, content processing, presentation, and administrative operations separated.

---

## Activation lifecycle

```text
Plugin activation
      ↓
CPT registration
      ↓
Taxonomy registration
      ↓
Rewrite flush
      ↓
Cron initialization
      ↓
Normal plugin execution
```

Deactivation removes the scheduled import event and flushes rewrite rules.

---

## Current version

```text
WP Anime Index 1.3.0
```

The current architecture includes the complete anime content pipeline:

```text
External APIs
    ↓
Normalization
    ↓
Import
    ↓
Content generation
    ↓
Metadata
    ↓
Taxonomies
    ↓
Media
    ↓
Cache invalidation
    ↓
Frontend
    ↓
SEO
```

---

## Related documentation

- [README](./README.md)
- [README en español](./README.es.md)