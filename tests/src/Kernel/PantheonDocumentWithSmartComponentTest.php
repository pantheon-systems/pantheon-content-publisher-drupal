<?php

declare(strict_types=1);

namespace Drupal\Tests\pantheon_content_publisher\Kernel;

use Drupal\KernelTests\AssertContentTrait;

/**
 * Smart component test.
 *
 * @group pantheon_content_publisher
 */
class PantheonDocumentWithSmartComponentTest extends PantheonSmartComponentTestBase implements PantheonContentDocumentTestInterface {

  use PantheonDocumentTestTrait {
    PantheonDocumentTestTrait::setUp as documentTraitSetup;
  }
  use AssertContentTrait;
  use PantheonKernelHandleTrait;

  protected string $textFieldValue;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'key',
    'search_api',
    'search_api_db',
    'search_api_db_defaults',
  ];

  protected function setUp(): void {
    // Make sure the string contains a single and a double quote both.
    $this->textFieldValue = $this->randomString() . '"' . "'";
    $args = [
      'tag' => 'component',
      'type' => 'smart_component_test',
      'attrs' => [
        'plain_text_field' => $this->textFieldValue,
        'list_field' => 'option_2',
      ],
    ];
    $this->articleContent = json_encode($args);
    $this->documentTraitSetup();

    // Force Drupal to show full error messages and stack traces.
    $this->container->get('config.factory')
      ->getEditable('system.logging')
      ->set('error_level', 'verbose')
      ->save();

    // Disable the custom error handler that masks exceptions.
    // This ensures the raw PHP error goes to the console.
    $this->container->get('settings')->set('error_level', 'verbose');
  }

  /**
   * @testdox Document formatter renders smart component with field labels and values
   */
  public function testFormatter(): void {
    $this->handle(sprintf('/api/pantheoncloud/document/%s?publishingLevel=PRODUCTION', static::ARTICLE_ID));
    // First check for the field labels.
    $this->assertText('A plain text field');
    $this->assertText('A list field');
    // Then the field values.
    $this->assertText(htmlspecialchars($this->textFieldValue, ENT_NOQUOTES));
    // Note how setUp is using the machine name option_2, this is the value.
    $this->assertText('Option 2');
  }

}
