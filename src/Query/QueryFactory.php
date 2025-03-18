<?php

namespace Drupal\pantheon_content_publisher\Query;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Query\QueryException;
use Drupal\Core\Entity\Query\QueryFactoryInterface;
use Drupal\pantheon_content_publisher\PantheonContentPublisherConverter;

/**
 * Provides a factory for creating the key value entity query.
 */
class QueryFactory implements QueryFactoryInterface {

  /**
   * The namespace of this class, the parent class etc.
   *
   * @var array
   */
  protected array $namespaces;

  /**
   * Constructs a QueryFactory object.
   */
  public function __construct(protected PantheonContentPublisherConverter $converter) {
    $this->namespaces = Query::getNamespaces($this);
  }

  /**
   * {@inheritdoc}
   */
  public function get(EntityTypeInterface $entity_type, $conjunction) {
    return new Query($entity_type, $conjunction, $this->namespaces, $this->converter);
  }

  /**
   * {@inheritdoc}
   */
  public function getAggregate(EntityTypeInterface $entity_type, $conjunction) {
    throw new QueryException('Aggregation over key-value entity storage is not supported');
  }

}
