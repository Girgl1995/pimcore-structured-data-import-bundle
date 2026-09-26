<?php

declare(strict_types=1);

namespace Factotum\StructuredDataImportBundle\Mapping\DataTarget;

use Factotum\StructuredDataImportBundle\Data\Builder\Factory\structuredDataBuilderFactory;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget\Direct;

class Structured extends Direct
{
    private const STRUCTURED_SEPARATOR = ',';

    private structuredDataBuilderFactory $structuredDataBuilderFactory;

    /**
     * @param structuredDataBuilderFactory $structuredDataBuilderFactory
     * @return void
     */
    public function setStructuredDataBuilderFactory(structuredDataBuilderFactory $structuredDataBuilderFactory): void
    {
        $this->structuredDataBuilderFactory = $structuredDataBuilderFactory;
    }

    protected string $overwriteMode;

    /**
     * @param array $settings
     * @return void
     *
     * @throws InvalidConfigurationException
     */
    public function setSettings(array $settings): void
    {
        parent::setSettings($settings);
        $this->overwriteMode = $settings['overwriteMode'] ?? self::OVERWRITE_MODE_REPLACE;
    }

    /**
     * @param ElementInterface $valueContainer
     * @param string $fieldName
     * @param mixed $data
     * @return void
     */
    protected function doAssignData($valueContainer, $fieldName, $data)
    {
        $fieldDefinition = $this->getFieldDefinition($valueContainer, $fieldName);

        $setter = 'set' . ucfirst($fieldName);
        $getter = 'get' . ucfirst($fieldName);

        $baseData    = $valueContainer->$getter($this->language);
        $newRawInput = $this->prepareData($data);

        $structuredDataBuilder = $this->structuredDataBuilderFactory->getBuilder($fieldDefinition->getFieldType());
        $result                = $structuredDataBuilder->build($fieldDefinition, $baseData, $newRawInput, $this->overwriteMode);

        $valueContainer->$setter($result, $this->language);
    }

    /**
     * @param array $inputValues
     * @return array
     */
    private function prepareData(array $inputValues): array
    {
        $result = [];

        foreach ($inputValues as $inputValue) {
            $values = explode(self::STRUCTURED_SEPARATOR, $inputValue);
            foreach ($values as $value) {
                $result[] = trim($value);
            }
        }

        return $result;
    }
}
