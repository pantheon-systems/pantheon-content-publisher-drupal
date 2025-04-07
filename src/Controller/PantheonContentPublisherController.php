<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\pantheon_content_publisher\Entity\PantheonDocument;
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

  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->pantheonContentPublisherStorage = $entityTypeManager->getStorage('pantheon_document');
  }

  /**
   * Builds the response.
   */
  public function webhook(Request $request): Response {
    if ($decoded = @json_decode($request->getContent(), TRUE)) {
      $collection_id = $decoded['payload']['siteId'];
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
        Index::load(strtolower($collection_id))->indexItems();
        $document->get('content')->view(['type' => 'pantheon_document_tags_formatter']);
        if ($document->_image_data) {
          \Drupal::queue('pantheon_document_images')->createItem([$collection_id, $document->_image_data]);
        }
      }
    }
    return new Response();
  }

  public function status(): JsonResponse {
    return new JsonResponse();
  }

}
