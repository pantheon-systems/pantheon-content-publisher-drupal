<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher;

use GraphQL\RequestBuilder\Argument;
use GraphQL\RequestBuilder\Interfaces\TypeInterface;
use GraphQL\RequestBuilder\RootType;
use GraphQL\RequestBuilder\Type;

readonly class GraphQL {

  public function __construct(protected PantheonContentPublisherCollInterface $collection) {}

  public function getMetadata(): array {
    $query = (new RootType('site'))->addArgument(new Argument('id', $this->collection->id()))->addSubTypes([
      'metadataFields'
    ]);
    return $this->request($query)['metadataFields'];
  }

  public function getArticle(string $id): array {
    $query = (new RootType('article'))->addArgument(new Argument('id', $id))->addSubTypes([
      'title',
      'content',
      'metadata'
    ]);
    return $this->request($query);
  }

  public function getArticles():array {
    $query = (new RootType('articlesv3'))->addSubTypes([
      (new Type('articles'))->addSubTypes([
        'id',
        'title',
        'metadata'
      ])
    ]);
    return $this->request($query)['articles'];
  }

  public function getArticleIds(?int $page_size = NULL, ?string $cursor = NULL):array {
    $query = (new RootType('articlesv3'));
    if ($page_size) {
      $query->addArgument(new Argument('pageSize', $page_size));
      if ($cursor) {
        $query->addArgument(new Argument('cursor', $cursor));
      }
    }
    $query->addSubTypes([
      (new Type('articles'))->addSubTypes([
        'id',
      ]),
      (new Type('pageInfo'))->addSubTypes([
        'nextCursor',
      ])
    ]);
    return $this->request($query);
  }


  protected function request(TypeInterface $query): array {
    $uri = sprintf("%s/sites/%s/query", $this->collection->getUrl(), $this->collection->id());
    $response = \Drupal::httpClient()->post($uri, [
      'body' => json_encode(['query' => (string) $query]),
      'headers' => [
        'Accept' => 'application/graphql-response+json',
        'Content-Type' => 'application/json',
        'PCC-SITE-ID' => $this->collection->id(),
        'PCC-TOKEN' => $this->collection->getToken(),
      ]]);
    if ($response->getStatusCode() === 200) {
      $name = $query->getName();
      $result = (string) $response->getBody();
      if (($decoded = json_decode($result, TRUE)) && isset($decoded['data'][$name])) {
        return $decoded['data'][$name];
      }
    }
    throw new \RuntimeException("Could not execute query for $name.");
  }

}
