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
use Drupal\Core\Url;
use Drupal\pantheon_content_publisher\ProgressBar;
use Drupal\pantheon_content_publisher\PantheonDocumentCollectionInterface;
use Drupal\search_api\Entity\Server;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Pantheon content publisher collection form.
 */
class PantheonDocumentCollectionForm extends EntityForm implements ContainerInjectionInterface {

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
    assert($this->entity instanceof PantheonDocumentCollectionInterface);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#required' => TRUE,
    ];

    $title = $this->t('Content Publisher Collection Identifier (Site ID)');
    if ($this->entity->isNew()) {
      $form['id'] = [
        '#type' => 'textfield',
        '#title' => $title,
        '#required' => TRUE,
      ];
    }
    else {
      $form['id_info'] = [
        '#type' => 'item',
        '#title' => $title,
        '#markup' => $this->entity->id(),
      ];
    }

    $form['key'] = [
      '#type' => 'key_select',
      '#title' => $this->t('Token'),
      '#key_filters' => [
        'type' => 'pantheon_content_publisher',
      ],
      '#default_value' => $this->entity->getKey(),
      '#required' => TRUE,
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
    if (count($servers) === 1) {
      $form['search_api_server'] = [
        '#type' => 'value',
        '#value' => array_key_first($servers),
      ];
      $form['search_api_server_info'] = [
        '#type' => 'item',
        '#title' => t('Search API server'),
        '#markup' => reset($servers)->label(),
      ];
    }
    else {
      $form['search_api_server'] = [
        '#type' => 'select',
        '#title' => $this->t('Search API server'),
        '#options' => array_map(fn ($s) => $s->label(), $servers),
        '#default_value' => $this->entity->get('search_api_server'),
      ];
    }
    $form['#after_build'][] = '::progressBarOrRedirect';

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
      assert($collection instanceof PantheonDocumentCollectionInterface);
      if ($collection->isNew()) {
        if ($this->entityTypeManager->getStorage($collection->getEntityTypeId())->load($collection->id())) {
          $form_state->setErrorByName('id', t('This collection already exists.'));
        }
        $collection->getGraphQL()->getMetadata();
      }
    }
    catch (\Exception $e) {
      $form_state->setErrorByName('id', t('Invalid collection ID/access token pair.'));
    }
  }

  public function progressBarOrRedirect(array $element): array {
    $destination = $this->getDestinationArray();
    // If there is exactly one search API server which is the most common use
    // case then the search_api_server element is not a select.
    $no_search_api_server = ($element['search_api_server']['#options'] ?? FALSE) === [];
    $no_key = !$element['key']['#options'];
    if (!$this->getRequest()->query->has('missing')) {
      $missing = '';
      if ($no_search_api_server) {
        $missing .= ProgressBar::SERVER;
      }
      if ($no_key) {
        $missing .= ProgressBar::KEY;
      }
      $destination['destination'] .= (str_contains($destination['destination'], '?') ? '&' : '?') . 'missing=' . $missing;
    }
    else {
      ProgressBar::addProgressBar($element, ProgressBar::PANTHEON);
    }
    if ($no_search_api_server) {
      $this->messenger()->addMessage(t('Please add a search API server.'));
      $url = $this->urlGenerator->generateFromRoute('entity.search_api_server.add_form', [], ['query' => $destination]);
      throw new EnforcedResponseException(new LocalRedirectResponse($url));
    }
    $url = $this->urlGenerator->generateFromRoute('entity.key.add_form', [], ['query' => $destination + ['key_type' => 'pantheon_content_publisher']]);
    if ($no_key) {
      $this->messenger()->addMessage(t('Please add your access token.'));
      throw new EnforcedResponseException(new LocalRedirectResponse($url));
    }
    $args = [
      ':new' => $url,
      ':list' => Url::fromRoute('entity.key.collection')->toString(),
    ];
    $element['key']['#description'] = t('Choose an available token. If the desired token is not listed, <a href=":new">create a new token</a>. You can edit tokens <a href=":list">here</a>.', $args);
    return $element;
  }

}
