<?php

declare(strict_types=1);

namespace Factotum\StructuredDataImportBundle\Mapping\Type;

use Pimcore\Model\DataObject\ClassDefinition\Data\Localizedfields;
use Pimcore\Model\DataObject\ClassDefinition\Data\Objectbricks;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\Objectbrick\Definition;
use Pimcore\Model\DataObject\ClassDefinition;

class TransformationStructuredDataTypeService
{
    private const DEFAULT_ARRAY   = 'array';
    private const TITLE_KEY       = 'title';
    private const KEY_KEY         = 'key';
    private const LOCALIZED_KEY   = 'localized';
    private const KEY_SEPARATOR   = '.';
    private const KEY_DESCRIPTION = " [%s]";

    protected array $transformationDataTypesMapping = [
        self::DEFAULT_ARRAY => [
            'table',
            'structuredTable'
        ],
    ];

    /**
     * @param string $classId
     * @param string $transformationTargetType
     * @return array
     */
    public function getStructuredPimcoreDataTypes(string $classId, string $transformationTargetType): array
    {
        $class = ClassDefinition::getById($classId);

        $attributes = [];

        foreach ($class->getFieldDefinitions() as $definition) {
            $this->addFieldsToAttributesArray($definition, $transformationTargetType, $attributes);
        }

        return array_values($attributes);
    }

    /**
     * @param Data $fieldDefinition
     * @param string $targetType
     * @param array $attributes
     * @param bool $localized
     * @param string $keyPrefix
     * @return void
     */
    private function addFieldsToAttributesArray(Data $fieldDefinition, string $targetType, array &$attributes, bool $localized = false, string $keyPrefix = ''): void
    {
        $fieldType = $this->transformationDataTypesMapping[$targetType] ?? [];
        if (in_array($fieldDefinition->getFieldtype(), $fieldType)) {
            $key = $fieldDefinition->getName();
            if ($keyPrefix) {
                $key = $keyPrefix . self::KEY_SEPARATOR . $key;
            }

            $attributes[$key] = [
                self::KEY_KEY       => $key,
                self::TITLE_KEY     => $fieldDefinition->getTitle() . sprintf(self::KEY_DESCRIPTION, $key),
                self::LOCALIZED_KEY => $localized
            ];
        }

        if ($fieldDefinition instanceof Localizedfields) {
            $this->addLocalizedFieldsToAttributesArray($fieldDefinition, $targetType, $attributes, $keyPrefix);
        }

        if ($fieldDefinition instanceof Objectbricks) {
            $this->addObjectBricksFieldsToAttributesArray($fieldDefinition, $targetType, $attributes);
        }
    }

    /**
     * @param Localizedfields $fieldDefinition
     * @param string $targetType
     * @param array $attributes
     * @param string $keyPrefix
     * @return void
     */
    private function addLocalizedFieldsToAttributesArray(Localizedfields $fieldDefinition, string $targetType, array &$attributes, string $keyPrefix): void
    {
        foreach ($fieldDefinition->getFieldDefinitions() as $localizedDefinition) {
            $this->addFieldsToAttributesArray($localizedDefinition, $targetType, $attributes, true, $keyPrefix);
        }
    }

    /**
     * @param Objectbricks $fieldDefinition
     * @param string $targetType
     * @param array $attributes
     * @return void
     */
    private function addObjectBricksFieldsToAttributesArray(Objectbricks $fieldDefinition, string $targetType, array &$attributes): void
    {
        foreach ($fieldDefinition->getAllowedTypes() as $brickType) {
            $brick = Definition::getByKey($brickType);
            if (!$brick) {
                continue;
            }

            foreach ($brick->getFieldDefinitions() as $brickFieldDefinition) {
                $keyPrefix = $fieldDefinition->getName() . self::KEY_SEPARATOR . $brickType;
                $this->addFieldsToAttributesArray($brickFieldDefinition, $targetType, $attributes, false, $keyPrefix);
            }
        }
    }
}
