<?php

namespace Drupal\pantheon_content_publisher\Plugin\search_api\datasource;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\pantheon_content_publisher\Entity\PantheonDocumentCollection;
use Drupal\pantheon_content_publisher\EventSubscriber\SearchApiTrackItemsSubscriber;
use Drupal\pantheon_content_publisher\PantheonDocumentStorage;
use Drupal\search_api\Plugin\search_api\datasource\ContentEntity;
use Drupal\search_api\Utility\Utility;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Represents a datasource which exposes the content entities.
 *
 * @SearchApiDatasource(
 *   id = "entity:pantheon_document"
 * )
 */
class PantheonDocumentDatasource extends ContentEntity {

  protected KeyValueStoreInterface $kv;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $datasource = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $datasource->kv = $container->get('keyvalue')->get(SearchApiTrackItemsSubscriber::COLLECTION);
    return $datasource;
  }

  public function getPartialItemIds($page = NULL, ?array $bundles = NULL, ?array $languages = NULL) {
    if (($bundles === [] && !$languages) || ($languages === [] && !$bundles)) {
      return NULL;
    }

    // We want to determine all entities of either one of the given bundles OR
    // one of the given languages. That means we can't just filter for $bundles
    // if $languages is given. Instead, we have to filter for all bundles we
    // might want to include and later sort out those for which we want only the
    // translations in $languages and those (matching $bundles) where we want
    // all (enabled) translations.
    if ($bundles && !$languages) {
      $bundles_for_query = $bundles;
    }
    else {
      $enabled_bundles = array_keys($this->getBundles());
      // Since this is also called for removed bundles/languages,
      // $enabled_bundles might not include $bundles.
      if ($bundles) {
        $enabled_bundles = array_unique(array_merge($bundles, $enabled_bundles));
      }
      $all_bundles = array_keys($this->getEntityBundles());
      $bundles_for_query = count($enabled_bundles) < count($all_bundles) ? $enabled_bundles : $all_bundles;
    }
    // Page is across all collections in this index.
    $page_context = [
      'index_id' => $this->getIndex()->id(),
      'datasource_id' => $this->getPluginId(),
      'bundles' => $bundles,
      'languages' => $languages,
    ];
    $page_key = Crypt::hashBase64(serialize($page_context));
    $previous_page = (int) $this->kv->get($page_key);
    $needs_cursor = $page > 0 && $previous_page === ($page - 1);
    $is_paging = isset($page);
    $page_size = $is_paging ? $this->getConfigValue('tracking_page_size') : NULL;

    $collections = PantheonDocumentCollection::loadMultiple($bundles_for_query);
    ksort($collections);
    $entity_ids = [];
    $found = FALSE;
    foreach ($collections as $collection_id => $collection) {
      // The cursor is per collection. ($needs_cursor can't be TRUE unless
      // $is_paging is also TRUE so it is not strictly needed here but it's
      // easier to understand.)
      if ($is_paging || $needs_cursor) {
        $cursor_context = [
          'index_id' => $this->getIndex()->id(),
          'datasource_id' => $this->getPluginId(),
          'languages' => $languages,
          'collection_id' => $collection_id,
        ];
        $cursor_key = Crypt::hashBase64(serialize($cursor_context));
      }
      $cursor = $needs_cursor ? $this->kv->get($cursor_key) : NULL;

      $result = $collection->getGraphQL()->getArticleIds($page_size, $cursor);
      foreach ($result['articles'] ?? [] as $info) {
        $entity_ids[] = PantheonDocumentStorage::getEntityId($collection, $info['id']);
      }
      $found = $found || $entity_ids;
      if ($is_paging) {
        $this->kv->set($cursor_key, $result['pageInfo']['nextCursor']);
      }
    }
    if (!$found) {
      return NULL;
    }
    if ($is_paging) {
      $this->kv->set($page_key, $page);
    }

    // For all loaded entities, compute all their item IDs (one for each
    // translation we want to include). For those matching the given bundles (if
    // any), we want to include translations for all enabled languages. For all
    // other entities, we just want to include the translations for the
    // languages passed to the method (if any).
    $item_ids = [];
    $enabled_languages = array_keys($this->getLanguages());
    // As above for bundles, $enabled_languages might not include $languages.
    if ($languages) {
      $enabled_languages = array_unique(array_merge($languages, $enabled_languages));
    }

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    foreach ($this->getEntityStorage()->loadMultiple($entity_ids) as $entity_id => $entity) {
      $translations = array_keys($entity->getTranslationLanguages());
      $translations = array_intersect($translations, $enabled_languages);
      // If only languages were specified, keep only those translations matching
      // them. If bundles were also specified, keep all (enabled) translations
      // for those entities that match those bundles.
      if ($languages !== NULL
          && (!$bundles || !in_array($entity->bundle(), $bundles))) {
        $translations = array_intersect($translations, $languages);
      }
      foreach ($translations as $langcode) {
        $item_ids[] = static::formatItemId($entity->getEntityTypeId(), $entity_id, $langcode);
      }
    }

    if (Utility::isRunningInCli()) {
      // When running in the CLI, this might be executed for all entities from
      // within a single process. To avoid running out of memory, reset the
      // static cache after each batch.
      $this->getEntityMemoryCache()->deleteAll();
    }

    return $item_ids;
  }

}
