<?php

declare(strict_types=1);

namespace Drupal\Tests\pantheon_content_publisher\Unit;

use Drupal\Core\State\StateInterface;
use Drupal\pantheon_content_publisher\EventSubscriber\SolrCacheReInvalidator;
use Drupal\search_api\Event\ItemsIndexedEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\IndexInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @group pantheon_content_publisher
 * @coversDefaultClass \Drupal\pantheon_content_publisher\EventSubscriber\SolrCacheReInvalidator
 */
class SolrCacheReInvalidatorTest extends UnitTestCase {

  protected StateInterface $state;
  protected SolrCacheReInvalidator $subscriber;

  protected function setUp(): void {
    parent::setUp();
    $this->state = $this->createMock(StateInterface::class);
    $this->subscriber = new SolrCacheReInvalidator($this->state);
  }

  /**
   * @covers ::getSubscribedEvents
   */
  public function testSubscribedEvents(): void {
    $events = SolrCacheReInvalidator::getSubscribedEvents();
    $this->assertArrayHasKey(SearchApiEvents::ITEMS_INDEXED, $events);
    $this->assertArrayHasKey(KernelEvents::REQUEST, $events);
  }

  /**
   * @covers ::onItemsIndexed
   */
  public function testOnItemsIndexedSchedulesPurge(): void {
    $index = $this->createMock(IndexInterface::class);
    $index->method('id')->willReturn('primary');
    $event = new ItemsIndexedEvent($index, ['item-1']);

    $this->state->expects($this->once())
      ->method('set')
      ->with(
        'pantheon_cp.cdn_purge_due',
        $this->callback(function ($value) {
          return $value['index_id'] === 'primary'
            && $value['time'] >= time() + 5
            && $value['time'] <= time() + 7;
        }),
      );

    $this->subscriber->onItemsIndexed($event);
  }

  /**
   * @covers ::onRequest
   */
  public function testOnRequestSkipsWhenNoPurgePending(): void {
    $this->state->method('get')
      ->with('pantheon_cp.cdn_purge_due')
      ->willReturn(NULL);

    $this->state->expects($this->never())->method('delete');

    $event = $this->createRequestEvent();
    $this->subscriber->onRequest($event);
  }

  /**
   * @covers ::onRequest
   */
  public function testOnRequestSkipsWhenNotYetDue(): void {
    $this->state->method('get')
      ->with('pantheon_cp.cdn_purge_due')
      ->willReturn(['time' => time() + 60, 'index_id' => 'primary']);

    $this->state->expects($this->never())->method('delete');

    $event = $this->createRequestEvent();
    $this->subscriber->onRequest($event);
  }

  /**
   * @covers ::onRequest
   */
  public function testOnRequestFiresWhenDue(): void {
    $this->state->method('get')
      ->with('pantheon_cp.cdn_purge_due')
      ->willReturn(['time' => time() - 1, 'index_id' => 'primary']);

    $this->state->expects($this->once())
      ->method('delete')
      ->with('pantheon_cp.cdn_purge_due');

    $event = $this->createRequestEvent();
    $this->subscriber->onRequest($event);
  }

  /**
   * @covers ::onRequest
   */
  public function testOnRequestSkipsSubRequests(): void {
    $this->state->expects($this->never())->method('get');

    $event = $this->createRequestEvent(HttpKernelInterface::SUB_REQUEST);
    $this->subscriber->onRequest($event);
  }

  protected function createRequestEvent(int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent {
    $kernel = $this->createMock(HttpKernelInterface::class);
    return new RequestEvent($kernel, new Request(), $type);
  }

}
