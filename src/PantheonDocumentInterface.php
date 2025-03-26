<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a pantheon content publisher entity type.
 */
interface PantheonDocumentInterface extends ContentEntityInterface, EntityChangedInterface {

}
