<?php

namespace Drupal\pantheon_content_publisher\Plugin\search_api\processor;

use Drupal\Core\Render\RendererInterface;
use Drupal\pantheon_content_publisher\PantheonTagsToRenderableInterface;
use Drupal\search_api\Annotation\SearchApiProcessor;
use Drupal\search_api\Processor\FieldsProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * @SearchApiProcessor(
 *   id = "pantheon_tags",
 *   label = @Translation("Pantheon Tags"),
 *   description = @Translation("Converts tagged JSON text into rendered HTML before indexing."),
 *   stages = {
 *     "preprocess_index" = 0
 *   }
 * )
 */
class PantheonTags extends FieldsProcessorPluginBase implements ContainerFactoryPluginInterface {

  /**
   * PantheonTags constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected PantheonTagsToRenderableInterface $tagsToRenderable, protected RendererInterface $renderer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('pantheon_content_publisher.tags_to_renderable'),
      $container->get('renderer')
    );
  }

  protected function processFieldValue(&$value, $type) {
    if ($build = $this->tagsToRenderable->convertJsonToRenderable($value)) {
      $value = (string) $this->renderer->renderInIsolation($build);
    }
  }

}
