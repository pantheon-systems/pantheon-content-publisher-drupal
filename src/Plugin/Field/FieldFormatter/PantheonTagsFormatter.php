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

      $this->processNode($domDocument, json_decode($item->value, TRUE), $container, $uniqueClass);

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
   *
   * @throws \DOMException
   */
  protected static function processNode(\DOMDocument $domDocument, array $node, \DOMElement $parent, string $uniqueClass): void {
    $tag = $node['tag'] ?? 'div';
    $content = $node['data'] ?? '';
    if ($tag === 'style' && $content) {
      $node['children'] = [];
      $content = ".$uniqueClass $content";
    }
    $element = $domDocument->createElement($tag);
    foreach ($node['attrs'] ?? [] as $key => $value) {
      $element->setAttribute($key, $value);
    }
    if ($styles = ($node['styles'] ?? [])) {
      $element->setAttribute('style', implode(', ', $styles));
    }
    if ($content) {
      $element->nodeValue = $content;
    }
    foreach ($node['children'] ?? [] as $child) {
      static::processNode($domDocument, $child, $element, $uniqueClass);
    }
    $parent->appendChild($element);
  }

  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getName() === 'content' && $field_definition->getTargetEntityTypeId() === 'pantheon_content_publisher';
  }

}
