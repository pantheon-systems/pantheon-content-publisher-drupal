<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\pantheon_content_publisher\PantheonContentPublisherInterface;

/**
 * Defines the pantheon content publisher entity class.
 *
 * @ContentEntityType(
 *   id = "pantheon_content_publisher",
 *   label = @Translation("Pantheon content publisher"),
 *   label_collection = @Translation("Pantheon content publishers"),
 *   label_singular = @Translation("pantheon content publisher"),
 *   label_plural = @Translation("pantheon content publishers"),
 *   label_count = @PluralTranslation(
 *     singular = "@count pantheon content publishers",
 *     plural = "@count pantheon content publishers",
 *   ),
 *   bundle_label = @Translation("Pantheon content publisher collection"),
 *   handlers = {
 *     "list_builder" = "Drupal\pantheon_content_publisher\PantheonContentPublisherListBuilder",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "form" = {
 *       "add" = "Drupal\pantheon_content_publisher\Form\PantheonContentPublisherForm",
 *       "edit" = "Drupal\pantheon_content_publisher\Form\PantheonContentPublisherForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" = "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *     "storage" = "Drupal\pantheon_content_publisher\PantheonContentPublisherStorage",
 *   },
 *   admin_permission = "administer pantheon_content_publisher types",
 *   field_ui_base_route = "entity.pantheon_content_publisher_coll.edit_form",
 *   entity_keys = {
 *     "id" = "id",
 *     "bundle" = "collection",
 *     "label" = "title",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "collection" = "/admin/content/pantheon-content-publisher",
 *     "add-form" = "/pantheon-content-publisher/add/{pantheon_content_publisher_coll}",
 *     "add-page" = "/pantheon-content-publisher/add",
 *     "canonical" = "/pantheon-content-publisher/{pantheon_content_publisher}",
 *     "edit-form" = "/pantheon-content-publisher/{pantheon_content_publisher}/edit",
 *     "delete-form" = "/pantheon-content-publisher/{pantheon_content_publisher}/delete",
 *     "delete-multiple-form" = "/admin/content/pantheon-content-publisher/delete-multiple",
 *   },
 *   bundle_entity_type = "pantheon_content_publisher_coll",
 * )
 */
class PantheonContentPublisher extends ContentEntityBase implements PantheonContentPublisherInterface {

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
      ->setSetting('is_ascii', TRUE);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Label'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
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
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the pantheon content publisher was last edited.'));

    $fields['content'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Content'))
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'pantheon_content_publisher_raw_formatter',
        'weight' => 20,
      ]);

    return $fields;
  }

}
