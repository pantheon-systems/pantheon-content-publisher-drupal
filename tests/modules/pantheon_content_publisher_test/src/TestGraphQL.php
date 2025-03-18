<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher_test;

use Drupal\pantheon_content_publisher\GraphQL;

readonly class TestGraphQL extends GraphQL {

  public function __construct() {}

  public function getMetadata(): array {
    return \Drupal::keyValue('pantheon_content_publisher_test')->get('metadata');
  }

  public function getArticle(string $id): array {
    return \Drupal::keyValue('pantheon_content_publisher_test')->get('getArticle')[$id];
  }

  public function getArticles(): array {
    return \Drupal::keyValue('pantheon_content_publisher_test')->get('getArticles');
  }

  public function getArticleIds(?int $page_size = NULL, ?string $cursor = NULL): array {
    return \Drupal::keyValue('pantheon_content_publisher_test')->get($cursor ? "getArticleIds.$cursor" : 'getArticleIds');
  }

}
