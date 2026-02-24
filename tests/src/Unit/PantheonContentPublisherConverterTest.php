<?php

declare(strict_types=1);

namespace Drupal\Tests\pantheon_content_publisher\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\MemoryCache\MemoryCache;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\pantheon_content_publisher\PantheonContentPublisherConverter;
use Drupal\Tests\UnitTestCase;

/**
 * @group pantheon_content_publisher
 * @coversDefaultClass \Drupal\pantheon_content_publisher\PantheonContentPublisherConverter
 */
class PantheonContentPublisherConverterTest extends UnitTestCase {

  protected PantheonContentPublisherConverter $converter;
  protected KeyValueStoreInterface $kvStore;

  protected function setUp(): void {
    parent::setUp();

    // MemoryCache requires the datetime.time service from the container.
    $container = new ContainerBuilder();
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(time());
    $time->method('getCurrentTime')->willReturn(time());
    $container->set('datetime.time', $time);
    \Drupal::setContainer($container);

    $this->kvStore = $this->createMock(KeyValueStoreInterface::class);
    $this->kvStore->method('getAll')->willReturn([]);

    $kvFactory = $this->createMock(KeyValueFactoryInterface::class);
    $kvFactory->method('get')
      ->with('pantheon_document.fields')
      ->willReturn($this->kvStore);

    $memoryCache = new MemoryCache();
    $this->converter = new PantheonContentPublisherConverter($kvFactory, $memoryCache);
  }

  /**
   * @covers ::convert
   */
  public function testConvertWithFullData(): void {
    $_SERVER['REQUEST_TIME'] = 1700000000;
    $pantheonData = [
      'id' => 'article-1',
      'title' => 'Test Article',
      'content' => '<p>Body content</p>',
      'slug' => 'test-article',
      'createdAt' => 1700000000000,
      'publishedDate' => 1700100000000,
      'publishStatus' => 'published',
      'metadata' => [
        'description' => 'A description',
        'image' => 'https://example.com/image.jpg',
      ],
    ];

    $result = $this->converter->convert($pantheonData, 'my_collection');

    $this->assertSame('article-1', $result['id']);
    $this->assertSame('Test Article', $result['title']);
    $this->assertSame('<p>Body content</p>', $result['content']);
    $this->assertSame('test-article', $result['slug']);
    $this->assertTrue($result['status']);
    $this->assertSame('my_collection', $result['collection']);
    $this->assertSame('A description', $result['description']);
    $this->assertSame('https://example.com/image.jpg', $result['image']);
    $this->assertSame(1700000000, $result['created']);
    $this->assertSame(1700100000, $result['changed']);
  }

  /**
   * @covers ::convert
   */
  public function testConvertWithExplicitId(): void {
    $_SERVER['REQUEST_TIME'] = 1700000000;
    $pantheonData = [
      'id' => 'original-id',
      'title' => 'Test',
      'content' => '',
      'slug' => '',
      'createdAt' => 1700000000000,
      'publishedDate' => 1700000000000,
      'publishStatus' => 'published',
      'metadata' => [],
    ];

    $result = $this->converter->convert($pantheonData, 'col', 'override-id');

    $this->assertSame('override-id', $result['id']);
  }

  /**
   * @covers ::convert
   */
  public function testConvertWithUnpublishedStatus(): void {
    $_SERVER['REQUEST_TIME'] = 1700000000;
    $pantheonData = [
      'id' => 'article-1',
      'title' => 'Draft',
      'content' => '',
      'slug' => '',
      'createdAt' => 1700000000000,
      'publishedDate' => 1700000000000,
      'publishStatus' => 'draft',
      'metadata' => [],
    ];

    $result = $this->converter->convert($pantheonData, 'col');

    $this->assertFalse($result['status']);
  }

  /**
   * @covers ::convert
   */
  public function testConvertWithMissingOptionalData(): void {
    $_SERVER['REQUEST_TIME'] = 1700000000;
    $pantheonData = [
      'id' => 'article-1',
      'metadata' => [],
    ];

    $result = $this->converter->convert($pantheonData, 'col');

    $this->assertSame('', $result['title']);
    $this->assertSame('', $result['content']);
    $this->assertSame('', $result['slug']);
    $this->assertSame('', $result['description']);
    $this->assertSame('', $result['image']);
    $this->assertFalse($result['status']);
    // Null dates should fall back to REQUEST_TIME.
    $this->assertSame(1700000000, $result['created']);
    $this->assertSame(1700000000, $result['changed']);
  }

  /**
   * @covers ::convert
   * @dataProvider dateConversionProvider
   */
  public function testDateConversion(int|array $input, int $expected): void {
    $_SERVER['REQUEST_TIME'] = 1700000000;
    $pantheonData = [
      'id' => 'article-1',
      'title' => 'Test',
      'content' => '',
      'slug' => '',
      'createdAt' => $input,
      'publishedDate' => $input,
      'publishStatus' => 'published',
      'metadata' => [],
    ];

    $result = $this->converter->convert($pantheonData, 'col');

    $this->assertSame($expected, $result['created']);
    $this->assertSame($expected, $result['changed']);
  }

  public static function dateConversionProvider(): array {
    return [
      'integer milliseconds' => [1700000000000, 1700000000],
      'msSinceEpoch array' => [['msSinceEpoch' => 1700000000000], 1700000000],
      'small integer' => [1000, 1],
      'msSinceEpoch array small' => [['msSinceEpoch' => 5000], 5],
    ];
  }

  /**
   * @covers ::set
   * @covers ::drupalFieldToPantheonField
   */
  public function testSetAndReverseLookup(): void {
    $this->kvStore->expects($this->once())
      ->method('set')
      ->with('pantheon_date', 'field_date.date');

    $this->converter->set('pantheon_date', 'field_date.date');

    $result = $this->converter->drupalFieldToPantheonField('field_date');
    $this->assertSame('pantheon_date', $result);
  }

  /**
   * @covers ::delete
   */
  public function testDelete(): void {
    $this->kvStore->expects($this->once())
      ->method('delete')
      ->with('pantheon_date');

    // First set so there's something to delete.
    $this->converter->set('pantheon_date', 'field_date.date');
    $this->converter->delete('pantheon_date');

    $result = $this->converter->drupalFieldToPantheonField('field_date');
    $this->assertFalse($result);
  }

  /**
   * @covers ::drupalFieldToPantheonField
   */
  public function testDrupalFieldToPantheonFieldNotFound(): void {
    $result = $this->converter->drupalFieldToPantheonField('nonexistent_field');
    $this->assertFalse($result);
  }

  /**
   * @covers ::convert
   */
  public function testConvertWithMetadataFieldMapping(): void {
    $_SERVER['REQUEST_TIME'] = 1700000000;

    // Set up a field mapping: pantheon "author" -> drupal "field_author".
    $this->converter->set('author', 'field_author');

    $pantheonData = [
      'id' => 'article-1',
      'title' => 'Test',
      'content' => '',
      'slug' => '',
      'createdAt' => 1700000000000,
      'publishedDate' => 1700000000000,
      'publishStatus' => 'published',
      'metadata' => [
        'author' => 'Jane Doe',
      ],
    ];

    $result = $this->converter->convert($pantheonData, 'col');

    $this->assertSame('Jane Doe', $result['field_author']);
  }

  /**
   * @covers ::convert
   */
  public function testConvertWithDateMethodMapping(): void {
    $_SERVER['REQUEST_TIME'] = 1700000000;

    // Set a field mapping with a converter method (dot notation).
    $this->converter->set('custom_date', 'field_custom_date.date');

    $pantheonData = [
      'id' => 'article-1',
      'title' => 'Test',
      'content' => '',
      'slug' => '',
      'createdAt' => 1700000000000,
      'publishedDate' => 1700000000000,
      'publishStatus' => 'published',
      'metadata' => [
        'custom_date' => ['msSinceEpoch' => 1700500000000],
      ],
    ];

    $result = $this->converter->convert($pantheonData, 'col');

    $this->assertSame(1700500000, $result['field_custom_date']);
  }

  /**
   * Tests that metadata-mapped fields take priority over defaults.
   *
   * @covers ::convert
   */
  public function testMetadataMappedFieldOverridesDefault(): void {
    $_SERVER['REQUEST_TIME'] = 1700000000;

    // Map a metadata field to 'title' — should override the default title.
    $this->converter->set('custom_title', 'title');

    $pantheonData = [
      'id' => 'article-1',
      'title' => 'Default Title',
      'content' => '',
      'slug' => '',
      'createdAt' => 1700000000000,
      'publishedDate' => 1700000000000,
      'publishStatus' => 'published',
      'metadata' => [
        'custom_title' => 'Custom Title',
      ],
    ];

    $result = $this->converter->convert($pantheonData, 'col');

    // pantheonMetadataToDrupalRecord runs first and its result is merged
    // with + operator, so metadata-mapped 'title' wins over default.
    $this->assertSame('Custom Title', $result['title']);
  }

}
