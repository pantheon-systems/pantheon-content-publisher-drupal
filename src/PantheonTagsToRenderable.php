<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Random;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityViewBuilderInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\pantheon_content_publisher\Entity\PantheonSmartInstance;

/**
 * Converts Pantheon content JSON to a renderable.
 */
class PantheonTagsToRenderable implements PantheonTagsToRenderableInterface {

  protected EntityViewBuilderInterface $viewBuilder;

  public function __construct(EntityTypeManagerInterface $entityTypeManager, protected RendererInterface $renderer) {
    $this->viewBuilder = $entityTypeManager->getViewBuilder('pantheon_smart_instance');
  }

  /**
   * {@inheritdoc}
   */
  public function convertJsonToRenderable(string $json): array {
    if (!$node = @json_decode($json, TRUE)) {
      return [];
    }
    $domDocument = new \DOMDocument();
    $container = $domDocument
      ->appendChild($domDocument->createElement('body'))
      ->appendChild($domDocument->createElement('div'));
    $random = new Random();

    // Generate a unique class name for scoping.
    $uniqueClass = 'pantheon_' . $random->machineName(16, TRUE);
    $container->setAttribute('class', $uniqueClass);

    $metadata = new CacheableMetadata();
    $this->processNode($node, $container, $uniqueClass, $metadata);
    $html = Html::serialize($domDocument);

    $build = [
      '#type' => 'inline_template',
      '#template' => '{{ value | raw }}',
      '#context' => ['value' => $html],
    ];
    $metadata->applyTo($build);

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getImageData(string $json): array {
    return $this->collectImageData(@json_decode($json, TRUE) ?: []);
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
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $metadata
   *   The caching metadata.
   */
  protected function processNode(array $node, \DOMElement $parent, string $uniqueClass, RefinableCacheableDependencyInterface $metadata): void {
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

      case 'component':
        if (!empty($node['type'])) {
          $component = PantheonSmartInstance::create(['component' => $node['type']] + $attrs);
          // Changing the display of the component should invalidate cache.
          $metadata->addCacheableDependency($component);
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
      $this->processNode($child, $element, $uniqueClass, $metadata);
    }
    $parent->appendChild($element);
  }

  protected function collectImageData(array $node): array {
    $image_data = [];
    if (($node['tag'] ?? '') === 'img' && !empty($node['attrs'])) {
      $image_data[$node['attrs']['src']] = $node['attrs'];
    }
    foreach ($node['children'] ?? [] as $child) {
      $image_data += $this->collectImageData($child);
    }
    return $image_data;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    return $field_definition->getName() === 'content' && $field_definition->getTargetEntityTypeId() === 'pantheon_document';
  }

}
