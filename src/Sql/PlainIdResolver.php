<?php

namespace Flyokai\LaminasDbBulkUpdate\Sql;

use Laminas\Db\Sql\InsertMultiple;
use Flyokai\LaminasDbBulkUpdate\LocalSequence;
use Flyokai\LaminasDbDriverAsync\ConnectionPool;
use Flyokai\LaminasDbDriverAsync\CreateSqlTrait;

class PlainIdResolver
{
    use CreateSqlTrait;

    public function __construct(
        protected ConnectionPool $connectionPool,
        protected LocalSequence  $localSequence,
    )
    {
    }

    /**
     * @return array<string, int>|array<string, array{0:int,1:int}>
     */
    public function resolveValues(PlainIdResolver\Context $context, array $values): array
    {
        if (empty($values)) {
            return [];
        }
        $resolvedValues = [];
        $values = array_flip($values);
        $inCondition = [];
        foreach ($values as $value => $_) {
            $inCondition[] = (string)$value;
        }

        $sequenceInfo = $context->sequenceInfo;

        $sql = $this->createSql();

        $fetchColumns = [$context->targetField, $context->sourceField];
        if ($sequenceInfo) {
            $fetchColumns[] = $sequenceInfo->field;
        }

        $select = $sql->select($context->tableName)
            ->columns($fetchColumns)
            ->where([$context->sourceField => $inCondition] + $context->filter);

        if ($sequenceInfo && $sequenceInfo->currentVersionId) {
            $select->where
                ->lessThanOrEqualTo('created_in', ($sequenceInfo->currentVersionId)())
                ->greaterThan('updated_in', ($sequenceInfo->currentVersionId)());
        }

        $__stmt = $sql->prepareStatementForSqlObject($select);
        /*var_dump('resolve ids');
        var_dump($__stmt->getSql());
        var_dump($__stmt->getParameterContainer()->getNamedArray());*/
        foreach ($__stmt->execute() as $row) {
            //var_dump($row);
            $sourceValue = $row[$context->sourceField];
            if ($sequenceInfo) {
                $resolvedValues[$sourceValue] = [
                    (int)$row[$context->targetField],
                    (int)$row[$sequenceInfo->field],
                ];
            } else {
                $resolvedValues[$sourceValue] = (int)$row[$context->targetField];
            }
            unset($values[$sourceValue]);
        }

        if ($context->generate && $values) {
            $connection = $sql->getAdapter()->getDriver()->getConnection();
            $connection->beginTransaction();
            try {
                $incrementRow = $context->incrementRow;
                unset($incrementRow[$context->sourceField]);

                $sequenceValues = [];
                if ($sequenceInfo) {
                    unset($incrementRow[$sequenceInfo->field]);
                    /*$select = $sql->select($sequenceInfo->sequenceTable)
                        ->columns([$sequenceInfo->sequenceField])
                        ->order('sequence_value desc')
                        ->limit(1)
                        ->forUpdate(true);
                    $__stmt = $sql->prepareStatementForSqlObject($select);
                    foreach ($__stmt->execute() as $row) {
                    }*/
                    $insertValues = [];
                    foreach (array_keys($values) as $pos => $key) {
                        $insertValues[] = [$sequenceInfo->sequenceField => null];
                    }
                    if (!$context->dryRun) {
                        $insertMultiple = (new InsertMultiple($sequenceInfo->sequenceTable))
                            ->columns([$sequenceInfo->sequenceField])
                            ->values($insertValues)
                        ;
                        $lastSequenceValue = $sql->prepareStatementForSqlObject($insertMultiple)
                            ->execute()
                            ->getGeneratedValue();
                    }
                    foreach (array_keys($values) as $pos => $key) {
                        if (!$context->dryRun) {
                            $sequenceValues[$pos] = $lastSequenceValue++;
                        } else {
                            $sequenceValues[$pos] = $this->localSequence->next();
                        }
                    }
                }

                if (!$context->dryRun) {
                    $insertColumns = array_keys($incrementRow);
                    $insertColumns[] = $context->sourceField;
                    if ($sequenceInfo) {
                        $insertColumns[] = $sequenceInfo->field;
                    }
                    $baseRow = array_values($incrementRow);

                    $insert = InsertOnDuplicate::create($context->tableName, $insertColumns);
                    $inCondition = [];

                    foreach (array_keys($values) as $pos => $key) {
                        $row = $baseRow;
                        $row[] = $key;
                        if ($sequenceInfo) {
                            $row[] = $sequenceValues[$pos];
                        }
                        $inCondition[] = (string)$key;
                        $insert = $insert->withRow(...$row);
                    }
                    $insert->executeIfNotEmpty($sql);

                    $select = $sql->select($context->tableName)
                        ->columns($fetchColumns)
                        ->where([$context->sourceField => $inCondition] + $context->filter);

                    foreach ($sql->prepareStatementForSqlObject($select)->execute() as $row) {
                        $sourceValue = $row[$context->sourceField];
                        if ($sequenceInfo) {
                            $resolvedValues[$sourceValue] = [
                                (int)$row[$context->targetField],
                                (int)$row[$sequenceInfo->field],
                            ];
                        } else {
                            $resolvedValues[$sourceValue] = (int)$row[$context->targetField];
                        }
                    }
                } else {
                    foreach (array_keys($values) as $pos => $sourceValue) {
                        if ($sequenceInfo) {
                            $resolvedValues[$sourceValue] = [
                                $this->localSequence->next(),
                                $sequenceValues[$pos],
                            ];
                        } else {
                            $resolvedValues[$sourceValue] = $this->localSequence->next();
                        }
                    }
                }

            } finally {
                $connection->rollback();
            }
        }
        return $resolvedValues;
    }
}
