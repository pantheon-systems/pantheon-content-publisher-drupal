<?php

declare(strict_types=1);

namespace Drupal\Tests\pantheon_content_publisher\Kernel;

use Drupal\Component\Utility\NestedArray;
use Drupal\key\Entity\Key;
use Drupal\pantheon_content_publisher\PantheonDocumentCollectionInterface;
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
    $mock->method('post')->willReturnCallback(fn ($uri, array $options) => new Response(200, [], $this->storage[json_decode($options['body'])->query]));
    $mock->method('get')->willReturnCallback(fn() => new Response(200, [], $this->storage['get']));
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
      $callable($data);
    }
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
    $content = [
      'event' => 'article.update',
      'payload' => [
        'articleId' => self::ARTICLE_ID,
        'siteId' => $this->collection->id(),
      ],
    ];
    $request = Request::create('/api/pantheoncloud/webhook', 'POST', content: json_encode($content));
    $this->handle($request);
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
      ],
      'content' => $this->articleContent,
      'title' => 'test title',
      'slug' => 'test-slug',
      'image' => 'test-image',
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
