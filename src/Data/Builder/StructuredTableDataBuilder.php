<?php

declare(strict_types=1);

namespace Factotum\StructuredDataImportBundle\Data\Builder;

use Factotum\StructuredDataImportBundle\Data\Builder\Exception\IndexOutOfBoundsException;
use Factotum\StructuredDataImportBundle\Data\Builder\Exception\InsufficientTableSpaceException;
use Factotum\StructuredDataImportBundle\Data\Builder\StructuredDataBuilder;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\Data\StructuredTable;

class StructuredTableDataBuilder extends StructuredDataBuilder
{
    private const STRUCTURED_TABLE_DATA_TYPE = 'structuredTable';
    private const EMPTY_CELL_VALUE           = null;
    private const KEY_KEY                    = 'key';

    /**
     * @param Data $fieldDefinition
     * @param mixed $baseData
     * @param array $rawInput
     * @param string $overwriteMode
     * @return mixed
     *
     * @throws IndexOutOfBoundsException|InsufficientTableSpaceException
     */
    public function build(Data $fieldDefinition, mixed $baseData, array $rawInput, string $overwriteMode): mixed
    {
        $baseData = $baseData ? $baseData->getData() : [];

        $baseData = $this->reshapeData($fieldDefinition, $baseData);

        if (!$rawInput) {
            return $this->createStructuredTable($baseData);
        }

        $storageIndex = 0;
        if ($overwriteMode === parent::OVERWRITE_MODE_MERGE) {
            $storageIndex = $this->getStorageIndex($fieldDefinition, $baseData);
            if ($storageIndex === null) {
                throw new IndexOutOfBoundsException(parent::DATA_IMPORTER_LOG_MESSAGE_SEPARATOR . $this->translateError(parent::INDEX_OUT_OF_BOUNDS_EXCEPTION_MESSAGE_KEY, $fieldDefinition->getName()));
            }
        }

        if (!$this->canDataVolumeBeStored($fieldDefinition, $rawInput, $storageIndex, $overwriteMode)) {
            throw new InsufficientTableSpaceException(parent::DATA_IMPORTER_LOG_MESSAGE_SEPARATOR . $this->translateError(parent::INSUFFICIENT_TABLE_SPACE_EXCEPTION_MESSAGE_KEY, $fieldDefinition->getName()));
        }

        $newData = $this->formatDataAccordingToDefinition($fieldDefinition, $rawInput);

        $result = $this->applyData($fieldDefinition, $baseData, $newData, $overwriteMode, $storageIndex);

        return $this->createStructuredTable($result);
    }

    /**
     * @param Data $fieldDefinition
     * @param array $data
     * @return array
     */
    protected function reshapeData(Data $fieldDefinition, array $data): array
    {
        $result = parent::reshapeData($fieldDefinition, $data);

        return $this->buildStructuredTableData($fieldDefinition, $result);
    }

    /**
     * @param array $data
     * @return StructuredTable|null
     */
    private function createStructuredTable(array $data): ?StructuredTable
    {
        if (!$data) {
            return null;
        }

        return new StructuredTable($data);
    }

    /**
     * @param Data $fieldDefintion
     * @param array $data
     * @return int|null
     */
    protected function getStorageIndex(Data $fieldDefintion, array $data): int|null
    {
        $rows = $fieldDefintion->getRows();
        $cols = $fieldDefintion->getCols();

        $reverseRows = array_reverse($rows);
        $reverseCols = array_reverse($cols);

        $rowIndex     = 0;
        $storageIndex = 0;
        foreach ($reverseRows as $rowData) {
            foreach ($reverseCols as $colData) {
                $cell = $data[$rowData[self::KEY_KEY]][$colData[self::KEY_KEY]];
                if ($cell !== null) {
                    $storageIndex = count($rows) - $rowIndex;
                    return $this->getPotentialStorageIndex($rows, $storageIndex);
                }
            }

            $rowIndex++;
        }

        return $storageIndex;
    }

    /**
     * @param array $data
     * @param int $index
     * @return int|null
     */
    private function getPotentialStorageIndex(array $data, int $index): ?int
    {
        if (!isset($data[$index])) {
            return null;
        }

        return $index;
    }

    /**
     * @param Data $fieldDefinition
     * @param array $data
     * @param array $newData
     * @param int $startIndex
     * @return array
     */
    protected function mergeData(Data $fieldDefinition, array $data, array $newData, int $startIndex): array
    {
        $rows = $fieldDefinition->getRows();

        $rowCount = $this->getRowCount($fieldDefinition);

        $rowKeys = $this->getKeys($rows);

        $result = $this->mergeDataAtIndex($data, $newData, $startIndex, $rowCount);

        $result = $this->buildStructuredTableData($fieldDefinition, $result);

        return array_combine($rowKeys, $result);
    }

    /**
     * @param Data $fieldDefinition
     * @param array $data
     * @return array
     */
    protected function replaceData(Data $fieldDefinition, array $data): array
    {
        $rows = $fieldDefinition->getRows();

        $rowKeys = $this->getKeys($rows);
        $result  = $this->populateRemainingStructuredData($fieldDefinition, $data);
        $result  = $this->buildStructuredTableData($fieldDefinition, $result);

        return array_combine($rowKeys, $result);
    }

    /**
     * @param array $data
     * @return array
     */
    protected function getKeys(array $data): array
    {
        $keys = [];
        foreach ($data as $element) {
            $keys[] = $element[self::KEY_KEY];
        }

        return $keys;
    }

    /**
     * @param Data $fieldDefinition
     * @param array $data
     * @return array
     */
    private function buildStructuredTableData(Data $fieldDefinition, array $data): array
    {
        $rows = $fieldDefinition->getRows();
        $cols = $fieldDefinition->getCols();

        $rowKeys = $this->getKeys($rows);
        $colKeys = $this->getKeys($cols);

        $result = [];
        foreach ($data as $value) {
            $result[] = array_combine($colKeys, $value);
        }

        return array_combine($rowKeys, $result);
    }

    /**
     * @param Data $fieldDefinition
     * @return int
     */
    protected function getRowCount(Data $fieldDefinition): int
    {
        return count($fieldDefinition->getRows());
    }

    /**
     * @param Data $fieldDefinition
     * @return int
     */
    protected function getColCount(Data $fieldDefinition): int
    {
        return count($fieldDefinition->getCols());
    }

    /**
     * @return mixed
     */
    protected function getEmptyCellValue(): mixed
    {
        return self::EMPTY_CELL_VALUE;
    }

    /**
     * @param string $dataType
     * @return bool
     */
    public function supports(string $dataType): bool
    {
        return $dataType === self::STRUCTURED_TABLE_DATA_TYPE;
    }
}
