<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines 'pantheon_content_publisher_entity_delete' queue worker.
 *
 * @QueueWorker(
 *   id = "pantheon_content_publisher_entity_delete",
 *   title = @Translation("Pantheon content publisher entity delete"),
 *   cron = {"time" = 60},
 * )
 */
class EntityDelete extends QueueWorkerBase implements ContainerFactoryPluginInterface {

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
    $this->entityTypeManager
      ->getStorage($data['entity_type'])
      ->load($data['entity_id'])
      ->delete();
  }

}
