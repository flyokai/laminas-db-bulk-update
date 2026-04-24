<?php

namespace Flyokai\LaminasDbBulkUpdate\Sql\TupleIdResolver;

use Flyokai\DataMate\Helper\DtoTrait;

class Context
{
    use DtoTrait;

    public function __construct(
        public readonly string    $tableName,
        public readonly string    $targetField,
        public readonly array     $tupleColumns,
        public readonly array     $incrementRow = [],
        public readonly ?\Closure $initRow = null,
        public readonly array     $filter = [],
        public readonly bool      $generate = false,
        public readonly bool      $dryRun = false,
        public readonly ?\Closure $cast = null,
        public readonly ?\Closure $selectBuilder = null,
    )
    {
    }
}
