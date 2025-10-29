<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines 'pantheon_content_publisher_entity' queue worker.
 *
 * @QueueWorker(
 *   id = "pantheon_content_publisher_entity",
 *   title = @Translation("Pantheon content publisher entity save"),
 *   cron = {"time" = 60},
 * )
 */
class EntityQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  protected KeyValueStoreInterface $seenStore;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
    KeyValueFactoryInterface $keyValueFactory,
  ) {
    $this->seenStore = $keyValueFactory->get('pantheon_document.seen');
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
      $container->get('keyvalue')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $entity_type_id = $data['entity_type'];
    $storage = $this->entityTypeManager->getStorage($entity_type_id);

    if (empty($data['delete'])) {
      $entity = $storage->load($data['entity_id']);
      if ($this->seenStore->setIfNotExists($entity->id(), 1)) {
        (new \ReflectionObject($storage))
          ->getMethod('invokeHook')
          ->invoke($storage, 'insert', $entity);
      }
      $entity->save();
    }
    else {
      $entityType = $this->entityTypeManager->getDefinition($entity_type_id);
      $entity = $storage->create([
        $entityType->getKey('bundle') => $data['bundle'],
        $entityType->getKey('id') => $data['entity_id'],
      ]);
      $entity->enforceIsNew(FALSE);
      $entity->delete();
      $this->seenStore->delete($entity->id());
    }
  }

}
