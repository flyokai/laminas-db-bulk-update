<?php

namespace Flyokai\LaminasDbBulkUpdate\Sql\PlainIdResolver;

class SequenceInfo
{
    public function __construct(
        public readonly string $field,
        public readonly string $sequenceTable,
        public readonly string $sequenceField,
        public readonly ?\Closure $currentVersionId = null,
    )
    {
    }
}
