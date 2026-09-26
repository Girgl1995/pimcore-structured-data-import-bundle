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
     * @param Data $fieldDefintion
     * @param array $data
     * @return int|null
     */
    abstract protected function getStorageIndex(Data $fieldDefintion, array $data): int|null;

    /**
     * @param Data $fieldDefinition
     * @param array $data
     * @param int $startIndex
     * @param string $overwriteMode
     * @return bool
     */
    abstract protected function canDataVolumeBeStored(Data $fieldDefinition, array $data, int $startIndex, string $overwriteMode): bool;

    /**
     * @param Data $fieldDefinition
     * @param array $data
     * @return array
     */
    abstract protected function convertToStructuredData(Data $fieldDefinition, array $data): array;

    /**
     * @param Data $fieldDefintion
     * @param array $data
     * @param array $newData
     * @param int $startIndex
     * @return array
     */
    abstract protected function mergeData(Data $fieldDefintion, array $data, array $newData, int $startIndex): array;

    /**
     * @param Data $fieldDefinition
     * @param array $data
     * @return array
     */
    abstract protected function replaceData(Data $fieldDefinition, array $data): array;

    /**
     * @param Data $fieldDefinition
     * @param array $data
     * @return array
     */
    abstract protected function populateRemainingTableData(Data $fieldDefinition, array $data): array;

    /**
     * @param string $dataType
     * @return bool
     */
    abstract public function supports(string $dataType): bool;

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
}
