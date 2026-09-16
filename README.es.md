# WP Anime Index

**Versión:** 1.3.0  
**Autor:** Rexdevai  
**Stack:** PHP · WordPress · JavaScript / jQuery · CSS · AniList GraphQL · Jikan API · Kitsu API · Google Gemini · LiteSpeed · Cloudflare

WP Anime Index es un plugin de WordPress para crear, importar, organizar y mostrar contenido de anime mediante un Custom Post Type dedicado, proveedores externos de datos de anime, importaciones automáticas, generación de contenido editorial, búsqueda frontend, rankings, trailers, configuración visual, gestión de caché e integración SEO.

---

## Características principales

- Custom Post Type dedicado para anime.
- Taxonomías de género, estudio, año, estado y temporada.
- Integración con AniList GraphQL.
- Integración con Jikan API.
- Integración con Kitsu API.
- Generación de contenido editorial mediante Google Gemini.
- Importación manual de anime.
- Importación automática programada.
- Procesamiento de importaciones por lotes.
- Gestión de importaciones pendientes.
- Detección de duplicados mediante IDs de AniList y MAL.
- Gestión de portadas externas de anime.
- Herramientas para migración y reintento de portadas.
- Shortcode para el directorio de anime.
- Shortcode para la página principal dinámica.
- Búsqueda frontend de anime.
- Filtros y paginación del directorio.
- Ranking Top 5.
- Trailer del día.
- Lightbox para trailers de YouTube.
- Colores configurables para temas claro y oscuro.
- Control completo del tema claro/oscuro en todo el sitio, incluyendo header, footer, blog, entradas relacionadas, comentarios y widgets.
- Ajustes específicos para mobile en el header de Astra, menú off-canvas, widgets y tarjetas de archivo.
- Integración con object cache (Redis/Memcached) cuando está disponible.
- Purga total de caché en LiteSpeed, Cloudflare, transients, WP Rocket, W3 Total Cache, WP Super Cache, WP Fastest Cache, Nginx Helper, Autoptimize, Breeze, SiteGround, Kinsta, WP Engine, Hummingbird, NitroPack, Comet Cache, OPcache y object cache.
- Purga dirigida por URL desde la barra de administración.
- Integración con la API de Cloudflare para purgado de caché.
- Módulo de registros unificado con pestañas para actividad, errores y vista combinada.
- Badge en el menú que indica la cantidad de errores sin leer.
- Integración con Yoast SEO.
- Datos estructurados JSON-LD para anime.
- Títulos, descripciones y URLs canónicas SEO dinámicas.
- Gestión de `noindex` para URLs filtradas del directorio.
- Comprobación del estado de las APIs.
- Panel de monitoreo de rendimiento (object cache, LiteSpeed, Cloudflare).
- Integración AJAX de WordPress.
- Integración con WordPress Cron.

---

## Instalación

1. Sube el directorio `wp-anime-index` a `/wp-content/plugins/`, o instala el ZIP del plugin desde WordPress.
2. Activa **WP Anime Index** desde la pantalla de Plugins de WordPress.
3. Abre **Anime Index → Configuración**.
4. Configura las opciones de API e importación.
5. Configura la página principal y las opciones visuales desde **Anime Index → Apariencia**.
6. Configura el Zone ID y el API Token de Cloudflare en **Anime Index → Configuración → Cloudflare** (opcional, requerido para el purgado de caché en Cloudflare).
7. Crea una página de WordPress para el directorio de anime.
8. Añade el shortcode `[anime_index]` a la página del directorio.
9. Añade `[anime_home]` a la página destinada a mostrar la página principal de anime.

Después de la activación, el plugin registra su Custom Post Type y sus taxonomías e inicializa el sistema de importaciones programadas.

---

## Compatibilidad

WP Anime Index está construido utilizando las APIs y funcionalidades nativas de WordPress.

El plugin se integra con:

- WordPress Custom Post Types.
- Taxonomías de WordPress.
- Opciones de WordPress.
- Transients de WordPress.
- WordPress Cron.
- WordPress AJAX.
- Biblioteca multimedia de WordPress.
- WordPress HTTP API.
- Tipos de contenido compatibles con REST.
- Yoast SEO cuando está instalado y activo.
- LiteSpeed Cache cuando está instalado y activo.
- Cloudflare CDN cuando está configurado.
- Redis / Memcached cuando está disponible.
- WP Rocket, W3 Total Cache, WP Super Cache, WP Fastest Cache, Nginx Helper, Autoptimize, Breeze, SiteGround, Kinsta, WP Engine, Hummingbird, NitroPack, Comet Cache.
- AniList GraphQL API.
- Jikan API.
- Kitsu API.
- Google Gemini API.

---

## Descripción de la arquitectura

El plugin utiliza la infraestructura nativa de WordPress. Sus componentes están separados según su responsabilidad:

- **Capa CPT** — registra el Custom Post Type y las taxonomías de anime.
- **Clientes API** — obtienen y normalizan información externa.
- **Importer** — convierte los datos externos en contenido de WordPress.
- **Media** — gestiona las fuentes de portadas y los medios del anime.
- **Homepage** — construye la página principal dinámica y sus secciones almacenadas temporalmente.
- **Shortcodes** — proporciona el directorio, la página principal y las tarjetas de anime.
- **AJAX** — gestiona operaciones asíncronas del frontend y administración.
- **Cron** — gestiona importaciones programadas y actualizaciones de rankings.
- **Admin** — proporciona configuración, herramientas de importación, migración, logs y estado de APIs.
- **Cache** — gestiona la purga de caché en sistemas externos y transients internos.
- **Theme** — gestiona colores configurables y presentación clara/oscura.
- **SEO** — integra Yoast y genera datos estructurados para anime.

El plugin almacena la información mediante posts de WordPress, metadatos y taxonomías, sin utilizar un esquema de base de datos independiente.

---

## Mapa de archivos

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

## Custom post types y taxonomías

Registrados por `class-cpt.php` mediante `init`.

### CPT: `anime`

- Público.
- Con archivo habilitado.
- Disponible mediante REST.
- Slug de rewrite: `anime`.
- Soporta `title`, `editor`, `thumbnail` y `excerpt`.

### Taxonomía: `anime_genre`

Rewrite:

```text
genero
```

Utilizada para los géneros del anime.

### Taxonomía: `anime_studio`

Rewrite:

```text
estudio
```

Utilizada para los estudios del anime.

### Taxonomía: `anime_year`

Rewrite:

```text
anio
```

Utilizada para los años de lanzamiento.

### Taxonomía: `anime_status`

Rewrite:

```text
estado
```

Utilizada para los estados de lanzamiento.

### Taxonomía: `anime_season`

Rewrite:

```text
temporada
```

Utilizada para las temporadas del anime.

Las cinco taxonomías son públicas, no jerárquicas y están disponibles mediante REST.

---

## Esquema de metadatos

### En `anime`

| Meta key | Tipo | Descripción |
|----------|------|-------------|
| `wai_anilist_id` | int | Identificador del anime en AniList |
| `wai_mal_id` | int | Identificador de MyAnimeList |
| `wai_import_source` | string | Fuente utilizada durante la importación |
| `wai_title_romaji` | string | Título en romaji |
| `wai_title_native` | string | Título en idioma nativo |
| `wai_episodes` | int | Cantidad de episodios |
| `wai_duration` | int | Duración del episodio |
| `wai_score` | float | Puntuación normalizada |
| `wai_popularity` | int | Valor de popularidad |
| `wai_format` | string | Formato del anime |
| `wai_status` | string | Estado de lanzamiento |
| `wai_season` | string | Temporada |
| `wai_season_year` | int | Año de temporada |
| `wai_trailer_id` | string | Identificador del trailer |
| `wai_trailer_site` | string | Plataforma del trailer |
| `wai_banner` | string | URL de la imagen banner |
| `wai_franchise` | string | Nombre de franquicia detectado |
| `wai_pending_fields` | array/string | Información pendiente de importación |
| `wai_cover_url` | string | URL externa de la portada (extraLarge) |
| `wai_cover_url_large` | string | URL externa de la portada (large, usada para `srcset`) |

---

## Shortcodes

### `[anime_home]`

Renderiza la página principal dinámica de anime.

La página principal incluye:

- Búsqueda.
- Hero slideshow.
- Filtros de anime.
- Últimos anime.
- Ranking Top 5.
- Trailer del día.
- Lightbox de trailers de YouTube.
- Cambio de tema.

### `[anime_index]`

Renderiza el directorio de anime.

Atributos disponibles:

| Atributo | Valor predeterminado | Descripción |
|-----------|----------------------|-------------|
| `genre` | `""` | Filtro por género |
| `year` | `""` | Filtro por año |
| `status` | `""` | Filtro por estado |
| `per_page` | `20` | Anime por página |
| `orderby` | `meta_value_num` | Campo de ordenamiento |
| `order` | `DESC` | Dirección del ordenamiento |

Ejemplo:

```text
[anime_index]
```

Ejemplo con filtros:

```text
[anime_index genre="Action" year="2024" status="RELEASING"]
```

El directorio también utiliza los siguientes parámetros GET:

```text
wai_genre
wai_year
wai_status
wai_search
```

Cuando se utiliza el ordenamiento predeterminado, el directorio ordena los anime mediante el campo de metadatos `wai_score`.

### `[anime_card]`

Renderiza una tarjeta individual de anime utilizando los datos disponibles del post.

---

## Flujo de la página principal

La página principal está implementada por `class-home.php`.

```text
[anime_home]
      │
      ├── Búsqueda
      ├── Hero
      ├── Filtros
      ├── Últimos anime
      ├── Top 5
      └── Trailer del día
```

### Hero

El hero muestra cinco anime en un slideshow.

Cada elemento puede contener:

- Título.
- Portada.
- Banner.
- Puntuación.
- Información del anime.
- Información del trailer.

### Últimos anime

La sección de últimos anime obtiene cinco publicaciones de anime ordenadas por fecha.

### Ranking Top 5

El ranking de la página principal utiliza `wai_score`.

El ranking generado se almacena temporalmente mediante:

```text
wai_top5_rankings
```

### Trailer del día

La sección de trailer selecciona un anime publicado que tenga información de trailer.

El resultado se almacena temporalmente mediante:

```text
wai_trailer_of_day
```

durante un día.

---

## Búsqueda de la página principal

La búsqueda de la página principal utiliza la acción AJAX pública:

```text
wai_home_search
```

La búsqueda devuelve hasta ocho anime publicados.

Cada resultado contiene:

- ID del post.
- Título.
- URL.
- Portada.
- Puntuación.
- Año.

---

## Datos de las tarjetas de anime

El constructor de tarjetas de la página principal proporciona:

- ID.
- Título.
- URL.
- Portada.
- Banner.
- Puntuación.
- Episodios.
- Formato.
- Estado.
- Año.
- ID del trailer.
- Sitio del trailer.
- Extracto.
- Géneros.
- Estudio.

El extracto generado está limitado aproximadamente a 20 palabras.

---

## API de AniList

AniList es la fuente externa principal de datos de anime.

Endpoint:

```text
https://graphql.anilist.co
```

El cliente de AniList utiliza consultas GraphQL y solicita:

```text
isAdult: false
```

El timeout HTTP es de 20 segundos.

Las respuestas HTTP 429 se gestionan mediante reintentos utilizando `Retry-After` cuando está disponible y un sistema de espera progresiva.

Se pueden realizar hasta tres intentos para solicitudes limitadas por tasa.

---

## API de Jikan

Jikan proporciona información complementaria y recuperación de portadas.

Endpoint:

```text
https://api.jikan.moe/v4
```

Los datos de Jikan se utilizan para:

- Recuperación de portadas.
- Información relacionada con MAL.
- Información complementaria del anime.
- Mapeo de estados.
- Mapeo de formatos.
- Normalización de puntuaciones.

Las puntuaciones de Jikan utilizan una escala de 0–10 y se normalizan a la escala interna de 0–100.

---

## API de Kitsu

Kitsu proporciona una fuente adicional para portadas y soporte de búsquedas relacionadas con MAL.

Endpoint:

```text
https://kitsu.io/api/edge
```

La información de portadas recuperada puede almacenarse temporalmente mediante transients de WordPress.

---

## Google Gemini

La integración con Gemini está implementada por `class-api-gemini.php`.

Configuración predeterminada:

| Configuración | Valor |
|---------------|-------|
| Versión API | `v1beta` |
| Modelo | `gemini-3.6-flash` |
| Timeout | `120` |
| Temperature | `0.7` |
| Máximo de tokens | `4096` |
| Thinking | `low` |

Formato del endpoint:

```text
https://generativelanguage.googleapis.com/{api_version}/models/{model}:generateContent
```

La API key se almacena en:

```text
wai_gemini_key
```

El campo correspondiente en la administración utiliza un input de tipo password.

---

## Generación de contenido con Gemini

Gemini se utiliza para generar contenido editorial de anime durante las importaciones.

El prompt solicita contenido editorial en español latinoamericano con una longitud aproximada de 250–800 palabras.

El artículo generado debe:

- Proporcionar texto editorial original.
- Evitar copiar la sinopsis oficial.
- Evitar introducciones genéricas.
- Utilizar un tono editorial accesible.
- Explicar el público objetivo.
- Proporcionar información cultural o contextual relevante.
- Evitar inventar hechos.
- Terminar con `¿Para quién es?`.
- Contener exactamente tres elementos en la sección final.
- Generar un artículo completo.

La respuesta se valida antes de almacenarse.

Una respuesta con `MAX_TOKENS` se considera incompleta.

Una respuesta válida requiere un motivo de finalización `STOP` y texto generado no vacío.

---

## Importador

El importador está implementado por `class-importer.php`.

La importación automática utiliza tres fases:

```text
1. Popular
2. Mejor valorados
3. Alfabético por año
```

La fase alfabética comienza en 1960.

El tamaño predeterminado del lote automático es 50.

El estado de publicación puede configurarse como:

```text
draft
publish
```

Después de cada importación, el plugin:

- Invalida los datos en caché de la página principal.
- Purga el permalink del anime, la página principal y el directorio en LiteSpeed, Cloudflare y WP Rocket (cuando están disponibles).

---

## Flujo de importación

```text
Datos externos del anime
        ↓
Normalización de datos
        ↓
Detección de duplicados
        ↓
Datos complementarios de APIs
        ↓
Contenido editorial de Gemini
        ↓
Creación del post anime
        ↓
Metadatos
        ↓
Taxonomías
        ↓
Media
        ↓
Invalidación de caché
        ↓
Log de importación
```

La detección de duplicados utiliza los identificadores de AniList y MAL antes de crear un nuevo post.

---

## Métodos del importador

El importador proporciona métodos para:

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

## Detección de franquicias

`detect_franchise()` identifica un nombre común de franquicia a partir del título del anime.

El proceso de normalización reconoce indicadores de continuación y lanzamiento como:

- Temporadas.
- Partes.
- Números romanos.
- Final Season.
- Películas.
- OVAs.
- Specials.

El valor resultante se almacena en:

```text
wai_franchise
```

---

## Importaciones pendientes

El importador puede almacenar información pendiente mediante:

```text
wai_pending_fields
```

Los registros pendientes pueden procesarse posteriormente desde la interfaz de administración.

Las operaciones disponibles incluyen:

- Añadir a pendientes.
- Eliminar de pendientes.
- Completar pendientes.
- Completar lote de pendientes.

---

## Sistema de medios

La funcionalidad multimedia está implementada por `class-media.php`.

El orden principal de resolución de portadas es:

```text
wai_cover_url
      ↓
Imagen destacada local
      ↓
Placeholder
```

Cuando no existe una URL externa de portada, el sistema multimedia puede consultar Jikan y Kitsu utilizando el MAL ID almacenado.

El método `cover_img()` renderiza portadas externas con soporte de `srcset` cuando el metadato `wai_cover_url_large` está disponible, permitiendo que los navegadores móviles descarguen una variante más pequeña cuando corresponde.

El método heredado `sideload()` puede descargar imágenes remotas a la Biblioteca Multimedia de WordPress.

Formatos soportados por el proceso de sideload:

- JPG.
- JPEG.
- PNG.
- WebP.

---

## Sistema de caché

La gestión de caché está implementada por `class-cache.php` a través de la clase `WAI_Cache`.

La capa de caché proporciona:

- **Purga total de caché** — limpia todas las capas de caché soportadas en todo el sitio.
- **Purga dirigida por URL** — limpia solo una URL específica en las capas de caché soportadas.
- **Integración con la API de Cloudflare** — purga vía `purge_everything` o por lista explícita de archivos.
- **Exclusión de filtros en LiteSpeed** — evita que LiteSpeed cachee URLs filtradas del directorio (`?wai_genre=`, `?wai_search=`, `?wai_year=`, `?wai_status=`).
- **Integración con object cache** — utiliza `wp_cache_*` automáticamente cuando Redis o Memcached está disponible; en caso contrario, recurre a transients.

El purgado de caché soporta, en orden:

```text
LiteSpeed
Cloudflare
Transients de WordPress
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

Cada capa se detecta en tiempo de ejecución y se omite si no está disponible.

---

## Administración de caché

La interfaz de administración y la barra de administración de WordPress proporcionan controles de purgado de caché.

### Barra de administración

La barra de administración expone un menú dedicado de caché:

```text
🧹 Caché WAI
   ├── 🧹 Purgar esta URL
   └── ☢️ Purga Total
```

- **Purgar esta URL** — purga únicamente la URL que se está visualizando en las capas de caché soportadas.
- **Purga Total** — purga toda la caché del sitio con un diálogo de confirmación que advierte sobre el impacto en sitios de alto tráfico.

### Página de configuración

**Anime Index → Configuración** proporciona:

- Una sección **Cloudflare** de configuración (Zone ID + API Token).
- Una sección **Purga Total** con botón de disparo manual.
- Un panel **Rendimiento** que reporta el estado de object cache, LiteSpeed y Cloudflare.

---

## Cron

La funcionalidad programada está implementada por `class-cron.php`.

Los intervalos personalizados incluyen:

```text
10 minutos
30 minutos
```

Hooks principales:

```text
wai_cron_import
wai_refresh_rankings
```

La activación registra el CPT y las taxonomías, actualiza las reglas de rewrite e inicializa las importaciones programadas.

La desactivación elimina la programación de importación y actualiza las reglas de rewrite.

---

## Actualización del ranking

El proceso diario de ranking obtiene información de los anime mejor posicionados desde AniList.

Los posts de anime coincidentes se actualizan con sus puntuaciones actuales.

El Top 5 se almacena temporalmente mediante:

```text
wai_top5_rankings
```

El trailer del día utiliza:

```text
wai_trailer_of_day
```

Las cachés de ranking tienen un TTL de 24 horas. Las cachés del hero y de últimas incorporaciones tienen un TTL de 6 horas. Cuando ocurre una importación, todas las cachés de la página principal se invalidan inmediatamente.

---

## wp_options

| Option | Descripción |
|--------|-------------|
| `wai_gemini_key` | API key de Google Gemini |
| `wai_gemini_api_version` | Versión de la API de Gemini |
| `wai_gemini_model` | Modelo de Gemini |
| `wai_gemini_timeout` | Timeout de las solicitudes Gemini |
| `wai_gemini_temperature` | Temperature de Gemini |
| `wai_gemini_max_output_tokens` | Máximo de tokens de salida de Gemini |
| `wai_gemini_thinking_level` | Nivel de thinking de Gemini |
| `wai_post_status` | Estado de publicación de los posts importados |
| `wai_cron_freq` | Frecuencia del cron de importación |
| `wai_import_mode` | Modo de importación automática |
| `wai_import_batch` | Tamaño del lote de importación |
| `wai_import_phase` | Fase actual del importador |
| `wai_import_offset` | Offset actual del importador |
| `wai_import_year` | Año actual del importador (fase alfabética) |
| `wai_imported_ids` | IDs de AniList importados registrados |
| `wai_pending_reprocess` | IDs de posts pendientes |
| `wai_cover_migration_state` | Estado del lote de migración de portadas |
| `wai_home_page_id` | Página principal configurada |
| `wai_theme_colors` | Configuraciones de tema claro y oscuro |
| `wai_cloudflare_config` | Zone ID y API Token de Cloudflare |
| `wai_import_log` | Log de actividad |
| `wai_error_log` | Log de errores |

---

## Registros

Los registros se gestionan a través de un módulo de administración unificado accesible desde:

**Anime Index → Registros**

El módulo presenta una interfaz con pestañas:

| Pestaña | Almacenamiento | Propósito |
|---------|----------------|-----------|
| Todo | Combinado | Línea de tiempo unificada de todas las entradas |
| Actividad | `wai_import_log` | Importaciones, fases del cron, migraciones, purgas de caché |
| Errores | `wai_error_log` | Fallos de APIs de AniList, Jikan, Kitsu y excepciones de importación |

El módulo proporciona:

- Búsqueda de texto en vivo sobre los mensajes.
- Filtro por nivel (Error, Advertencia, Éxito, Info).
- Tabla unificada con columnas de fecha, nivel, fuente y mensaje.
- Botón de limpieza que opera sobre la pestaña activa.
- Un badge rojo en el menú de administración que muestra la cantidad actual de errores.

Límites de almacenamiento:

| Log | Máximo de entradas |
|-----|---------------------|
| `wai_import_log` | 200 |
| `wai_error_log` | 300 |

---

## Endpoints AJAX

| Action | Archivo | Auth | Descripción |
|--------|---------|------|-------------|
| `wai_search_anime` | `class-admin.php` | Admin + nonce | Buscar anime desde administración |
| `wai_import_one` | `class-ajax.php` | Admin + nonce | Importar un anime |
| `wai_import_single` | `class-admin.php` | Admin + nonce | Importar un anime individual |
| `wai_migrate_covers` | `class-admin.php` | Admin + nonce | Migrar portadas |
| `wai_retry_covers` | `class-admin.php` | Admin + nonce | Reintentar recuperación de portadas |
| `wai_api_status` | `class-admin.php` | Admin + nonce | Comprobar estado de APIs |
| `wai_revert_anime` | `class-admin.php` | Admin + nonce | Revertir un anime |
| `wai_revert_bulk` | `class-admin.php` | Admin + nonce | Revertir múltiples anime |
| `wai_complete_pending` | `class-admin.php` | Admin + nonce | Completar importaciones pendientes |
| `wai_purge_all_cache` | `class-cache.php` | Admin + nonce | Purgar todas las capas de caché |
| `wai_purge_single_url` | `class-cache.php` | Admin + nonce | Purgar una URL específica |
| `wai_home_search` | `class-home.php` | Público | Búsqueda de anime de la página principal |
| `wai_index_page` | `class-shortcodes.php` | Público | Paginación del directorio |

Las operaciones AJAX administrativas utilizan validación de capacidades y nonces de WordPress.

---

## Migración de portadas

La interfaz de administración proporciona un flujo de migración para convertir referencias de portadas locales existentes en URLs externas de portadas de AniList.

El procesamiento de la migración se realiza por lotes.

El proceso de migración almacena tanto la variante `extraLarge` como la variante `large` de la portada, de modo que la capa multimedia pueda renderizar atributos `srcset`.

La interfaz también proporciona:

- Reintento de recuperación de portadas.
- Comprobación del estado de las APIs.
- Reversión individual de anime.
- Reversión masiva de anime.
- Finalización de importaciones pendientes.

---

## Sistema de colores

La configuración visual está implementada por `class-theme.php`.

El plugin permite configurar colores independientes para los temas claro y oscuro.

El sistema de tema:

- Aplica sus variables de color en todo el sitio, no solo en las páginas del plugin, incluyendo entradas de blog, archivos, entradas relacionadas, comentarios y widgets.
- Proporciona overrides específicos para mobile en el header móvil de Astra, el menú off-canvas, widgets y tarjetas de archivo.
- Mapea los slots de color globales de Astra a la paleta semántica del plugin para mantener los componentes de Astra consistentes entre los modos claro y oscuro.
- Maneja el FOUC (flash de contenido sin estilos) aplicando el tema almacenado antes del paint.
- Utiliza `filemtime()` sobre los archivos de assets para versionar automáticamente CSS y JavaScript, forzando a los navegadores a obtener archivos actualizados cuando la fuente cambia.

Los valores configurados se utilizan en el sistema visual del frontend y pueden modificarse desde:

**Anime Index → Apariencia**

El JavaScript relacionado con el tema está implementado en:

```text
assets/js/theme.js
```

---

## JavaScript

### `home.js`

Funcionalidad frontend de la página principal.

Gestiona:

- Búsqueda de anime.
- Resultados dinámicos.
- Interacciones de la página principal.
- Lightbox de trailers.
- Interacciones de la interfaz.

### `index-pagination.js`

Gestiona la paginación y navegación asíncrona del directorio de anime.

### `theme.js`

Gestiona el cambio entre la presentación clara y oscura.

### `admin-search.js`

Proporciona las interacciones de búsqueda de anime en administración.

### `admin-migrate.js`

Gestiona las operaciones administrativas de migración de portadas.

### `admin-logs.js`

Proporciona el cambio de pestañas y el filtrado en vivo del módulo unificado de Registros.

### `cpt-list.js`

Proporciona interacciones para las listas administrativas del Custom Post Type.

---

## SEO

La funcionalidad SEO está implementada por `class-seo.php`.

Cuando Yoast SEO está activo, WP Anime Index se integra mediante los filtros de Yoast.

Cuando Yoast no está activo, el plugin proporciona su propia salida SEO básica.

La capa SEO gestiona:

- Títulos de anime.
- Meta descriptions.
- URLs canónicas.
- Directivas robots.
- Datos estructurados de anime.
- Preconnect para recursos de AniList.
- Precarga de la imagen principal del hero mediante `<link rel="preload" as="image">` con `fetchpriority="high"` para optimización de LCP.

---

## Datos estructurados

Las páginas de anime pueden generar datos estructurados JSON-LD.

Los tipos de Schema soportados incluyen:

```text
TVSeries
Movie
AggregateRating
VideoObject
BreadcrumbList
```

Los datos estructurados pueden utilizar:

- Nombre.
- Imagen.
- Géneros.
- Estudio.
- Episodios.
- Año de lanzamiento.
- Puntuación.
- Popularidad.
- Información del trailer.

---

## Filtros SEO

El componente SEO se integra con WordPress y con el procesamiento de títulos y metadatos de Yoast.

Las URLs del directorio que contienen parámetros de filtrado del plugin pueden recibir tratamiento `noindex`.

Los parámetros relevantes incluyen:

```text
wai_genre
wai_year
wai_status
wai_search
```

El plugin también añade un recurso preconnect para:

```text
s4.anilist.co
```

cuando corresponde a páginas que utilizan la página principal de anime.

La primera imagen del hero recibe:

```text
fetchpriority="high"
```

---

## Rendimiento

El plugin incluye varias optimizaciones de rendimiento en el frontend:

- Soporte de object cache — los datos de la página principal se sirven desde Redis/Memcached cuando está disponible.
- Precarga del hero mediante `<link rel="preload">` en `<head>`.
- Soporte de `srcset` para portadas externas, permitiendo que los dispositivos móviles obtengan imágenes más pequeñas.
- Invalidación de caché de la página principal disparada solo cuando el contenido cambia realmente.
- Purga dirigida de caché — las importaciones purgan solo la URL afectada, la página principal y el directorio, no todo el sitio.
- Exclusión de filtros en LiteSpeed para URLs filtradas del directorio, previniendo la fragmentación de caché.
- Versionado automático de assets usando `filemtime()`.

---

## Templates

### `templates/index.php`

Template principal del directorio de anime.

### `templates/index-grid.php`

Renderiza la cuadrícula del directorio.

### `templates/home.php`

Renderiza la estructura de la página principal.

### `templates/card.php`

Template reutilizable para tarjetas de anime.

### `templates/single-anime.php`

Renderiza el contenido individual de un anime.

---

## Seguridad

Las operaciones administrativas utilizan controles estándar de WordPress, incluyendo:

- Comprobaciones de capacidad `manage_options`.
- Nonces de WordPress.
- Sanitización de entradas.
- Escape de salidas.
- WordPress HTTP API.
- APIs de base de datos de WordPress.
- APIs de posts y metadatos de WordPress.

Las credenciales de APIs externas son gestionadas mediante la configuración del plugin en el servidor.

---

## Notas de desarrollo

El plugin utiliza una estructura PHP basada en componentes y no depende de un framework ni de un sistema de build.

Las principales áreas de desarrollo son:

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

Los clientes API son responsables de acceder y normalizar los datos externos.

El importer convierte esa información normalizada en contenido de WordPress.

La capa de shortcodes y templates utiliza los datos almacenados en WordPress para la presentación frontend.

---

## Flujo de datos

```text
AniList
   │
   ├── Información del anime
   ├── Géneros
   ├── Estudio
   ├── Puntuación
   ├── Popularidad
   ├── Episodios
   ├── Formato
   ├── Estado
   ├── Temporada
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
          ├── Metadatos
          ├── Taxonomías
          ├── Media
          └── Contenido editorial
          │
          ▼
   Invalidación de caché
          │
          ▼
       Frontend
```

---

## Activación y desactivación

### Activación

El proceso de activación:

- Registra el CPT de anime.
- Registra las taxonomías.
- Actualiza las reglas de rewrite.
- Inicializa la importación programada.

### Desactivación

El proceso de desactivación:

- Elimina el evento programado de importación.
- Actualiza las reglas de rewrite.

---

## Historial de versiones

| Versión | Notas |
|---------|-------|
| 1.3.0 | Capa unificada de gestión de caché (LiteSpeed, Cloudflare, WP Rocket, W3TC y más de 17 sistemas), controles de caché en la barra de administración, integración con object cache para la página principal, purga dirigida por URL, módulo unificado de registros con pestañas y badge en el menú, cobertura completa del tema claro/oscuro incluyendo mobile y componentes específicos de Astra, versionado automático de assets mediante `filemtime()`, precarga del hero para LCP y soporte de `srcset` para portadas externas. |
| 1.2.5 | Directorio de anime, página principal, integraciones con APIs externas, generación de contenido mediante Gemini, importación, media, cron, AJAX, tema y SEO. |

---

*[EN] English version available in [README.md](./README.md)*