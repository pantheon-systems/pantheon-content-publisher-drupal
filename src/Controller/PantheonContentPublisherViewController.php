<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\Controller;

use Drupal\Core\Entity\Controller\EntityViewController;
use Drupal\pantheon_content_publisher\Entity\PantheonContentPublisher;
use Drupal\pantheon_content_publisher\Entity\PantheonContentPublisherColl;
use Drupal\pantheon_content_publisher\PantheonContentPublisherStorage;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for Pantheon content publisher routes.
 */
class PantheonContentPublisherViewController extends EntityViewController {

  /**
   * HTTP response header name.
   *
   * PantheonContentPublisherXFrameSubscriber checks this header and if present
   * with ::PREVIEW_HEADER_VALUE value then both this header and the
   * X-Frame-Options header added by FinishResponseSubscriber is removed and
   * so this header is never sent to the client.
   */
  const PREVIEW_HEADER_NAME = 'X-Pantheon-Content-Publisher';

  const PREVIEW_HEADER_VALUE = 'preview';

  public function pantheonView(Request $request, $pantheon_id): array {
    $collections = PantheonContentPublisherColl::loadMultiple();
    $collection = reset($collections);
    $document = PantheonContentPublisher::load(PantheonContentPublisherStorage::getEntityId($collection, $pantheon_id));
    if ($is_preview = $request->query->get('publishingLevel') === 'REALTIME') {
      $content['attrs']['id'] = 'pantheon-content-publisher-preview';
      $document->get('content')->value = $content;
    }
    $page = $this->view($document);
    if ($is_preview) {
      $page['#attached']['library'][] = 'pantheon_content_publisher/drupal.pantheon_content_publisher.preview';
      $page['#attached']['drupalSettings']['pantheon_content_publisher']['site_id'] = $collection->id();
      $page['#attached']['http_header'][] = [self::PREVIEW_HEADER_NAME, self::PREVIEW_HEADER_VALUE];
    }
    return $page;
  }


}
