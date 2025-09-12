<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\EventSubscriber;

use Composer\InstalledVersions;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Process\Process;

class QueueRunner implements EventSubscriberInterface {

  protected bool $enabled = FALSE;

  public function onTerminate(): void {
    if (!$this->enabled || !class_exists(Process::class)) {
      return;
    }
    $install_path = InstalledVersions::getRootPackage()['install_path'];
    $drush = "$install_path/vendor/bin/drush";
    if (!file_exists($drush)) {
      return;
    }
    $process = new Process([$drush, 'queue-run', 'pantheon_content_publisher_entity_save']);
    $process->setTimeout(0);
    $process->run();
  }

  public function enable(): void {
    $this->enabled = TRUE;
  }

  public static function getSubscribedEvents(): array {
    return [KernelEvents::TERMINATE => 'onTerminate'];
  }

}
