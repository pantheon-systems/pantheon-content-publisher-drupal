<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\FieldConfigInterface;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\media\Entity\Media;
use Drupal\pantheon_content_publisher\GraphQL;
use Drupal\pantheon_content_publisher\PantheonContentPublisherCollInterface;
use Drupal\pantheon_content_publisher\PantheonContentPublisherConverter;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\IndexBatchHelper;
use Drupal\search_api\Utility\FieldsHelperInterface;

/**
 * Defines the pantheon content publisher collection entity type.
 *
 * @ConfigEntityType(
 *   id = "pantheon_content_publisher_coll",
 *   label = @Translation("Pantheon content publisher collection"),
 *   label_collection = @Translation("Pantheon content publisher collections"),
 *   label_singular = @Translation("pantheon content publisher collection"),
 *   label_plural = @Translation("pantheon content publisher collections"),
 *   label_count = @PluralTranslation(
 *     singular = "@count pantheon content publisher collection",
 *     plural = "@count pantheon content publisher collections",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\pantheon_content_publisher\PantheonContentPublisherCollListBuilder",
 *     "form" = {
 *       "add" = "Drupal\pantheon_content_publisher\Form\PantheonContentPublisherCollForm",
 *       "edit" = "Drupal\pantheon_content_publisher\Form\PantheonContentPublisherCollForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *   },
 *   config_prefix = "pantheon_content_publisher_coll",
 *   admin_permission = "administer pantheon_content_publisher_coll",
 *   bundle_of = "pantheon_content_publisher",
 *   links = {
 *     "collection" = "/admin/structure/pantheon-content-publisher-collection",
 *     "add-form" = "/admin/structure/pantheon-content-publisher-collection/add",
 *     "edit-form" = "/admin/structure/pantheon-content-publisher-collection/{pantheon_content_publisher_coll}",
 *     "delete-form" = "/admin/structure/pantheon-content-publisher-collection/{pantheon_content_publisher_coll}/delete",
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
 *     "token",
 *     "url",
 *     "description",
 *     "search_api_server"
 *   },
 * )
 */
class PantheonContentPublisherColl extends ConfigEntityBase implements PantheonContentPublisherCollInterface {

  const TYPE_MAP = [
    'boolean' => 'boolean',
    'date' => 'timestamp',
    'list' => 'list_string',
    'text' => 'string',
    'textarea' => 'string_long',
  ];

  protected string $id;

  protected string $label;

  protected string $description = '';

  protected string $search_api_server = '';

  protected string $token = '';

  protected string $url = '';

  public function getToken(): string {
    return $this->token;
  }

  public function getUrl(): string {
    return $this->url;
  }

  public function postSave(EntityStorageInterface $entity_storage, $update = TRUE) {
    $metadata = $this->getGraphQL()->getMetadata();
    // First change and delete existing Drupal fields.
    $field_ids = \Drupal::entityQuery('field_config')
      ->condition('entity_type', 'pantheon_content_publisher')
      ->condition('bundle', $this->id())
      ->condition('field_name', 'media', '<>')
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
    $prefix = 'field.storage.pantheon_content_publisher.';
    $field_storage_ids = array_flip(\Drupal::service('config.storage')->listAll($prefix));
    foreach ($metadata as $pantheon_field => $pantheon_data) {
      $candidate_base = strtolower(preg_replace('/[^a-z0-9_]+/i', '', $pantheon_field));
      /** @noinspection PhpStatementHasEmptyBodyInspection */
      for ($counter = 0, $drupal_field_name = $candidate_base; isset($field_storage_ids["$prefix$drupal_field_name"]); $drupal_field_name = sprintf('%s_%d', $candidate_base, $counter++));
      if (isset(self::TYPE_MAP[$pantheon_data['type']])) {
        $field = $this->createNewDrupalField($drupal_field_name, self::TYPE_MAP[$pantheon_data['type']]);
        $this->getConverter()->set($pantheon_field, $field->getName());
        $this->updateDrupalField($field, $pantheon_data);
        $field_storage_ids["$prefix$drupal_field_name"] = TRUE;
        $fields[] = $field;
      }
    }
    if (!$update) {
      $fs = \Drupal::service('file_system');
      assert($fs instanceof FileSystemInterface);
      $directory = 'public://pantheon_content_publisher/' . $this->id();
      $fs->prepareDirectory($directory, FileSystemInterface:: CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
      $datasource = 'entity:pantheon_content_publisher';
      $dependencies['enforced']['config'] = [$this->getConfigDependencyName()];
      $index = Index::create([
        'name' => $this->label(),
        'id' => $this->id,
        'status' => 1,
        'server' => $this->search_api_server,
        'datasource_settings' => [$datasource => []],
        'dependencies' => $dependencies,
      ]);
      $index->getDatasource($datasource)->getEntityTypeBundleInfo()->clearCachedBundles();
      $fields_helper = \Drupal::service('search_api.fields_helper');
      assert($fields_helper instanceof FieldsHelperInterface);
      $base_fields = \Drupal::service('entity_field.manager')->getBaseFieldDefinitions('pantheon_content_publisher');
      $fields[] = $base_fields['content'];
      foreach ($fields as $field) {
        $storage = $field->getFieldStorageDefinition();
        $data_definition = $storage->getPropertyDefinition($storage->getMainPropertyName());
        $index->addField($fields_helper->createFieldFromProperty($index, $data_definition, $datasource, $field->getName()));
      }
      // Save automatically tracks all items in a batch. This tracking does
      // not happen during config sync so handle that separately.
      $index->save();
      // @TODO Check what happens in the core config UI.
      $is_drush_batch = !\Drupal::service('config.installer')->isSyncing() && function_exists('drush_backend_batch_process');
      if ($is_drush_batch) {
        \Drupal::service('search_api.index_task_manager')->addItemsBatch($index);
      }
      // Index all items in the same batch as well.
      IndexBatchHelper::create($index);
      if ($is_drush_batch) {
        drush_backend_batch_process();
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
    if (!$field_storage = FieldStorageConfig::loadByName('pantheon_content_publisher', $drupal_field_name)) {
      $data = [
        'type' => $type,
        'field_name' => $drupal_field_name,
        'entity_type' => 'pantheon_content_publisher',
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
      'entity_type' => 'pantheon_content_publisher',
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
