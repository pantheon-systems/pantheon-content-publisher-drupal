<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityStorageBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\pantheon_content_publisher\Entity\PantheonDocument;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list controller for the pantheon content publisher entity type.
 */
class PantheonDocumentStorage extends ContentEntityStorageBase implements PantheonDocumentStorageInterface {

  const SEPARATOR = '.';

  public function __construct(
    EntityTypeInterface $entity_type,
    EntityFieldManagerInterface $entity_field_manager,
    CacheBackendInterface $cache,
    MemoryCacheInterface $memory_cache,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    protected EntityStorageInterface $collectionStorage,
    protected PantheonContentPublisherConverter $pantheonContentPublisherConverter,
  ) {
    parent::__construct($entity_type, $entity_field_manager, $cache, $memory_cache, $entity_type_bundle_info);
  }

  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    return new static(
      $entity_type,
      $container->get('entity_field.manager'),
      $container->get('cache.entity'),
      $container->get('entity.memory_cache'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager')->getStorage('pantheon_document_collection'),
      $container->get('pantheon_content_publisher.converter')
    );
  }

  protected function doLoadMultiple(?array $ids = NULL) {
    $entities = [];
    foreach ($ids as $id) {
      [$collection_name, $pantheon_id] = explode(self::SEPARATOR, $id, 2);
      if (!$collection = $this->collectionStorage->load($collection_name)) {
        continue;
      }
      try {
        $pantheon_data = $collection->getGraphQL()->getArticle($pantheon_id);
      }
      catch (GraphQLException $e) {
        continue;
      }
      $drupal_data = $this->pantheonContentPublisherConverter
        ->convert($pantheon_data, $collection_name, $id);
      $document = PantheonDocument::create($drupal_data);
      $entities[$id] = $document->enforceIsNew(FALSE);
    }
    return $entities;
  }

  protected function getQueryServiceName() {
    return 'pantheon_content_publisher.query';
  }

  protected function has($id, EntityInterface $entity) {
    // As per the comments in ::doLoadMultiple() it's better to pretend it's
    // always an update.
    return TRUE;
  }

  public function countFieldData($storage_definition, $as_bool = FALSE) {
    return 0;
  }

  protected function readFieldItemsToPurge(FieldDefinitionInterface $field_definition, $batch_size) {
    return [];
  }

  protected function purgeFieldItems(ContentEntityInterface $entity, FieldDefinitionInterface $field_definition) {
    // Nothing to do.
  }

  protected function doLoadMultipleRevisionsFieldItems($revision_ids) {
    return [];
  }

  protected function doSaveFieldItems(ContentEntityInterface $entity, array $names = []) {
    // Nothing to do.
  }

  protected function doDeleteFieldItems($entities) {
    // Nothing to do.
  }

  protected function doDeleteRevisionFieldItems(ContentEntityInterface $revision) {
    // Nothing to do.
  }

  /**
   * Return an entity id.
   *
   * Drupal wants a single id for entities however Pantheon needs a collection
   * and an article id so this method concatenates the two to create a single
   * Drupal entity id.
   *
   * @param string|\Drupal\pantheon_content_publisher\PantheonDocumentCollectionInterface $collection
   *   Either the collection name or the collection config entity.
   * @param string $pantheon_id
   *   The article id in Pantheon.
   *
   * @return string
   *   The Drupal entity id.
   */
  public static function getEntityId(string|PantheonDocumentCollectionInterface $collection, string $pantheon_id): string {
    return ($collection instanceof PantheonDocumentCollectionInterface ? $collection->id() : $collection) . self::SEPARATOR .
      $pantheon_id;
  }

}
