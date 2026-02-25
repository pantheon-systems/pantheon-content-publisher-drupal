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
   * @testdox Component schema endpoint returns correct JSON schema
   */
  public function testSchemaConversion(): void {
    // smart components are created in PantheonSmartComponentTestBase by
    // importing the config of the pantheon_smart_component_test module.
    $response = $this->handle('/api/pantheoncloud/component_schema');
    $this->assertInstanceOf(JsonResponse::class, $response);
    $expected = json_decode(file_get_contents(__DIR__ . '/../../fixtures/smart_component_schema_test.json'), TRUE);
    $actual = json_decode($response->getContent(), TRUE);
    $this->assertEquals($expected, $actual);
  }

  /**
   * @testdox Component view renders field values without X-Frame-Options header
   */
  public function testView(): void {
    // @TODO write a unit test to ensure this functionality fires the
    // entity view builder.
    $values = [
      'plain_text_field' => $this->randomString(),
      'list_field' => 'option_2',
    ];
    $response = $this->handle('/api/pantheoncloud/component/smart_component_test', ['attrs' => base64_encode(json_encode($values))]);
    $this->assertFalse($response->headers->has('X-Frame-Options'));
    $this->assertFalse($response->headers->has(PantheonContentPublisherXFrameSubscriber::HEADER_NAME));
    $this->assertText(htmlspecialchars($values['plain_text_field'], ENT_QUOTES));
    $this->assertText('Option 2');
  }

}
