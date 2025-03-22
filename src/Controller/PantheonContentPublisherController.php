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
use Drupal\pantheon_content_publisher\Entity\PantheonContentPublisher;
use Drupal\pantheon_content_publisher\Entity\PantheonContentPublisherColl;
use Drupal\pantheon_content_publisher\PantheonContentPublisherCollInterface;
use Drupal\pantheon_content_publisher\PantheonContentPublisherInterface;
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
      $document->save();
      Index::load($collection->id())->indexItems();
      $this->handleImages($document, $decoded);
    }
  }

  protected function handleImage(PantheonContentPublisherInterface $document) {
    $document->get('content')->view(['type' => 'pantheon_content_publisher_tags_formatter']);
    // The formatter collects the attributes of image tags in _image_data
    // keyed by the source of the image.
    if (!$pantheon_files = $document->_image_data) {
      return;
    }
    $fids = \Drupal::entityQuery('file')
      ->condition('uri', array_keys($pantheon_files), 'IN')
      ->execute();
    $uris = array_flip(array_map(fn (FileInterface $file) => $file->getUri(), File::loadMultiple($fids)));
    $pantheon_files = array_diff_key($pantheon_files, $uris);
    $fs = \Drupal::service('file_system');
    assert($fs instanceof FileSystemInterface);
    $directory = 'public://pantheon_content_publisher/' . $document->bundle();
    foreach ($pantheon_files as $uri => $image) {
      $filename = basename($uri);
      $destination = $fs->getDestinationFilename("$directory/$filename", FileExists::Rename);
      $destination_stream = @fopen($destination, 'w');
      \Drupal::httpClient()->get($uri, ['sink' => $destination_stream]);
      $file = File::create(['uri' => $destination]);
      $file->setPermanent();
      $file->save();
      $media = Media::create([
        'bundle' => 'image',
        'name' => $file->getFilename(),
        'field_media_image' => [
            'target_id' => $file->id(),
          ] + $image,
      ]);
      $media->save();
    }
  }

}
