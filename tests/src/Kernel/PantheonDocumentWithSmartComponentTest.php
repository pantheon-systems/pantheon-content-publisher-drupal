<?php

declare(strict_types=1);

namespace Drupal\Tests\pantheon_content_publisher\Kernel;

use Symfony\Component\HttpFoundation\Request;

/**
 * Smart component test.
 *
 * @group pantheon_content_publisher
 */
class PantheonDocumentWithSmartComponentTest extends PantheonSmartComponentTestBase implements PantheonContentDocumentTestInterface {

  use PantheonContentPublisherDocumentTrait {
    PantheonContentPublisherDocumentTrait::setUp as documentTraitSetup;
  }

  protected string $textFieldValue;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'search_api',
    'search_api_db',
    'search_api_db_defaults',
  ];

  protected function setUp(): void {
    // Make sure the string contains a double quote.
    $this->textFieldValue = $this->randomString() . '"';
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
   * Test callback.
   */
  public function testFormatter(): void {
    $request = Request::create(sprintf('/api/pantheoncloud/document/%s?publishingLevel=PRODUCTION', static::ARTICLE_ID));
    $response = $this->handle($request);
    $content = $response->getContent();
    $this->assertStringContainsString('<div>A plain text field</div>', $content);
    $this->assertStringContainsString('<div>A list field</div>', $content);
    $this->assertStringContainsString('Option 2', $content);
    $this->assertStringContainsString(htmlspecialchars($this->textFieldValue), $content);
  }

}
