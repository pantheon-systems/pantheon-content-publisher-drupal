<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'Raw formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "pantheon_content_publisher_raw_formatter",
 *   label = @Translation("Raw formatter"),
 *   field_types = {"string_long"},
 * )
 */
class RawFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $element = [];
    foreach ($items as $delta => $item) {
      $element[$delta] = [
        '#type' => 'inline_template',
        '#template' => '{{ value | raw }}',
        '#context' => ['value' => $item->value],
      ];
    }
    return $element;
  }

}
