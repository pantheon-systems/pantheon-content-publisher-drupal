<?php

namespace Drupal\pantheon_content_publisher;

abstract class ProgressBar {

  const SERVER = 's';

  const KEY = 'k';

  const PANTHEON = 'p';

  public static function addProgressBar(&$form, string $current_key): void {
    $query = \Drupal::request()->query;
    // This is for the add search api server form and the add key form.
    if ($query->has('destination')) {
      $missing = ProgressBar::SERVER . ProgressBar::KEY;
      preg_match("#/pantheon_document_collection/.+missing=([$missing]{1,2})#", $query->get('destination'), $matches);
    }
    // This is for the pantheon document collection form.
    elseif ($current_key === ProgressBar::PANTHEON && $query->has('missing')) {
      $matches[1] = $query->get('missing');
    }
    if (isset($matches[1])) {
      $items = [
        ProgressBar::SERVER => t('Search API server'),
        ProgressBar::KEY => t('Access token'),
      ];
      $items = array_intersect_key($items, array_flip(str_split($matches[1])));
      $items[ProgressBar::PANTHEON] = t('Pantheon collection');
      $form['pantheon_progress'] = [
        '#theme' => 'pantheon_progress',
        '#weight' => -100,
        '#items' => $items,
        '#current_step' => array_search($current_key, array_keys($items)) + 1,
      ];
    }
  }

}
