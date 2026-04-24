<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Flyokai\LaminasDbBulkUpdate;


class TableRangeConditionGenerator implements SelectConditionGenerator
{
    /**
     * @var SelectConditionFactory
     */
    private $conditionFactory;

    /**
     * @var int
     */
    private $minValue;

    /**
     * @var int
     */
    private $maxValue;

    private const STEP = 200;

    /**
     * @var int[]
     */
    private $ranges;

    public function __construct(
        SelectConditionFactory $conditionFactory,
        int $minValue,
        int $maxValue,
        array $ranges
    ) {
        $this->conditionFactory = $conditionFactory;
        $this->minValue = $minValue;
        $this->maxValue = $maxValue;
        $this->ranges = $ranges;
    }

    public function conditions(): \Generator
    {
        $start = $this->minValue;

        $currentRange = 0;

        do {
            $rangeMax = min($this->ranges[$currentRange] ?? ($start + self::STEP + 1), $this->maxValue);
            yield $this->conditionFactory->range($start, $rangeMax);
            $start = $rangeMax + 1;
            $currentRange ++;
        } while ($start <= $this->maxValue);
    }
}
