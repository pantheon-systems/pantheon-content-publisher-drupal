<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\Entity;

use Drupal\Core\Entity\ContentEntityBase;

/**
 * Defines the pantheon content publisher entity class.
 *
 * @ContentEntityType(
 *   id = "pantheon_smart_instance",
 *   label = @Translation("Pantheon smart instance"),
 *   bundle_label = @Translation("Pantheon smart component"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "storage" = "Drupal\Core\Entity\ContentEntityNullStorage",
 *   },
 *   admin_permission = "administer pantheon_smart_instance types",
 *   field_ui_base_route = "entity.pantheon_smart_component.edit_form",
 *   entity_keys = {
 *     "id" = "id",
 *     "bundle" = "component",
 *   },
 *   bundle_entity_type = "pantheon_smart_component",
 * )
 */
class PantheonSmartInstance extends ContentEntityBase {

}
