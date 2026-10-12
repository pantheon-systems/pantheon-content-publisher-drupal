<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\DelayedRequeueException;
use Drupal\Core\Queue\QueueWorkerBase;
use Psr\Log\LoggerInterface;
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

  const MAX_RETRIES = 3;
  const RETRY_DELAY = 15;

  protected KeyValueStoreInterface $seenStore;
  protected KeyValueStoreInterface $retryStore;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
    KeyValueFactoryInterface $keyValueFactory,
    protected LoggerInterface $logger,
  ) {
    $this->seenStore = $keyValueFactory->get('pantheon_document.seen');
    $this->retryStore = $keyValueFactory->get('pantheon_content_publisher.queue_retries');
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
      $container->get('keyvalue'),
      $container->get('logger.factory')->get('pantheon_content_publisher'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $entity_type_id = $data['entity_type'];
    $entity_id = $data['entity_id'];
    $storage = $this->entityTypeManager->getStorage($entity_type_id);

    if (empty($data['delete'])) {
      $entity = $storage->load($entity_id);
      if ($entity === NULL) {
        $this->handleLoadFailure($entity_id);
        return;
      }
      $this->retryStore->delete($entity_id);
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
        $entityType->getKey('id') => $entity_id,
      ]);
      $entity->enforceIsNew(FALSE);
      $entity->delete();
      $this->seenStore->delete($entity->id());
    }
  }

  protected function handleLoadFailure(string $entity_id): void {
    $retries = (int) $this->retryStore->get($entity_id, 0);

    if ($retries >= self::MAX_RETRIES) {
      $this->logger->error('Entity %id not available from Content Cloud API after @max attempts. Discarding queue item.', [
        '%id' => $entity_id,
        '@max' => self::MAX_RETRIES,
      ]);
      $this->retryStore->delete($entity_id);
      return;
    }

    $this->retryStore->set($entity_id, $retries + 1);
    $this->logger->warning('Entity %id not yet available from Content Cloud API (attempt @attempt/@max). Requeuing with @delay second delay.', [
      '%id' => $entity_id,
      '@attempt' => $retries + 1,
      '@max' => self::MAX_RETRIES,
      '@delay' => self::RETRY_DELAY,
    ]);
    throw new DelayedRequeueException(self::RETRY_DELAY);
  }

}
