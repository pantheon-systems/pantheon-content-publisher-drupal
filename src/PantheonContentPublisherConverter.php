<?php

namespace Drupal\pantheon_content_publisher;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;

class PantheonContentPublisherConverter {

  protected array $fields;

  protected KeyValueStoreInterface $keyValueStore;

  public function __construct(KeyValueFactoryInterface $keyValueFactory, protected MemoryCacheInterface $memoryCache) {
    $this->keyValueStore = $keyValueFactory->get('pantheon_document.fields');
  }

  /**
   * Get the field map.
   *
   * This is using the entity cache memory backend for solid cache clearing.
   *
   * @return array
   *   Keys are Pantheon field, values are Drupal field name or Drupal field
   *   name, a dot and a method in this class. The latter is used when
   *   Pantheon data needs conversion for Drupal, currently only ::date is
   *   used. The map is returned by reference for faster manipulation of the
   *   memory cache.
   */
  protected function &getFields(): array {
    $cid = 'pantheon_document:fields';
    if (!$cache = $this->memoryCache->get($cid)) {
      $fields = $this->keyValueStore->getAll();
      $this->memoryCache->set($cid, $fields, Cache::PERMANENT, ['field_config_list']);
      $cache = $this->memoryCache->get($cid);
    }
    return $cache->data;
  }

  public function set(string $pantheon_field, string $drupal_field): void {
    $this->keyValueStore->set($pantheon_field, $drupal_field);
    $this->getFields()[$pantheon_field] = $drupal_field;
  }

  public function delete(string $pantheon_field): void {
    $this->keyValueStore->delete($pantheon_field);
    unset($this->getFields()[$pantheon_field]);
  }

  public function pantheonMetadataToDrupalRecord(array $pantheon_record): array {
    $drupal_data = [];
    foreach ($pantheon_record['metadata'] as $pantheon_field => $metadata_value) {
      if ($drupal_field = $this->pantheonFieldToDrupalField($pantheon_field)) {
        // Currently only date fields need conversion but it was easier to
        // be generic.
        if (str_contains($drupal_field, '.')) {
          [$drupal_field, $method] = explode('.', $drupal_field);
          $metadata_value = $this->$method($metadata_value);
        }
        $drupal_data[$drupal_field] = $metadata_value;
      }
    }
    return $drupal_data;
  }

  public function drupalFieldToPantheonField(string $drupal_field): string|FALSE {
    return array_search($drupal_field, array_map(fn ($field) => strtok($field, '.'), $this->getFields()));
  }

  protected function pantheonFieldToDrupalField(string $pantheon_field): string {
    return $this->getFields()[$pantheon_field] ?? '';
  }

  /**
   * Convert Pantheon date to PHP date.
   *
   * @param $date
   *   A date in milliseconds since 1970 Jan 1. Custom metadata fields send an
   *   array with a key 'msSinceEpoch' while top level publishedDate is just
   *   an int with the same data.
   *
   * @return int
   *   Number of seconds since 1970 Jan 1.
   */
  public function date(int|array $date): int {
    return intdiv((is_int($date) ? $date : $date['msSinceEpoch']), 1000);
  }

}
