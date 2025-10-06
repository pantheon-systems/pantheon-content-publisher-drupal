<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines 'pantheon_content_publisher_entity_save' queue worker.
 *
 * @QueueWorker(
 *   id = "pantheon_content_publisher_entity",
 *   title = @Translation("Pantheon content publisher entity save"),
 *   cron = {"time" = 60},
 * )
 */
class EntityQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $entity_type_id = $data['entity_type'];
    $entity = $this->entityTypeManager
      ->getStorage($entity_type_id)
      ->load($data['entity_id'])
      ->save();
    if (empty($data['delete'])) {
      $entity->save();
    }
    else {
      $entity->delete();
    }
    $index_storage = $this->entityTypeManager->getStorage('search_api_index');
    $datasource_id = "entity:$entity_type_id";
    // Querying for datasource_id is possible, however the default config
    // entity query implementation loads all indexes anyways.
    foreach ($index_storage->loadMultiple() as $index) {
      $index->indexItems(-1, $datasource_id);
    }
  }

}
