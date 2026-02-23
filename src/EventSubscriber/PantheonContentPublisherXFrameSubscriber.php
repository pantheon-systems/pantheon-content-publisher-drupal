<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Remove X-Frame-Options from the preview page.
 */
final class PantheonContentPublisherXFrameSubscriber implements EventSubscriberInterface {

  /**
   * HTTP response header name.
   *
   * If this header is present then both this header and the X-Frame-Options
   * header added by FinishResponseSubscriber is removed and so this header
   * is never sent to the client.
   */
  const HEADER_NAME = 'X-Pantheon-Content-Publisher';

  /**
   * Request attribute name.
   *
   * Controllers that return render arrays should set this attribute on the
   * request instead of using #attached['http_header'] because the attachment
   * processor and this subscriber both fire on KernelEvents::RESPONSE at
   * competing priorities.
   */
  const REQUEST_ATTRIBUTE = '_pantheon_content_publisher_remove_xframe';

  /**
   * Constructs a PantheonDocumentXFrameSubscriber object.
   */
  public function __construct() {}

  /**
   * Kernel response event handler.
   */
  public function onKernelResponse(ResponseEvent $event): void {
    $headers = $event->getResponse()->headers;
    // Check request attribute (set by controllers returning render arrays)
    // or response header (set by controllers returning Response objects).
    if ($event->getRequest()->attributes->get(static::REQUEST_ATTRIBUTE) || $headers->has(static::HEADER_NAME)) {
      // This page is meant to be presented in an iframe.
      $headers->remove('X-Frame-Options');
      // This header was only used to signal this subscriber.
      $headers->remove(static::HEADER_NAME);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Make sure to fire after FinishResponseSubscriber.
    return [KernelEvents::RESPONSE => ['onKernelResponse', -1]];
  }

}
