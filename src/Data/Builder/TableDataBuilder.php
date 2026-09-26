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
        if (!$rawInput) {
            return $baseData;
        }

        $baseData = $baseData ?? [];

        $storageIndex = 0;
        if ($baseData && $overwriteMode === parent::OVERWRITE_MODE_MERGE) {
            $storageIndex = $this->getStorageIndex($fieldDefinition, $baseData);
            if ($storageIndex === null) {
                throw new IndexOutOfBoundsException(parent::DATA_IMPORTER_LOG_MESSAGE_SEPARATOR . $this->translateError(parent::INDEX_OUT_OF_BOUNDS_EXCEPTION_MESSAGE_KEY, $fieldDefinition->getName()));
            }
        }

        $scopedfieldDefinition = $this->createScopedFieldDefinition($fieldDefinition, $baseData, $rawInput);

        $rowsFixed = $scopedfieldDefinition->getRowsFixed();
        if ($rowsFixed && !$this->canDataVolumeBeStored($scopedfieldDefinition, $rawInput, $storageIndex, $overwriteMode)) {
            throw new InsufficientTableSpaceException(parent::DATA_IMPORTER_LOG_MESSAGE_SEPARATOR . $this->translateError(parent::INSUFFICIENT_TABLE_SPACE_EXCEPTION_MESSAGE_KEY, $fieldDefinition->getName()));
        }

        $newData = $this->convertToStructuredData($scopedfieldDefinition, $rawInput);

        $result = $newData;
        if ($overwriteMode === parent::OVERWRITE_MODE_MERGE) {
            $result = $this->mergeData($scopedfieldDefinition, $baseData, $newData, $storageIndex);
        }

        if ($overwriteMode === parent::OVERWRITE_MODE_REPLACE) {
            $result = $this->replaceData($scopedfieldDefinition, $newData);
        }

        return $result;
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
                if ($data[$i][$j] !== self::EMPTY_CELL_VALUE) {
                    return $this->checkPotentialStorageIndex($fieldDefinition, $data, $i);
                }
            }
        }

        return $storageIndex;
    }

    /**
     * @param Data $fieldDefinition
     * @param array $data
     * @param int $index
     * @return int|null
     */
    protected function checkPotentialStorageIndex(Data $fieldDefinition, array $data, int $index): ?int
    {
        $storageIndex = $index + 1;

        if ($fieldDefinition->getRowsFixed() && $fieldDefinition->getRows() <= $storageIndex && !isset($data[$storageIndex])) {
            return null;
        }

        return $storageIndex;
    }

    /**
     * @param Data $fieldDefinition
     * @param array $data
     * @param int $startIndex
     * @param string $overwriteMode
     * @return bool
     */
    protected function canDataVolumeBeStored(Data $fieldDefinition, array $data, int $startIndex, string $overwriteMode): bool
    {
        $rows = $fieldDefinition->getRows();
        $cols = $fieldDefinition->getCols();

        $maxCellAmount    = $rows * $cols;
        $neededCellAmount = count($data);

        if ($overwriteMode === parent::OVERWRITE_MODE_MERGE) {
            $usedCellAmount = $startIndex * $cols;

            if ($usedCellAmount + $neededCellAmount > $maxCellAmount) {
                return false;
            }
        }

        if ($overwriteMode === parent::OVERWRITE_MODE_REPLACE) {
            if ($neededCellAmount > $maxCellAmount) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param Data $fieldDefinition
     * @param array $data
     * @return array
     */
    protected function convertToStructuredData(Data $fieldDefinition, array $data): array
    {
        $maxCols = $fieldDefinition->getCols();

        $result   = [];
        $rowCount = 0;
        $index    = 0;
        while (isset($data[$index])) {
            for ($i = 0; $i < $maxCols; $i++) {
                $result[$rowCount][$i] = isset($data[$index]) ? $data[$index] : self::EMPTY_CELL_VALUE;
                $index++;
            }

            $rowCount++;
        }

        return $result;
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

        $maxRows = $rows;

        $newDataCount = count($newData);

        if (!$data) {
            return $this->populateRemainingTableData($fieldDefinition, $newData);
        }

        $data = $this->updateColumnSize($fieldDefinition, $data);

        $oldData = array_slice($data, 0, $startIndex);
        $tailData = array_slice($data, $startIndex + $newDataCount, $maxRows);

        return array_merge($oldData, $newData, $tailData);
    }

    /**
     * @param Data $fieldDefinition
     * @param array $data
     * @return array
     */
    protected function replaceData(Data $fieldDefinition, array $data): array
    {
        return $this->populateRemainingTableData($fieldDefinition, $data);
    }

    /**
     * @param Data $fieldDefinition
     * @param array $data
     * @return array
     */
    protected function populateRemainingTableData(Data $fieldDefinition, array $data): array
    {
        $maxRows = $fieldDefinition->getRows();
        $maxCols = $fieldDefinition->getCols();

        $startIndex = count($data);

        for ($i = $startIndex; $i < $maxRows; $i++) {
            $data = $this->populateRemainingCells($data, $i, $maxCols);
        }

        return $data;
    }

    /**
     * @param Data $fieldDefinition
     * @param array $data
     * @return array
     */
    private function updateColumnSize(Data $fieldDefinition, array $data): array
    {
        $dataCount       = count($data);
        $definedColCount = $fieldDefinition->getCols();

        for ($i = 0; $i < $dataCount; $i++) {
            $realColCount = count($data[$i]);

            if ($definedColCount === $realColCount) {
                continue;
            }

            if ($definedColCount > $realColCount) {
                $data = $this->populateRemainingCells($data, $i, $definedColCount);
                continue;
            }

            $maxColIndex = $definedColCount - 1;
            $data        = $this->unpopulateUnnecessaryCells($data, $i, $realColCount, $maxColIndex);
        }

        return $data;
    }

    /**
     * @param array $data
     * @param int $colCount
     * @return array
     */
    private function populateRemainingCells(array $data, int $rowIndex, int $colCount): array
    {
        for ($j = 0; $j < $colCount; $j++) {
            if (!isset($data[$rowIndex][$j])) {
                $data[$rowIndex][$j] = self::EMPTY_CELL_VALUE;
            }
        }

        return $data;
    }

    /**
     * @param array $data
     * @param int $rowIndex
     * @param int $colCount
     * @param int $maxColIndex
     * @return array
     */
    private function unpopulateUnnecessaryCells(array $data, int $rowIndex, int $colCount, int $maxColIndex): array
    {
        for ($j = 0; $j < $colCount; $j++) {
            if ($j > $maxColIndex) {
                unset($data[$rowIndex][$j]);
            }
        }

        return $data;
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
            return intval(ceil(count($rawInput) / $fieldDefinition->getCols()));
        }

        return $fieldDefinition->getRows() ? $fieldDefinition->getRows() : self::ROW_AMOUNT_DEFAULT_VALUE;
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
