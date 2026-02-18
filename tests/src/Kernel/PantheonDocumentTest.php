<?php

declare(strict_types=1);

namespace Drupal\Tests\pantheon_content_publisher\Kernel;

use Drupal\Component\Utility\NestedArray;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\pantheon_content_publisher\EventSubscriber\PantheonContentPublisherXFrameSubscriber;
use Drupal\pantheon_content_publisher\PantheonDocumentStorage;
use Drupal\search_api\Entity\Index;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * Test description.
 *
 * @group pantheon_document
 */
class PantheonDocumentTest extends KernelTestBase implements PantheonContentDocumentTestInterface {

  use PantheonDocumentTestTrait;
  use PantheonKernelHandleTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'options',
    'text',
    'key',
    'search_api',
    'search_api_db',
    'search_api_db_defaults',
    'pantheon_content_publisher',
  ];

  public function testSearchAPIIndex() {
    // Creating the collection created a batch, let's run it.
    $batch = &batch_get();
    $batch['progressive'] = FALSE;
    batch_process();
    $indexes = Index::loadMultiple();
    $this->assertCount(1, $indexes);
    $index = reset($indexes);
    $this->assertSame(strtolower($this->collection->id()), $index->id());
    $this->assertSame(['abooleanmeta', 'adatemeta', 'afilemeta', 'alistmeta', 'atextareameta', 'atextmeta', 'content', 'title'], array_keys($index->getFields()));
    $this->assertSame(1, $index->getTrackerInstance()->getTotalItemsCount());
    $this->assertSame(0, $index->getTrackerInstance()->getRemainingItemsCount());
    $this->assertSame('textarea test contents', $this->getSearchAPIvalue('atextareameta'));
    $newValue = $this->updateArticleInPantheon();
    $this->assertSame($newValue, $this->getSearchAPIvalue('atextareameta'));
  }

  public function testCollectionUpdate(): void {
    // Setup created a collection, let's check it's correct.
    $storages = FieldStorageConfig::loadMultiple();
    $this->assertSame($storages['pantheon_document.abooleanmeta']->getType(), 'boolean');
    $this->assertSame($storages['pantheon_document.adatemeta']->getType(), 'timestamp');
    $this->assertSame($storages['pantheon_document.alistmeta']->getType(), 'list_string');
    $this->assertSame(options_allowed_values($storages['pantheon_document.alistmeta']), [
      'Option a' => 'Option a',
      'Option b' => 'Option b',
      'Option c' => 'Option c',
    ]);
    $this->assertSame($storages['pantheon_document.atextmeta']->getType(), 'string');
    $this->assertSame($storages['pantheon_document.atextareameta']->getType(), 'string_long');
    // Remove Option b from the metadata.
    $this->setGuzzleResponse('metadata', fn (&$metadata) => NestedArray::unsetValue($metadata, ['metadataFields', 'A list meta', 'options', 1]));
    // Update the collection.
    $this->collection->save();
    // Verify the list field changed.
    $storages = FieldStorageConfig::loadMultiple();
    $this->assertSame(options_allowed_values($storages['pantheon_document.alistmeta']), [
      'Option a' => 'Option a',
      'Option c' => 'Option c',
    ]);
    // Remove the list field.
    $this->setGuzzleResponse('metadata', fn (&$metadata) => NestedArray::unsetValue($metadata, ['metadataFields', 'A list meta']));
    // Update the collection.
    $this->collection->save();
    // Verify it's gone.
    $storages = FieldStorageConfig::loadMultiple();
    $this->assertArrayNotHasKey('pantheon_document.alistmeta', $storages);
  }

  public function testStorageDoLoadMultiple(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('pantheon_document');
    $entity_id = PantheonDocumentStorage::getEntityId($this->collection->id(), self::ARTICLE_ID);
    $pantheonContentPublisher = $storage->load($entity_id);
    $this->assertSame('textarea test contents', $pantheonContentPublisher->atextareameta->value);
    $this->assertSame('test title', $pantheonContentPublisher->label());
    $this->assertSame(1741385249, $pantheonContentPublisher->adatemeta->value);
    $this->assertSame(sprintf('<a href="/pantheon-content-publisher/%s" hreflang="und">test title</a>', $entity_id), $pantheonContentPublisher->toLink()->toString()->getGeneratedLink());
    $newValue = $this->updateArticleInPantheon();
    $pantheonContentPublisher = $storage->load($entity_id);
    $this->assertSame($newValue, $pantheonContentPublisher->atextareameta->value);
  }

  /**
   * Test the list builder and entity query both.
   *
   * The core list builder uses entity query. If it gets overridden for
   * pagination purposes then a separate entity query test needs to be added
   * but until then, for basic functionality this test is enough to test both
   * the list builder and entity query.
   *
   * @coversClass \Drupal\pantheon_content_publisher\Query\Query
   * @coversClass \Drupal\pantheon_content_publisher\PantheonDocumentListBuilder
   */
  public function testListBuilder() {
    $second_article_id = str_replace('d', 'x', self::ARTICLE_ID);
    $second_article = [
      'id' => $second_article_id,
    ] + $this->getArticle();
    $this->setGuzzleResponse('getArticles', fn (&$x) => $x['articles'][] = $second_article);
    $this->setGuzzleResponse('getArticle', fn ($x, &$y) => $y = str_replace(static::ARTICLE_ID, $second_article_id, $y));
    $build = $this->container->get('entity_type.manager')
      ->getListBuilder('pantheon_document')
      ->render();
    $html = (string) $this->container->get('renderer')->renderInIsolation($build);
    $entity_id = PantheonDocumentStorage::getEntityId($this->collection->id(), self::ARTICLE_ID);
    $this->assertStringContainsString(sprintf('<td><a href="/pantheon-content-publisher/%s" hreflang="und">test title</a></td>', $entity_id), $html);
    $entity_id = PantheonDocumentStorage::getEntityId($this->collection->id(), $second_article_id);
    $this->assertStringContainsString(sprintf('<td><a href="/pantheon-content-publisher/%s" hreflang="und">test title</a></td>', $entity_id), $html);
  }

  public function testContentFormatter() {
    $content_base = '{"tag":"img","attrs":{"alt": "alt text","src":"https:\/\/foo\/bar.jpg"}}';
    foreach ([TRUE, FALSE] as $trigger_webhook) {
      foreach (['bar' => $content_base, 'bar1' => str_replace('bar.jpg', 'bar1.jpg', $content_base)] as $name => $content) {
        $this->updateArticleInPantheon(['content'], $content, $trigger_webhook);
        $entity_id = PantheonDocumentStorage::getEntityId($this->collection->id(), self::ARTICLE_ID);
        $document = $this->container->get('entity_type.manager')->getStorage('pantheon_document')->load($entity_id);
        if (!$trigger_webhook) {
          $this->expectException(ExpectationFailedException::class);
        }
        $this->assertSame($content, $document->get('content')->value);
        $response = $this->handle(sprintf('/api/pantheoncloud/document/%s?publishingLevel=PRODUCTION', static::ARTICLE_ID));
        $url = "https://foo/$name.jpg";
        if (!$trigger_webhook) {
          $this->expectException(ExpectationFailedException::class);
        }
        $this->assertStringContainsString('<img alt="alt text" src="' . $url . '">', $response->getContent());
      }
    }
  }

  public function testPreview() {
    $response = $this->handle(sprintf('/api/pantheoncloud/document/%s?publishingLevel=REALTIME', static::ARTICLE_ID));
    $this->assertFalse($response->headers->has('X-Frame-Options'));
    $this->assertFalse($response->headers->has(PantheonContentPublisherXFrameSubscriber::HEADER_NAME));
    // REALTIME creates empty preview div for client-side rendering.
    $this->assertStringContainsString('<div id="pantheon-content-publisher-preview"></div>', $response->getContent());
    // @TODO assert preview.js is loaded.
  }

  public function testDraftPreview() {
    // Reset entity cache to force a fresh GraphQL request.
    $this->container->get('entity_type.manager')->getStorage('pantheon_document')->resetCache();
    // Register a mock response for the DRAFT GraphQL query with distinct
    // content so we can verify the DRAFT query is actually being sent.
    $draftArticle = $this->getArticle();
    $draftArticle['content'] = 'draft content';
    $draftQuery = sprintf('{article(id:"%s",publishingLevel:DRAFT){title,content,slug,createdAt,publishedDate,publishStatus,metadata}}', static::ARTICLE_ID);
    $this->storage[$draftQuery] = json_encode(['data' => ['article' => $draftArticle]]);

    $response = $this->handle(sprintf('/api/pantheoncloud/document/%s?publishingLevel=DRAFT', static::ARTICLE_ID));
    $this->assertFalse($response->headers->has('X-Frame-Options'));
    $this->assertFalse($response->headers->has(PantheonContentPublisherXFrameSubscriber::HEADER_NAME));
    // DRAFT uses server-side rendering with the draft-specific content.
    $this->assertStringContainsString('draft content', $response->getContent());
    // DRAFT should NOT load preview.js library.
    $this->assertStringNotContainsString('preview.js', $response->getContent());
  }

  public function testDraftPreviewWithVersionId() {
    // Reset entity cache to force a fresh GraphQL request.
    $this->container->get('entity_type.manager')->getStorage('pantheon_document')->resetCache();
    // Register a mock response for the DRAFT+versionId GraphQL query with
    // distinct content to verify both parameters reach the API.
    $versionId = 'test-version-id-123';
    $draftArticle = $this->getArticle();
    $draftArticle['content'] = 'draft version content';
    $draftQuery = sprintf('{article(id:"%s",publishingLevel:DRAFT,versionId:"%s"){title,content,slug,createdAt,publishedDate,publishStatus,metadata}}', static::ARTICLE_ID, $versionId);
    $this->storage[$draftQuery] = json_encode(['data' => ['article' => $draftArticle]]);

    $response = $this->handle(sprintf('/api/pantheoncloud/document/%s?publishingLevel=DRAFT&versionId=%s', static::ARTICLE_ID, $versionId));
    $this->assertFalse($response->headers->has('X-Frame-Options'));
    $this->assertFalse($response->headers->has(PantheonContentPublisherXFrameSubscriber::HEADER_NAME));
    // DRAFT uses server-side rendering with the version-specific content.
    $this->assertStringContainsString('draft version content', $response->getContent());
    // DRAFT should NOT load preview.js library.
    $this->assertStringNotContainsString('preview.js', $response->getContent());
  }

}
