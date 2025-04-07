<?php

declare(strict_types=1);

namespace Drupal\Tests\pantheon_content_publisher\Kernel;

/**
 * Smart component test
 *
 * @group pantheon_content_publisher
 */
class PantheonSmartComponentSchemaTest extends PantheonSmartComponentTestBase implements PantheonContentDocumentTestInterface {

  use PantheonContentPublisherDocumentTrait;

  /**
   * Test callback.
   */
  public function testFormatter(): void {
    $args = [
      'tag' => 'component',
      'type' => 'smart_component_test',
      'plain_text_field' => $this->randomString(),
      'list_field' => 'option_2',
    ];
  }
  
}
