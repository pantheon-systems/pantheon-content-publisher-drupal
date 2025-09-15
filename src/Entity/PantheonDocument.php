<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\pantheon_content_publisher\PantheonDocumentInterface;

/**
 * Defines the pantheon content publisher entity class.
 *
 * @ContentEntityType(
 *   id = "pantheon_document",
 *   label = @Translation("Pantheon Document"),
 *   label_collection = @Translation("Pantheon Documents"),
 *   label_singular = @Translation("pantheon document"),
 *   label_plural = @Translation("pantheon documents"),
 *   label_count = @PluralTranslation(
 *     singular = "@count pantheon content documents",
 *     plural = "@count pantheon content documents",
 *   ),
 *   bundle_label = @Translation("Pantheon document collection"),
 *   handlers = {
 *     "list_builder" = "Drupal\pantheon_content_publisher\PantheonDocumentListBuilder",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "access" = "Drupal\pantheon_content_publisher\PantheonDocumentAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\pantheon_content_publisher\Form\PantheonDocumentForm",
 *       "edit" = "Drupal\pantheon_content_publisher\Form\PantheonDocumentForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" = "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *     "storage" = "Drupal\pantheon_content_publisher\PantheonDocumentStorage",
 *   },
 *   admin_permission = "administer pantheon_document types",
 *   field_ui_base_route = "entity.pantheon_document_collection.edit_form",
 *   entity_keys = {
 *     "id" = "id",
 *     "bundle" = "collection",
 *     "label" = "title",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "collection" = "/admin/content/pantheon-content-publisher",
 *     "canonical" = "/pantheon-content-publisher/{pantheon_document}",
 *     "edit-form" = "/pantheon-content-publisher/{pantheon_document}/edit",
 *   },
 *   bundle_entity_type = "pantheon_document_collection",
 * )
 */
class PantheonDocument extends ContentEntityBase implements PantheonDocumentInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {

    $fields = parent::baseFieldDefinitions($entity_type);
    // In order to work around the InnoDB 191 character limit on utf8mb4
    // primary keys, we set the character set for the field to ASCII.
    $fields['id'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('ID'))
      ->setReadOnly(TRUE)
      ->setSetting('is_ascii', TRUE)
      ->setDisplayOptions('view', [
        'region' => 'hidden',
      ])
      ->setDisplayConfigurable('view', FALSE);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['description'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Description'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 4096)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['image'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Image'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'imagecache_external_image',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Status'))
      ->setDefaultValue(TRUE)
      ->setSetting('on_label', 'Enabled')
      ->setDisplayOptions('view', [
        'region' => 'hidden',
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the pantheon content publisher was created.'))
      ->setDisplayOptions('view', [
        'region' => 'hidden',
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the pantheon content publisher was last edited.'));
      ->setDisplayOptions('view', [
        'region' => 'hidden',
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['content'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Content'))
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'pantheon_document_tags_formatter',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['slug'] = BaseFieldDefinition::create('string')
      ->setSetting('is_ascii', TRUE)
      ->setDisplayOptions('view', [
        'region' => 'hidden',
      ])
      ->setDisplayConfigurable('view', FALSE);

    return $fields;
  }

}
