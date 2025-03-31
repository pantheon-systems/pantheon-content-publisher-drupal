<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
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

    $form['icon_media'] = [
      '#type' => 'media_library',
      '#title' => $this->t('Icon'),
      '#allowed_bundles' => ['image'],
      '#default_value' => $this->entity->get('icon'),
      '#description' => t('Upload or select the icon for this component.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    if ($mid = $form_state->getValue('icon_media')) {
      $this->entity->set('icon', (int) $mid);
    }
    else {
      $this->entity->set('icon', NULL);
    }
    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];
    $this->messenger()->addStatus(
      match($result) {
        \SAVED_NEW => $this->t('Created new example %label.', $message_args),
        \SAVED_UPDATED => $this->t('Updated example %label.', $message_args),
      }
    );
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

}
