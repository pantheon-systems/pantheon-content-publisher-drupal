<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Random;
use Drupal\Core\Entity\EntityViewBuilderInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\pantheon_content_publisher\Entity\PantheonSmartInstance;
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
class PantheonTagsFormatter extends FormatterBase {

  protected EntityViewBuilderInterface $viewBuilder;

  protected RendererInterface $renderer;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $formatter = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $formatter->viewBuilder = $container
      ->get('entity_type.manager')
      ->getViewBuilder('pantheon_smart_instance');
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
      $container = $domDocument
        ->appendChild($domDocument->createElement('body'))
        ->appendChild($domDocument->createElement('div'));
      $random = new Random();

      // Generate a unique class name for scoping.
      $uniqueClass = 'pantheon_' . $random->machineName(16, TRUE);
      $container->setAttribute('class', $uniqueClass);

      $this->processNode($node, $container, $uniqueClass, $items->getEntity()->_image_data);

      $element[$delta] = [
        '#type' => 'inline_template',
        '#template' => '{{ value | raw }}',
        '#context' => ['value' => Html::serialize($domDocument)],
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
   * @param array $image_data
   *   Image tag information is collected in this array.
   */
  protected function processNode(array $node, \DOMElement $parent, string $uniqueClass, array &$image_data): void {
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
    $domDocument = $parent->ownerDocument;
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
          $component = PantheonSmartInstance::create(['component' => $node['type']] + $attrs);
          $build = $this->viewBuilder->view($component);
          $html = (string) $this->renderer->renderInIsolation($build);
          $element = $domDocument->importNode(Html::load($html)->documentElement, TRUE);
          $attrs = [];
        }
        break;
    }
    if (!isset($element)) {
      $element = $domDocument->createElement($tag, $data);
    }
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

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    return $field_definition->getName() === 'content' && $field_definition->getTargetEntityTypeId() === 'pantheon_document';
  }

}
