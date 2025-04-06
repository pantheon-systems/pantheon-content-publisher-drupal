<?php

declare(strict_types=1);

namespace Drupal\Tests\pantheon_content_publisher\Kernel;

use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\pantheon_content_publisher\Controller\PantheonSmartComponentController;
use Drupal\Tests\media\Kernel\MediaKernelTestBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Smart component test
 *
 * @group pantheon_content_publisher
 */
class PantheonSmartComponentTest extends MediaKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'pantheon_content_publisher',
    'pantheon_smart_component_test',
    'field',
    'text',
    'options',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['pantheon_smart_component_test']);
    $file = File::create([
      'uri' => 'https://localhost/icons/icon.png',
      'uid' => $this->user->id(),
    ]);
    $file->setPermanent();
    $file->save();
    $icon = Media::create([
      'mid' => 1,
      'bundle' => $this->createMediaType('file')->id(),
      'name' => 'Test media',
      'field_media_file' => [
        'target_id' => $file->id(),
      ],
    ]);
    $icon->save();
  }

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

  public function testFormatter(): void {
    $controller = $this->container
      ->get('controller_resolver')
      ->getControllerFromDefinition(PantheonSmartComponentController::class . '::viewSmartComponent');
    $args = [
      'tag' => 'component',
      'type' => 'smart_component_test',
      'plain_text_field' => $this->randomString(),
      'list_field' => 'option_2',
    ];
    $request = new Request(['args' => base64_encode(json_encode([$args]))]);
    $result = $controller($request, 'smart_component_test');
  }

}
