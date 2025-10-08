<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\pantheon_content_publisher\Entity\PantheonDocumentCollection;
use Drupal\pantheon_content_publisher\EventSubscriber\QueueRunner;
use Drupal\pantheon_content_publisher\PantheonDocumentStorage;
use Drupal\pantheon_content_publisher\PantheonTagsToRenderableInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns responses for Pantheon content publisher routes.
 */
class PantheonContentPublisherController extends ControllerBase {

  protected QueueInterface $imageQueue;

  protected QueueInterface $entityQueue;

  public function __construct(
      EntityTypeManagerInterface $entityTypeManager,
      QueueFactory $queueFactory,
      protected PantheonTagsToRenderableInterface $tagsToRenderable,
      protected QueueRunner $queueRunner,
  ) {
    $this->imageQueue = $queueFactory->get('pantheon_document_images');
    $this->entityQueue = $queueFactory->get('pantheon_content_publisher_entity', TRUE);
  }

  /**
   * Builds the response.
   */
  public function webhook(Request $request): Response {
    if ($decoded = @json_decode($request->getContent(), TRUE)) {
      $collection_id = $decoded['payload']['siteId'] ?? array_key_first(PantheonDocumentCollection::loadMultiple());
      // Sync metadata changes.
      $this->entityQueue->createItem([
        'entity_type' => 'pantheon_document_collection',
        'entity_id' => $collection_id,
      ]);
      $entity_id = PantheonDocumentStorage::getEntityId($collection_id, $decoded['payload']['articleId']);
      if ($decoded['event'] === 'article.unpublish') {
        $this->entityQueue->createItem([
          'entity_type' => 'pantheon_document',
          'entity_id' => $entity_id,
          'delete' => 1,
        ]);
      }
      else {
        // Clear the appropriate entity caches and queue the document for
        // indexing in Search API.
        $this->entityQueue->createItem([
          'entity_type' => 'pantheon_document',
          'entity_id' => $entity_id,
        ]);
        // Extract images.
        $this->imageQueue->createItem($entity_id);
      }
    }
    $this->queueRunner->enable();
    return new Response();
  }

  public function status(): JsonResponse {
    return new JsonResponse();
  }

}
