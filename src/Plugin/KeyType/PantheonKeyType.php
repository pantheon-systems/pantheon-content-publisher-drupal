<?php

namespace Drupal\pantheon_content_publisher\Plugin\KeyType;

use Drupal\key\Plugin\KeyType\AuthenticationKeyType;

/**
 * Defines a Pantheon Content Publisher key type.
 *
 * @KeyType(
 *   id = "pantheon_content_publisher",
 *   label = @Translation("Pantheon content publisher"),
 *   description = @Translation("A Pantheon content publisher token."),
 *   group = "authentication",
 *   key_value = {
 *     "plugin" = "text_field"
 *   }
 * )
 */
class PantheonKeyType extends AuthenticationKeyType {

}
