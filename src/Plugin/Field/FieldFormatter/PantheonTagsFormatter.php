<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the Pantheon Tags formatter.
 *
 * @FieldFormatter(
 *   id = "pantheon_document_tags_formatter",
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
    $items->getEntity()->_image_data = [];
    foreach ($items as $delta => $item) {
      if (!$item->value || (!$node = @json_decode($item->value, TRUE))) {
        continue;
      }
      $domDocument = new \DOMDocument();
      $container = $domDocument->createElement('div');

      // Generate a unique class name for scoping
      $uniqueClass = 'pantheon_' . Html::cleanCssIdentifier((new Random)->string(16));
      $container->setAttribute('class', $uniqueClass);

      static::processNode($domDocument, $node, $container, $uniqueClass, $items->getEntity()->_image_data);

      $element[$delta] = [
        '#type' => 'inline_template',
        '#template' => '{{ value | raw }}',
        '#context' => ['value' => $domDocument->saveHTML($container)],
      ];
    }

    return $element;
  }

  /**
   * Process a node array into a DOM element.
   *
   * @param \DOMDocument $domDocument
   *   The dom document being worked on.
   * @param array $node
   *   The current node data.
   * @param \DOMElement $parent
   *   The parent DOM element.
   * @param string $uniqueClass
   *   The unique class used for CSS scoping.
   * @param array $image_data
   *   Image tag information is collected in this array.
   *
   * @throws \DOMException
   */
  protected static function processNode(\DOMDocument $domDocument, array $node, \DOMElement $parent, string $uniqueClass, array &$image_data): void {
    $defaults = [
      'tag' => 'div',
      'data' => '',
      'attrs' => [],
      'style' => [],
      'children' => [],
    ];
    ['tag' => $tag, 'data' => $data, 'attrs' => $attrs, 'style' => $style, 'children' => $children] = array_filter($node) + $defaults;
    if (!$attrs && !$data && !$children) {
      return;
    }
    if ($tag === 'style' && $data) {
      $children = [];
      $data = ".$uniqueClass $data";
    }
    if ($tag === 'img') {
      $image_data[$attrs['src']] = $attrs;
    }
    $element = $domDocument->createElement($tag, $data);
    foreach ($attrs as $key => $value) {
      if (isset($value)) {
        $element->setAttribute($key, $value);
      }
    }
    if ($style) {
      $element->setAttribute('style', implode('; ', $style));
    }
    foreach ($children as $child) {
      static::processNode($domDocument, $child, $element, $uniqueClass, $image_data);
    }
    $parent->appendChild($element);
  }

  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getName() === 'content' && $field_definition->getTargetEntityTypeId() === 'pantheon_document';
  }

}
