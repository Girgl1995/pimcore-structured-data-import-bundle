<?php

declare(strict_types=1);

namespace Factotum\StructuredDataImportBundle\Data\Builder\Factory;

use Factotum\StructuredDataImportBundle\Data\Builder\StructuredDataBuilder;

class StructuredDataBuilderFactory
{
    private iterable $builders;

    /**
     * @param iterable $builders
     */
    public function __construct(iterable $builders)
    {
        $this->builders = $builders;
    }

    /**
     * @param string $dataType
     * @return null|StructuredDataBuilder
     */
    public function getBuilder(string $dataType): ?StructuredDataBuilder
    {
        foreach ($this->builders as $builder) {
            if ($builder->supports($dataType)) {
                return $builder;
            }
        }

        return null;
    }
}
