<?php

declare(strict_types=1);

namespace Drupal\Tests\pantheon_content_publisher\Kernel;

use Symfony\Component\HttpFoundation\Request;

/**
 * Smart component test
 *
 * @group pantheon_content_publisher
 */
class PantheonDocumentWithSmartComponentTest extends PantheonSmartComponentTestBase implements PantheonContentDocumentTestInterface {

  use PantheonContentPublisherDocumentTrait {
    PantheonContentPublisherDocumentTrait::setUp as documentTraitSetup;
  }

  protected string $textContent;

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
      $this->textContent = $this->randomString() . '"';
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
    $this->assertStringContainsString(htmlspecialchars($this->textContent), $content);
  }

  protected function getArticle() {
    $args = [
      'tag' => 'component',
      'type' => 'smart_component_test',
      'attrs' => [
        'plain_text_field' => $this->textContent,
        'list_field' => 'option_2',
      ],
    ];
    return [
      'metadata' => [
        'A boolean meta' => TRUE,
        'A date meta' => ['msSinceEpoch' => 1741385249172],
        'A file meta' => 'https://cdn.prod.pcc.pantheon.io/pcc-prod-user-uploads/dfa6f309-537c-4ffe-bbdf-4a40a6e70a61',
        'A list meta' => 'Option c',
        'A text meta' => 'Plain text field test contents',
        'A textarea meta' => 'textarea test contents',
        'description' => 'A random description',
      ],
      'content' => json_encode($args),
      'title' => 'test title',
      'slug' => 'test-slug',
    ];
  }

}
