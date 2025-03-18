<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\EnforcedResponseException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\pantheon_content_publisher\PantheonContentPublisherCollInterface;
use Drupal\search_api\Entity\Server;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Pantheon content publisher collection form.
 */
class PantheonContentPublisherCollForm extends EntityForm implements ContainerInjectionInterface {

  use RedirectDestinationTrait;

  public function __construct(protected UrlGeneratorInterface $urlGenerator) {}

  public static function create(ContainerInterface $container) {
    return new static($container->get('url_generator'));
  }

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

    if (!$servers = Server::loadMultiple()) {
      $this->messenger()->addMessage(t('Please add a search API server first'));
      $url = $this->urlGenerator->generateFromRoute('entity.search_api_server.add_form', [], ['query' => $this->getDestinationArray()]);
      throw new EnforcedResponseException(new LocalRedirectResponse($url));
    }
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

  public function validateForm(array &$form, FormStateInterface $form_state) {
    try {
      $collection = $this->getEntity();
      assert($collection instanceof PantheonContentPublisherCollInterface);
      if ($collection->isNew()) {
        $collection->getGraphQL()->getMetadata();

      }
    }
    catch (\Exception $e) {
      $form_state->setErrorByName('id', t('Unable to retrieve metadata for this collection with this token'));
    }
  }

}
