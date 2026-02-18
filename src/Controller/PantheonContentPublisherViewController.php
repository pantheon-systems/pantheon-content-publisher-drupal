<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\Controller;

use Drupal\Core\Entity\Controller\EntityViewController;
use Drupal\pantheon_content_publisher\Entity\PantheonDocument;
use Drupal\pantheon_content_publisher\Entity\PantheonDocumentCollection;
use Drupal\pantheon_content_publisher\EventSubscriber\PantheonContentPublisherXFrameSubscriber;
use Drupal\pantheon_content_publisher\GraphQLException;
use Drupal\pantheon_content_publisher\PantheonDocumentStorage;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for Pantheon content publisher routes.
 */
class PantheonContentPublisherViewController extends EntityViewController {

  public function pantheonView(Request $request, $pantheon_id): array {
    $query = $request->query;
    $publishingLevel = in_array($query->get('publishingLevel'), ['PRODUCTION', 'REALTIME', 'DRAFT'], TRUE)
      ? $query->get('publishingLevel')
      : NULL;
    $is_preview = in_array($publishingLevel, ['REALTIME', 'DRAFT'], TRUE);
    $is_realtime = $publishingLevel === 'REALTIME';
    $collection = $query->get('siteId') ?: array_key_first(PantheonDocumentCollection::loadMultiple());
    try {
      $document = PantheonDocument::load(PantheonDocumentStorage::getEntityId($collection, $pantheon_id));
    }
    catch (GraphQLException $e) {
      if ($is_preview) {
        $document = PantheonDocument::create(['collection' => $collection]);
      }
      else {
        throw $e;
      }
    }
    if ($is_realtime) {
      // Only REALTIME needs empty preview div for client-side rendering.
      // DRAFT and PRODUCTION use server-side rendering (document loaded via PantheonDocumentStorage).
      // PantheonTagsFormatter turns this into
      // <div id="pantheon-content-publisher-preview"></div>
      $document->get('content')->value = '{"attrs":{"id":"pantheon-content-publisher-preview"}}';
    }
    $page = $this->view($document);
    if ($publishingLevel) {
      // Remove X-Frame-Options for all publishing levels (PRODUCTION, REALTIME, DRAFT) to allow iframe embedding in Content Publisher.
      $page['#attached']['http_header'][] = [PantheonContentPublisherXFrameSubscriber::HEADER_NAME, ''];
    }
    if ($is_realtime) {
      // Only REALTIME needs preview JavaScript library for live updates.
      $page['#attached']['library'][] = 'pantheon_content_publisher/preview';
      $page['#attached']['drupalSettings']['pantheon_content_publisher']['site_id'] = $collection;
    }
    $page['#cache']['contexts'][] = 'url.query_args:publishingLevel';
    $page['#cache']['contexts'][] = 'url.query_args:versionId';
    return $page;
  }

}
