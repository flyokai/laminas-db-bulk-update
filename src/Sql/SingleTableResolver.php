<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Flyokai\LaminasDbBulkUpdate\Sql;

use Laminas\Db\Sql\Sql;

class SingleTableResolver implements IdResolver
{
    /**
     * @var Sql
     */
    private $sql;
    /**
     * @var string
     */
    private $tableName;
    /**
     * @var string
     */
    private $sourceField;
    /**
     * @var string
     */
    private $targetField;

    /**
     * @var array<string, Identifier>
     */
    private $unresolvedValues;

    /** @var int[] */
    private $resolvedValues = [];

    /**
     * @var array<string, Identifier>
     */
    private array $resolvedIds = [];

    /**
     * @var \WeakMap<Identifier, mixed>
     */
    private \WeakMap $idMap;

    /** @var array[] */
    private $incrementRow = [];

    /**
     * @var array
     */
    private $filter = [];

    private \Closure $removeId;

    public function __construct(Sql $sql, string $tableName, string $sourceField, string $targetField)
    {
        $this->sql = $sql;
        $this->tableName = $tableName;
        $this->sourceField = $sourceField;
        $this->targetField = $targetField;
        $resolvedValues = &$this->resolvedValues;
        $unresolvedValues = &$this->unresolvedValues;
        $resolvedIds = &$this->resolvedIds;
        $this->removeId = static function (string $value) use (&$resolvedValues, &$unresolvedValues, &$resolvedIds) {
            unset($resolvedValues[$value], $unresolvedValues[$value], $resolvedIds[$value]);
        };
    }

    public function withAutoIncrement(array $defaultRow): self
    {
        $resolver = clone $this;
        $resolver->incrementRow = $defaultRow;
        return $resolver;
    }

    /**
     * @throws IdentifierNotResolved when value does not exists and cannot be generated
     */
    public function resolve(Identifier $value): int
    {
        if (!$this->canResolve($value)) {
            throw new IdentifierNotResolved();
        }
        $this->resolveValues();

        return $value->findValue($this->resolvedValues);
    }

    public function canResolve(Identifier $value): bool
    {
        return isset($this->idMap()[$value]);
    }

    private function resolveValues()
    {
        if (!$this->unresolvedValues) {
            return;
        }

        $resolveValue = $this->unresolvedValues;
        $this->unresolvedValues = [];

        $inCondition = [];
        foreach ($resolveValue as $key => $value) {
            unset($value);
            $inCondition[] = (string)$key;
        }

        $select = $this->sql->select($this->tableName)
            ->columns([$this->targetField, $this->sourceField])
            ->where([$this->sourceField => $inCondition] + $this->filter);

        foreach ($this->sql->prepareStatementForSqlObject($select)->execute() as $row) {
            $this->resolvedValues[$row[$this->sourceField]] = (int)$row[$this->targetField];
            unset($resolveValue[$row[$this->sourceField]]);
        }

        if ($this->incrementRow && $resolveValue) {
            $this->sql->getAdapter()->getDriver()->getConnection()->beginTransaction();

            $incrementRow = $this->incrementRow;
            unset($incrementRow[$this->sourceField]);

            $columns = array_keys($incrementRow);
            $columns[] = $this->sourceField;
            $baseRow = array_values($incrementRow);

            $insert = InsertOnDuplicate::create($this->tableName, $columns);
            $inCondition = [];

            foreach (array_keys($resolveValue) as $key) {
                $row = $baseRow;
                $row[] = $key;
                $inCondition[] = (string)$key;
                $insert = $insert->withRow(...$row);
            }

            $insert->executeIfNotEmpty($this->sql);

            $select = $this->sql->select($this->tableName)
                ->columns([$this->targetField, $this->sourceField])
                ->where([$this->sourceField => $inCondition] + $this->filter);

            foreach ($this->sql->prepareStatementForSqlObject($select)->execute() as $row) {
                $this->resolvedValues[$row[$this->sourceField]] = (int)$row[$this->targetField];
            }

            $this->sql->getAdapter()->getDriver()->getConnection()->rollback();
        }
    }

    private function idMap(): \WeakMap
    {
        return $this->idMap ??= new \WeakMap();
    }

    public function unresolved($value): Identifier
    {
        $unresolved = $this->_unresolved($value);
        $this->idMap()[$unresolved] = $value;
        return $unresolved;
    }

    private function _unresolved($value): Identifier
    {
        if (isset($this->resolvedValues[$value])) {
            return $this->resolvedIds[$value]
                ??= new ResolvedIdentifier($this->resolvedValues[$value], $value, $value, [$this, 'removeId']);
        }

        return $this->unresolvedValues[$value]
            ??= new UnresolvedIdentifier($value, $this->removeId);
    }

    public function withFilter(array $filter): self
    {
        $resolver = clone $this;
        $resolver->filter = $filter;
        return $resolver;
    }
}
