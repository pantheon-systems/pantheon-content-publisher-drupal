<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\pantheon_content_publisher\PantheonDocumentStorageInterface;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines 'pantheon_document_images' queue worker.
 *
 * @QueueWorker(
 *   id = "pantheon_document_images",
 *   title = @Translation("Pantheon image handler"),
 *   cron = {"time" = 60},
 * )
 */
class Images extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  protected EntityStorageInterface $mediaStorage;

  protected PantheonDocumentStorageInterface $pantheonContentPublisherStorage;

  /**
   * Constructs a new Images instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entityTypeManager,
    protected readonly FileSystemInterface $fileSystem,
    protected readonly ClientInterface $httpClient,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->pantheonContentPublisherStorage = $entityTypeManager->getStorage('pantheon_document');
    $this->mediaStorage = $entityTypeManager->getStorage('media');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('file_system'),
      $container->get('http_client'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    if (!$document = $this->pantheonContentPublisherStorage->load($data)) {
      return;
    }
    $content = $document->get('content');
    if ($content->isEmpty() || !($pantheon_files = static::getImageData($content->value))) {
      return;
    }
    $media_ids = $this->mediaStorage->getQuery()
      ->condition('remote_url', array_keys($pantheon_files), 'IN')
      ->accessCheck(FALSE)
      ->execute();
    if ($media_ids) {
      $existing_remote_urls = array_map(static fn($media) => $media->remote_url->value, Media::loadMultiple($media_ids));
      $pantheon_files = array_diff_key($pantheon_files, array_flip($existing_remote_urls));
    }
    $directory = 'public://pantheon_document/' . $document->bundle();
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    foreach ($pantheon_files as $src => $image) {
      $filename = basename($src);
      $destination = $this->fileSystem->getDestinationFilename("$directory/$filename", FileExists::Rename);
      $destination_stream = @fopen($destination, 'w');
      $this->httpClient->get($src, ['sink' => $destination_stream]);
      $file = File::create(['uri' => $destination]);
      $file->setPermanent();
      $file->save();
      $media = Media::create([
        'bundle' => 'image',
        'name' => $file->getFilename(),
        'field_media_image' => [
          'target_id' => $file->id(),
        ] + $image,
        'remote_url' => $src,
      ]);
      $media->save();
    }

  }

  /**
   * Extract image data from JSON.
   *
   * @param string $json
   *   A serialized JSON object describing a DOM.
   *
   * @return array
   *   Keys are URLs to images, the values are key-value pairs. Each key-value
   *   pair is a HTML attribute of an img tag and its value.
   */
  protected static function getImageData(string $json): array {
    return static::collectImageData(@json_decode($json, TRUE) ?: []);
  }

  /**
   * @param array $node
   *   An array describing a DOM node.
   *
   * @return array
   *   Same return as ::getImageData().)
   */
  protected static function collectImageData(array $node): array {
    $image_data = [];
    if (($node['tag'] ?? '') === 'img' && !empty($node['attrs'])) {
      $image_data[$node['attrs']['src']] = $node['attrs'];
    }
    foreach ($node['children'] ?? [] as $child) {
      $image_data += static::collectImageData($child);
    }
    return $image_data;
  }

}
