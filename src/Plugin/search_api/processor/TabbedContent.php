<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\Plugin\search_api\processor;

use Drupal\Core\Render\RendererInterface;
use Drupal\pantheon_content_publisher\PantheonTagsToRenderableInterface;
use Drupal\pantheon_content_publisher\Plugin\search_api\datasource\PantheonDocumentDatasource;
use Drupal\search_api\Annotation\SearchApiProcessor;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Processor\FieldsProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Processes tabbed content for search indexing.
 *
 * @SearchApiProcessor(
 *   id = "tabbed_content",
 *   label = @Translation("Tabbed Content"),
 *   description = @Translation("Extracts and processes content from TabTree array structure."),
 *   stages = {
 *     "preprocess_index" = 0
 *   }
 * )
 */
class TabbedContent extends FieldsProcessorPluginBase implements ContainerFactoryPluginInterface {

  /**
   * TabbedContent constructor.
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

  /**
   * {@inheritdoc}
   */
  protected function testField($name, FieldInterface $field) {
    return $name === 'tabbed_content' && $field->getDatasource() instanceof PantheonDocumentDatasource;
  }

  /**
   * {@inheritdoc}
   */
  protected function processFieldValue(&$value, $type) {
    if (empty($value)) {
      return;
    }

    // Parse the TabTree array structure.
    if (!$tabbed_content = json_decode($value, TRUE)) {
      return;
    }

    // Not a valid array structure.
    if (!is_array($tabbed_content)) {
      return;
    }

    // Extract all tab content recursively.
    $extracted_content = $this->extractTabContent($tabbed_content);

    // Combine all extracted content.
    $value = implode(' ', $extracted_content);
  }

  /**
   * Recursively extract content from TabTree structure.
   *
   * @param array $tabs
   *   Array of TabTree objects.
   *
   * @return array
   *   Array of extracted content strings.
   */
  protected function extractTabContent(array $tabs): array {
    $content = [];

    foreach ($tabs as $tab) {
      if (empty($tab) || !is_array($tab)) {
        continue;
      }

      // Extract content from documentTab field.
      if (isset($tab['documentTab'])) {
        $document_tab = $tab['documentTab'];

        if (is_string($document_tab)) {
          // Check if it's JSON (PantheonTree) or plain text.
          $decoded = json_decode($document_tab, TRUE);
          if ($decoded && is_array($decoded)) {
            // It's PantheonTree JSON - process it.
            if ($build = $this->tagsToRenderable->convertJsonToRenderable($document_tab)) {
              $content[] = (string) $this->renderer->renderInIsolation($build);
            }
          }
          else {
            // It's plain text - add it directly.
            $content[] = $document_tab;
          }
        }
        elseif (is_array($document_tab)) {
          // It's already decoded PantheonTree structure.
          $json = json_encode($document_tab);
          if ($build = $this->tagsToRenderable->convertJsonToRenderable($json)) {
            $content[] = (string) $this->renderer->renderInIsolation($build);
          }
        }
      }

      // Also extract tab title for searchability.
      if (!empty($tab['tabProperties']['title'])) {
        $content[] = $tab['tabProperties']['title'];
      }

      // Recursively process child tabs.
      if (!empty($tab['childTabs']) && is_array($tab['childTabs'])) {
        $child_content = $this->extractTabContent($tab['childTabs']);
        $content = array_merge($content, $child_content);
      }
    }

    return $content;
  }

}
