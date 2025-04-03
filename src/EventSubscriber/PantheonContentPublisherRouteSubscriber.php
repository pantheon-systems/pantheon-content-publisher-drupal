<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\EventSubscriber;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Route subscriber.
 */
class PantheonContentPublisherRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    foreach (['pantheon_document', 'pantheon_smart_instance'] as $entity_type) {
      $collection->get("entity.entity_form_display.$entity_type.default")?->
        addRequirements(['_custom_access' => AccessResult::class . '::forbidden']);
    }
    $collection->get("entity.pantheon_document.field_ui_fields")?->
      addRequirements(['_custom_access' => AccessResult::class . '::forbidden']);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [RoutingEvents::ALTER => ['onAlterRoutes', -101]];
  }

}
