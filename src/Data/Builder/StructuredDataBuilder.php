<?php

declare(strict_types=1);

namespace Factotum\StructuredDataImportBundle\Data\Builder;

use Factotum\StructuredDataImportBundle\Data\Builder\Exception\IndexOutOfBoundsException;
use Factotum\StructuredDataImportBundle\Data\Builder\Exception\InsufficientTableSpaceException;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class StructuredDataBuilder
{
    protected const OVERWRITE_MODE_MERGE                           = 'merge';
    protected const OVERWRITE_MODE_REPLACE                         = 'replace';
    protected const INDEX_OUT_OF_BOUNDS_EXCEPTION_MESSAGE_KEY      = 'index_out_of_bounds_exception_message';
    protected const INSUFFICIENT_TABLE_SPACE_EXCEPTION_MESSAGE_KEY = 'insufficient_table_space_exception_message';
    protected const DATA_IMPORTER_LOG_MESSAGE_SEPARATOR            = ' ';
    protected const ADMIN_DOMAIN                                   = 'admin';

    /**
     * @param TranslatorInterface $translator
     */
    public function __construct(
        protected readonly TranslatorInterface $translator,
    ) {}

    /**
     * @param Data $fieldDefinition
     * @param mixed $baseData
     * @param array $rawInput
     * @param string $overwriteMode
     * @return mixed
     *
     * @throws IndexOutOfBoundsException|InsufficientTableSpaceException
     */
    abstract public function build(Data $fieldDefinition, mixed $baseData, array $rawInput, string $overwriteMode): mixed;

    /**
     * @param Data $fieldDefinition
     * @param array $data
     * @return array
     */
    protected function reshapeData(Data $fieldDefinition, array $data): array
    {
        $desiredColCount = $this->getColCount($fieldDefinition);
        $desiredRowCount = $this->getRowCount($fieldDefinition);

        $flattened = $this->flattenArray($data);

        if (!$this->canConvertData($flattened, $desiredRowCount * $desiredColCount)) {
            throw new InsufficientTableSpaceException(self::DATA_IMPORTER_LOG_MESSAGE_SEPARATOR . $this->translateError(self::INSUFFICIENT_TABLE_SPACE_EXCEPTION_MESSAGE_KEY, $fieldDefinition->getName()));
        }

        $result = $this->buildTableStructure($flattened, $desiredColCount);

        $result = $this->removeExcessRows($result, $desiredRowCount);

        return $this->populateRemainingStructuredData($fieldDefinition, $result);
    }

    /**
     * @param array $array
     * @return array
     */
    protected function flattenArray(array $array): array
    {
        $result = [];

        foreach ($array as $value) {
            if (is_array($value)) {
                $result = array_merge($result, $this->flattenArray($value));
            } else {
                $result[] = $value;
            }
        }

        return $result;
    }

    /**
     * @param array $data
     * @param int $colCount
     * @return array
     */
    protected function buildTableStructure(array $data, int $colCount): array
    {
        $dataCount = count($data);

        $result = array_chunk(
            array_pad(
                $data,
                $this->calculateMaxCols($dataCount, $colCount),
                $this->getEmptyCellValue()
            ),
            $colCount
        );

        return $result;
    }

    /**
     * @param array $result
     * @param int $maxRowCount
     * @return array
     */
    protected function removeExcessRows(array $result, int $maxRowCount): array
    {
        if (count($result) > $maxRowCount) {
            $result = array_slice($result, 0, $maxRowCount);
        }

        return $result;
    }

    /**
     * @param Data $fieldDefinition
     * @param array $data
     * @return array
     */
    protected function populateRemainingStructuredData(Data $fieldDefinition, array $data): array
    {
        $maxRows = $this->getRowCount($fieldDefinition);
        $maxCols = $this->getColCount($fieldDefinition);

        $startIndex = count($data);

        for ($i = $startIndex; $i < $maxRows; $i++) {
            $data = $this->populateRemainingCells($data, $i, $maxCols);
        }

        return $data;
    }

    /**
     * @param array $data
     * @param int $rowIndex
     * @param int $colCount
     * @return array
     */
    protected function populateRemainingCells(array $data, int $rowIndex, int $colCount): array
    {
        $data[$rowIndex] = array_pad(
            $data[$rowIndex] ?? [],
            $colCount,
            $this->getEmptyCellValue()
        );

        return $data;
    }

    /**
     * @param Data $fieldDefinition
     * @param array $baseData
     * @param array $newData
     * @param string $overwriteMode
     * @param int $newDataIndex
     * @return array
     */
    protected function applyData(Data $fieldDefinition, array $baseData, array $newData, string $overwriteMode, int $newDataIndex): array
    {
        $result = $newData;

        if ($overwriteMode === self::OVERWRITE_MODE_MERGE) {
            $result = $this->mergeData($fieldDefinition, $baseData, $newData, $newDataIndex);
        }

        if ($overwriteMode === self::OVERWRITE_MODE_REPLACE) {
            $result = $this->replaceData($fieldDefinition, $newData);
        }

        return $result;
    }

    /**
     * @param Data $fieldDefintion
     * @param array $data
     * @return int|null
     */
    abstract protected function getStorageIndex(Data $fieldDefintion, array $data): int|null;

    /**
     * @param Data $fieldDefinition
     * @return int
     */
    abstract protected function getRowCount(Data $fieldDefinition): int;

    /**
     * @param Data $fieldDefinition
     * @return int
     */
    abstract protected function getColCount(Data $fieldDefinition): int;

    /**
     * @param Data $fieldDefinition
     * @param array $data
     * @param int $startIndex
     * @param string $overwriteMode
     * @return bool
     */
    protected function canDataVolumeBeStored(Data $fieldDefinition, array $data, int $startIndex, string $overwriteMode): bool
    {
        $rowCount = $this->getRowCount($fieldDefinition);
        $colCount = $this->getColCount($fieldDefinition);

        $maxCellAmount    = $rowCount * $colCount;
        $neededCellAmount = count($data);

        if ($overwriteMode === self::OVERWRITE_MODE_MERGE) {
            $usedCellAmount = $startIndex * $colCount;

            if ($usedCellAmount + $neededCellAmount > $maxCellAmount) {
                return false;
            }
        }

        if ($overwriteMode === self::OVERWRITE_MODE_REPLACE) {
            if ($neededCellAmount > $maxCellAmount) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param Data $fieldDefinition
     * @param array $data
     * @return array|null
     */
    protected function formatDataAccordingToDefinition(Data $fieldDefinition, array $data): array
    {
        return $this->buildTableStructure($data, $this->getColCount($fieldDefinition));
    }

    /**
     * @return mixed
     */
    // im not really a fan of late static binding...
    abstract protected function getEmptyCellValue(): mixed;

    /**
     * @param array $data
     * @param int $maxCellAmount
     * @return bool
     */
    protected function canConvertData(array $data, int $maxCellAmount): bool
    {
        $reversedData = array_reverse($data);

        $dataCount = count($data);

        $firstEmptyCellIndex = 0;
        for ($i = 0; $i < $dataCount; $i++) {
            if ($reversedData[$i] !== $this->getEmptyCellValue()) {
                $firstEmptyCellIndex = $dataCount - $i;
                break;
            }
        }

        if ($firstEmptyCellIndex > $maxCellAmount) {
            return false;
        }

        return true;
    }

    /**
     * @param int $dataCount
     * @param int $colCount
     * @return int
     */
    protected function calculateMaxCols(int $dataCount, int $colCount): int
    {
        return intval(ceil($dataCount / $colCount) * $colCount);
    }

    /**
     * @param Data $fieldDefintion
     * @param array $data
     * @param array $newData
     * @param int $startIndex
     * @return array
     */
    abstract protected function mergeData(Data $fieldDefintion, array $data, array $newData, int $startIndex): array;

    /**
     * @param array $data
     * @param array $newData
     * @param int $newDataIndex
     * @param int $maxRows
     * @return array
     */
    protected function mergeDataAtIndex(array $data, array $newData, int $newDataIndex, int $maxRows): array
    {
        $newDataCount = count($newData);

        $oldData  = array_slice($data, 0, $newDataIndex);
        $tailData = array_slice($data, $newDataIndex + $newDataCount, $maxRows);

        return array_merge($oldData, $newData, $tailData);
    }

    /**
     * @param Data $fieldDefinition
     * @param array $data
     * @return array
     */
    abstract protected function replaceData(Data $fieldDefinition, array $data): array;

    /**
     * @param string $messageKey
     * @param string $fieldName
     * @return string
     */
    protected function translateError(string $messageKey, string $fieldName): string
    {
        return sprintf(
            $this->translator->trans($messageKey, [], self::ADMIN_DOMAIN),
            $fieldName
        );
    }

    /**
     * @param string $dataType
     * @return bool
     */
    abstract public function supports(string $dataType): bool;
}
