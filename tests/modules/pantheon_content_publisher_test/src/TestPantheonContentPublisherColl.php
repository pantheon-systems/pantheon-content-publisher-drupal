<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher_test;

use Drupal\pantheon_content_publisher\Entity\PantheonContentPublisherColl;
use Drupal\pantheon_content_publisher\GraphQL;

class TestPantheonContentPublisherColl extends PantheonContentPublisherColl {

  public function getGraphQL(): GraphQL {
    return new TestGraphQL();
  }

}
