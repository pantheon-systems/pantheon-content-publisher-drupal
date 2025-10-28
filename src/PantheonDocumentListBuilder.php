<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Provides a list controller for the pantheon content publisher entity type.
 */
class PantheonDocumentListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['id'] = $this->t('ID');
    $header['label'] = $this->t('Label');
    $header['status'] = $this->t('Status');
    $header['created'] = $this->t('Created');
    $header['changed'] = $this->t('Updated');
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\pantheon_content_publisher\PantheonDocumentInterface $entity */
    $row['id'] = $entity->id();
    $row['label'] = $entity->toLink();
    $row['status'] = $entity->isPublished() ? $this->t('Published') : $this->t('Draft');
    $row['created']['data'] = $entity->get('created')->view(['label' => 'hidden']);
    $row['changed']['data'] = $entity->get('changed')->view(['label' => 'hidden']);
    return $row;
  }

}
