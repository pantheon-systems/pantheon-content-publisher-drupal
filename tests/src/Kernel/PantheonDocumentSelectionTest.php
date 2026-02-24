<?php

declare(strict_types=1);

namespace Drupal\Tests\pantheon_content_publisher\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\pantheon_content_publisher\PantheonDocumentStorage;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests entity reference field creation targeting pantheon_document.
 *
 * @group pantheon_document
 * @coversDefaultClass \Drupal\pantheon_content_publisher\Plugin\EntityReferenceSelection\PantheonDocumentSelection
 */
#[RunTestsInSeparateProcesses]
class PantheonDocumentSelectionTest extends KernelTestBase implements PantheonContentDocumentTestInterface {

  use PantheonDocumentTestTrait {
    PantheonDocumentTestTrait::setUp as traitSetUp;
  }
  use PantheonKernelHandleTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'options',
    'text',
    'key',
    'search_api',
    'search_api_db',
    'search_api_db_defaults',
    'pantheon_content_publisher',
    'entity_test',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    // PantheonDocumentTestTrait::setUp() calls parent::setUp() and
    // initializes $this->collection which testEntityReferenceFieldCreation
    // depends on.
    $this->traitSetUp();
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('user');
  }

  /**
   * @testdox Entity reference field targeting pantheon_document can be created and references load correctly
   */
  public function testEntityReferenceFieldCreation(): void {
    $entity_id = PantheonDocumentStorage::getEntityId($this->collection->id(), self::ARTICLE_ID);

    // Create an entity reference field storage targeting pantheon_document.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_pantheon_document',
      'entity_type' => 'entity_test',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'pantheon_document',
      ],
    ]);
    $field_storage->save();

    // Create the field instance with the selection handler.
    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Pantheon Document Reference',
      'settings' => [
        'handler' => 'default:pantheon_document',
        'handler_settings' => [
          'target_bundles' => [$this->collection->id()],
          'sort' => ['field' => '_none', 'direction' => 'ASC'],
        ],
      ],
    ]);
    $field->save();

    // Verify field storage was created with correct target type.
    $loaded_storage = FieldStorageConfig::loadByName('entity_test', 'field_pantheon_document');
    $this->assertNotNull($loaded_storage);
    $this->assertSame('pantheon_document', $loaded_storage->getSetting('target_type'));

    // Create an entity_test entity referencing the pantheon document.
    $entity = EntityTest::create([
      'name' => 'Test entity',
      'field_pantheon_document' => ['target_id' => $entity_id],
    ]);
    $entity->save();

    // Reload and verify the reference is stored.
    $entity = EntityTest::load($entity->id());
    $this->assertSame($entity_id, $entity->get('field_pantheon_document')->target_id);

    // Verify the referenced pantheon document can be loaded via the field.
    $referenced = $entity->get('field_pantheon_document')->entity;
    $this->assertNotNull($referenced);
    $this->assertSame('test title', $referenced->label());
    $this->assertSame($entity_id, $referenced->id());
  }

}
