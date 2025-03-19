<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\pantheon_content_publisher\Entity\PantheonContentPublisher;
use Drupal\pantheon_content_publisher\Entity\PantheonContentPublisherColl;
use Drupal\pantheon_content_publisher\PantheonContentPublisherCollInterface;
use Drupal\pantheon_content_publisher\PantheonContentPublisherStorage;
use Drupal\pantheon_content_publisher\PantheonContentPublisherStorageInterface;
use Drupal\search_api\Entity\Index;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns responses for Pantheon content publisher routes.
 */
class PantheonContentPublisherController extends ControllerBase {

  protected PantheonContentPublisherStorageInterface $pantheonContentPublisherStorage;

  public function __construct(
    EntityTypeManagerInterface $entityTypeManager
  ) {
    $this->pantheonContentPublisherStorage = $entityTypeManager->getStorage('pantheon_content_publisher');
  }

  /**
   * Builds the response.
   */
  public function webhook(Request $request): Response {
    if ($decoded = @json_decode($request->getContent(), TRUE)) {
      if (isset($decoded['siteId'])) {
        $collection = PantheonContentPublisherColl::load($decoded['siteId']);
      }
      else {
        $collections = PantheonContentPublisherColl::loadMultiple();
        $collection = reset($collections);
      }
      $this->handleEvent($collection, $decoded);
    }
    return new Response();
  }

  public function status(): JsonResponse {
    return new JsonResponse();
  }

  /**
   * @param \Drupal\pantheon_content_publisher\Entity\PantheonContentPublisherColl $collection
   *   The Pantheon content publisher collection.
   * @param array $decoded
   *   The decoded webhook payload.
   */
  protected function handleEvent(PantheonContentPublisherCollInterface $collection, array $decoded): void {
    $entity_id = PantheonContentPublisherStorage::getEntityId($collection->id(), $decoded['payload']['articleId']);
    if ($decoded['event'] === 'article.unpublish') {
      PantheonContentPublisher::create([
        'collection' => $collection->id(),
        'id' => $entity_id,
      ])->delete();
    }
    else {
      $document = $this->pantheonContentPublisherStorage->load($entity_id);
      $document->enforceIsNew($decoded['event'] === 'article.publish');
      $document->save();
      Index::load($collection->id())->indexItems();
    }
  }

}
