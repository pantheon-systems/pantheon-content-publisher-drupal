<?php

declare(strict_types=1);

namespace Drupal\Tests\pantheon_content_publisher\Kernel;

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\pantheon_content_publisher\Controller\PantheonContentPublisherController;
use Drupal\pantheon_content_publisher\PantheonContentPublisherCollInterface;
use Drupal\search_api\Entity\Index;
use Symfony\Component\HttpFoundation\Request;

/**
 * Test description.
 *
 * @group pantheon_content_publisher
 */
class PantheonContentPublisherTest extends KernelTestBase {

  const ARTICLEID = '1_dRWJT4gJ05ZwtD6HyE1GdRxExL4FIAMkDIcIH8nlgM';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'options',
    'text',
    'pantheon_content_publisher',
    'pantheon_content_publisher_test',
    'search_api',
    'search_api_db',
    'search_api_db_defaults',
  ];

  protected string $bundle;

  protected PantheonContentPublisherCollInterface $collection;

  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['search_api']);
    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('search_api_task');
    $keyValue = $this->keyValue->get('pantheon_content_publisher_test');
    $articleIds = $this->getArticleIds();
    $keyValue->set('getArticleIds', $articleIds);
    $articleIds['articles'] = [];
    $keyValue->set('getArticleIds.next cursor', $articleIds);
    $keyValue->set('metadata', $this->metadata());
    $keyValue->set('getArticle', $this->getArticle());
    // Create a collection, this also creates an index and puts all items
    // in it.
    $this->bundle = $this->randomMachineName();
    $this->collection = $this->container->get('entity_type.manager')->getStorage('pantheon_content_publisher_coll')->create([
      'id' => $this->bundle,
      'label' => $this->randomString(),
      'token' => $this->randomMachineName(),
      'url' => $this->randomMachineName(),
      'search_api_server' => 'default_server',
    ]);
    $this->collection->save();
  }

  public function testSearchAPIIndex() {
    $this->runBatch();
    $indexes = Index::loadMultiple();
    $this->assertCount(1, $indexes);
    $index = reset($indexes);
    $this->assertSame($this->bundle, $index->id());
    $this->assertSame(['abooleanmeta', 'adatemeta', 'alistmeta', 'atextareameta', 'atextmeta'], array_keys($index->getFields()));
    $this->assertSame(1, $index->getTrackerInstance()->getTotalItemsCount());
    $this->assertSame(0, $index->getTrackerInstance()->getRemainingItemsCount());
    $this->assertSame('textarea test contents', $this->getSearchAPIvalue('atextareameta'));
    $newValue = $this->updateArticleInPantheon();
    // Notify the system the value has been updated.
    $webhook = $this->container->get('controller_resolver')->getControllerFromDefinition(PantheonContentPublisherController::class . '::webhook');
    $content = [
      'event' => 'article.publish',
      'payload' => ['articleId' => self::ARTICLEID],
    ];
    $request = Request::create('/webhook', content: json_encode($content));
    $webhook($request);
    $this->assertSame($newValue, $this->getSearchAPIvalue('atextareameta'));
  }

  public function testCollectionUpdate(): void {
    $storages = FieldStorageConfig::loadMultiple();
    $this->assertSame($storages['pantheon_content_publisher.abooleanmeta']->getType(), 'boolean');
    $this->assertSame($storages['pantheon_content_publisher.adatemeta']->getType(), 'timestamp');
    $this->assertSame($storages['pantheon_content_publisher.alistmeta']->getType(), 'list_string');
    $this->assertSame(options_allowed_values($storages['pantheon_content_publisher.alistmeta']), [
      'Option a' => 'Option a',
      'Option b' => 'Option b',
      'Option c' => 'Option c'
    ]);
    $this->assertSame($storages['pantheon_content_publisher.atextmeta']->getType(), 'string');
    $this->assertSame($storages['pantheon_content_publisher.atextareameta']->getType(), 'string_long');
    // Remove Option b from the metadata.
    $metadata = $this->metadata();
    $metadata['A list meta']['options'] = array_diff($metadata['A list meta']['options'], ['Option b']);
    $this->keyValue->get('pantheon_content_publisher_test')->set('metadata', $metadata);
    // Update the collection.
    $this->collection->save();
    // Verify the list field changed.
    $storages = FieldStorageConfig::loadMultiple();
    $this->assertSame(options_allowed_values($storages['pantheon_content_publisher.alistmeta']), [
      'Option a' => 'Option a',
      'Option c' => 'Option c'
    ]);
    // Remove the list field.
    unset($metadata['A list meta']);
    $this->keyValue->get('pantheon_content_publisher_test')->set('metadata', $metadata);
    // Update the collection.
    $this->collection->save();
    // Verify it's gone.
    $storages = FieldStorageConfig::loadMultiple();
    $this->assertArrayNotHasKey('pantheon_content_publisher.alistmeta', $storages);
  }

  public function testStorageDoLoadMultiple(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('pantheon_content_publisher');
    $pantheonContentPublisher = $storage->load("$this->bundle:" . self::ARTICLEID);
    $this->assertSame('textarea test contents', $pantheonContentPublisher->atextareameta->value);
    // Update article in Pantheon
    $newValue = $this->updateArticleInPantheon();
    $pantheonContentPublisher = $storage->loadUnchanged("$this->bundle:" . self::ARTICLEID);
    $this->assertSame($newValue, $pantheonContentPublisher->atextareameta->value);
  }

  protected function metadata(): array {
    return [
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
    ];
  }

  public function getArticleIds(): array {
    return [
      'articles' => [
        ['id' => self::ARTICLEID],
      ],
      "pageInfo" => [
        "totalCount" => 1,
        'nextCursor' => 'next cursor',
      ],
    ];
  }

  public function getArticle() {
    return [
      self::ARTICLEID => [
        'metadata' => [
          'A boolean meta' => TRUE,
          'A date meta' => ['msSinceEpoch' => 1741385249172],
          'A file meta' => 'https://cdn.prod.pcc.pantheon.io/pcc-prod-user-uploads/dfa6f309-537c-4ffe-bbdf-4a40a6e70a61',
          'A list meta' => 'Option c',
          'A text meta' => 'Plain text field test contents',
          'A textarea meta' => 'textarea test contents',
          'description' => 'A random description',
        ],
        'content' => 'test content',
      ],
    ];
  }

  protected function updateArticleInPantheon(): string {
    $newValue = $this->randomString();
    $article = $this->getArticle();
    $article[self::ARTICLEID]['metadata']['A textarea meta'] = $newValue;
    $this->keyValue->get('pantheon_content_publisher_test')
      ->set('getArticle', $article);
    return $newValue;
  }

  protected function runBatch(): void {
    $batch = &batch_get();
    $batch['progressive'] = FALSE;
    batch_process();
  }

  public function getSearchAPIvalue(string $field): string {
    return $this->container->get('database')
      ->select(sprintf("search_api_db_%s_%s", $this->bundle, $field), 's')
      ->fields('s', ['value'])
      ->execute()
      ->fetchField();
  }

}

