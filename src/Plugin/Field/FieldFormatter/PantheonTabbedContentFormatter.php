<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\pantheon_content_publisher\PantheonTagsToRenderableInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders tabbed content JSON as a tabbed UI.
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
   * Build the render array for a set of tabs.
   *
   * @param array $tabs
   *   The decoded tabbed content array.
   *
   * @return array
   *   A Drupal render array.
   */
  protected function buildTabs(array $tabs): array {
    $headers = [];
    $panels = [];

    foreach ($tabs as $index => $tab) {
      if (empty($tab) || !is_array($tab)) {
        continue;
      }
      $tab_id = $tab['tabProperties']['tabId'] ?? 'tab-' . $index;
      $title = $tab['tabProperties']['title'] ?? 'Tab ' . ($index + 1);
      $safe_id = preg_replace('/[^a-zA-Z0-9\-]/', '-', $tab_id);

      $headers[] = [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => $title,
        '#attributes' => [
          'class' => ['pantheon-tab-button'],
          'data-tab-id' => $safe_id,
          'role' => 'tab',
          'aria-selected' => $index === 0 ? 'true' : 'false',
          'aria-controls' => 'pantheon-tab-panel-' . $safe_id,
        ],
      ];

      $content = $this->renderTabContent($tab['documentTab'] ?? '');

      $panel = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['pantheon-tab-panel'],
          'data-tab-id' => $safe_id,
          'id' => 'pantheon-tab-panel-' . $safe_id,
          'role' => 'tabpanel',
        ],
        'content' => $content,
      ];

      // Render child tabs recursively if present.
      if (!empty($tab['childTabs']) && is_array($tab['childTabs'])) {
        $panel['child_tabs'] = $this->buildTabs($tab['childTabs']);
      }

      $panels[] = $panel;
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['pantheon-tabs'],
      ],
      'headers' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['pantheon-tab-headers'],
          'role' => 'tablist',
        ],
        'buttons' => $headers,
      ],
      'panels' => $panels,
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
