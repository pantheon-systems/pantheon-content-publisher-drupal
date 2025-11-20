<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Random;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityViewBuilderInterface;
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
    $build = [
      '#type' => 'inline_template',
      '#template' => '{{ value | raw }}',
      '#context' => ['value' => ''],
      '#attached' => [],
    ];

    $this->processNode($node, $container, $uniqueClass, $metadata, $build);
    $html = Html::serialize($domDocument);
    $build['#context']['value'] = $html;
    $metadata->applyTo($build);

    return $build;
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
   * @param array &$build
   *   The top-level render array to merge attachments into (passed by reference).
   */
  protected function processNode(array $node, \DOMElement $parent, string $uniqueClass, RefinableCacheableDependencyInterface $metadata, array &$build): void {
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
          // Changing the component should invalidate cache.
          $metadata->addCacheableDependency($component);
          $component_build = $this->viewBuilder->view($component);
          $html = (string) $this->renderer->renderInIsolation($component_build);

          if (!empty($component_build['#attached'])) {
            $build['#attached'] = array_merge_recursive($build['#attached'], $component_build['#attached']);
          }

          $element = $domDocument->importNode(Html::load($html)->documentElement, TRUE);
          $attrs = [];
        }
        break;
    }
    if (!isset($element)) {
      $element = $domDocument->createElement(
        $tag,
        htmlspecialchars($data, ENT_XML1 | ENT_COMPAT, 'UTF-8')
      );
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
      $this->processNode($child, $element, $uniqueClass, $metadata, $build);
    }
    $parent->appendChild($element);
  }

}
