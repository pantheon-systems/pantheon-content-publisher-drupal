<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\FieldConfigInterface;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\key\Entity\Key;
use Drupal\pantheon_content_publisher\GraphQL;
use Drupal\pantheon_content_publisher\PantheonDocumentCollectionInterface;
use Drupal\pantheon_content_publisher\PantheonContentPublisherConverter;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\IndexBatchHelper;
use Drupal\search_api\Utility\FieldsHelperInterface;

/**
 * Defines the pantheon document collection entity type.
 *
 * @ConfigEntityType(
 *   id = "pantheon_document_collection",
 *   label = @Translation("Pantheon document collection"),
 *   label_collection = @Translation("Pantheon document collections"),
 *   label_singular = @Translation("pantheon document collection"),
 *   label_plural = @Translation("pantheon document collections"),
 *   label_count = @PluralTranslation(
 *     singular = "@count pantheon document collection",
 *     plural = "@count pantheon document collections",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\pantheon_content_publisher\PantheonDocumentCollectionListBuilder",
 *     "form" = {
 *       "add" = "Drupal\pantheon_content_publisher\Form\PantheonDocumentCollectionForm",
 *       "edit" = "Drupal\pantheon_content_publisher\Form\PantheonDocumentCollectionForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *   },
 *   config_prefix = "pantheon_document_collection",
 *   admin_permission = "administer pantheon_document_collection",
 *   bundle_of = "pantheon_document",
 *   links = {
   *     "collection" = "/admin/structure/pantheon_content_publisher_collection",
   *     "add-form" = "/admin/structure/pantheon_content_publisher_collection/add",
   *     "edit-form" = "/admin/structure/pantheon_content_publisher_collection/{pantheon_document_collection}",
   *     "delete-form" = "/admin/structure/pantheon_content_publisher_collection/{pantheon_document_collection}/delete",
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   static_cache = TRUE,
 *   config_export = {
 *     "label",
 *     "id",
 *     "key",
 *     "url",
 *     "description",
 *     "search_api_server"
 *   },
 * )
 */
class PantheonDocumentCollection extends ConfigEntityBase implements PantheonDocumentCollectionInterface {

  const TYPE_MAP = [
    'boolean' => 'boolean',
    'date' => 'timestamp',
    'list' => 'list_string',
    'text' => 'string',
    'textarea' => 'string_long',
    'file' => 'string',
  ];

  protected string $id;

  protected string $label;

  protected string $description = '';

  protected string $search_api_server = '';

  protected string $key = '';

  protected string $url = '';

  public function getToken(): string {
    return ($this->key && ($key = Key::load($this->key))) ? $key->getKeyValue() : '';
  }

  public function getKey(): string {
    return $this->key;
  }

  public function getUrl(): string {
    return $this->url;
  }

  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);
    if ($this->isNew()) {
      $this->dependencies['enforced']['config'][] = Key::load($this->key)->getConfigDependencyName();
      $this->dependencies['enforced']['config'][] = Server::load($this->search_api_server)->getConfigDependencyName();
    }
  }

  public function postSave(EntityStorageInterface $entity_storage, $update = TRUE) {
    $metadata = $this->getGraphQL()->getMetadata();
    // First change and delete existing Drupal fields.
    $field_ids = \Drupal::entityQuery('field_config')
      ->condition('entity_type', 'pantheon_document')
      ->condition('bundle', $this->id())
      ->execute();
    // Do this in a single transaction for speed and consistency.
    $txn = \Drupal::database()->startTransaction();
    if ($field_ids) {
      $fields = FieldConfig::loadMultiple($field_ids);
      foreach ($fields as $field_id => $field) {
        $field_storage = $field->getFieldStorageDefinition();
        $pantheon_field = $this->getConverter()->drupalFieldToPantheonField($field->getName());
        if (!isset($metadata[$pantheon_field])) {
          $field->delete();
          unset($fields[$field_id]);
          $this->getConverter()->delete($pantheon_field);
          continue;
        }
        $new_field_data = $metadata[$pantheon_field];
        // The new field data is a GraphQL response, the order of fields is
        // undefined, sort it for reliability.
        ksort($new_field_data);
        if ($new_field_data !== $field_storage->getThirdPartySetting('pantheon_content_publisher', 'pantheon_data')) {
          $this->updateDrupalField($field, $new_field_data);
        }
        unset($metadata[$pantheon_field]);
      }
    }
    // By now only the new fields remain.
    $prefix = 'field.storage.pantheon_document.';
    $field_storage_ids = array_flip(\Drupal::service('config.storage')->listAll($prefix));
    $field_storage_ids[$prefix . 'content'] = TRUE;
    $field_storage_ids[$prefix . 'title'] = TRUE;
    foreach ($metadata as $pantheon_field => $pantheon_data) {
      $candidate_base = strtolower(preg_replace('/[^a-z0-9_]+/i', '', $pantheon_field));
      /** @noinspection PhpStatementHasEmptyBodyInspection */
      for ($counter = 0, $drupal_field_name = $candidate_base; isset($field_storage_ids["$prefix$drupal_field_name"]); $drupal_field_name = sprintf('%s_%d', $candidate_base, $counter++));
      if ($type = (self::TYPE_MAP[$pantheon_data['type']] ?? FALSE)) {
        $field = $this->createNewDrupalField($drupal_field_name, $type);
        $drupal_field = $field->getName();
        if ($type === 'timestamp') {
          $drupal_field .= '.date';
        }
        $this->getConverter()->set($pantheon_field, $drupal_field);
        $this->updateDrupalField($field, $pantheon_data);
        $field_storage_ids["$prefix$drupal_field_name"] = TRUE;
        $fields[] = $field;
      }
    }
    $datasource = 'entity:pantheon_document';
    $index_id = strtolower($this->id());
    if ($update) {
      $index = Index::load($index_id);
    }
    else {
      $index = Index::create([
        'name' => $this->label(),
        'id' => $index_id,
        'status' => 1,
        'server' => $this->search_api_server,
        'datasource_settings' => [$datasource => []],
      ]);
      $processor = \Drupal::service('search_api.plugin_helper')
        ->createProcessorPlugin($index, 'pantheon_tags', ['fields' => ['content']]);
      $index->addProcessor($processor);
      $base_fields = \Drupal::service('entity_field.manager')
        ->getBaseFieldDefinitions('pantheon_document');
      $fields[] = $base_fields['content'];
      $fields[] = $base_fields['title'];
    }
    // Save automatically tracks all items in a batch. This tracking does
    // not happen during config sync so handle that separately.
    if (!empty($fields)) {
      $fields_helper = \Drupal::service('search_api.fields_helper');
      assert($fields_helper instanceof FieldsHelperInterface);
      $index->getDatasource($datasource)
        ->getEntityTypeBundleInfo()
        ->clearCachedBundles();
      foreach ($fields as $field) {
        $storage = $field->getFieldStorageDefinition();
        $data_definition = $storage->getPropertyDefinition($storage->getMainPropertyName());
        $search_api_field = $fields_helper->createFieldFromProperty($index, $data_definition, $datasource, $field->getName());
        $search_api_field->setLabel($field->getLabel());
        if ($field->getName() === 'content') {
          $search_api_field->setType('text');
        }
        $index->addField($search_api_field);
      }
      $index->save();
      \Drupal::service('search_api.index_task_manager')->addItemsBatch($index);
      // Index all items in the same batch as well.
      if ($this->getGraphQL()->getArticleIds(1)) {
        IndexBatchHelper::create($index);
        if (function_exists('drush_backend_batch_process')) {
          drush_backend_batch_process();
        }
      }
    }
    unset($txn);
    parent::postSave($entity_storage);
  }

  /**
   * Create a new Drupal field storage and field config.
   *
   * Everything set here is immutable. Mutable data is set in
   * ::updateDrupalField().
   *
   * @param string $drupal_field_name
   *   The field name.
   * @param string $type
   *   The field type.
   *
   * @return \Drupal\field\FieldConfigInterface
   *   The field config object.
   */
  protected function createNewDrupalField(string $drupal_field_name, string $type): FieldConfigInterface {
    if (!$field_storage = FieldStorageConfig::loadByName('pantheon_document', $drupal_field_name)) {
      $data = [
        'type' => $type,
        'field_name' => $drupal_field_name,
        'entity_type' => 'pantheon_document',
      ];
      $field_storage = FieldStorageConfig::create($data);
      if ($type === 'list_string') {
        // The pantheon data needs to be stored anyways to find out if there
        // are any changes, reuse it for allowed values instead of storing
        // it twice.
        $field_storage->setSetting('allowed_values_function', static::class . '::getPantheonListOptions');
      }
    }
    $data = [
      'field_name' => $drupal_field_name,
      'entity_type' => 'pantheon_document',
      'bundle' => $this->id(),
      'field_storage' => $field_storage,
    ];
    return FieldConfig::create($data);
  }

  /**
   * Change and save a Drupal field storage and field config object.
   *
   * @param \Drupal\field\FieldConfigInterface $field
   *   The field config object. This and the storage will be updated.
   * @param array $pantheon_data
   *   The data returned from Pantheon.
   */
  protected function updateDrupalField(FieldConfigInterface $field, array $pantheon_data): void {
    $field
      ->setLabel($pantheon_data['title'])
      ->save();
    // The new field data is a GraphQL response, the order of fields is
    // undefined, sort it for reliability.
    ksort($pantheon_data);
    $field->getFieldStorageDefinition()
      ->setThirdPartySetting('pantheon_content_publisher', 'pantheon_data', $pantheon_data)
      ->save();
  }

  /**
   * allowed_values_function callback for our list fields.
   *
   * @param \Drupal\field\FieldStorageConfigInterface $definition
   *   The field storage config.
   * @param $entity
   *   The entity (unused).
   * @param $cacheable
   *   Will be set to TRUE to indicate the results can be cached.
   *
   * @return array
   *   The list field options.
   */
  public static function getPantheonListOptions(FieldStorageConfigInterface $definition, $entity, &$cacheable): array {
    $cacheable = TRUE;
    $options = $definition->getThirdPartySetting('pantheon_content_publisher', 'pantheon_data')['options'];
    return array_combine($options, $options);
  }

  /**
   * Get the GraphQL helper object.
   *
   * This is a separate method for testing purposes only.
   *
   * @return \Drupal\pantheon_content_publisher\GraphQL
   */
  public function getGraphQL(): GraphQL {
    return new GraphQL($this);
  }

  /**
   * @return mixed
   */
  public function getConverter(): PantheonContentPublisherConverter {
    return \Drupal::service('pantheon_content_publisher.converter');
  }

}
