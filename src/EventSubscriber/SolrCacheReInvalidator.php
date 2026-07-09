<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\EventSubscriber;

use Drupal\Core\Cache\Cache;
use Drupal\Core\State\StateInterface;
use Drupal\search_api\Event\ItemsIndexedEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Re-invalidates Search API cache tags after Solr commits.
 *
 * Solr's autoSoftCommit (5s on Pantheon) means search results aren't
 * visible immediately after indexing. If a visitor request arrives in
 * that window, stale Solr results get cached. This subscriber fires a
 * second invalidation after the commit window closes.
 *
 * Also directly purges the CDN using the PAPC-renamed tag variant,
 * working around the _list → _emit_list Surrogate-Key mismatch.
 */
class SolrCacheReInvalidator implements EventSubscriberInterface {

  const SOLR_COMMIT_BUFFER_SECONDS = 6;

  public function __construct(
    protected StateInterface $state,
  ) {}

  public function onItemsIndexed(ItemsIndexedEvent $event): void {
    $index_id = $event->getIndex()->id();
    $this->state->set('pantheon_cp.cdn_purge_due', [
      'time' => time() + self::SOLR_COMMIT_BUFFER_SECONDS,
      'index_id' => $index_id,
    ]);
  }

  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    $purge = $this->state->get('pantheon_cp.cdn_purge_due');
    if (!$purge || time() < $purge['time']) {
      return;
    }

    $this->state->delete('pantheon_cp.cdn_purge_due');
    $index_id = $purge['index_id'];

    // Re-invalidate Drupal's internal caches (page_cache, dynamic_page_cache).
    // Uses the original tag name which internal caches store unchanged.
    Cache::invalidateTags(["search_api_list:$index_id"]);

    // Purge CDN with the PAPC-renamed tag. PAPC's override_list_tags
    // renames _list → _emit_list in Surrogate-Key headers, but the
    // normal invalidation chain sends the original name. This call
    // sends the renamed variant so the CDN actually purges.
    if (function_exists('pantheon_clear_edge_keys')) {
      pantheon_clear_edge_keys(["search_api_emit_list:$index_id"]);
    }
  }

  public static function getSubscribedEvents(): array {
    return [
      SearchApiEvents::ITEMS_INDEXED => 'onItemsIndexed',
      KernelEvents::REQUEST => ['onRequest', 100],
    ];
  }

}
