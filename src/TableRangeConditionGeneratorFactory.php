<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Flyokai\LaminasDbBulkUpdate;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql;

class TableRangeConditionGeneratorFactory
{
    public const DEFAULT_RANGE_SIZE = 500;

    private array $filter = [];

    public function __construct(
        protected Sql\Sql $sql,
        protected SelectConditionFactory $conditionFactory,
        protected int $rangeSize = self::DEFAULT_RANGE_SIZE
    )
    {
    }

    public static function createFromAdapter(Adapter $adapter, int $rangeSize = self::DEFAULT_RANGE_SIZE): self
    {
        return new self(new Sql\Sql($adapter), new SelectConditionFactory(), $rangeSize);
    }

    public function createForTable(string $tableName, string $fieldName): TableRangeConditionGenerator
    {
        $select = $this->sql->select($tableName)
            ->columns([
                'min' => new Sql\Expression(sprintf('MIN(%s)', $fieldName)),
                'max' => new Sql\Expression(sprintf('MAX(%s)', $fieldName)),
            ])
            ->where($this->filter);

        list($minValue, $maxValue) = array_values(
            $this->sql->prepareStatementForSqlObject($select)->execute()->current()
        );


        $range = new Sql\Expression(sprintf('CEIL(%s / %2$d) * %2$d', $fieldName, $this->rangeSize));
        $order = new Sql\Expression(sprintf('CEIL(%s / %2$d) * %2$d ASC', $fieldName, $this->rangeSize));

        $select = $this->sql->select($tableName)
            ->columns([
                'range' => $range
            ])
            ->group($range)
            ->order($order)
        ;

        $ranges = [];
        $__stmt = $this->sql->prepareStatementForSqlObject($select);
        foreach ($__stmt->execute() as $row) {
            $ranges[] = (int)$row['range'];
        }

        return new TableRangeConditionGenerator($this->conditionFactory, (int)$minValue, (int)$maxValue, $ranges);
    }

    public function withFilter(array $filter): self
    {
        $clone = clone $this;
        $clone->filter = $filter;
        return $clone;
    }
}
