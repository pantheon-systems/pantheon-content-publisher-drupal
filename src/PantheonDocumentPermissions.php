<?php

namespace Drupal\pantheon_content_publisher;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\BundlePermissionHandlerTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\pantheon_content_publisher\Entity\PantheonDocument;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides dynamic permissions of the Pantheon Content Publisher module.
 *
 * @see pantheon_content_publisher.permissions.yml
 */
class PantheonDocumentPermissions implements ContainerInjectionInterface {
  use BundlePermissionHandlerTrait;
  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a pantheon comtent publisher instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * Returns permissions for the pantheon document entity type.
   *
   * @return array
   *   An array of permission names and descriptions.
   */
  public function permissions() {
    return [
      'view pantheon documents' => [
        'title' => $this->t('View Pantheon Documents'),
      ],
    ];
  }

}
