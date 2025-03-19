<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the Pantheon Tags formatter.
 *
 * @FieldFormatter(
 *   id = "pantheon_content_publisher_tags_formatter",
 *   label = @Translation("Pantheon tags formatter"),
 *   field_types = {"string_long"},
 * )
 */
class PantheonTagsFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $element = [];
    foreach ($items as $delta => $item) {
      $domDocument = new \DOMDocument();
      $container = $domDocument->createElement('div');

      // Generate a unique class name for scoping
      $uniqueClass = 'pantheon_' . Html::cleanCssIdentifier((new Random)->string());
      $container->setAttribute('class', $uniqueClass);

      $this->processNode($domDocument, $item->value, $container, $uniqueClass);

      $element[$delta] = [
        '#type' => 'inline_template',
        '#template' => '{{ value | raw }}',
        '#context' => ['value' => $domDocument->saveHTML($container)],
      ];
    }
    return $element;
  }

  protected static function processNode(\DOMDocument $domDocument, array $node_data, \DOMElement $parent, string $uniqueClass): void {
    $tag = $node_data['tag'] ?? 'div';
    $content = $node_data['data'] ?? '';
    $children = $node_data['children'] ?? [];

    if (!$children && !$content) {
      return;
    }

    if ($tag === 'style' && $content) {
      $element = static::createElement($domDocument, $tag, $node_data['attrs'], $node_data['style'], ".$uniqueClass $content");
      $parent->appendChild($element);
      return;
    }
    $element = static::createElement($domDocument, $tag, $node_data['attrs'], $node_data['style'], $content);
    foreach ($children as $child) {
      static::processNode($domDocument, $child, $element, $uniqueClass);
    }
    $parent->appendChild($element);
  }

  protected static function createElement(\DOMDocument $domDocument, string $tag, ?array $attrs, ?array $styles, string $content): \DOMElement {
    $element = $domDocument->createElement($tag);
    foreach ((array) $attrs as $key => $value) {
      $element->setAttribute($key, $value);
    }
    if ($styles) {
      $element->setAttribute('style', implode(', ', $styles));
    }
    if ($content) {
      $element->nodeValue = $content;
    }
    return $element;
  }

}
