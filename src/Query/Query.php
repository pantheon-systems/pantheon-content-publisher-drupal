<?php

namespace Drupal\pantheon_content_publisher\Query;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Query\QueryBase;
use Drupal\pantheon_content_publisher\Entity\PantheonContentPublisherColl;
use Drupal\pantheon_content_publisher\PantheonContentPublisherConverter;
use Drupal\pantheon_content_publisher\PantheonContentPublisherStorage;

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
    foreach ($conditions as $key => $condition) {
      if ($condition['field'] === 'collection' && in_array(($condition['operator'] ?? '='), ['=', 'IN'])) {
        $collections = (array) $condition['value'];
        unset($conditions[$key]);
        break;
      }
    }
    $collections = PantheonContentPublisherColl::loadMultiple($collections);
    $records = [];
    foreach ($collections as $collection) {
      foreach ($collection->getGraphQL()->getArticles() as $pantheon_record) {
        $key = PantheonContentPublisherStorage::getEntityId($collection, $pantheon_record['id']);
        $value = $this->converter->pantheonMetadataToDrupalRecord($pantheon_record);
        $records[$key] = $value;
      }
    }

    // Copy-paste from
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
