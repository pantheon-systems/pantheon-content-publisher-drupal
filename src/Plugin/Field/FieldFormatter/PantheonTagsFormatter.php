<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\pantheon_content_publisher\PantheonTagsToRenderable;
use Drupal\text\Plugin\Field\FieldFormatter\TextTrimmedFormatter;
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
class PantheonTagsFormatter extends TextTrimmedFormatter {

  protected PantheonTagsToRenderable $tagsToRenderable;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $formatter = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $formatter->tagsToRenderable = $container->get('pantheon_content_publisher.tags_to_renderable');
    return $formatter;
  }

  public static function defaultSettings() {
    $settings = parent::defaultSettings();
    $settings['trim_length'] = 0;
    return $settings;
  }

  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);
    $element['trim_length']['#description'] = t('The maximum length of the displayed content. Set to zero 0 to disable trimming');
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $element = [];
    $trimLength = (int) $this->getSetting('trim_length');
    foreach ($items as $delta => $item) {
      $element[$delta] = $this->tagsToRenderable->convertJsonToRenderable($item->value, $trimLength);
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getName() === 'content' && $field_definition->getTargetEntityTypeId() === 'pantheon_document';
  }


}
