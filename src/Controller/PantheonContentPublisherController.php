<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\media\Entity\Media;
use Drupal\pantheon_content_publisher\Entity\PantheonDocument;
use Drupal\pantheon_content_publisher\Entity\PantheonDocumentCollection;
use Drupal\pantheon_content_publisher\PantheonDocumentCollectionInterface;
use Drupal\pantheon_content_publisher\PantheonDocumentInterface;
use Drupal\pantheon_content_publisher\PantheonDocumentStorage;
use Drupal\pantheon_content_publisher\PantheonDocumentStorageInterface;
use Drupal\search_api\Entity\Index;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns responses for Pantheon content publisher routes.
 */
class PantheonContentPublisherController extends ControllerBase {

  protected PantheonDocumentStorageInterface $pantheonContentPublisherStorage;

  public function __construct(
    EntityTypeManagerInterface $entityTypeManager
  ) {
    $this->pantheonContentPublisherStorage = $entityTypeManager->getStorage('pantheon_document');
  }

  /**
   * Builds the response.
   */
  public function webhook(Request $request): Response {
    if ($decoded = @json_decode($request->getContent(), TRUE)) {
      if (isset($decoded['siteId'])) {
        $collection = PantheonDocumentCollection::load($decoded['siteId']);
      }
      else {
        $collections = PantheonDocumentCollection::loadMultiple();
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
   * @param \Drupal\pantheon_content_publisher\Entity\PantheonDocumentCollection $collection
   *   The Pantheon content publisher collection.
   * @param array $decoded
   *   The decoded webhook payload.
   */
  protected function handleEvent(PantheonDocumentCollectionInterface $collection, array $decoded): void {
    $entity_id = PantheonDocumentStorage::getEntityId($collection->id(), $decoded['payload']['articleId']);
    if ($decoded['event'] === 'article.unpublish') {
      PantheonDocument::create([
        'collection' => $collection->id(),
        'id' => $entity_id,
      ])->delete();
    }
    else {
      $document = $this->pantheonContentPublisherStorage->load($entity_id);
      $document->save();
      Index::load($collection->id())->indexItems();
      $document->get('content')->view(['type' => 'pantheon_document_tags_formatter']);
      if ($document->_image_data) {
        \Drupal::queue('pantheon_document_images')->createItem([$collection->id(), $document->_image_data]);
      }
    }
  }


}
