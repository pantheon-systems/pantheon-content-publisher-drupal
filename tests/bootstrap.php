<?php

/**
 * @file
 * Bootstrap for PHPUnit when running from the module root.
 *
 * This registers the Drupal test base classes (UnitTestCase, etc.) that live
 * under vendor/drupal/core/tests/ so that Unit tests can run standalone
 * without a full Drupal installation.
 */

declare(strict_types=1);

$loader = require __DIR__ . '/../vendor/autoload.php';

// Register Drupal test infrastructure classes (UnitTestCase, TestTools, etc.).
$loader->add('Drupal\\Tests', __DIR__ . '/../vendor/drupal/core/tests');
$loader->add('Drupal\\TestTools', __DIR__ . '/../vendor/drupal/core/tests');
