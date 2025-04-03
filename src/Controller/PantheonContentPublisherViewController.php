<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\Controller;

use Drupal\Core\Entity\Controller\EntityViewController;
use Drupal\pantheon_content_publisher\Entity\PantheonDocument;
use Drupal\pantheon_content_publisher\Entity\PantheonDocumentCollection;
use Drupal\pantheon_content_publisher\EventSubscriber\PantheonContentPublisherXFrameSubscriber;
use Drupal\pantheon_content_publisher\PantheonDocumentStorage;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for Pantheon content publisher routes.
 */
class PantheonContentPublisherViewController extends EntityViewController {

  /**
   * HTTP response header name.
   *
   * PantheonDocumentXFrameSubscriber checks this header and if present
   * with ::PREVIEW_HEADER_VALUE value then both this header and the
   * X-Frame-Options header added by FinishResponseSubscriber is removed and
   * so this header is never sent to the client.
   */

  public function pantheonView(Request $request, $pantheon_id): array {
    $collections = PantheonDocumentCollection::loadMultiple();
    $collection = reset($collections);
    $document = PantheonDocument::load(PantheonDocumentStorage::getEntityId($collection, $pantheon_id));
    if ($is_preview = $request->query->get('publishingLevel') === 'REALTIME') {
      // PantheonTagsFormatter turns this into
      // <div id="pantheon-content-publisher-preview"></div>
      $document->get('content')->value = '{"attrs":{"id":"pantheon-content-publisher-preview"}}';
    }
    $page = $this->view($document);
    if ($is_preview) {
      $page['#attached']['library'][] = 'pantheon_content_publisher/drupal.pantheon_content_publisher.preview';
      $page['#attached']['drupalSettings']['pantheon_content_publisher']['site_id'] = $collection->id();
      $page['#attached']['http_header'][] = [PantheonContentPublisherXFrameSubscriber::HEADER_NAME, PantheonContentPublisherXFrameSubscriber::HEADER_VALUE];
    }
    $page['#cache']['contexts'][] = 'url.query_args:publishingLevel';
    return $page;
  }

}
