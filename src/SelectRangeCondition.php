<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Flyokai\LaminasDbBulkUpdate;

use Laminas\Db\Sql\Where;

class SelectRangeCondition implements SelectCondition
{
    public function __construct(
        public readonly int $from,
        public readonly int $to
    ) {
    }

    public function apply(string $field, Where $where): void
    {
        $where->greaterThanOrEqualTo($field, $this->from);
        $where->lessThanOrEqualTo($field, $this->to);
    }
}
