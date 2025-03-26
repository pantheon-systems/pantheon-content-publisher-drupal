<?php

namespace Drupal\pantheon_content_publisher\Plugin\search_api\datasource;


use Drupal\Component\Utility\Crypt;
use Drupal\pantheon_content_publisher\Entity\PantheonDocumentCollection;
use Drupal\pantheon_content_publisher\PantheonDocumentStorage;
use Drupal\search_api\Plugin\search_api\datasource\ContentEntity;
use Drupal\search_api\Utility\Utility;

/**
 * Represents a datasource which exposes the content entities.
 *
 * @SearchApiDatasource(
 *   id = "entity:pantheon_document"
 * )
 */
class PantheonDocumentDatasource extends ContentEntity {

  public function getPartialItemIds($page = NULL, ?array $bundles = NULL, ?array $languages = NULL) {
    if (($bundles === [] && !$languages) || ($languages === [] && !$bundles)) {
      return NULL;
    }

    // Build up the context for tracking the last ID for this batch page.
    $batch_page_context = [
      'index_id' => $this->getIndex()->id(),
      // The derivative plugin ID includes the entity type ID.
      'datasource_id' => $this->getPluginId(),
      'bundles' => $bundles,
      'languages' => $languages,
    ];
    $context_key = Crypt::hashBase64(serialize($batch_page_context));
    $last_ids = $this->getState()->get(self::TRACKING_PAGE_STATE_KEY, []);
    $page_size = $this->getConfigValue('tracking_page_size');

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
      $all_bundles = $this->getEntityBundles();
      $bundles_for_query = count($enabled_bundles) < count($all_bundles) ? $enabled_bundles : $all_bundles;
    }

    $collections = PantheonDocumentCollection::loadMultiple(array_keys($bundles_for_query));
    $entity_ids = [];
    $found = FALSE;
    foreach ($collections as $collection) {
      if ($page > 0 && isset($last_ids[$context_key])
          && $last_ids[$context_key]['page'] == ($page - 1)
          && $this->getEntityTypeId() !== 'search_api_task') {
          $cursor = $last_ids[$context_key]['cursor'];
      }
      else {
        $cursor = NULL;
      }
      $result = $collection->getGraphQL()->getArticleIds(isset($page) ? $page_size : NULL, $cursor);
      foreach ($result['articles'] ?? [] as $info) {
        $entity_ids[] = PantheonDocumentStorage::getEntityId($collection,  $info['id']);
      }
      $found = $found || $entity_ids;
      if (isset($page)) {
        if ($entity_ids) {
          $last_ids[$context_key] = [
            'page' => (int) $page,
            'cursor' => $result['pageInfo']['nextCursor'],
          ];
        }
        else {
          // Clean up state tracking of last ID.
          unset($last_ids[$context_key]);
        }
        $this->getState()->set(self::TRACKING_PAGE_STATE_KEY, $last_ids);
      }
    }
    if (!$found) {
      return NULL;
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
