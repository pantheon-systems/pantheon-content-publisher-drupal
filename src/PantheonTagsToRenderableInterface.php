<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher;

/**
 * Converts Pantheon content JSON to a renderable.
 */
interface PantheonTagsToRenderableInterface {

  /**
   * Convert JSON to renderable.
   *
   * @param string $json
   *   A serialized json object describing a DOM.
   */
  public function convertJsonToRenderable(string $json): array;

  /**
   * Extract image data from JSON.
   *
   * @param string $json
   *   A serialized json object describing a DOM.
   *
   * @return array
   *   A Drupal renderable with a custom #pantheon_image_data associated array.
   *   The keys are URLs to images, the values are key-value pairs of HTML
   *   attributes of an img tag and its value.
   */
  public function getImageData(string $json): array;

}
