<?php

namespace Flyokai\LaminasDbBulkUpdate\Sql;

use Laminas\Db\Sql\InsertMultiple;
use Flyokai\LaminasDbBulkUpdate\LocalSequence;
use Flyokai\LaminasDbDriverAsync\ConnectionPool;
use Flyokai\LaminasDbDriverAsync\CreateSqlTrait;

class JoinIdResolver
{
    use CreateSqlTrait;

    public function __construct(
        protected ConnectionPool $connectionPool,
        protected LocalSequence  $localSequence,
    )
    {
    }

    /**
     * @return array<string, int>
     */
    public function resolveValues(JoinIdResolver\Context $context, array $values): array
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

        $sql = $this->createSql();

        $select = ($context->selectBuilder)($sql, $context, $inCondition);

        $__stmt = $sql->prepareStatementForSqlObject($select);
        /*var_dump('resolve ids');
        var_dump($__stmt->getSql());
        var_dump($__stmt->getParameterContainer()->getNamedArray());*/
        foreach ($__stmt->execute() as $row) {
            //var_dump($row);
            $sourceValue = $row[$context->sourceField];
            $resolvedValues[$sourceValue] = (int)$row[$context->targetField];
            unset($values[$sourceValue]);
        }

        if ($context->generate && $values) {
            $connection = $sql->getAdapter()->getDriver()->getConnection();
            $connection->beginTransaction();
            try {
                $incrementRow = $context->incrementRow;
                unset($incrementRow[$context->sourceField]);

                $insertColumns = array_keys($incrementRow);
                $insertValues = [];
                foreach (array_keys($values) as $pos => $key) {
                    $insertValues[] = $incrementRow;
                }

                $sequenceValues = [];
                if (!$context->dryRun) {
                    $insertMultiple = (new InsertMultiple($context->tableName))
                        ->columns($insertColumns)
                        ->values($insertValues);
                    $lastSequenceValue = $sql->prepareStatementForSqlObject($insertMultiple)->execute()->getGeneratedValue();
                }
                foreach (array_keys($values) as $pos => $key) {
                    if (!$context->dryRun) {
                        $sequenceValues[$pos] = $lastSequenceValue++;
                    } else {
                        $sequenceValues[$pos] = $this->localSequence->next();
                    }
                }

                foreach (array_keys($values) as $pos => $key) {
                    $resolvedValues[$key] = (int)$sequenceValues[$pos];
                }
            } finally {
                $connection->rollback();
            }
        }
        return $resolvedValues;
    }
}
