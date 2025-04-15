<?php

declare(strict_types=1);

namespace Drupal\Tests\pantheon_content_publisher\Kernel;

use Drupal\KernelTests\AssertContentTrait;
use Symfony\Component\HttpFoundation\Request;

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
  }

  /**
   * Test the formatter when a smart component is present.
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
