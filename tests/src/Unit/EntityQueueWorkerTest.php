<?php

declare(strict_types=1);

namespace Drupal\Tests\pantheon_content_publisher\Unit;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Core\Queue\DelayedRequeueException;
use Drupal\pantheon_content_publisher\PantheonDocumentStorage;
use Drupal\pantheon_content_publisher\Plugin\QueueWorker\EntityQueueWorker;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * @group pantheon_content_publisher
 * @coversDefaultClass \Drupal\pantheon_content_publisher\Plugin\QueueWorker\EntityQueueWorker
 */
class EntityQueueWorkerTest extends UnitTestCase {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected KeyValueStoreInterface $seenStore;
  protected KeyValueStoreInterface $retryStore;
  protected LoggerInterface $logger;
  protected EntityQueueWorker $worker;

  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->seenStore = $this->createMock(KeyValueStoreInterface::class);
    $this->retryStore = $this->createMock(KeyValueStoreInterface::class);
    $this->logger = $this->createMock(LoggerInterface::class);

    $keyValueFactory = $this->createMock(KeyValueFactoryInterface::class);
    $keyValueFactory->method('get')
      ->willReturnMap([
        ['pantheon_document.seen', $this->seenStore],
        ['pantheon_content_publisher.queue_retries', $this->retryStore],
      ]);

    $this->worker = new EntityQueueWorker(
      [],
      'pantheon_content_publisher_entity',
      ['cron' => ['time' => 60]],
      $this->entityTypeManager,
      $keyValueFactory,
      $this->logger,
    );
  }

  /**
   * @covers ::processItem
   */
  public function testProcessItemSavesEntity(): void {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('id')->willReturn('collection.doc-1');

    $storage = $this->createMock(ContentEntityStorageInterface::class);
    $storage->expects($this->once())
      ->method('load')
      ->with('collection.doc-1')
      ->willReturn($entity);

    $this->entityTypeManager->method('getStorage')
      ->with('pantheon_document')
      ->willReturn($storage);

    $this->seenStore->method('setIfNotExists')->willReturn(FALSE);
    $this->retryStore->expects($this->once())
      ->method('delete')
      ->with('collection.doc-1');

    $entity->expects($this->once())->method('save');

    $this->worker->processItem([
      'entity_type' => 'pantheon_document',
      'entity_id' => 'collection.doc-1',
    ]);
  }

  /**
   * @covers ::processItem
   */
  public function testProcessItemInvokesInsertHookForNewEntity(): void {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('id')->willReturn('collection.doc-new');

    $storage = $this->getMockBuilder(PantheonDocumentStorage::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['load', 'invokeHook'])
      ->getMock();
    $storage->method('load')->willReturn($entity);
    $storage->expects($this->once())
      ->method('invokeHook')
      ->with('insert', $entity);

    $this->entityTypeManager->method('getStorage')->willReturn($storage);

    $this->seenStore->expects($this->once())
      ->method('setIfNotExists')
      ->with('collection.doc-new', 1)
      ->willReturn(TRUE);

    $this->retryStore->expects($this->once())
      ->method('delete')
      ->with('collection.doc-new');

    $entity->expects($this->once())->method('save');

    $this->worker->processItem([
      'entity_type' => 'pantheon_document',
      'entity_id' => 'collection.doc-new',
    ]);
  }

  /**
   * @covers ::processItem
   */
  public function testProcessItemRequeuesOnNullEntity(): void {
    $storage = $this->createMock(ContentEntityStorageInterface::class);
    $storage->method('load')->willReturn(NULL);

    $this->entityTypeManager->method('getStorage')->willReturn($storage);

    $this->retryStore->method('get')
      ->with('collection.doc-1', 0)
      ->willReturn(0);
    $this->retryStore->expects($this->once())
      ->method('set')
      ->with('collection.doc-1', 1);

    $this->logger->expects($this->once())
      ->method('warning')
      ->with(
        $this->stringContains('not yet available'),
        $this->callback(fn($ctx) => $ctx['%id'] === 'collection.doc-1' && $ctx['@attempt'] === 1),
      );

    $this->expectException(DelayedRequeueException::class);

    $this->worker->processItem([
      'entity_type' => 'pantheon_document',
      'entity_id' => 'collection.doc-1',
    ]);
  }

  /**
   * @covers ::processItem
   */
  public function testProcessItemDiscardsAfterMaxRetries(): void {
    $storage = $this->createMock(ContentEntityStorageInterface::class);
    $storage->method('load')->willReturn(NULL);

    $this->entityTypeManager->method('getStorage')->willReturn($storage);

    $this->retryStore->method('get')
      ->with('collection.doc-1', 0)
      ->willReturn(3);
    $this->retryStore->expects($this->once())
      ->method('delete')
      ->with('collection.doc-1');

    $this->logger->expects($this->once())
      ->method('error')
      ->with(
        $this->stringContains('not available'),
        $this->callback(fn($ctx) => $ctx['%id'] === 'collection.doc-1' && $ctx['@max'] === 3),
      );

    $this->worker->processItem([
      'entity_type' => 'pantheon_document',
      'entity_id' => 'collection.doc-1',
    ]);
  }

  /**
   * @covers ::processItem
   */
  public function testProcessItemDeletePath(): void {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('id')->willReturn('collection.doc-del');

    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType->method('getKey')
      ->willReturnMap([
        ['bundle', 'type'],
        ['id', 'id'],
      ]);

    $storage = $this->createMock(ContentEntityStorageInterface::class);
    $storage->expects($this->once())
      ->method('create')
      ->with(['type' => 'article', 'id' => 'collection.doc-del'])
      ->willReturn($entity);

    $this->entityTypeManager->method('getStorage')->willReturn($storage);
    $this->entityTypeManager->method('getDefinition')
      ->with('pantheon_document')
      ->willReturn($entityType);

    $entity->expects($this->once())->method('enforceIsNew')->with(FALSE);
    $entity->expects($this->once())->method('delete');

    $this->seenStore->expects($this->once())
      ->method('delete')
      ->with('collection.doc-del');

    $this->worker->processItem([
      'entity_type' => 'pantheon_document',
      'entity_id' => 'collection.doc-del',
      'bundle' => 'article',
      'delete' => TRUE,
    ]);
  }

  /**
   * @covers ::processItem
   */
  public function testRetryCountResetsOnSuccess(): void {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('id')->willReturn('collection.doc-retry');

    $storage = $this->createMock(ContentEntityStorageInterface::class);
    $storage->method('load')->willReturn($entity);

    $this->entityTypeManager->method('getStorage')->willReturn($storage);
    $this->seenStore->method('setIfNotExists')->willReturn(FALSE);

    $this->retryStore->expects($this->once())
      ->method('delete')
      ->with('collection.doc-retry');

    $this->worker->processItem([
      'entity_type' => 'pantheon_document',
      'entity_id' => 'collection.doc-retry',
    ]);
  }

}
