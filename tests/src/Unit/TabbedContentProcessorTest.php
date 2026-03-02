<?php

declare(strict_types=1);

namespace Drupal\Tests\pantheon_content_publisher\Unit;

use Drupal\Core\Render\RendererInterface;
use Drupal\pantheon_content_publisher\PantheonTagsToRenderableInterface;
use Drupal\pantheon_content_publisher\Plugin\search_api\processor\TabbedContent;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @group pantheon_content_publisher
 * @coversDefaultClass \Drupal\pantheon_content_publisher\Plugin\search_api\processor\TabbedContent
 */
class TabbedContentProcessorTest extends UnitTestCase {

  protected TabbedContent $processor;
  protected PantheonTagsToRenderableInterface|MockObject $tagsToRenderable;
  protected RendererInterface|MockObject $renderer;

  protected function setUp(): void {
    parent::setUp();

    $this->tagsToRenderable = $this->createMock(PantheonTagsToRenderableInterface::class);
    $this->renderer = $this->createMock(RendererInterface::class);

    $this->processor = new TabbedContent(
      [],
      'tabbed_content',
      ['id' => 'tabbed_content'],
      $this->tagsToRenderable,
      $this->renderer,
    );
  }

  /**
   * Calls the protected processFieldValue method via closure binding.
   */
  protected function callProcessFieldValue(&$value, string $type = 'string'): void {
    $processor = $this->processor;
    $fn = \Closure::bind(function () use (&$value, $type) {
      $this->processFieldValue($value, $type);
    }, $processor, TabbedContent::class);
    $fn();
  }

  /**
   * @covers ::processFieldValue
   */
  public function testProcessFieldValueWithEmptyValue(): void {
    $value = '';
    $this->callProcessFieldValue($value);
    $this->assertSame('', $value);
  }

  /**
   * @covers ::processFieldValue
   */
  public function testProcessFieldValueWithInvalidJson(): void {
    $value = 'not json';
    $this->callProcessFieldValue($value);
    $this->assertSame('not json', $value);
  }

  /**
   * @covers ::processFieldValue
   */
  public function testProcessFieldValueWithNonArrayJson(): void {
    $value = '"just a string"';
    $this->callProcessFieldValue($value);
    $this->assertSame('"just a string"', $value);
  }

  /**
   * @covers ::processFieldValue
   */
  public function testProcessFieldValueWithPlainTextTabs(): void {
    $value = json_encode([
      [
        'tabProperties' => [
          'tabId' => 't.tab1',
          'title' => 'First Tab',
        ],
        'documentTab' => 'Plain text content',
        'childTabs' => [],
      ],
      [
        'tabProperties' => [
          'tabId' => 't.tab2',
          'title' => 'Second Tab',
        ],
        'documentTab' => 'More plain text',
        'childTabs' => [],
      ],
    ]);

    $this->callProcessFieldValue($value);

    $this->assertStringContainsString('Plain text content', $value);
    $this->assertStringContainsString('More plain text', $value);
    $this->assertStringContainsString('First Tab', $value);
    $this->assertStringContainsString('Second Tab', $value);
  }

  /**
   * @covers ::processFieldValue
   */
  public function testProcessFieldValueWithNestedChildTabs(): void {
    $value = json_encode([
      [
        'tabProperties' => [
          'tabId' => 't.parent',
          'title' => 'Parent Tab',
        ],
        'documentTab' => 'Parent content',
        'childTabs' => [
          [
            'tabProperties' => [
              'tabId' => 't.child',
              'title' => 'Child Tab',
            ],
            'documentTab' => 'Child content',
            'childTabs' => [],
          ],
        ],
      ],
    ]);

    $this->callProcessFieldValue($value);

    $this->assertStringContainsString('Parent content', $value);
    $this->assertStringContainsString('Child content', $value);
    $this->assertStringContainsString('Parent Tab', $value);
    $this->assertStringContainsString('Child Tab', $value);
  }

  /**
   * @covers ::processFieldValue
   */
  public function testProcessFieldValueWithJsonDocumentTab(): void {
    $jsonContent = json_encode(['tag' => 'p', 'data' => 'Rendered content']);
    $value = json_encode([
      [
        'tabProperties' => [
          'tabId' => 't.json',
          'title' => 'JSON Tab',
        ],
        'documentTab' => $jsonContent,
        'childTabs' => [],
      ],
    ]);

    $this->tagsToRenderable
      ->expects($this->once())
      ->method('convertJsonToRenderable')
      ->with($jsonContent)
      ->willReturn(['#markup' => '<p>Rendered content</p>']);

    $this->renderer
      ->expects($this->once())
      ->method('renderInIsolation')
      ->willReturn('<p>Rendered content</p>');

    $this->callProcessFieldValue($value);

    $this->assertStringContainsString('Rendered content', $value);
    $this->assertStringContainsString('JSON Tab', $value);
  }

  /**
   * @covers ::processFieldValue
   */
  public function testProcessFieldValueWithArrayDocumentTab(): void {
    $arrayContent = ['tag' => 'div', 'data' => 'Array content'];
    $value = json_encode([
      [
        'tabProperties' => [
          'tabId' => 't.arr',
          'title' => 'Array Tab',
        ],
        'documentTab' => $arrayContent,
        'childTabs' => [],
      ],
    ]);

    $this->tagsToRenderable
      ->expects($this->once())
      ->method('convertJsonToRenderable')
      ->willReturn(['#markup' => '<div>Array content</div>']);

    $this->renderer
      ->expects($this->once())
      ->method('renderInIsolation')
      ->willReturn('<div>Array content</div>');

    $this->callProcessFieldValue($value);

    $this->assertStringContainsString('Array content', $value);
    $this->assertStringContainsString('Array Tab', $value);
  }

  /**
   * @covers ::processFieldValue
   */
  public function testProcessFieldValueSkipsEmptyTabs(): void {
    $value = json_encode([
      NULL,
      [],
      'not an array',
      [
        'tabProperties' => [
          'tabId' => 't.valid',
          'title' => 'Valid Tab',
        ],
        'documentTab' => 'Valid content',
        'childTabs' => [],
      ],
    ]);

    $this->callProcessFieldValue($value);

    $this->assertStringContainsString('Valid content', $value);
    $this->assertStringContainsString('Valid Tab', $value);
  }

  /**
   * @covers ::processFieldValue
   */
  public function testProcessFieldValueWithMissingDocumentTab(): void {
    $value = json_encode([
      [
        'tabProperties' => [
          'tabId' => 't.notab',
          'title' => 'No Content Tab',
        ],
        'childTabs' => [],
      ],
    ]);

    $this->callProcessFieldValue($value);

    $this->assertStringContainsString('No Content Tab', $value);
  }

}
