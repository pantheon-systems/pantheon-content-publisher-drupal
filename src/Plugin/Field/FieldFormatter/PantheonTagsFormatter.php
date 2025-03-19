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

  protected \DOMDocument $domDocument;

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $element = [];
    foreach ($items as $delta => $item) {
      $this->domDocument = new \DOMDocument();
      $container = $this->domDocument->createElement('div');

      // Generate a unique class name for scoping
      $uniqueClass = 'pantheon_' . Html::cleanCssIdentifier((new Random)->string());
      $container->setAttribute('class', $uniqueClass);

      $this->processNode($item->value, $container, $uniqueClass);

      $element[$delta] = [
        '#type' => 'inline_template',
        '#template' => '{{ value | raw }}',
        '#context' => ['value' => $this->domDocument->saveHTML($container)],
      ];
    }
    return $element;
  }

  protected function processNode(array $node, \DOMElement $parent, string $uniqueClass): void {
    $tag = $node['tag'] ?? 'div';
    $data = $node['data'] ?? '';
    $style = $node['style'] ?? [];
    $children = $node['children'] ?? [];
    $attrs = $node['attrs'] ?? [];

    if (!$children && !$data) {
      return;
    }

    // Scope styles if the tag is 'style'
    if ($tag === 'style' && $data) {
      $element = $this->createElement($tag, $attrs, $style, ".$uniqueClass $data");
      $parent->appendChild($element);
      return;
    }
    $element = $this->createElement($tag, $attrs, $style, $data);
    foreach ($children as $child) {
      $this->processNode($child, $element, $uniqueClass);
    }
    $parent->appendChild($element);
  }

  protected function createElement(string $tag, array $attrs, array $styles, string $content): \DOMElement {
    $element = $this->domDocument->createElement($tag);
    foreach ($attrs as $key => $value) {
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
