<?php

declare(strict_types=1);

namespace Drupal\Tests\pantheon_content_publisher\Kernel;

use Drupal\KernelTests\AssertContentTrait;
use Drupal\pantheon_content_publisher\EventSubscriber\PantheonContentPublisherXFrameSubscriber;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Smart component test.
 *
 * @group pantheon_content_publisher
 */
class PantheonSmartComponentControllerTest extends PantheonSmartComponentTestBase {

  use AssertContentTrait;
  use PantheonKernelHandleTrait;

  /**
   * Test listComponents.
   */
  public function testSchemaConversion(): void {
    $response = $this->handle('/api/pantheoncloud/component_schema');
    $this->assertInstanceOf(JsonResponse::class, $response);
    $expected = json_decode(file_get_contents(__DIR__ . '/../../fixtures/smart_component_schema_test.json'), TRUE);
    $actual = json_decode($response->getContent(), TRUE);
    $this->assertEquals($expected, $actual);
  }

  /**
   * Test component view
   */
  public function testView(): void {
    $values = [
      'plain_text_field' => $this->randomString(),
      'list_field' => 'option_2',
    ];
    $response = $this->handle('/api/pantheoncloud/component/smart_component_test', ['attrs' => base64_encode(json_encode($values))]);
    $this->assertFalse($response->headers->has('X-Frame-Options'));
    $this->assertFalse($response->headers->has(PantheonContentPublisherXFrameSubscriber::HEADER_NAME));
    $this->assertText(htmlspecialchars($values['plain_text_field'], ENT_NOQUOTES));
    $this->assertText('Option 2');
  }

}
