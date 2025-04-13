<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a pantheon content publisher collection entity type.
 */
interface PantheonDocumentCollectionInterface extends ConfigEntityInterface {

  public function getToken(): string;

  /**
   * @internal
   *
   * @return string
   *   The id of th key entity.
   */
  public function getKey(): string;

  public function getUrl(): string;

  public function getGraphQL(): GraphQL;

}
