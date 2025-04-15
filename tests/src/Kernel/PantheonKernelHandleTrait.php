<?php

declare(strict_types=1);

namespace Drupal\Tests\pantheon_content_publisher\Kernel;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Creates amd handles a request.
 */
trait PantheonKernelHandleTrait {

  protected function handle(string|Request $request, $query = []): SymfonyResponse {
    if (is_string($request)) {
      $request = Request::create($request, parameters: $query);
    }
    $response = $this->container->get('http_kernel')->handle($request);
    if (method_exists($this, 'setRawContent')) {
      $this->setRawContent($response->getContent());
    }
    return $response;
  }

}
