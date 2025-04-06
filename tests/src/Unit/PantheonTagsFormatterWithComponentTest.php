<?php

declare(strict_types=1);

namespace Drupal\Tests\pantheon_content_publisher\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityViewBuilderInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\pantheon_content_publisher\Plugin\Field\FieldFormatter\PantheonTagsFormatter;
use Drupal\Tests\UnitTestCase;

/**
 * Test description.
 *
 * @group pantheon_content_publisher
 */
class PantheonTagsFormatterWithComponentTest extends UnitTestCase {

  public function testFormatter(): void {
    $html = '<div>some test with <div>more divs<div>embedded for kicks</div></div> and an <img src="https://example.com" alt=""></div>';
    $tags = [
      'tag' => 'component',
      'type' => 'does_not_matter_the_mock_ignores_this',
    ];
    $element = $this
      ->getFormatter($html)
      ->viewElements($this->getItemList($tags), '');
    $resultHtml = $element[0]['#context']['value'];
    $preg = sprintf('#<div class="pantheon_[a-zA-Z0-9_-]+">%s</div>#', $html);
    $this->assertMatchesRegularExpression($preg, $resultHtml);
  }

  /**
   * @param string $html
   *   The HTML for the smart component.
   *
   * @return \Drupal\pantheon_content_publisher\Plugin\Field\FieldFormatter\PantheonTagsFormatter
   *   The formatter which will replace any smart component tag with $html.
   */
  public function getFormatter(string $html): PantheonTagsFormatter {
    $container = new ContainerBuilder();
    $component = $this->createMock(EntityInterface::class);
    $build = [
      $this->randomMachineName() => $this->randomString(),
    ];
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage
      ->expects($this->once())
      ->method('load')
      ->willReturn($component);
    $viewBuilder = $this->createMock(EntityViewBuilderInterface::class);
    $viewBuilder
      ->expects($this->once())
      ->method('view')
      ->with($component)
      ->willReturn($build);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager
      ->expects($this->once())
      ->method('getStorage')
      ->with('pantheon_smart_component')
      ->willReturn($storage);
    $entityTypeManager
      ->expects($this->once())
      ->method('getViewBuilder')
      ->willReturn($viewBuilder);
    $container->set('entity_type.manager', $entityTypeManager);
    $renderer = $this->createMock(RendererInterface::class);
    $renderer
      ->expects($this->once())
      ->method('renderInIsolation')
      ->with($build)
      ->willReturn($html);
    $container->set('renderer', $renderer);
    $configuration = [
      'field_definition' => $this->createMock(FieldDefinitionInterface::class),
      'settings' => [],
      'label' => '',
      'view_mode' => '',
      'third_party_settings' => [],
    ];
    return PantheonTagsFormatter::create($container, $configuration, '', NULL);
  }

  /**
   * @param array $tags
   *   A tags array as recognized by PantheonTagsFormatter
   *
   * @return \Drupal\Core\Field\FieldItemListInterface
   *   An item list where the first item contains $tags JSON encoded.
   */
  public function getItemList(array $tags): FieldItemListInterface {
    $itemIterator = new \ArrayIterator([
      (object) ['value' => json_encode($tags)],
    ]);
    $items = $this->createMock(FieldItemListInterface::class);
    $items
      ->method('getEntity')
      ->willReturn(new \stdClass());
    foreach (['rewind', 'current', 'key', 'next', 'valid'] as $method) {
      $items
        ->method($method)
        ->willReturnCallback(fn() => $itemIterator->$method());
    }
    return $items;
  }

}
