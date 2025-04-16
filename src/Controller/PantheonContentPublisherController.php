<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\pantheon_content_publisher\Entity\PantheonDocument;
use Drupal\pantheon_content_publisher\Entity\PantheonDocumentCollection;
use Drupal\pantheon_content_publisher\PantheonDocumentStorage;
use Drupal\pantheon_content_publisher\PantheonDocumentStorageInterface;
use Drupal\pantheon_content_publisher\PantheonTagsToRenderableInterface;
use Drupal\search_api\Entity\Index;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns responses for Pantheon content publisher routes.
 */
class PantheonContentPublisherController extends ControllerBase {

  protected PantheonDocumentStorageInterface $pantheonContentPublisherStorage;

  protected QueueInterface $queue;

  public function __construct(
      EntityTypeManagerInterface $entityTypeManager,
      QueueFactory $queueFactory,
      protected PantheonTagsToRenderableInterface $tagsToRenderable
  ) {
    $this->pantheonContentPublisherStorage = $entityTypeManager->getStorage('pantheon_document');
    $this->queue = $queueFactory->get('pantheon_document_images');
  }

  /**
   * Builds the response.
   */
  public function webhook(Request $request): Response {
    if ($decoded = @json_decode($request->getContent(), TRUE)) {
      $collection_id = $decoded['payload']['siteId'] ?? array_key_first(PantheonDocumentCollection::loadMultiple());
      $entity_id = PantheonDocumentStorage::getEntityId($collection_id, $decoded['payload']['articleId']);
      if ($decoded['event'] === 'article.unpublish') {
        PantheonDocument::create([
          'collection' => $collection_id,
          'id' => $entity_id,
        ])->delete();
      }
      else {
        $document = $this->pantheonContentPublisherStorage->load($entity_id);
        $document->save();
        PantheonDocumentCollection::load($collection_id)->save();
        Index::load(strtolower($collection_id))->indexItems();
        if ($image_data = $this->tagsToRenderable->getImageData($document->get('content')->value)) {
          $this->queue->createItem([$collection_id, $image_data]);
        }
      }
    }
    return new Response();
  }

  public function status(): JsonResponse {
    return new JsonResponse();
  }

}
