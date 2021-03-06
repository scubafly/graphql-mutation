<?php

namespace Drupal\graphql_mutation\Plugin\Deriver\InputTypes;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\graphql\Utility\StringHelper;
use Drupal\graphql_content_mutation\ContentEntityMutationSchemaConfig;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EntityInputFieldDeriver extends DeriverBase implements ContainerDeriverInterface {
  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $basePluginId) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    EntityFieldManagerInterface $entityFieldManager
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($basePluginDefinition) {
    foreach ($this->entityTypeManager->getDefinitions() as $entityTypeId => $type) {
      if (!($type instanceof ContentEntityTypeInterface)) {
        continue;
      }

      foreach ($this->entityFieldManager->getFieldStorageDefinitions($entityTypeId) as $fieldName => $field) {
        $properties = [];
        $propertyDefinitions = $field->getPropertyDefinitions();

        // Skip this field input type if it's a single value field.
        if (count($propertyDefinitions) == 1 && array_keys($propertyDefinitions)[0] === $field->getMainPropertyName()) {
          continue;
        }

        foreach ($propertyDefinitions as $propertyName => $propertyDefinition) {
          if ($propertyDefinition->isReadOnly() || $propertyDefinition->isComputed()) {
            continue;
          }

          $typeName = 'String';
          $typeName = $propertyDefinition->isList() ? StringHelper::listType($typeName) : $typeName;
          $typeName = $propertyDefinition->isRequired() ? StringHelper::nonNullType($typeName) : $typeName;

          $properties[StringHelper::propCase($propertyName)] = [
            'type' => $typeName,
            'property_name' => $propertyName,
          ];
        }

        $this->derivatives["$entityTypeId:$fieldName"] = [
          'name' => StringHelper::camelCase($entityTypeId, $fieldName, 'field', 'input'),
          'fields' => $properties,
          'entity_type' => $entityTypeId,
          'field_name' => $fieldName,
        ] + $basePluginDefinition;
      }
    }

    return parent::getDerivativeDefinitions($basePluginDefinition);
  }

}
