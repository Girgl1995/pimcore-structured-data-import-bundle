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
        if (!$rawInput) {
            return $baseData;
        }

        $baseData = $baseData ? $baseData->getData() : [];

        $storageIndex = 0;
        if ($baseData && $overwriteMode === parent::OVERWRITE_MODE_MERGE) {
            $storageIndex = $this->getStorageIndex($fieldDefinition, $baseData);
            if ($storageIndex === null) {
                throw new IndexOutOfBoundsException(parent::DATA_IMPORTER_LOG_MESSAGE_SEPARATOR . $this->translateError(parent::INDEX_OUT_OF_BOUNDS_EXCEPTION_MESSAGE_KEY, $fieldDefinition->getName()));
            }
        }

        if (!$this->canDataVolumeBeStored($fieldDefinition, $rawInput, $storageIndex, $overwriteMode)) {
            throw new InsufficientTableSpaceException(parent::DATA_IMPORTER_LOG_MESSAGE_SEPARATOR . $this->translateError(parent::INSUFFICIENT_TABLE_SPACE_EXCEPTION_MESSAGE_KEY, $fieldDefinition->getName()));
        }

        $newData = $this->convertToStructuredData($fieldDefinition, $rawInput);

        $result = $baseData;
        if ($overwriteMode === parent::OVERWRITE_MODE_MERGE) {
            $result = $this->mergeData($fieldDefinition, $baseData, $newData, $storageIndex);
        }

        if ($overwriteMode === parent::OVERWRITE_MODE_REPLACE) {
            $result = $this->replaceData($fieldDefinition, $newData);
        }

        return $this->createStructuredTable($result);
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
                    return $this->checkPotentialStorageIndex($rows, $rowIndex);
                }
            }

            $rowIndex++;
        }

        return $storageIndex;
    }

    /**
     * @param array $data
     * @param int $storageIndex
     * @return int|null
     */
    private function checkPotentialStorageIndex(array $rows, int $rowIndex): ?int
    {
        $storageIndex = abs($rowIndex - count($rows));
        if (!isset($rows[$storageIndex])) {
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

        $maxCellAmount    = count($rows) * count($cols);
        $neededCellAmount = count($data);

        if ($overwriteMode === parent::OVERWRITE_MODE_MERGE) {
            $usedCellAmount = $startIndex * count($cols);

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
        $cols = $fieldDefinition->getCols();

        $result   = [];
        $rowCount = 0;
        $index    = 0;
        while (isset($data[$index])) {
            foreach ($cols as $col) {
                $result[$rowCount][$col[self::KEY_KEY]] = isset($data[$index]) ? $data[$index] : self::EMPTY_CELL_VALUE;
                $index++;
            }

            $rowCount++;
        }

        return $result;
    }

    /**
     * @param Data $fieldDefintion
     * @param array $data
     * @param array $newData
     * @param int $startIndex
     * @return array
     */
    protected function mergeData(Data $fieldDefintion, array $data, array $newData, int $startIndex): array
    {
        $rows = $fieldDefintion->getRows();

        $maxRows = count($rows);

        $newDataCount = count($newData);

        $rowKeys = $this->getRowKeys($rows);

        if (!$data) {
            $result = $this->populateRemainingTableData($fieldDefintion, $newData);

            return array_combine($rowKeys, $result);
        }

        $oldData  = array_slice($data, 0, $startIndex);
        $tailData = array_slice($data, $startIndex + $newDataCount, $maxRows);

        $result = array_merge($oldData, $newData, $tailData);

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

        $rowKeys = $this->getRowKeys($rows);
        $result  = $this->populateRemainingTableData($fieldDefinition, $data);

        return array_combine($rowKeys, $result);
    }

    /**
     * @param Data $fieldDefinition
     * @param array $data
     * @return array
     */
    protected function populateRemainingTableData(Data $fieldDefinition, array $data): array
    {
        $rows = $fieldDefinition->getRows();
        $cols = $fieldDefinition->getCols();

        $maxRows = count($rows);
        $maxCols = count($cols);

        $startIndex = count($data);
 
        for ($i = $startIndex; $i < $maxRows; $i++) {
            for ($j = 0; $j < $maxCols; $j++) {
                $data[$rows[$i][self::KEY_KEY]][$cols[$j][self::KEY_KEY]] = self::EMPTY_CELL_VALUE;
            }
        }

        return $data;
    }

    /**
     * @param array $rows
     * @return array
     */
    protected function getRowKeys(array $rows): array
    {
        $keys = [];
        foreach ($rows as $row) {
            $keys[] = $row[self::KEY_KEY];
        }

        return $keys;
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
     * @param string $dataType
     * @return bool
     */
    public function supports(string $dataType): bool
    {
        return $dataType === self::STRUCTURED_TABLE_DATA_TYPE;
    }
}
