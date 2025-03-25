<?php

declare(strict_types=1);

namespace Drupal\Tests\pantheon_content_publisher\Kernel;

use Drupal\Component\Utility\NestedArray;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\pantheon_content_publisher\PantheonContentPublisherStorage;
use Drupal\search_api\Entity\Index;
use Symfony\Component\HttpFoundation\Request;

/**
 * Test description.
 *
 * @group pantheon_content_publisher
 */
class PantheonContentPublisherTest extends PantheonContentPublisherTestBase {

  public function testSearchAPIIndex() {
    // Creating the collection created a batch, let's run it.
    $batch = &batch_get();
    $batch['progressive'] = FALSE;
    batch_process();
    $indexes = Index::loadMultiple();
    $this->assertCount(1, $indexes);
    $index = reset($indexes);
    $this->assertSame($this->collection->id(), $index->id());
    $this->assertSame(['abooleanmeta', 'adatemeta', 'alistmeta', 'atextareameta', 'atextmeta', 'content'], array_keys($index->getFields()));
    $this->assertSame(1, $index->getTrackerInstance()->getTotalItemsCount());
    $this->assertSame(0, $index->getTrackerInstance()->getRemainingItemsCount());
    $this->assertSame('textarea test contents', $this->getSearchAPIvalue('atextareameta'));
    $newValue = $this->updateArticleInPantheon();
    // Notify the system the value has been updated.
    $this->executeWebhook();
    $this->assertSame($newValue, $this->getSearchAPIvalue('atextareameta'));
  }

  public function testCollectionUpdate(): void {
    // Setup created a collection, let's check it's correct.
    $storages = FieldStorageConfig::loadMultiple();
    $this->assertSame($storages['pantheon_content_publisher.abooleanmeta']->getType(), 'boolean');
    $this->assertSame($storages['pantheon_content_publisher.adatemeta']->getType(), 'timestamp');
    $this->assertSame($storages['pantheon_content_publisher.alistmeta']->getType(), 'list_string');
    $this->assertSame(options_allowed_values($storages['pantheon_content_publisher.alistmeta']), [
      'Option a' => 'Option a',
      'Option b' => 'Option b',
      'Option c' => 'Option c'
    ]);
    $this->assertSame($storages['pantheon_content_publisher.atextmeta']->getType(), 'string');
    $this->assertSame($storages['pantheon_content_publisher.atextareameta']->getType(), 'string_long');
    // Remove Option b from the metadata.
    $this->setGuzzleResponse('metadata', fn (&$metadata) => NestedArray::unsetValue($metadata, ['metadataFields', 'A list meta', 'options', 1]));
    // Update the collection.
    $this->collection->save();
    // Verify the list field changed.
    $storages = FieldStorageConfig::loadMultiple();
    $this->assertSame(options_allowed_values($storages['pantheon_content_publisher.alistmeta']), [
      'Option a' => 'Option a',
      'Option c' => 'Option c'
    ]);
    // Remove the list field.
    $this->setGuzzleResponse('metadata', fn (&$metadata) => NestedArray::unsetValue($metadata, ['metadataFields', 'A list meta']));
    // Update the collection.
    $this->collection->save();
    // Verify it's gone.
    $storages = FieldStorageConfig::loadMultiple();
    $this->assertArrayNotHasKey('pantheon_content_publisher.alistmeta', $storages);
  }

  public function testStorageDoLoadMultiple(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('pantheon_content_publisher');
    $entity_id = PantheonContentPublisherStorage::getEntityId($this->collection->id(), self::ARTICLE_ID);
    $pantheonContentPublisher = $storage->load($entity_id);
    $this->assertSame('textarea test contents', $pantheonContentPublisher->atextareameta->value);
    $this->assertSame('test title', $pantheonContentPublisher->label());
    $this->assertSame(sprintf('<a href="/pantheon-content-publisher/%s" hreflang="und">test title</a>', $entity_id), $pantheonContentPublisher->toLink()->toString()->getGeneratedLink());
    // Update article in Pantheon
    $newValue = $this->updateArticleInPantheon();
    $pantheonContentPublisher = $storage->load($entity_id);
    $this->assertSame($newValue, $pantheonContentPublisher->atextareameta->value);
  }

  public function testListBuilder() {
    // The core list builder uses entity query. If it gets overridden for
    // pagination purposes then a separate entity query test needs to be
    // added but until then, for basic functionality this test is enough.
    $build = $this->container->get('entity_type.manager')
      ->getListBuilder('pantheon_content_publisher')
      ->render();
    $html = (string) $this->container->get('renderer')
      ->renderInIsolation($build);
    $entity_id = PantheonContentPublisherStorage::getEntityId($this->collection->id(), self::ARTICLE_ID);
    $this->assertStringContainsString(sprintf('<td><a href="/pantheon-content-publisher/%s" hreflang="und">test title</a></td>', $entity_id), $html);
  }

  public function testContentFormatter() {
    $content_base = '{"tag":"img","attrs":{"alt": "alt text","src":"https:\/\/foo\/bar.jpg"}}';
    foreach (['bar' => $content_base, 'bar1' => str_replace('bar.jpg', 'bar1.jpg', $content_base)] as $name => $content) {
      $this->updateArticleInPantheon(['content'], $content);
      $entity_id = PantheonContentPublisherStorage::getEntityId($this->collection->id(), self::ARTICLE_ID);
      $document = $this->container->get('entity_type.manager')->getStorage('pantheon_content_publisher')->load($entity_id);
      $this->assertSame($content, $document->get('content')->value);
      $request = Request::create(sprintf('/api/pantheoncloud/document/%s?publishingLevel=PRODUCTION', static::ARTICLE_ID));
      $response = $this->container->get('kernel')->handle($request);
      $url = "https://foo/$name.jpg";
      $this->assertSame($document->_image_data, [$url => ['alt' => 'alt text', 'src' => $url]]);
      $this->assertStringContainsString('<img alt="alt text" src="' . $url . '">', $response->getContent());
    }
  }

}

