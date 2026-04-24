<?php

namespace Flyokai\LaminasDbBulkUpdate\Sql;

use Laminas\Db\Sql;
use Laminas\Db\Sql\InsertMultiple;
use Flyokai\LaminasDbBulkUpdate\LocalSequence;
use Flyokai\LaminasDbDriverAsync\ConnectionPool;
use Flyokai\LaminasDbDriverAsync\CreateSqlTrait;

class TupleIdResolver
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
    public function resolveValues(TupleIdResolver\Context $context, array $values): array
    {
        if (empty($values)) {
            return [];
        }
        $resolvedValues = [];
        $tuplesPredicate = new Sql\Predicate\Predicate();
        $mainColumns = $tupleColumns = [];
        foreach ($context->tupleColumns as $tcIdx => $tcVal) {
            $tcCol = is_numeric($tcIdx) ? $tcVal : $tcIdx;
            $tupleColumns[] = $tcCol;
            if (is_numeric($tcIdx) || $tcVal == 'main') {
                $mainColumns[] = $tcCol;
            }
        }
        $fetchColumns = $mainColumns;
        foreach ($values as $key => $tuple) {
            if (!is_array($tuple)
                || !empty(array_diff($tupleColumns, array_keys($tuple)))
                || !empty(array_diff(array_keys($tuple), $tupleColumns))
            ) {
                throw new \ValueError('Invalid input value tuple: type or columns mismatch');
            }
            $tupleNest = $tuplesPredicate->or->nest();
            foreach ($context->tupleColumns as $tcIdx => $tcVal) {
                $tcCol = is_numeric($tcIdx) ? $tcVal : $tcIdx;
                $tcAlias = is_numeric($tcIdx) ? $tcCol : $tcVal . '.' . $tcIdx;
                $tupleNest->equalTo($tcAlias, $tuple[$tcCol]);
            }
        }

        $sql = $this->createSql();

        $fetchColumns[] = $context->targetField;

        if ($context->selectBuilder) {
            $select = ($context->selectBuilder)($sql, $context, $tuplesPredicate, $fetchColumns);
        } else {
            $select = $sql->select($context->tableName)
                ->columns($fetchColumns)
                ->where($tuplesPredicate)
                ->where($context->filter);
        }

        $__stmt = $sql->prepareStatementForSqlObject($select);
        /*var_dump('resolve ids');
        var_dump($__stmt->getSql());
        var_dump($__stmt->getParameterContainer()->getNamedArray());*/
        foreach ($__stmt->execute() as $row) {
            //var_dump($row);
            $fetchedTuple = [];
            foreach ($tupleColumns as $tcCol) {
                $fetchedTuple[$tcCol] = $row[$tcCol];
            }
            if ($context->cast) {
                $fetchedTuple = ($context->cast)($fetchedTuple);
            }
            foreach ($values as $key => $tuple) {
                if (empty(array_diff_assoc($fetchedTuple, $tuple))) {
                    $resolvedValues[$key] = (int)$row[$context->targetField];
                    unset($values[$key]);
                }
            }
        }

        if ($context->generate && $values) {
            $connection = $sql->getAdapter()->getDriver()->getConnection();
            $connection->beginTransaction();
            try {
                $incrementRow = $context->incrementRow;
                foreach ($tupleColumns as $tcCol) {
                    unset($incrementRow[$tcCol]);
                }

                $insertColumns = array_keys($incrementRow);
                foreach ($mainColumns as $tcCol) {
                    $insertColumns[] = $tcCol;
                }
                $baseRow = $incrementRow;
                $insertValues = [];
                foreach ($values as $key => $tuple) {
                    $row = $baseRow;
                    if ($context->initRow !== null) {
                        $row = ($context->initRow)($incrementRow);
                    }
                    foreach ($mainColumns as $tcCol) {
                        $row[$tcCol] = $tuple[$tcCol];
                    }
                    $insertValues[] = $row;
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
