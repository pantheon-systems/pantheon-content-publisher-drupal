<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\pantheon_content_publisher\PantheonTagsToRenderableInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders tabbed content JSON as heading/content sections.
 *
 * @FieldFormatter(
 *   id = "pantheon_tabbed_content_formatter",
 *   label = @Translation("Pantheon tabbed content"),
 *   field_types = {"string_long"},
 * )
 */
class PantheonTabbedContentFormatter extends FormatterBase {

  protected PantheonTagsToRenderableInterface $tagsToRenderable;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $formatter = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $formatter->tagsToRenderable = $container->get('pantheon_content_publisher.tags_to_renderable');
    return $formatter;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    foreach ($items as $delta => $item) {
      if (empty($item->value)) {
        continue;
      }
      $tabs = @json_decode($item->value, TRUE);
      if (!is_array($tabs) || empty($tabs)) {
        continue;
      }
      $elements[$delta] = $this->buildTabs($tabs);
    }
    return $elements;
  }

  /**
   * Build the render array for a set of tabs as heading/content sections.
   *
   * @param array $tabs
   *   The decoded tabbed content array.
   * @param int $heading_level
   *   The heading level to use (2-6).
   *
   * @return array
   *   A Drupal render array.
   */
  protected function buildTabs(array $tabs, int $heading_level = 2): array {
    $sections = [];

    foreach ($tabs as $index => $tab) {
      if (empty($tab) || !is_array($tab)) {
        continue;
      }
      $title = $tab['tabProperties']['title'] ?? 'Tab ' . ($index + 1);
      $tag = 'h' . min($heading_level, 6);

      $section = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['pantheon-tabbed-section'],
        ],
        'heading' => [
          '#type' => 'html_tag',
          '#tag' => $tag,
          '#value' => $title,
        ],
        'content' => $this->renderTabContent($tab['documentTab'] ?? ''),
      ];

      // Render child tabs recursively with a deeper heading level.
      if (!empty($tab['childTabs']) && is_array($tab['childTabs'])) {
        $section['children'] = $this->buildTabs($tab['childTabs'], $heading_level + 1);
      }

      $sections[] = $section;
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['pantheon-tabbed-content'],
      ],
      'sections' => $sections,
    ];
  }

  /**
   * Render tab content from documentTab value.
   *
   * @param string|array $document_tab
   *   The documentTab value (string JSON, plain text, or array).
   *
   * @return array
   *   A render array.
   */
  protected function renderTabContent(string|array $document_tab): array {
    if (is_array($document_tab)) {
      $json = json_encode($document_tab);
      $build = $this->tagsToRenderable->convertJsonToRenderable($json);
      return $build ?: ['#markup' => ''];
    }
    if (is_string($document_tab) && !empty($document_tab)) {
      $decoded = @json_decode($document_tab, TRUE);
      if ($decoded && is_array($decoded)) {
        $build = $this->tagsToRenderable->convertJsonToRenderable($document_tab);
        return $build ?: ['#markup' => ''];
      }
      return ['#markup' => $document_tab];
    }
    return ['#markup' => ''];
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getName() === 'tabbed_content' && $field_definition->getTargetEntityTypeId() === 'pantheon_document';
  }

}
