<?php

declare(strict_types=1);

namespace Drupal\Tests\pantheon_content_publisher\Kernel;

use Drupal\Component\Utility\NestedArray;
use Drupal\key\Entity\Key;
use Drupal\pantheon_content_publisher\PantheonDocumentCollectionInterface;
use Drupal\search_api\Entity\Index;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\HttpFoundation\Request;

/**
 * Test trait to set up a document test.
 *
 * When this trait is used, PantheonContentDocumentTestInterface must be
 * implemented as that interface holds the constants.
 */
trait PantheonDocumentTestTrait {

  /**
   * Guzzle responses keyed by GraphQL queries.
   *
   * @var array
   */
  protected array $storage = [];

  protected string $articleContent = 'test content';

  protected PantheonDocumentCollectionInterface $collection;

  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['search_api', 'system']);
    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('search_api_task');
    Key::create(['id' => 'test', 'key_provider' => 'config'])->save();
    // Create a collection, this also creates a search API index and puts all
    // items in it. Do not save it yet, though because saving triggers GraphQL
    // queries and the GraphQL responses need the collection id, so collection
    // create comes first, then Guzzle response setup then collection save.
    $this->collection = $this->container->get('entity_type.manager')->getStorage('pantheon_document_collection')->create([
      'id' => $this->randomMachineName(),
      'label' => $this->randomString(),
      'key' => 'test',
      'url' => $this->randomMachineName(),
      'search_api_server' => 'default_server',
    ]);
    // Store the replies for every GraphQL query.
    foreach (array_keys(static::QUERIES) as $method) {
      $this->setGuzzleResponse($method);
    }
    // Then create the mock object.
    $mock = $this->getMockBuilder(Client::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['get', 'post'])
      ->getMock();
    $mock->method('post')->willReturnCallback(function ($uri, array $options) {
      $query = json_decode($options['body'])->query;
      $query = preg_replace('/\s+/', ' ', trim($query));
      if (!isset($this->storage[$query])) {
        throw new \Exception(sprintf('Query not mocked: %s', $query));
      }
      return new Response(200, [], $this->storage[$query]);
    });
    $mock->method('get')->willReturnCallback(fn() => new Response(200, [], $this->storage['get'] ?? ''));
    $this->container->set('http_client', $mock);
    // Finally, save the collection.
    $this->collection->save();
  }

  /**
   * Updates an article in Pantheon.
   *
   * @return string
   *   The new value of the field.
   */
  protected function updateArticleInPantheon(array $parents = ['metadata', 'A textarea meta'], string $newValue = '', bool $triggerWebhook = TRUE): string {
    if (!$newValue) {
      $newValue = $this->randomString();
    }
    $this->setGuzzleResponse('getArticle', fn (&$article) => NestedArray::setValue($article, $parents, $newValue));
    if ($triggerWebhook) {
      $this->executeWebhook();
    }
    return $newValue;
  }

  /**
   * Sets a Guzzle response.
   *
   * @param string $method
   *   The method on this class, the only valid methods are the keys of
   *   the ::QUERIES constant. The return value of this method -- optionally
   *   changed by a callable -- is stored as the Guzzle response to the query
   *   specified in the ::QUERIES constant for this method.
   * @param ?callable $callable
   *   A callable to change the data.
   */
  protected function setGuzzleResponse(string $method, ?callable $callable = NULL): void {
    $query = static::QUERIES[$method];
    $type = static::QUERY_TYPES[$method] ?? 'articlesv3';
    if (str_contains($query, '%')) {
      $query = sprintf($query, $type === 'site' ? $this->collection->id() : static::ARTICLE_ID);
    }
    $data = $this->$method();
    if ($callable) {
      $callable($data, $query);
    }
    $query = preg_replace('/\s+/', ' ', trim($query));
    $this->storage[$query] = json_encode(['data' => [$type => $data]]);
  }

  /**
   * Retrieve the value Search API stores for a given field.
   *
   * This is a very stupid helper, only usable if there is only one document
   * in search API.
   *
   * @param string $field
   *   Name of the field.
   *
   * @return string
   *   The search API stored value.
   */
  protected function getSearchAPIvalue(string $field): string {
    return $this->container->get('database')
      ->select(sprintf("search_api_db_%s_%s", strtolower($this->collection->id()), $field), 's')
      ->fields('s', ['value'])
      ->execute()
      ->fetchField();
  }

  protected function executeWebhook(): void {
    // Reset entity cache so the queue worker fetches fresh data from GraphQL.
    // In production, webhooks arrive as separate HTTP requests with an empty
    // memory cache; in tests everything runs in one process.
    $this->container->get('entity_type.manager')->getStorage('pantheon_document')->resetCache();
    $content = [
      'event' => 'article.update',
      'payload' => [
        'articleId' => self::ARTICLE_ID,
        'siteId' => $this->collection->id(),
      ],
    ];
    $request = Request::create('/api/pantheoncloud/webhook', 'POST', content: json_encode($content));
    $this->handle($request);
    // Process the queue items created by the webhook.
    $queue = $this->container->get('queue')->get('pantheon_content_publisher_entity');
    $queue_worker = $this->container->get('plugin.manager.queue_worker')->createInstance('pantheon_content_publisher_entity');
    while ($item = $queue->claimItem()) {
      $queue_worker->processItem($item->data);
      $queue->deleteItem($item);
    }
    // Re-index Search API items. The index does not use index_directly so
    // items are only marked for re-indexing during entity save, not actually
    // re-indexed until indexItems() is called.
    foreach (Index::loadMultiple() as $index) {
      $index->indexItems();
    }
  }

  protected function metadata(): array {
    return [
    'metadataFields' => [
      'A boolean meta' => [
        'title' => 'A boolean meta',
        'type' => 'boolean',
      ],
      'A date meta' => [
        'title' => 'A date meta',
        'type' => 'date',
      ],
      'A file meta' => [
        'title' => 'A file meta',
        'type' => 'file',
      ],
      'A list meta' => [
        'options' => [
          0 => 'Option a',
          1 => 'Option b',
          2 => 'Option c',
        ],
        'title' => 'A list meta',
        'type' => 'list',
      ],
      'A text meta' => [
        'title' => 'A text meta',
        'type' => 'text',
      ],
      'A textarea meta' => [
        'title' => 'A textarea meta',
        'type' => 'textarea',
      ],
    ],
];
  }

  protected function getArticleIds(): array {
    return [
      'articles' => [
        ['id' => self::ARTICLE_ID],
      ],
      "pageInfo" => [
        "totalCount" => 1,
        'nextCursor' => 'next cursor',
      ],
    ];
  }

  protected function getArticleIdsButJustOne(): array {
    return $this->getArticleIds();
  }

  protected function getPage2ArticleIds(): array {
    $articleIds = $this->getArticleIds();
    $articleIds['articles'] = [];
    return $articleIds;
  }

  protected function getArticleProduction() {
    return $this->getArticle();
  }

  protected function getArticleRealtime() {
   return $this->getArticle();
  }

  protected function getArticle() {
    return [
      'metadata' => [
        'A boolean meta' => TRUE,
        'A date meta' => ['msSinceEpoch' => 1741385249172],
        'A file meta' => 'https://cdn.prod.pcc.pantheon.io/pcc-prod-user-uploads/dfa6f309-537c-4ffe-bbdf-4a40a6e70a61',
        'A list meta' => 'Option c',
        'A text meta' => 'Plain text field test contents',
        'A textarea meta' => 'textarea test contents',
        'description' => 'A random description',
        'image' => 'test-image',
      ],
      'content' => $this->articleContent,
      'title' => 'test title',
      'slug' => 'test-slug',
      'createdAt' => ['msSinceEpoch' => 1741385249172],
      'publishedDate' => ['msSinceEpoch' => 1741385249172],
      'publishStatus' => 'PUBLISHED',
    ];
  }

  protected function getArticles() {
    return [
    'articles' => [[
      'id' => self::ARTICLE_ID,
    ] + $this->getArticle(),
],
];
  }

}
