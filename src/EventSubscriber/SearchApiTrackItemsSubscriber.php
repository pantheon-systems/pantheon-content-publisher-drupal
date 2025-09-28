<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\EventSubscriber;

use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\search_api\Task\TaskEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SearchApiTrackItemsSubscriber implements EventSubscriberInterface {

  const COLLECTION = 'pantheon_document.search_api_tracker';

  protected KeyValueStoreInterface $kv;

  public function __construct(
    #[Autowire(service: 'keyvalue')]
    KeyValueFactoryInterface $keyValueFactory
  ) {
    $this->kv = $keyValueFactory->get(self::COLLECTION);
  }

  public static function getSubscribedEvents(): array {
    // High priority to fire first.
    $events[SearchApiEvents::REINDEX_SCHEDULED] = ['onReindex', 100];
    return $events;
  }

  public function onReindex(): void {
    $this->kv->deleteAll();
  }

}
