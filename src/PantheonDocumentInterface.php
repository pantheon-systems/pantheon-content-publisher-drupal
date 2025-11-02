<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;

/**
 * Provides an interface defining a pantheon content publisher entity type.
 */
interface PantheonDocumentInterface extends ContentEntityInterface, EntityChangedInterface, EntityPublishedInterface {

  /**
   * Denotes that the document is not published.
   */
  const NOT_PUBLISHED = 0;

  /**
   * Denotes that the document is published.
   */
  const PUBLISHED = 1;

}
