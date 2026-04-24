<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Flyokai\LaminasDbBulkUpdate;


class SelectConditionFactory
{
    public function composition(array $conditions): SelectConditionComposition
    {
        return new SelectConditionComposition($conditions);
    }
    public function notIn(array $values): SelectNotInCondition
    {
        return new SelectNotInCondition($values);
    }
    public function in(array $values): SelectInCondition
    {
        return new SelectInCondition($values);
    }

    public function range(int $from, int $to): SelectRangeCondition
    {
        return new SelectRangeCondition($from, $to);
    }
}
