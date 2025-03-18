<?php

declare(strict_types=1);

namespace Drupal\Tests\pantheon_content_publisher\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\pantheon_content_publisher\Entity\PantheonContentPublisherColl;

/**
 * Test description.
 *
 * @group pantheon_content_publisher
 */
class PantheonContentPublisherTest extends KernelTestBase {

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

  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['search_api']);
    $this->installEntitySchema('search_api_task');
  }

  /**
   * @coversClass  \Drupal\pantheon_content_publisher\Entity\PantheonContentPublisherColl
   */
  public function testCollectionCreate(): void {
    $metadata = $this->metadata();
    $this->keyValue->get('pantheon_content_publisher_test')->set('metadata', $metadata);
    $articleIds = $this->getArticleIds();
    $this->keyValue->get('pantheon_content_publisher_test')->set('getArticleIds', $articleIds);
    $articleIds['articles'] = [];
    $this->keyValue->get('pantheon_content_publisher_test')->set('getArticleIds.next cursor', $articleIds);
    $this->keyValue->get('pantheon_content_publisher_test')->set('getArticle', $this->getArticle());
    $bundle = $this->randomMachineName();
    $collection = $this->container->get('entity_type.manager')->getStorage('pantheon_content_publisher_coll')->create([
      'id' => $bundle,
      'label' => $this->randomString(),
      'token' => $this->randomMachineName(),
      'url' => $this->randomMachineName(),
      'search_api_server' => 'default_server',
    ]);
    $collection->save();
    $storages = $this->container->get('entity_type.manager')->getStorage('field_storage_config')->loadMultiple();
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
    $metadata['A list meta']['options'] = array_diff($metadata['A list meta']['options'], ['Option b']);
    $this->keyValue->get('pantheon_content_publisher_test')->set('metadata', $metadata);
    $collection->save();
    $storages = $this->container->get('entity_type.manager')->getStorage('field_storage_config')->loadMultiple();
    $this->assertSame(options_allowed_values($storages['pantheon_content_publisher.alistmeta']), [
      'Option a' => 'Option a',
      'Option c' => 'Option c'
    ]);
    unset($metadata['A list meta']);
    $this->keyValue->get('pantheon_content_publisher_test')->set('metadata', $metadata);
    $collection->save();
    $storages = $this->container->get('entity_type.manager')->getStorage('field_storage_config')->loadMultiple();
    $this->assertArrayNotHasKey('pantheon_content_publisher.alistmeta', $storages);
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
        ['id' => '1_dRWJT4gJ05ZwtD6HyE1GdRxExL4FIAMkDIcIH8nlgM'],
      ],
      "pageInfo" => [
        "totalCount" => 1,
        'nextCursor' => 'next cursor',
      ],
    ];
  }

  public function getArticle() {
    return [
      '1_dRWJT4gJ05ZwtD6HyE1GdRxExL4FIAMkDIcIH8nlgM' => [
        'metadata' => [
          "A boolean meta" => TRUE,
          "A date meta" => ["msSinceEpoch" => 1741385249172],
          "A file meta" => "https://cdn.prod.pcc.pantheon.io/pcc-prod-user-uploads/dfa6f309-537c-4ffe-bbdf-4a40a6e70a61",
          "A list meta" => "Option c",
          "A text meta" => "Plain text field test contents",
          "A textarea meta" => "textarea test contents",
          "description" => "A random description",
        ],
        'content' => 'test content',
      ],
    ];
  }

}

