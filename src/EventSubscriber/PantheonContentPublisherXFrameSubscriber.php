<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\EventSubscriber;

use Drupal\pantheon_content_publisher\Controller\PantheonContentPublisherController;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Remove X-Frame-Options from the preview page.
 */
final class PantheonContentPublisherXFrameSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a PantheonContentPublisherXFrameSubscriber object.
   */
  public function __construct() {}

  /**
   * Kernel response event handler.
   */
  public function onKernelResponse(ResponseEvent $event): void {
    $headers = $event->getResponse()->headers;
    if ($headers->get(PantheonContentPublisherController::PREVIEW_HEADER_NAME) === PantheonContentPublisherController::PREVIEW_HEADER_VALUE) {
      // This page is meant to be presented in an iframe.
      $headers->remove('X-Frame-Options');
      // This header was only used to signal this subscriber.
      $headers->remove(PantheonContentPublisherController::PREVIEW_HEADER_NAME);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::RESPONSE => ['onKernelResponse']];
  }

}
