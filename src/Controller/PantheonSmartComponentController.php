<?php

declare(strict_types=1);

namespace Drupal\pantheon_content_publisher\Controller;

use Drupal\Core\Entity\Controller\EntityViewController;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Render\BareHtmlPageRendererInterface;
use Drupal\pantheon_content_publisher\Entity\PantheonSmartComponent;
use Drupal\pantheon_content_publisher\Entity\PantheonSmartInstance;
use Drupal\pantheon_content_publisher\PantheonSmartComponentInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

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
        $json[$component->id()]['title'] = $component->label();
        $json[$component->id()]['fields'][$field->getName()] = $this->convertFieldToPantheon($field);
      }
    }
    return new JsonResponse($pantheon_smart_component ? ($json[$pantheon_smart_component->id()] ?? []) : $json);
  }

  protected function convertFieldToPantheon(FieldConfigInterface $field): array {
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
    }
    return $return;
  }

  public function viewSmartComponent(Request $request, string $component) {
    $values = ['component' => $component];
    if ($request->query->has('attrs') && ($attrs = base64_decode($request->query->get('attrs'), TRUE)) && ($data = json_decode($attrs, TRUE))) {
      $values += $data;
    }
    $build = parent::view(PantheonSmartInstance::create($values));
    $build['#cache']['contexts'][] = 'url.path';
    $build['#cache']['contexts'][] = 'url.query_args:args';
    $renderer = \Drupal::service('bare_html_page_renderer');
    assert($renderer instanceof BareHtmlPageRendererInterface);
    return $renderer->renderBarePage($build, PantheonSmartComponent::load($component)->label(), 'markup');
  }

}
