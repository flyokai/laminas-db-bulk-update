<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Flyokai\LaminasDbBulkUpdate\Sql;

use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;

class CombinedTableResolver implements IdResolver
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

    /** @var string */
    private $foreignField;

    /** @var IdResolver */
    private $foreignResolver;

    /**
     * @var array<string, Identifier>
     */
    private $unresolvedValues;

    /** @var int[] */
    private $resolvedValues;

    /**
     * @var array<string, Identifier>
     */
    private array $resolvedIds = [];

    /**
     * @var \WeakMap<Identifier, mixed>
     */
    private \WeakMap $idMap;

    /** @var Identifier[] */
    private $foreignValues = [];

    private \Closure $removeId;

    public function __construct(
        Sql $sql,
        string $tableName,
        string $sourceField,
        string $targetField,
        string $foreignField,
        IdResolver $foreignResolver
    ) {
        $this->sql = $sql;
        $this->tableName = $tableName;
        $this->sourceField = $sourceField;
        $this->targetField = $targetField;
        $this->foreignResolver = $foreignResolver;
        $this->foreignField = $foreignField;
        $resolvedValues = &$this->resolvedValues;
        $unresolvedValues = &$this->unresolvedValues;
        $resolvedIds = &$this->resolvedIds;
        $this->removeId = static function (string $value) use (&$resolvedValues, &$unresolvedValues, &$resolvedIds) {
            unset($resolvedValues[$value], $unresolvedValues[$value], $resolvedIds[$value]);
        };
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

        $unresolvedValueList = $this->unresolvedValues;

        $resolveValue = array_map(
            function ($value) {
                return explode('|', $value, 2)[0];
            },
            array_keys($unresolvedValueList)
        );

        $foreignValueList = array_filter(array_map(
            function (Identifier $id) {
                try {
                    return $this->foreignResolver->resolve($id);
                } catch (IdentifierNotResolved $exception) {
                    return null;
                }
            },
            $this->foreignValues
        ));

        $this->unresolvedValues = [];
        $this->foreignValues = [];

        if (!$foreignValueList || !$unresolvedValueList) {
            return;
        }

        $select = $this->sql->select($this->tableName)
            ->columns([
                $this->targetField, $this->sourceField, $this->foreignField
            ])
            ->where([$this->sourceField => $resolveValue, $this->foreignField => $foreignValueList]);

        $foreignMap = array_flip($foreignValueList);

        foreach ($this->sql->prepareStatementForSqlObject($select)->execute() as $row) {
            $key = implode('|', [$row[$this->sourceField], $foreignMap[$row[$this->foreignField]]]);
            $this->resolvedValues[$key] = (int)$row[$this->targetField];
            unset($unresolvedValueList[$key]);
        }

        $this->sql->getAdapter()->getDriver()->getConnection()->beginTransaction();

        foreach (array_keys($unresolvedValueList) as $key) {
            list($source, $foreignValue) = explode('|', $key, 2);

            if (!isset($foreignValueList[$foreignValue])) {
                continue;
            }

            $this->resolvedValues[$key] = (int)$this->sql->prepareStatementForSqlObject(
                $this->sql->insert($this->tableName)
                    ->values([$this->sourceField => $source, $this->foreignField => $foreignValueList[$foreignValue]])
            )->execute()->getGeneratedValue();
        }

        $this->sql->getAdapter()->getDriver()->getConnection()->rollback();
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
        $key = implode('|', $value);

        if (isset($this->resolvedValues[$key])) {
            return $this->resolvedIds[$key]
                ??= new ResolvedIdentifier($this->resolvedValues[$key], $value, $key, $this->removeId);
        }

        list(, $foreignKey) = $value;

        $this->foreignValues[$foreignKey] ??= $this->foreignResolver->unresolved($foreignKey);
        $this->unresolvedValues[$key] = $this->unresolvedValues[$key]
            ?? new UnresolvedIdentifier($key, $this->removeId, $value);

        return $this->unresolvedValues[$key];
    }

}
