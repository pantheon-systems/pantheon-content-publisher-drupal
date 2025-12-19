<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher;

use GraphQL\RequestBuilder\Argument;
use GraphQL\RequestBuilder\Interfaces\TypeInterface;
use GraphQL\RequestBuilder\RootType;
use GraphQL\RequestBuilder\Type;

class GraphQL {

  public function __construct(protected PantheonDocumentCollectionInterface $collection) {}

  public function getMetadata(): array {
    $query = (new RootType('site'))->addArgument(new Argument('id', $this->collection->id()))->addSubTypes([
      'metadataFields',
    ]);
    return $this->request($query)['metadataFields'];
  }

  /**
   * Get an article.
   *
   * @param string $id
   *   The article ID.
   * @param string|null $publishingLevel
   *   The publishing level (PRODUCTION, REALTIME, or DRAFT).
   * @param string|null $versionId
   *   The version ID (used with DRAFT publishing level).
   *
   * @return array
   *   title, content and metadata of the article.
   */
  public function getArticle(string $id, ?string $publishingLevel = NULL, ?string $versionId = NULL): array {
    $query = (new RootType('article'))->addArgument(new Argument('id', $id));

    if ($publishingLevel) {
      $query->addArgument(new Argument('publishingLevel', $publishingLevel));
    }

    if ($versionId) {
      $query->addArgument(new Argument('versionId', $versionId));
    }

    $query->addSubTypes([
      'title',
      'content',
      'slug',
      'createdAt',
      'publishedDate',
      'publishStatus',
      'metadata',
    ]);
    return $this->request($query);
  }

  /**
   * A list of articles.
   *
   * @TODO: cursor support? merge into getArticleIds() ? Only Query uses this.
   *
   * @return array
   *   A list of article arrays, each array has id, title and metadata keys.
   */
  public function getArticles():array {
    $query = (new RootType('articlesv3'))->addSubTypes([
      (new Type('articles'))->addSubTypes([
        'id',
        'title',
        'metadata',
      ]),
    ]);
    return $this->request($query)['articles'];
  }

  /**
   * Get a paged list of article ids.
   *
   * @param int|null $page_size
   *   The page size.
   * @param string|null $cursor
   *   The cursor for this page.
   *
   * @return array
   *   Has two keys, articles and pageInfo. Articles is a list of arrays
   *   where each array only has an id key. pageInfo has a nextCursor key.
   */
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
      ]),
    ]);
    return $this->request($query);
  }

  /**
   * Run the GraphQL request.
   *
   * @param \GraphQL\RequestBuilder\Interfaces\TypeInterface $query
   *   The built-up GraphQL query.
   *
   * @return array
   *   The GraphQL response.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function request(TypeInterface $query): array {
    $uri = sprintf("%s/sites/%s/query", $this->collection->getUrl(), $this->collection->id());
    $response = \Drupal::httpClient()->post($uri, [
      'body' => json_encode(['query' => (string) $query]),
      'headers' => [
        'Accept' => 'application/graphql-response+json',
        'Content-Type' => 'application/json',
        'PCC-SITE-ID' => $this->collection->id(),
        'PCC-TOKEN' => $this->collection->getToken(),
      ],
    ]);
    if ($response->getStatusCode() === 200) {
      $name = $query->getName();
      $result = (string) $response->getBody();
      if (($decoded = json_decode($result, TRUE)) && isset($decoded['data'][$name])) {
        return $decoded['data'][$name];
      }
    }
    throw new GraphQLException("Could not execute query for $name.");
  }

}
