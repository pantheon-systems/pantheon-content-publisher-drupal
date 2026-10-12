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
    $pending = $this->state->get('pantheon_cp.cdn_purge_due', []);
    $pending[$index_id] = time() + self::SOLR_COMMIT_BUFFER_SECONDS;
    $this->state->set('pantheon_cp.cdn_purge_due', $pending);
  }

  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    $pending = $this->state->get('pantheon_cp.cdn_purge_due', []);
    if (empty($pending)) {
      return;
    }

    $now = time();
    $due = array_filter($pending, fn($time) => $now >= $time);
    if (empty($due)) {
      return;
    }

    $remaining = array_diff_key($pending, $due);
    empty($remaining)
      ? $this->state->delete('pantheon_cp.cdn_purge_due')
      : $this->state->set('pantheon_cp.cdn_purge_due', $remaining);

    $tags = [];
    $edge_keys = [];
    foreach (array_keys($due) as $index_id) {
      $tags[] = "search_api_list:$index_id";
      $edge_keys[] = "search_api_emit_list:$index_id";
    }

    Cache::invalidateTags($tags);

    if (function_exists('pantheon_clear_edge_keys')) {
      pantheon_clear_edge_keys($edge_keys);
    }
  }

  public static function getSubscribedEvents(): array {
    return [
      SearchApiEvents::ITEMS_INDEXED => 'onItemsIndexed',
      KernelEvents::REQUEST => ['onRequest', 100],
    ];
  }

}
