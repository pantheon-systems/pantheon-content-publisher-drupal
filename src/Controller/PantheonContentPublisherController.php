<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\pantheon_content_publisher\Entity\PantheonContentPublisher;
use Drupal\pantheon_content_publisher\Entity\PantheonContentPublisherColl;
use Drupal\pantheon_content_publisher\PantheonContentPublisherCollInterface;
use Drupal\search_api\Entity\Index;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns responses for Pantheon content publisher routes.
 */
class PantheonContentPublisherController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function webhook(): Response {
    if ($decoded = @json_decode(file_get_contents('php://input'), TRUE)) {
      $collections = PantheonContentPublisherColl::loadMultiple();
      $this->handleEvent(reset($collections), $decoded);
    }
    return new Response();
  }

  public function preview($pantheon_id): Response {
    $collections = PantheonContentPublisherColl::loadMultiple();
    $collection = reset($collections);
    $build = [
      '#markup' => '<div id="pantheon-content-publisher-preview"></div>',
      '#attached' => [
        'library' => ['pantheon_content_publisher/drupal.pantheon_content_publisher.preview'],
        'drupalSettings' => ['pantheon_content_publisher' => [
          'site_id' => $collection->id(),
          'token' => 'pcc_grant ' . \Drupal::request()->query->get('pccGrant'),
          'pantheon_id' => $pantheon_id,
        ]],
      ],
    ];
    $response = \Drupal::service('bare_html_page_renderer')->renderBarePage([], 'Preview', 'markup', $build);
    $response->headers->set('X-Pantheon-Content-Publisher', 'preview');
    return $response;
  }

  /**
   * @param \Drupal\pantheon_content_publisher\Entity\PantheonContentPublisherColl $collection
   * @param array $decoded
   *   The decoded webhook payload.
   *
   * @return void
   */
  public function handleEvent(PantheonContentPublisherCollInterface $collection, array $decoded): void {
    switch ($decoded['event']) {
      case 'article.publish':
        $pantheon_content_publisher = PantheonContentPublisher::load($collection->id() . ':' . $decoded['payload']['articleId']);
        search_api_entity_update($pantheon_content_publisher);
        $index_ids = \Drupal::entityQuery('search_api_index')
          ->condition('third_party_settings.pantheon_collection_publisher.collection', $collection->id())
          ->execute();
        if ($index_ids) {
          Index::load(reset($index_ids))->indexItems();
        }
        break;
    }
  }

}
