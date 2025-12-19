<?php

namespace Drupal\pantheon_content_publisher\Query;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Query\QueryBase;
use Drupal\pantheon_content_publisher\Entity\PantheonDocumentCollection;
use Drupal\pantheon_content_publisher\PantheonContentPublisherConverter;
use Drupal\pantheon_content_publisher\PantheonDocumentStorage;

/**
 * Defines the entity query for entities stored in a key value backend.
 */
class Query extends QueryBase {

  public function __construct(EntityTypeInterface $entity_type, $conjunction, array $namespaces, protected PantheonContentPublisherConverter $converter) {
    parent::__construct($entity_type, $conjunction, $namespaces);
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $conditions = &$this->condition->conditions();
    $collections = NULL;
    $target_ids = NULL;
    foreach ($conditions as $key => $condition) {
      if ($condition['field'] === 'collection' && in_array($condition['operator'] ?? '=', ['=', 'IN'])) {
        $collections = (array) $condition['value'];
        unset($conditions[$key]);
      }

      // Extract id filters to support entity reference validation and widget rendering.
      if ($condition['field'] === 'id' && in_array($condition['operator'] ?? '=', ['=', 'IN'])) {
        $target_ids = (array) $condition['value'];
        unset($conditions[$key]);
      }
    }

    $collections = PantheonDocumentCollection::loadMultiple($collections);
    $records = [];
    foreach ($collections as $collection) {
      foreach ($collection->getGraphQL()->getArticles() as $pantheon_record) {
        $key = PantheonDocumentStorage::getEntityId($collection, $pantheon_record['id']);

        // Skip documents not in the target id list when specific ids are requested.
        if ($target_ids !== NULL && !in_array($key, $target_ids)) {
          continue;
        }

        $records[$key] = $this->converter->convert($pantheon_record, $collection->id());
      }
    }

    // Drupal\Core\Entity\KeyValueStore\Query\Query::execute().
    $result = $this->condition->compile($records);

    // Apply sort settings.
    foreach ($this->sort as $sort) {
      $direction = $sort['direction'] == 'ASC' ? -1 : 1;
      $field = $sort['field'];
      uasort($result, function ($a, $b) use ($field, $direction) {
        return ($a[$field] <= $b[$field]) ? $direction : -$direction;
      });
    }

    // Let the pager do its work.
    $this->initializePager();

    if ($this->range) {
      $result = array_slice($result, $this->range['start'], $this->range['length'], TRUE);
    }
    if ($this->count) {
      return count($result);
    }

    // Create the expected structure of entity_id => entity_id.
    $entity_ids = array_keys($result);
    return array_combine($entity_ids, $entity_ids);
  }

}
