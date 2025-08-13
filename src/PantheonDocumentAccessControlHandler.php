<?php

namespace Drupal\pantheon_content_publisher;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the Pantheon Document entity type.
 *
 * @see \Drupal\pantheon_content_publisher\Entity\PantheonDocument
 */
class PantheonDocumentAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($account->hasPermission('administer pantheon_document types')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    switch ($operation) {
      case 'view':
        $access_result = AccessResult::allowedIf($account->hasPermission('view pantheon documents'))
          ->cachePerPermissions()
          ->addCacheableDependency($entity);
        if (!$access_result->isAllowed()) {
          $access_result->setReason("The 'view pantheon documents' permission is required.");
        }
        return $access_result;

      default:
        // No opinion.
        return AccessResult::neutral()->cachePerPermissions();
    }
  }

}
