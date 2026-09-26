<?php

declare(strict_types=1);

namespace Factotum\StructuredDataImportBundle\Data\Builder;

use Factotum\StructuredDataImportBundle\Data\Builder\Exception\IndexOutOfBoundsException;
use Factotum\StructuredDataImportBundle\Data\Builder\Exception\InsufficientTableSpaceException;
use Factotum\StructuredDataImportBundle\Data\Builder\StructuredDataBuilder;
use Pimcore\Model\DataObject\ClassDefinition\Data;

class TableDataBuilder extends StructuredDataBuilder
{
    private const TABLE_DATA_TYPE          = 'table';
    private const COL_AMOUNT_DEFAULT_VALUE = 1;
    private const ROW_AMOUNT_DEFAULT_VALUE = 1;
    private const EMPTY_CELL_VALUE         = '';

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
        $scopedfieldDefinition = $this->createScopedFieldDefinition($fieldDefinition, $baseData, $rawInput);

        $baseData = $baseData ?? [];

        $baseData = $this->reshapeData($scopedfieldDefinition, $baseData);

        if (!$rawInput) {
            return $baseData;
        }

        $storageIndex = 0;
        if ($overwriteMode === parent::OVERWRITE_MODE_MERGE) {
            $storageIndex = $this->getStorageIndex($scopedfieldDefinition, $baseData);
            if ($storageIndex === null) {
                throw new IndexOutOfBoundsException(parent::DATA_IMPORTER_LOG_MESSAGE_SEPARATOR . $this->translateError(parent::INDEX_OUT_OF_BOUNDS_EXCEPTION_MESSAGE_KEY, $fieldDefinition->getName()));
            }
        }

        if ($scopedfieldDefinition->getRowsFixed() && !$this->canDataVolumeBeStored($scopedfieldDefinition, $rawInput, $storageIndex, $overwriteMode)) {
            throw new InsufficientTableSpaceException(parent::DATA_IMPORTER_LOG_MESSAGE_SEPARATOR . $this->translateError(parent::INSUFFICIENT_TABLE_SPACE_EXCEPTION_MESSAGE_KEY, $fieldDefinition->getName()));
        }

        $newData = $this->formatDataAccordingToDefinition($scopedfieldDefinition, $rawInput);

        $result = $this->applyData($fieldDefinition, $baseData, $newData, $overwriteMode, $storageIndex);

        return $result;
    }

    /**
     * @param Data $fieldDefinition
     * @param array $baseData
     * @param array $rawInput
     * @return mixed
     */
    private function createScopedFieldDefinition(Data $fieldDefinition, array $baseData, array $rawInput): mixed
    {
        $scopedFieldDefinition = clone $fieldDefinition;

        $scopedFieldDefinition->setCols($this->getRealColCount($scopedFieldDefinition, $baseData));
        $scopedFieldDefinition->setRows($this->getRealRowCount($scopedFieldDefinition, $baseData, $rawInput));

        return $scopedFieldDefinition;
    }

    /**
     * @param Data $fieldDefinition
     * @param array $data
     * @return int|null
     */
    protected function getStorageIndex(Data $fieldDefinition, array $data): int|null
    {
        $storageIndex = 0;

        if (!$data) {
            return $storageIndex;
        }

        $rowCount = count($data) - 1;
        $colCount = count($data[0]) - 1;

        for ($i = $rowCount; $i >= 0; $i--) {
            for ($j = $colCount; $j >= 0; $j--) {
                $cell = $data[$i][$j];
                if ($cell !== self::EMPTY_CELL_VALUE) {
                    $storageIndex = $i + 1;
                    if ($fieldDefinition->getRowsFixed() && $storageIndex >= $fieldDefinition->getRows()) {
                        return null;
                    }

                    return $storageIndex;
                }
            }
        }

        return $storageIndex;
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
        $rowCount = $this->getRowCount($fieldDefinition);

        return $this->mergeDataAtIndex($data, $newData, $startIndex, $rowCount);
    }

    /**
     * @param Data $fieldDefinition
     * @param array $data
     * @return array
     */
    protected function replaceData(Data $fieldDefinition, array $data): array
    {
        return $this->populateRemainingStructuredData($fieldDefinition, $data);
    }

    /**
     * @param Data $fieldDefinition
     * @param array $baseData
     * @return int
     */
    private function getRealColCount(Data $fieldDefinition, array $baseData): int
    {
        if ($baseData && !$fieldDefinition->getColsFixed()) {
            return count($baseData[0]);
        }

        return $fieldDefinition->getCols() ? $fieldDefinition->getCols() : self::COL_AMOUNT_DEFAULT_VALUE;
    }

    /**
     * @param Data $fieldDefinition
     * @param array $baseData
     * @param array $rawInput
     * @return int
     */
    private function getRealRowCount(Data $fieldDefinition, array $baseData, array $rawInput): int
    {
        if ($baseData && !$fieldDefinition->getRowsFixed()) {
            return count($baseData);
        }

        if ($fieldDefinition->getCols() && !$fieldDefinition->getRowsFixed()) {
            return $this->calculateMaxRows(count($rawInput), $fieldDefinition->getCols());
        }

        return $fieldDefinition->getRows() ? $fieldDefinition->getRows() : self::ROW_AMOUNT_DEFAULT_VALUE;
    }

    /**
     * @param int $dataCount
     * @param int $colCount
     * @return int
     */
    private function calculateMaxRows(int $dataCount, int $colCount): int
    {
        return intval(ceil($dataCount / $colCount));
    }

    /**
     * @param Data $fieldDefinition
     * @return int
     */
    protected function getRowCount(Data $fieldDefinition): int
    {
        return $fieldDefinition->getRows();
    }

    /**
     * @param Data $fieldDefinition
     * @return int
     */
    protected function getColCount(Data $fieldDefinition): int
    {
        return $fieldDefinition->getCols();
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
        return $dataType === self::TABLE_DATA_TYPE;
    }
}
