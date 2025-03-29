<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\pantheon_content_publisher\PantheonSmartComponentInterface;

/**
 * Defines the pantheon smart component entity type.
 *
 * @ConfigEntityType(
 *   id = "pantheon_smart_component",
 *   label = @Translation("Pantheon smart component"),
 *   label_collection = @Translation("Pantheon smart components"),
 *   label_singular = @Translation("pantheon smart component"),
 *   label_plural = @Translation("pantheon smart components"),
 *   label_count = @PluralTranslation(
 *     singular = "@count pantheon smart component",
 *     plural = "@count pantheon smart components",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\pantheon_content_publisher\PantheonSmartComponentListBuilder",
 *     "form" = {
 *       "add" = "Drupal\pantheon_content_publisher\Form\PantheonSmartComponentForm",
 *       "edit" = "Drupal\pantheon_content_publisher\Form\PantheonSmartComponentForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *   },
 *   config_prefix = "pantheon_smart_component",
 *   admin_permission = "administer pantheon_smart_component",
 *   bundle_of = "pantheon_smart_instance",
 *   links = {
 *     "collection" = "/admin/structure/pantheon-smart-component",
 *     "add-form" = "/admin/structure/pantheon-smart-component/add",
 *     "edit-form" = "/admin/structure/pantheon-smart-component/{pantheon_smart_component}",
 *     "delete-form" = "/admin/structure/pantheon-smart-component/{pantheon_smart_component}/delete",
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "title",
 *     "uuid" = "uuid",
 *   },
 *   config_export = {
 *     "id",
 *     "title",
 *   },
 * )
 */
class PantheonSmartComponent extends ConfigEntityBase implements PantheonSmartComponentInterface {

  /**
   * The example ID.
   */
  protected string $id;

  /**
   * The example label.
   */
  protected string $title;

  // @TODO handle iconUrl.

}
