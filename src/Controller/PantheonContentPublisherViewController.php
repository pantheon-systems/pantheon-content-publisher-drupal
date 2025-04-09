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

  public function pantheonView(Request $request, $pantheon_id): array {
    $query = $request->query;
    $collection = $query->get('siteId') ?: array_key_first(PantheonDocumentCollection::loadMultiple());
    $document = PantheonDocument::load(PantheonDocumentStorage::getEntityId($collection, $pantheon_id));
    if ($is_preview = $query->get('publishingLevel') === 'REALTIME') {
      // PantheonTagsFormatter turns this into
      // <div id="pantheon-content-publisher-preview"></div>
      $document->get('content')->value = '{"attrs":{"id":"pantheon-content-publisher-preview"}}';
    }
    $page = $this->view($document);
    if ($is_preview) {
      $page['#attached']['library'][] = 'pantheon_content_publisher/drupal.pantheon_content_publisher.preview';
      $page['#attached']['drupalSettings']['pantheon_content_publisher']['site_id'] = $collection;
      $page['#attached']['http_header'][] = [PantheonContentPublisherXFrameSubscriber::HEADER_NAME, ''];
    }
    $page['#cache']['contexts'][] = 'url.query_args:publishingLevel';
    return $page;
  }

}
