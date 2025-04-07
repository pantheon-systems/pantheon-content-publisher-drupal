<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Random;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the Pantheon Tags formatter.
 *
 * @FieldFormatter(
 *   id = "pantheon_document_tags_formatter",
 *   label = @Translation("Pantheon tags formatter"),
 *   field_types = {"string_long"},
 * )
 */
class PantheonTagsFormatter extends FormatterBase  {

  protected EntityTypeManagerInterface $entityTypeManager;

  protected RendererInterface $renderer;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $formatter = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $formatter->entityTypeManager = $container->get('entity_type.manager');
    $formatter->renderer = $container->get('renderer');
    return $formatter;
  }

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
      $random = new Random();
      $quote = $random->string(16);

      // Generate a unique class name for scoping
      $uniqueClass = 'pantheon_' . Html::cleanCssIdentifier($random->string(16));
      $container->setAttribute('class', $uniqueClass);

      $this->processNode($node, $container, $uniqueClass, $quote, $items->getEntity()->_image_data);

      $element[$delta] = [
        '#type' => 'inline_template',
        '#template' => '{{ value | raw }}',
        // See $quote in ::processNode() what this replace is.
        '#context' => ['value' => str_replace($quote, '&quot;', $domDocument->saveHTML($container))],
      ];
    }

    return $element;
  }

  /**
   * Process a node array into a DOM element.
   *
   * @param array $node
   *   The current node data.
   * @param \DOMElement $parent
   *   The parent DOM element.
   * @param string $uniqueClass
   *   The unique class used for CSS scoping.
   * @param string $quote
   *   DOM is decoding &quot; but not the rest. Don't ask me why. So this
   *   random string is replacing &quot; on importing the HTML output of a
   *   smart component and in turn it is replaced back when exporting the
   *   entire DOM.
   * @param array $image_data
   *   Image tag information is collected in this array.
   *
   * @throws \DOMException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function processNode(array $node, \DOMElement $parent, string $uniqueClass, string $quote, array &$image_data): void {
    $defaults = [
      'tag' => 'div',
      'data' => '',
      'attrs' => [],
      'style' => [],
      'children' => [],
    ];
    ['tag' => $tag, 'data' => $data, 'attrs' => $attrs, 'style' => $style, 'children' => $children] = array_filter($node) + $defaults;
    if (!$attrs && !$data && !$children && empty($node['type'])) {
      return;
    }
    switch ($tag) {
      case 'style':
        if ($data) {
          $children = [];
          $data = ".$uniqueClass $data";
        }
        break;

      case 'img':
        $image_data[$attrs['src']] = $attrs;
        break;

      case 'component':

        if (!empty($node['type'])) {
          $component = $this->entityTypeManager
            ->getStorage('pantheon_smart_instance')
            ->create(['component' => $node['type']] + $attrs);
          $build = $this->entityTypeManager
            ->getViewBuilder('pantheon_smart_instance')
            ->view($component);
          $html = (string) $this->renderer->renderInIsolation($build);
          $tmp = new \DOMDocument();
          // DOM is decoding &quot; but not the rest. Don't ask me why.
          $tmp->loadHTML(str_replace('&quot;', $quote, $html), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
          $parent->appendChild($parent->ownerDocument->importNode($tmp->documentElement, TRUE));
          return;
        }
    }
    $element = $parent->ownerDocument->createElement($tag, $data);
    foreach ($attrs as $key => $value) {
      if (isset($value)) {
        $element->setAttribute($key, $value);
      }
    }
    if ($style) {
      $element->setAttribute('style', implode('; ', $style));
    }
    foreach ($children as $child) {
      $this->processNode($child, $element, $uniqueClass, $quote, $image_data);
    }
    $parent->appendChild($element);
  }

  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    return $field_definition->getName() === 'content' && $field_definition->getTargetEntityTypeId() === 'pantheon_document';
  }

}
