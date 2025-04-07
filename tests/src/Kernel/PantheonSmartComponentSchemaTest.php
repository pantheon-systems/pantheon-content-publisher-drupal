<?php

declare(strict_types=1);

namespace Drupal\Tests\pantheon_content_publisher\Kernel;

use Drupal\pantheon_content_publisher\Controller\PantheonSmartComponentController;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Smart component test.
 *
 * @group pantheon_content_publisher
 */
class PantheonSmartComponentSchemaTest extends PantheonSmartComponentTestBase {

  /**
   * Test callback.
   */
  public function testSchemaConversion(): void {
    $response = $this->container
      ->get('controller_resolver')
      ->getControllerFromDefinition(PantheonSmartComponentController::class . '::listComponents')();
    $this->assertInstanceOf(JsonResponse::class, $response);
    $expected = json_decode(file_get_contents(__DIR__ . '/../../fixtures/smart_component_schema_test.json'), TRUE);
    $actual = json_decode($response->getContent(), TRUE);
    $this->assertEquals($expected, $actual);
  }

}
