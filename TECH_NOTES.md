# Architecture & Internals

Technical reference for the Pantheon Content Publisher Drupal module. This document is intended for developers contributing to or extending the module.

All PHP classes referenced below live under the `Drupal\pantheon_content_publisher` namespace unless otherwise noted.

## Architecture Overview

The module bridges Drupal with the Pantheon Content Publisher GraphQL API. Content lives in Content Publisher and is synced to Drupal via webhooks. The module creates local entities for display and integrates with Search API for Views-based listing and search.

```
Google Docs --> Content Publisher API --webhook POST--> Drupal Module
                       |                                     |
                  GraphQL API <--- fetch on demand <---------+
                                                             |
                                                      Search API Index
                                                             |
                                                        Drupal Views
```

Content Publisher _pushes_ webhook events to Drupal (Drupal does not poll). The module then _pulls_ content on demand via GraphQL.

## Entity Types

### PantheonDocument (Content Entity)

The main content entity representing a synced document from Content Publisher.

- **Storage**: Custom `PantheonDocumentStorage` backed by Drupal's `keyvalue` service (not standard SQL entity tables)
- **Bundles**: Dynamic -- one per `PantheonDocumentCollection`
- **Base fields**: `id`, `title`, `description`, `image`, `content` (JSON), `slug`, `status`, `created`, `changed`
- **Dynamic fields**: Auto-generated from the Pantheon metadata schema (text, textarea, date, list, boolean, file)
- **Read-only**: Content is managed in Content Publisher, not editable in Drupal

### PantheonDocumentCollection (Config Entity)

Configuration for connecting to a Content Publisher site/collection.

- **Properties**: `id` (site ID), `label`, `description`, `url` (GraphQL endpoint), `key` (token reference), `search_api_server`
- **On save**: `postSave()` auto-creates Drupal fields from the Pantheon metadata schema and sets up the Search API index
- **On delete**: Removes the associated Search API index and dynamically created fields. Documents belonging to the collection become inaccessible

### PantheonSmartComponent (Config Entity)

Defines a reusable content component type with custom fields.

- **Properties**: `id`, `title`, `icon` (media reference)
- **Acts as bundle** for `PantheonSmartInstance`

### PantheonSmartInstance (Content Entity)

Ephemeral instances of smart components embedded in document content.

- **Storage**: `ContentEntityNullStorage` -- entities exist only in memory during the current request and are never persisted to the database
- **Created on the fly** during content rendering from JSON component data

## Key Services

All classes below are in the `Drupal\pantheon_content_publisher` namespace.

| Service | Class | Role |
|---|---|---|
| `...converter` | `PantheonContentPublisherConverter` | Maps Pantheon API data to Drupal entity field values |
| `...tags_to_renderable` | `PantheonTagsToRenderable` | Converts JSON `content` field to Drupal render arrays (structured arrays that Drupal's rendering engine converts to HTML) |
| `...query` | `Query\QueryFactory` | Creates entity queries for `PantheonDocument` |
| `...event_subscriber` | `EventSubscriber\PantheonContentPublisherXFrameSubscriber` | Removes `X-Frame-Options` header for iframe preview embedding |
| `...route_subscriber` | `EventSubscriber\PantheonContentPublisherRouteSubscriber` | Provides dynamic routes for document viewing |
| `...queue_runner` | `EventSubscriber\QueueRunner` | Processes queues during the response phase |
| `...search_api_tracker` | `EventSubscriber\SearchApiTrackItemsSubscriber` | Tracks items for Search API indexing |

Service IDs are prefixed with `pantheon_content_publisher.` (abbreviated as `...` above).

## Document Sync Flow

### Webhook Processing

1. Content Publisher sends a POST to `/api/pantheoncloud/webhook` on publish/unpublish
2. Payload contains `event` (`article.publish` or `article.unpublish`) and `payload.siteId` + `payload.articleId`
3. The controller queues items for processing:
   - **Collection metadata sync**: Re-fetches field schema from GraphQL
   - **Document update/delete**: Fetches or removes the document entity
   - **Image processing**: Queues external images from the `content` field for download
4. `QueueRunner` processes queues during the response phase (deferred execution)
5. Search API index is updated via `SearchApiTrackItemsSubscriber`

**If webhook processing fails**, the collection becomes stale. Re-publish the document in Content Publisher to re-sync.

### Error Handling

- **GraphQL errors**: `GraphQL.php` throws `GraphQLException` on API failures. These are caught by the webhook controller and logged via `\Drupal::logger('pantheon_content_publisher')`
- **Webhook failures**: Logged via Drupal's database logger. Check **Reports > Recent log messages** (`/admin/reports/dblog`) and filter by type `pantheon_content_publisher`
- **Queue failures**: Failed queue items remain in the queue for retry on the next cron run. Inspect with `drush queue:list`

### Queue Workers

- **`EntityQueueWorker`** (`pantheon_content_publisher_entity`): Updates or deletes `PantheonDocument` entities, clears caches, triggers Search API tracking
- **`Images`** (`pantheon_document_images`): Downloads external images, creates file/media entities via `imagecache_external`. Images are queued because download time is unpredictable

## GraphQL Integration

**Client**: `GraphQL.php`

**Endpoint**: Configurable per collection (default: `https://gql.prod.pcc.pantheon.io`). Site-specific URL: `{base_url}/sites/{siteId}/query`

**Headers sent**:
```
PCC-SITE-ID: {collection.id}
PCC-TOKEN: {key.value}
Accept: application/graphql-response+json
Content-Type: application/json
```

**Queries**:

| Method | GraphQL Query | Purpose |
|---|---|---|
| `getMetadata()` | `site` | Fetch metadata field definitions for a collection |
| `getArticle($id, $publishingLevel, $versionId)` | `article` | Fetch a single document with optional publishing level |
| `getArticles()` | `articlesv3` | Fetch paginated document list |
| `getArticleIds($pageSize, $cursor)` | `articlesv3` | Cursor-based pagination for Search API datasource |

**Pantheon-to-Drupal field type mapping**:

| Pantheon Type | Drupal Field Type |
|---|---|
| `text` | `string` |
| `textarea` | `string_long` |
| `date` | `timestamp` |
| `list` | `list_string` |
| `boolean` | `boolean` |
| `file` | `string` |

## Content Rendering Pipeline

The `content` field stores a JSON array (Pantheon's proprietary format) that must be converted to HTML for display.

### Rendering process (`PantheonTagsToRenderable`)

1. Parse JSON into a nested object tree
2. Create a `DOMDocument` for structured HTML output
3. Generate a unique CSS scoping class (prevents Content Publisher styles from leaking into the host Drupal theme by prefixing all selectors with a unique class)
4. Recursively process nodes:
   - Text nodes become DOM text
   - Element nodes become DOM elements with attributes
   - `style` tags get the scoping class prepended to selectors
   - `component` tags create `PantheonSmartInstance` entities and render them inline
5. Serialize to HTML string
6. Return as a Drupal render array with library attachments

### Where rendering happens

| Context | Class | Purpose |
|---|---|---|
| Document page display | `Plugin\Field\FieldFormatter\PantheonTagsFormatter` | Renders the `content` field for end users |
| Search API indexing | `Plugin\search_api\processor\PantheonTags` | Converts JSON to plain text for full-text search |
| Iframe preview | `Controller\PantheonContentPublisherViewController` | Renders content with publishing level support |

## Smart Components

Smart components let site builders define reusable content blocks with custom fields that content authors can insert into documents from the Content Publisher editor.

### Setup (site builder)

1. Create a `PantheonSmartComponent` config entity at **Structure > Pantheon smart component configuration** (`/admin/structure/pantheon-smart-component`)
2. Add fields to the component via Drupal's field UI
3. Configure the component's display settings

The component schema is automatically exposed at `/api/pantheoncloud/component_schema` as JSON. Content Publisher reads this schema and presents the component in its editor UI.

### Rendering pipeline (developer)

When a document containing a component is rendered:

1. `PantheonTagsToRenderable` encounters a `{"tag": "component", "type": "component_id", "attrs": {...}}` node in the JSON
2. A `PantheonSmartInstance` entity is created in memory with the attributes as field values
3. The entity view builder renders the component using its configured display
4. The rendered HTML is injected into the document output

### Key constraints

- Components without fields are not exposed in the schema endpoint
- Instance data lives in Firebase on the Pantheon side, not in Drupal
- Rendering must track cache metadata from each component for proper cache invalidation

## Search API Integration

### Datasource (`PantheonDocumentDatasource`)

- Extends the standard `ContentEntity` datasource
- Uses cursor-based GraphQL pagination via `getArticleIds()`
- Tracks page cursors per collection per index in Drupal's `keyvalue` store (`pantheon_document.search_api_tracker`)
- Supports batch processing for large collections

### Processor (`PantheonTags`)

- Runs at the `preprocess_index` stage
- Converts the JSON `content` field to rendered HTML text before indexing
- Enables full-text search across document content

## Publishing Levels and Caching

For a user-facing summary of publishing levels, see [README.md](README.md#publishing-levels).

### Level behavior

| Level | Rendering | JS SDK | Cache max-age | Use Case |
|---|---|---|---|---|
| **PRODUCTION** | Server-side | No | Cacheable | Published, frozen content |
| **REALTIME** | Client-side (JS) | Yes (subscriptions) | 0 | Live editing preview |
| **DRAFT** | Server-side | No | 0 | Approval workflow snapshots |

### Controller behavior (`Controller\PantheonContentPublisherViewController`)

- Reads `publishingLevel` and `versionId` from URL query parameters
- **REALTIME**: Outputs an empty `<div id="pantheon-content-publisher-preview">` and attaches the `preview` JS library
- **DRAFT**: Fetches content via GraphQL with the specific `versionId`
- **PRODUCTION** (default): Standard server-side render
- All levels: `X-Frame-Options` header removed for iframe embedding

### Cache contexts

The following cache contexts ensure the correct variant is served from cache:

- `url.query_args:publishingLevel` -- varies output by publishing level
- `url.query_args:versionId` -- varies by draft version
- `user.permissions` -- varies by user role for access-controlled content

### Cache tags

- Webhook updates invalidate entity caches for the affected document, triggering re-render on next request
- `field_config_list` is invalidated when collection `postSave()` creates or updates fields
- Per-field tags (`field_config:{field_id}`) track individual field config changes

### Entity caching

- `PantheonContentPublisherConverter` uses in-memory cache for field mappings during a single request
- Entity static cache via `ContentEntityStorageBase` prevents redundant loads within a request
- Field mapping stored in Drupal's `keyvalue` store (`pantheon_document.fields`), cleared on field config changes

## Permissions

### Static

| Permission | Scope |
|---|---|
| `administer pantheon_document_collection` | Manage collections |
| `administer pantheon_document types` | Manage document types |
| `administer pantheon_smart_component` | Manage smart components |

### Dynamic (per collection)

Generated by `PantheonDocumentPermissions` for each collection:

- `view pantheon_document in {collection}`
- `create pantheon_document in {collection}`
- `edit own pantheon_document in {collection}`
- `delete own pantheon_document in {collection}`

## File Structure

```
src/
├── Controller/           # Webhook, document view, smart component API
├── Entity/               # PantheonDocument, Collection, SmartComponent, SmartInstance
├── EventSubscriber/      # X-Frame-Options, routing, queue runner, Search API tracking
├── Form/                 # Collection, document, smart component forms
├── Plugin/
│   ├── EntityReferenceSelection/  # Document reference widget
│   ├── Field/FieldFormatter/      # PantheonTagsFormatter (JSON -> HTML)
│   ├── KeyType/                   # API token key type
│   ├── QueueWorker/               # Entity sync + image download queues
│   └── search_api/                # Datasource + processor plugins
├── Query/                # Custom entity query system
├── GraphQL.php           # Pantheon GraphQL API client
├── PantheonContentPublisherConverter.php  # Pantheon -> Drupal data mapping
├── PantheonDocumentStorage.php           # Custom entity storage
├── PantheonTagsToRenderable.php          # JSON content -> HTML conversion
└── ProgressBar.php       # Setup progress indicator

config/
├── install/              # Default field storage, actions
├── hook_install/         # Media field config (installed on module enable)
└── schema/               # Config entity schema definitions

css/                      # Progress bar + preview styles
js/                       # Preview script (webpack-built from js/source/)
```
