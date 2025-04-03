<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\Controller;

use Drupal\Core\Entity\Controller\EntityViewController;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Render\BareHtmlPageRendererInterface;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\options\Plugin\Field\FieldType\ListItemBase;
use Drupal\pantheon_content_publisher\Entity\PantheonSmartComponent;
use Drupal\pantheon_content_publisher\Entity\PantheonSmartInstance;
use Drupal\pantheon_content_publisher\EventSubscriber\PantheonContentPublisherXFrameSubscriber;
use Drupal\pantheon_content_publisher\PantheonSmartComponentInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns responses for Pantheon content publisher routes.
 */
class PantheonSmartComponentController extends EntityViewController {

  const TYPES = [
    'text' => 'textarea',
    'int' => 'number',
    'integer' => 'number',
    'tinyint' => 'boolean',
    'date' => 'date',
    'blob' => 'file',
  ];

  public function listComponents(?PantheonSmartComponentInterface $pantheon_smart_component = NULL) {
    $components = $this->entityTypeManager->getStorage('pantheon_smart_component')->loadMultiple();
    $storage = $this->entityTypeManager->getStorage('field_config');
    $ids = $storage
      ->getQuery()
      ->condition('entity_type', 'pantheon_smart_instance')
      ->execute();
    $json = [];
    if ($ids) {
      $fields = $storage->loadMultiple($ids);
      foreach ($fields as $field) {
        $component = $components[$field->getTargetBundle()];
        $id = $component->id();
        if (!isset($json[$id])) {
          $json[$id]['title'] = $component->label();
          $mid = $component->get('icon');
          if ($mid && ($media = Media::load($mid))) {
            $fid = $media->getSource()->getSourceFieldValue($media);
            $json[$id]['iconUrl'] = File::load($fid)->createFileUrl(FALSE);
          }
        }
        $json[$id]['fields'][$field->getName()] = $this->convertFieldToPantheon($field);
      }
    }
    return new JsonResponse($pantheon_smart_component ? ($json[$pantheon_smart_component->id()] ?? []) : $json);
  }

  protected function convertFieldToPantheon(FieldConfigInterface $field): array {
    $bundle = $field->getTargetBundle();
    $display = EntityViewDisplay::load("pantheon_smart_instance.$bundle.default");
    $schema = $field->getFieldStorageDefinition()->getSchema();
    $properties = $field->getFieldStorageDefinition()->getPropertyDefinitions();
    if (empty($schema['columns'])) {
      return [];
    }
    $return = [
      'displayName' => $field->label(),
      'type' => 'object',
      'required' => $field->isRequired(),
    ];
    foreach ($schema['columns'] as $columnName => $column) {
      $return['fields'][$columnName] = [
        'displayName' => $properties[$columnName]->getLabel(),
        'type' => static::TYPES[$column['type']] ?? 'string',
        'required' => $field->isRequired(),
      ];
    }
    if (count($return['fields']) === 1) {
      $return['type'] = $return['fields'][array_key_first($return['fields'])]['type'];
      unset($return['fields']);
      if (is_a($field->getItemDefinition()->getClass(), ListItemBase::class, TRUE))  {
        $return['type'] = 'enum';
        foreach (options_allowed_values($field->getFieldStorageDefinition()) as $value => $label) {
          $return['options'][] = ['label' => $label, 'value' => $value];
        }
      }
      if (($configuration = $display->getComponent($field->getName())) && isset($configuration['type']) && str_starts_with($configuration['type'], 'imagecache_external_')) {
        $return['type'] = 'file';
      }
    }
    return $return;
  }

  /**
   * Preview a component.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param string $component
   *   The name of the pantheon smart component, this is passed in uppercase
   *   which Drupal can't handle properly and it's not really possible to
   *   change the request path in midddleware or elsewhere so just take the
   *   name as string and convert it in method.
   *
   * @return \Drupal\Core\Render\AttachmentsInterface|\Drupal\Core\Render\HtmlResponse|void
   */
  public function viewSmartComponent(Request $request, string $component) {
    $component = strtolower($component);
    $values = ['component' => $component];
    if (!$component = PantheonSmartComponent::load($component)) {
      throw new NotFoundHttpException();
    }
    if ($request->query->has('attrs') && ($attrs = base64_decode($request->query->get('attrs'), TRUE)) && ($data = json_decode($attrs, TRUE))) {
      $values += $data;
    }
    $build = parent::view(PantheonSmartInstance::create($values));
    $build['#cache']['contexts'][] = 'url.path';
    $build['#cache']['contexts'][] = 'url.query_args:args';
    $renderer = \Drupal::service('bare_html_page_renderer');
    assert($renderer instanceof BareHtmlPageRendererInterface);
    $response = $renderer->renderBarePage($build, $component->label(), 'markup');
    $response->headers->set(PantheonContentPublisherXFrameSubscriber::HEADER_NAME, PantheonContentPublisherXFrameSubscriber::HEADER_VALUE);
    return $response;
  }

}
