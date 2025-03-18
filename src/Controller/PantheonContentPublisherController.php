<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\BareHtmlPageRendererInterface;
use Drupal\pantheon_content_publisher\Entity\PantheonContentPublisher;
use Drupal\pantheon_content_publisher\Entity\PantheonContentPublisherColl;
use Drupal\pantheon_content_publisher\PantheonContentPublisherCollInterface;
use Drupal\search_api\Entity\Index;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns responses for Pantheon content publisher routes.
 */
class PantheonContentPublisherController extends ControllerBase {

  public function __construct(protected RequestStack $requestStack, protected BareHtmlPageRendererInterface $bareHtmlPageRenderer) {

  }

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
        'drupalSettings' => [
          'pantheon_content_publisher' => [
            'site_id' => $collection->id(),
            'token' => 'pcc_grant ' . $this->requestStack->getCurrentRequest()->query->get('pccGrant'),
            'pantheon_id' => $pantheon_id,
          ],
        ],
      ],
    ];
    $response = $this->bareHtmlPageRenderer->renderBarePage([], 'Preview', 'markup', $build);
    $response->headers->set('X-Pantheon-Content-Publisher', 'preview');
    return $response;
  }

  /**
   * @param \Drupal\pantheon_content_publisher\Entity\PantheonContentPublisherColl $collection
   *   The Pantheon content publisher collection.
   * @param array $decoded
   *   The decoded webhook payload.
   */
  protected function handleEvent(PantheonContentPublisherCollInterface $collection, array $decoded): void {
    switch ($decoded['event']) {
      case 'article.publish':
        $pantheon_content_publisher = PantheonContentPublisher::load($collection->id() . ':' . $decoded['payload']['articleId']);
        search_api_entity_update($pantheon_content_publisher);
        Index::load($collection->id())->indexItems();
        break;
    }
  }

}
