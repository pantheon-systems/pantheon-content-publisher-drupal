<?php

declare(strict_types=1);

namespace Drupal\Tests\pantheon_content_publisher\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\pantheon_content_publisher\GraphQL;
use Drupal\pantheon_content_publisher\GraphQLException;
use Drupal\pantheon_content_publisher\PantheonDocumentCollectionInterface;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

/**
 * @group pantheon_content_publisher
 * @coversDefaultClass \Drupal\pantheon_content_publisher\GraphQL
 */
class GraphQLTest extends UnitTestCase {

  protected PantheonDocumentCollectionInterface $collection;
  protected array $requestHistory = [];

  protected function setUp(): void {
    parent::setUp();
    $this->collection = $this->createMock(PantheonDocumentCollectionInterface::class);
    $this->collection->method('id')->willReturn('site-123');
    $this->collection->method('getUrl')->willReturn('https://api.example.com');
    $this->collection->method('getToken')->willReturn('test-token');
  }

  protected function createGraphQL(array $responses): GraphQL {
    $this->requestHistory = [];
    $history = Middleware::history($this->requestHistory);
    $mock = new MockHandler($responses);
    $stack = HandlerStack::create($mock);
    $stack->push($history);
    $client = new Client(['handler' => $stack]);

    $container = new ContainerBuilder();
    $container->set('http_client', $client);
    \Drupal::setContainer($container);

    return new GraphQL($this->collection);
  }

  protected function jsonResponse(array $data, int $status = 200): Response {
    return new Response($status, ['Content-Type' => 'application/json'], json_encode($data));
  }

  /**
   * @covers ::getArticle
   */
  public function testGetArticleDefault(): void {
    $articleData = [
      'title' => 'Test Article',
      'content' => '<p>Hello</p>',
      'slug' => 'test-article',
      'createdAt' => 1700000000000,
      'publishedDate' => 1700000000000,
      'publishStatus' => 'published',
      'metadata' => ['description' => 'A test'],
    ];
    $graphql = $this->createGraphQL([
      $this->jsonResponse(['data' => ['article' => $articleData]]),
    ]);

    $result = $graphql->getArticle('article-1');

    $this->assertSame($articleData, $result);
    $body = json_decode((string) $this->requestHistory[0]['request']->getBody(), TRUE);
    $this->assertStringContainsString('article', $body['query']);
    $this->assertStringNotContainsString('publishingLevel', $body['query']);
    $this->assertStringNotContainsString('versionId', $body['query']);
  }

  /**
   * @covers ::getArticle
   */
  public function testGetArticleWithProductionLevel(): void {
    $graphql = $this->createGraphQL([
      $this->jsonResponse(['data' => ['article' => ['title' => 'Prod']]]),
    ]);

    $graphql->getArticle('article-1', 'PRODUCTION');

    $body = json_decode((string) $this->requestHistory[0]['request']->getBody(), TRUE);
    $this->assertStringContainsString('PRODUCTION', $body['query']);
  }

  /**
   * @covers ::getArticle
   */
  public function testGetArticleWithRealtimeLevel(): void {
    $graphql = $this->createGraphQL([
      $this->jsonResponse(['data' => ['article' => ['title' => 'RT']]]),
    ]);

    $graphql->getArticle('article-1', 'REALTIME');

    $body = json_decode((string) $this->requestHistory[0]['request']->getBody(), TRUE);
    $this->assertStringContainsString('REALTIME', $body['query']);
  }

  /**
   * @covers ::getArticle
   */
  public function testGetArticleWithDraftLevel(): void {
    $graphql = $this->createGraphQL([
      $this->jsonResponse(['data' => ['article' => ['title' => 'Draft']]]),
    ]);

    $graphql->getArticle('article-1', 'DRAFT');

    $body = json_decode((string) $this->requestHistory[0]['request']->getBody(), TRUE);
    $this->assertStringContainsString('DRAFT', $body['query']);
  }

  /**
   * @covers ::getArticle
   */
  public function testGetArticleWithVersionId(): void {
    $graphql = $this->createGraphQL([
      $this->jsonResponse(['data' => ['article' => ['title' => 'Versioned']]]),
    ]);

    $graphql->getArticle('article-1', 'DRAFT', 'version-abc');

    $body = json_decode((string) $this->requestHistory[0]['request']->getBody(), TRUE);
    $this->assertStringContainsString('DRAFT', $body['query']);
    $this->assertStringContainsString('version-abc', $body['query']);
  }

  /**
   * @covers ::getArticle
   */
  public function testGetArticleSendsCorrectHeaders(): void {
    $graphql = $this->createGraphQL([
      $this->jsonResponse(['data' => ['article' => ['title' => 'Test']]]),
    ]);

    $graphql->getArticle('article-1');

    $request = $this->requestHistory[0]['request'];
    $this->assertSame('application/graphql-response+json', $request->getHeaderLine('Accept'));
    $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
    $this->assertSame('site-123', $request->getHeaderLine('PCC-SITE-ID'));
    $this->assertSame('test-token', $request->getHeaderLine('PCC-TOKEN'));
    $this->assertSame('https://api.example.com/sites/site-123/query', (string) $request->getUri());
  }

  /**
   * @covers ::getArticle
   */
  public function testGetArticleThrowsOnNon200(): void {
    $graphql = $this->createGraphQL([
      new Response(500, [], 'Server Error'),
    ]);

    $this->expectException(GraphQLException::class);
    $graphql->getArticle('article-1');
  }

  /**
   * @covers ::getArticle
   */
  public function testGetArticleThrowsOnMissingData(): void {
    $graphql = $this->createGraphQL([
      $this->jsonResponse(['data' => ['wrong_key' => []]]),
    ]);

    $this->expectException(GraphQLException::class);
    $graphql->getArticle('article-1');
  }

  /**
   * @covers ::getArticleIds
   */
  public function testGetArticleIdsNoPagination(): void {
    $responseData = [
      'articles' => [['id' => 'a1'], ['id' => 'a2']],
      'pageInfo' => ['nextCursor' => NULL],
    ];
    $graphql = $this->createGraphQL([
      $this->jsonResponse(['data' => ['articlesv3' => $responseData]]),
    ]);

    $result = $graphql->getArticleIds();

    $this->assertSame($responseData, $result);
    $body = json_decode((string) $this->requestHistory[0]['request']->getBody(), TRUE);
    $this->assertStringNotContainsString('pageSize', $body['query']);
  }

  /**
   * @covers ::getArticleIds
   */
  public function testGetArticleIdsWithPageSize(): void {
    $graphql = $this->createGraphQL([
      $this->jsonResponse(['data' => ['articlesv3' => ['articles' => [], 'pageInfo' => ['nextCursor' => 'abc']]]]),
    ]);

    $graphql->getArticleIds(10);

    $body = json_decode((string) $this->requestHistory[0]['request']->getBody(), TRUE);
    $this->assertStringContainsString('pageSize', $body['query']);
  }

  /**
   * @covers ::getArticleIds
   */
  public function testGetArticleIdsWithCursor(): void {
    $graphql = $this->createGraphQL([
      $this->jsonResponse(['data' => ['articlesv3' => ['articles' => [], 'pageInfo' => ['nextCursor' => NULL]]]]),
    ]);

    $graphql->getArticleIds(10, 'cursor-xyz');

    $body = json_decode((string) $this->requestHistory[0]['request']->getBody(), TRUE);
    $this->assertStringContainsString('cursor-xyz', $body['query']);
  }

  /**
   * @covers ::getMetadata
   */
  public function testGetMetadata(): void {
    $metadataFields = [
      ['tag' => 'description', 'type' => 'string'],
      ['tag' => 'image', 'type' => 'string'],
    ];
    $graphql = $this->createGraphQL([
      $this->jsonResponse(['data' => ['site' => ['metadataFields' => $metadataFields]]]),
    ]);

    $result = $graphql->getMetadata();

    $this->assertSame($metadataFields, $result);
  }

  /**
   * @covers ::getArticles
   */
  public function testGetArticles(): void {
    $articles = [
      ['id' => 'a1', 'title' => 'First', 'metadata' => []],
      ['id' => 'a2', 'title' => 'Second', 'metadata' => []],
    ];
    $graphql = $this->createGraphQL([
      $this->jsonResponse(['data' => ['articlesv3' => ['articles' => $articles]]]),
    ]);

    $result = $graphql->getArticles();

    $this->assertSame($articles, $result);
  }

}
