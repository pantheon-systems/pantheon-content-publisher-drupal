<?php

namespace Drupal\pantheon_content_publisher\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Attribute\EntityReferenceSelection;
use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a selection plugin for Pantheon documents.
 */
#[EntityReferenceSelection(
  id: 'default:pantheon_document',
  label: new TranslatableMarkup('Pantheon Document Selection'),
  entity_types: ['pantheon_document'],
  group: 'default',
  weight: 1
)]
class PantheonDocumentSelection extends DefaultSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS'): QueryInterface {
    $configuration = $this->getConfiguration();
    $target_type = 'pantheon_document';
    $entity_type = $this->entityTypeManager->getDefinition($target_type);

    /** @var \Drupal\Core\Entity\Query\QueryInterface $query */
    $query = $this->entityTypeManager->getStorage($target_type)->getQuery();
    $query->accessCheck(TRUE);

    if (isset($match) && $label_key = $entity_type->getKey('label')) {
      $query->condition($label_key, $match, $match_operator);
    }

    $collection_ids = $configuration['target_bundles'] ?? [];
    if (!empty($collection_ids)) {
      $query->condition('collection', $collection_ids, 'IN');
    }

    // Add entity-access tag.
    $query->addTag($target_type . '_access');
    $query->addTag('entity_reference');
    $query->addMetaData('entity_reference_selection_handler', $this);

    // Add the sort option.
    if (!empty($configuration['sort']) && $configuration['sort']['field'] !== '_none') {
      $query->sort($configuration['sort']['field'], $configuration['sort']['direction']);
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function validateReferenceableEntities(array $ids): array {
    return parent::validateReferenceableEntities($ids);
  }

}
