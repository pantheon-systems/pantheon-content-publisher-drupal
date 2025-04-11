<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher;

/**
 * Converts Pantheon content JSON to a renderable.
 */
interface PantheonTagsToRenderableInterface {

  /**
   * Convert JSON to renderable.
   */
  public function convertJsonToRenderable(string $json, array &$image_data = []): array;
}
