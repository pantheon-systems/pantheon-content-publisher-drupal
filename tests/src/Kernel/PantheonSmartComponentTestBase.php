<?php

declare(strict_types=1);

namespace Drupal\Tests\pantheon_content_publisher\Kernel;

use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\Tests\media\Kernel\MediaKernelTestBase;

/**
 * Smart component test base.
 *
 * @group pantheon_content_publisher
 */
class PantheonSmartComponentTestBase extends MediaKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'pantheon_content_publisher',
    'pantheon_smart_component_test',
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

}
