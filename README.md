# Pantheon Content Publisher for Drupal

A Drupal module that integrates [Pantheon Content Publisher](https://content.pantheon.io) with your Drupal site, enabling content teams to publish directly from Google Docs to Drupal.

## How It Works

1. Content authors write in Google Docs using the Content Publisher add-on
2. On publish, Content Publisher sends the content to its API
3. A webhook notifies your Drupal site of the update
4. The module fetches the content via GraphQL, creates Drupal entities, and indexes them via Search API
5. Content is viewable at configurable Drupal paths with full Views integration

The module also supports **Smart Components** -- reusable content blocks with custom fields that site builders define in Drupal and content authors insert from the Content Publisher editor. See [TECH_NOTES.md](TECH_NOTES.md#smart-components) for details.

## Requirements

- **Drupal**: 10.x or 11.x
- **PHP**: 8.2+
- **Google Workspace**: Paid account (personal Gmail is limited to the [playground](https://content.pantheon.io))
- **Content Publisher access**: [Create your account](https://content.pantheon.io) and install the [Google Docs add-on](https://docs.content.pantheon.io/quickstart)
- **Search backend**: Any [Search API](https://www.drupal.org/project/search_api) backend (e.g., [Database Search](https://www.drupal.org/project/search_api_db), [Solr](https://www.drupal.org/project/search_api_solr)). For Pantheon-hosted sites, use [Search API Pantheon](https://www.drupal.org/project/search_api_pantheon)

## Installation

Install the module via Composer:

```bash
composer require drupal/pantheon_content_publisher:"^1.0"
```

Then enable the module at **Extend** (`/admin/modules`).

### Pantheon-Hosted Sites

If your site is hosted on Pantheon, also install the Search API Pantheon module and enable Solr:

```bash
composer require drupal/search_api_pantheon:^8
```

Add the following to your `pantheon.yml`:

```yaml
search:
  version: 8
```

Push `composer.json`, `composer.lock`, and `pantheon.yml` to your Pantheon environment before enabling the modules.

## Configuration

### 1. Create an Access Token

In the [Content Publisher Dashboard](https://content.pantheon.io), go to **Settings > Tokens** and generate a read-only access token. Save it for the next step.

> For production environments, use [Pantheon's Secrets Manager](https://docs.pantheon.io/guides/secrets) instead of storing tokens directly.

### 2. Create a Collection

In the Content Publisher Dashboard:

- Create a new Collection and associate it with your Drupal site's URL
- Set the webhook URL to: `https://your-site.com/api/pantheoncloud/webhook`
- Copy the **Collection Identifier** (you'll need it below)

### 3. Configure the Drupal Module

Navigate to **Structure > Pantheon Content Publisher collections** (`/admin/structure/pantheon-content-publisher-collection`):

1. Click **Add collection**
2. Enter the access token from step 1
3. Paste the **Collection Identifier** from the Content Publisher Dashboard
4. Select a Search API server (auto-populated on Pantheon)
5. Click **Save**

Saving the collection automatically creates Drupal fields from the Pantheon metadata schema and sets up a Search API index.

### 4. Publish Content

1. Open a Google Doc
2. Click the Content Publisher add-on icon
3. Connect to your Collection
4. Click **Publish**

The content appears on your Drupal site at `/pantheon-content-publisher/{id}`. Use Views to build custom listing pages from the Search API index.

If content does not appear, verify the webhook URL in the Content Publisher Dashboard and check **Reports > Recent log messages** (`/admin/reports/dblog`) for errors.

## Publishing Levels

The module supports three publishing levels, selectable via the `publishingLevel` query parameter:

| Level | Description |
|---|---|
| **PRODUCTION** | Published, frozen snapshot of the content |
| **REALTIME** | Live state, updates as the document changes in Content Publisher |
| **DRAFT** | Arbitrary snapshot for approval workflow review |

Example: `https://your-site.com/api/pantheoncloud/document/{id}?publishingLevel=DRAFT&versionId=xyz`

The `versionId` parameter identifies a specific DRAFT snapshot. Find version IDs in the Content Publisher Dashboard under the document's approval workflow history.

## Module Dependencies

| Package | Purpose |
|---|---|
| `drupal/search_api` | Full-text search and Views integration |
| `drupal/imagecache_external` | Caching of external images from documents |
| `drupal/key` | Secure API token storage |
| `dpauli/graphql-request-builder` | GraphQL query construction |
| `drupal:media` | Media entity support for images |

## API Endpoints

| Endpoint | Purpose |
|---|---|
| `/api/pantheoncloud/webhook` | Receives publish/unpublish events from Content Publisher |
| `/api/pantheoncloud/status` | Health check |
| `/api/pantheoncloud/document/{id}` | View a document (supports `publishingLevel` and `versionId` params) |
| `/api/pantheoncloud/component_schema` | List smart component schemas (JSON) |
| `/api/pantheoncloud/component/{id}` | Render a smart component |

## Development

### Code Quality

```bash
# Lint PHP syntax
composer code:lint

# Run PHPCS with Drupal coding standards
composer codesniff

# Auto-fix coding standard issues
composer code:fix
```

## Documentation

- [Drupal Getting Started Tutorial](https://docs.content.pantheon.io/drupal-tutorial)
- [Content Publisher Quickstart](https://docs.content.pantheon.io/quickstart)
- [Architecture & Internals](TECH_NOTES.md)
- [Content Publisher Dashboard](https://content.pantheon.io)

## Feedback and Collaboration

Report bugs and request features in the [GitHub repository](https://github.com/pantheon-systems/pantheon-content-publisher-drupal). For code changes, submit pull requests against GitHub rather than drupal.org.
