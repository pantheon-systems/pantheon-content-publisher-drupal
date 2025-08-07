<?php

declare(strict_types=1);

namespace Drupal\Tests\pantheon_content_publisher\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\EnforcedResponseException;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\pantheon_content_publisher\Form\PantheonDocumentCollectionForm;
use Drupal\pantheon_content_publisher\ProgressBar;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Test description.
 *
 * @group pantheon_content_publisher
 * @coversClass \Drupal\pantheon_content_publisher\Form\PantheonDocumentCollectionForm
 */
class ProgressBarTest extends UnitTestCase {

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
   * @dataProvider redirectWhenDataIsMissingProvider
   */
  public function testRedirectsWhenDataIsMissing(array $element, string $missing, string $route_name, string $query = ''): void {
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
      $this->assertSame($route_name . '?destination=' . rawurlencode("/admin/structure/pantheon_document_collection/add?missing=$missing") . $query, $e->getResponse()->getTargetUrl());
    }
  }

  /**
   * @dataProvider progressBarOnCollectionFormProvider
   */
  public function testProgressBarOnCollectionForm(string $missing, bool $has_server, bool $has_key, int $current_step): void {
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

    $this->assertProgressBar($form, $has_server, $has_key, $current_step);
  }

  /**
   * @dataProvider progressBarOnOtherFormSProvider
   */
  public function testProgressBarOnOtherForms(string $missing, string $current_key, bool $has_server, bool $has_key, int $current_step): void {
    $this->request->query->set('destination', "/admin/structure/pantheon_document_collection/add?missing=$missing");
    $form = [];
    ProgressBar::addProgressBar($form, $current_key);
    $this->assertProgressBar($form, $has_server, $has_key, $current_step);
  }

  public function assertProgressBar(array $form, bool $has_servererver, bool $has_keyey, int $expected_current_step): void {
    $items = $form['pantheon_progress']['#items'];
    if ($has_servererver) {
      $this->assertArrayHasKey(ProgressBar::SERVER, $items);
    }
    if ($has_keyey) {
      $this->assertArrayHasKey(ProgressBar::KEY, $items);
    }
    $this->assertArrayHasKey(ProgressBar::PANTHEON, $items);
    $this->assertSame($expected_current_step, $form['pantheon_progress']['#current_step']);
  }

  public static function redirectWhenDataIsMissingProvider(): array {
    return [
      // Key is present but server is not.
      [
        [
          'search_api_server' => ['#options' => []],
          'key' => ['#options' => ['key1' => 'Key']],
        ],
        ProgressBar::SERVER,
        'entity.search_api_server.add_form',
      ],
      // Server is present but key is not.
      [
        [
          'search_api_server' => ['#options' => ['server1' => 'Server']],
          'key' => ['#options' => []],
        ],
        ProgressBar::KEY,
        'entity.key.add_form',
        '&key_type=pantheon_content_publisher',
      ],
      // Neither are present.
      [
        [
          'search_api_server' => ['#options' => []],
          'key' => ['#options' => []],
        ],
        ProgressBar::SERVER . ProgressBar::KEY,
        'entity.search_api_server.add_form',
      ],
    ];
  }

  public static function progressBarOnCollectionFormProvider(): array {
    return [
      // Only the server is missing.
      [ProgressBar::SERVER, TRUE, FALSE, 2],
      // Only the key is missing.
      [ProgressBar::KEY, FALSE, TRUE, 2],
      // Both the server and the key are missing.
      ['sk', TRUE, TRUE, 3],
    ];
  }

  public static function progressBarOnOtherFormSProvider(): array {
    return [
      // Add server form when only the server is missing.
      [ProgressBar::SERVER, ProgressBar::SERVER, TRUE, FALSE, 1],
      // Add server form when both the server and the key are missing.
      [ProgressBar::SERVER . ProgressBar::KEY, ProgressBar::SERVER, TRUE, TRUE, 1],
      // Add key form when only the key are missing.
      [ProgressBar::KEY, ProgressBar::KEY, FALSE, TRUE, 1],
      // Add key form when both the server and the key are missing.
      [ProgressBar::SERVER . ProgressBar::KEY, ProgressBar::KEY, TRUE, TRUE, 2],
    ];
  }

}
