<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\pantheon_content_publisher\PantheonContentPublisherCollInterface;
use Drupal\search_api\Entity\Server;

/**
 * Pantheon content publisher collection form.
 */
class PantheonContentPublisherCollForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {

    $form = parent::form($form, $form_state);
    assert($this->entity instanceof PantheonContentPublisherCollInterface);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#required' => TRUE,
    ];

    if ($this->entity->isNew()) {
      $form['id'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Content Publisher Site ID'),
        '#required' => TRUE,
      ];
    }

    $form['token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Content Publisher token'),
      '#required' => TRUE,
      '#default_value' => $this->entity->getToken(),
    ];

    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Content Publisher URL'),
      '#required' => TRUE,
      '#default_value' => $this->entity->getUrl() ?: 'https://gql.prod.pcc.pantheon.io',
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->entity->get('description'),
    ];

    $servers = Server::loadMultiple();
    $form['search_api_server'] = [
      '#type' => 'select',
      '#title' => $this->t('Search server'),
      '#options' => array_map(fn ($s) => $s->label(), $servers),
      '#default_value' => $this->entity->get('search_api_server'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];
    $this->messenger()->addStatus([
        \SAVED_NEW => $this->t('Created new Content Publisher collection %label.', $message_args),
        \SAVED_UPDATED => $this->t('Updated Content Publisher collection %label.', $message_args),
    ][$result]);
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

}
