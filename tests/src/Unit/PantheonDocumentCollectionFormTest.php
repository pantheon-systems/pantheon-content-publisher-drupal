<?php

declare(strict_types=1);

namespace Drupal\Tests\pantheon_content_publisher\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\EnforcedResponseException;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\pantheon_content_publisher\Form\PantheonDocumentCollectionForm;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Test description.
 *
 * @group pantheon_content_publisher
 */
final class PantheonDocumentCollectionFormTest extends UnitTestCase {

  protected PantheonDocumentCollectionForm $form;
  protected UrlGeneratorInterface|MockObject $urlGenerator;
  protected MessengerInterface|MockObject $messenger;
  protected Request $request;

  protected function setUp(): void {
    parent::setUp();

    $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
    $this->messenger = $this->createMock(MessengerInterface::class);
    $this->request = new Request();

    $requestStack = new RequestStack();
    $requestStack->push($this->request);

    $this->form = new PantheonDocumentCollectionForm($this->urlGenerator);
    $this->form->setMessenger($this->messenger);
    $this->form->setRequestStack($requestStack);

    $redirectDestination = $this->createMock(RedirectDestinationInterface::class);
    $redirectDestination
      ->method('getAsArray')
      ->willReturn(['destination' => '/admin/structure/pantheon_document_collection/add']);

    $container = new ContainerBuilder();
    $container->set('request_stack', $requestStack);
    $container->set('redirect.destination', $redirectDestination);
    \Drupal::setContainer($container);
  }

  /**
   * @dataProvider missingDataProvider
   */
  public function testMissing(array $element, string $missing, string $route_name, string $query = ''): void {
    $this->urlGenerator->expects($this->once())
      ->method('generateFromRoute')
      ->with($route_name, [], $this->callback(fn ($options) => $this->assertStringContainsString('missing=' . $missing, $options['query']['destination']) || TRUE))
      ->willReturnCallback(static fn ($route_name, $parameters, $options) => $route_name . '?' . http_build_query($options['query']));
    $this->messenger->expects($this->once())
      ->method('addMessage');

    try {
      $this->form->progressBarOrRedirect($element);
    }
    catch (EnforcedResponseException $e) {
      $response = $e->getResponse();
      $this->assertInstanceOf(RedirectResponse::class, $response);
      $this->assertSame($route_name . '?destination=' . rawurlencode("/admin/structure/pantheon_document_collection/add?missing=$missing") . $query, $response->getTargetUrl());
    }
  }

  /**
   * @dataProvider missingPresentDataProvider
   */
  public function testProgressBarWhenMissingPresent(string $missing, bool $has_s, bool $has_k, int $current_step): void {
    $this->request->query->set('missing', $missing);
    $this->urlGenerator->expects($this->exactly(2))
      ->method('generateFromRoute')
      ->with($this->callback(static fn ($route_name) => in_array($route_name, ['entity.key.add_form', 'entity.key.collection'])));
    \Drupal::getContainer()->set('url_generator', $this->urlGenerator);

    $form = [
      'search_api_server' => ['#options' => ['server1' => 'Server']],
      'key' => ['#options' => ['key1' => 'Key']],
    ];

    $form = $this->form->progressBarOrRedirect($form);

    $this->assertProgressBar($form, $has_s, $has_k, $current_step);
  }

  /**
   * @dataProvider progressBarDataProvider
   */
  public function testAddProgressBar(string $missing, string $current_key, bool $has_s, bool $has_k, int $current_step): void {
    $this->request->query->set('destination', "/admin/structure/pantheon_document_collection/add?missing=$missing");
    $form = [];
    PantheonDocumentCollectionForm::addProgressBar($form, $current_key);
    $this->assertProgressBar($form, $has_s, $has_k, $current_step);
  }

  public function assertProgressBar(array $form, bool $has_s, bool $has_k, int $expected_current_step): void {
    $items = $form['pantheon_progress']['#items'];
    if ($has_s) {
      $this->assertArrayHasKey('s', $items);
    }
    if ($has_k) {
      $this->assertArrayHasKey('k', $items);
    }
    $this->assertArrayHasKey('p', $items);
    $this->assertSame($expected_current_step, $form['pantheon_progress']['#current_step']);
  }

  public static function missingDataProvider(): array  {
    return [
      [
        [
          'search_api_server' => ['#options' => []],
          'key' => ['#options' => ['key1' => 'Key']],
        ],
        's',
        'entity.search_api_server.add_form',
      ],
      [
        [
          'search_api_server' => ['#options' => ['server1' => 'Server']],
          'key' => ['#options' => []],
        ],
        'k',
        'entity.key.add_form',
        '&key_type=pantheon_content_publisher',
      ],
      [
        [
          'search_api_server' => ['#options' => []],
          'key' => ['#options' => []],
        ],
        'sk',
        'entity.search_api_server.add_form',
      ],
    ];
  }

  public static function missingPresentDataProvider(): array  {
    return [
      ['s', TRUE, FALSE, 2],
      ['sk', TRUE, TRUE, 3],
      ['k', FALSE, TRUE, 2],
    ];
  }

  public static function progressBarDataProvider(): array  {
    return [
      ['s', 's', TRUE, FALSE, 1],
      ['sk', 's', TRUE, TRUE, 1],
      ['sk', 'k', TRUE, TRUE, 2],
      ['k', 'k', FALSE, TRUE, 1],
    ];
  }


}
