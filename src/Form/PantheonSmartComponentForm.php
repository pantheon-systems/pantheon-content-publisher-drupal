<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\Core\Url;
use Drupal\pantheon_content_publisher\Entity\PantheonSmartComponent;

/**
 * Pantheon smart component form.
 */
class PantheonSmartComponentForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {

    $form = parent::form($form, $form_state);

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => [PantheonSmartComponent::class, 'load'],
        'source' => ['title'],
      ],
      '#disabled' => !$this->entity->isNew(),
    ];

    // Removed hard dependency on media_library_form_element contrib module.
    // Only add icon field if media_library form element is available.
    if (\Drupal::service(ElementInfoManagerInterface::class)->getDefinition('media_library', FALSE)) {
      $form['icon_media'] = [
        '#type' => 'media_library',
        '#title' => $this->t('Icon'),
        '#allowed_bundles' => ['image'],
        '#default_value' => $this->entity->get('icon'),
        '#description' => $this->t('Upload or select the icon for this component.'),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    // Only save icon if the field was present in the form.
    if ($form_state->hasValue('icon_media')) {
      if ($mid = $form_state->getValue('icon_media')) {
        $this->entity->set('icon', (int) $mid);
      }
      else {
        $this->entity->set('icon', NULL);
      }
    }
    $message_args = ['%label' => $this->entity->label()];
    $result = parent::save($form, $form_state);
    if ($result === \SAVED_NEW) {
      $message = $this->t('Fields needs to be added to the new component %label.', $message_args);
      $url = Url::fromRoute('field_ui.field_storage_config_add_pantheon_smart_instance', ['pantheon_smart_component' => $this->entity->id()]);
    }
    else {
      $message = $this->t('Updated smart component %label.', $message_args);
      $url = $this->entity->toUrl('collection');
    }
    $this->messenger()->addStatus($message);
    // Add fields error out without a cache flush.
    drupal_flush_all_caches();
    $form_state->setRedirectUrl($url);
    return $result;
  }

}
