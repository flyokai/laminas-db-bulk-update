<?php

namespace Flyokai\LaminasDbBulkUpdate;

use Laminas\Db\Sql\Where;

class SelectConditionComposition implements SelectCondition
{
    public function __construct(public readonly array $conditions)
    {
    }

    public function apply(string $field, Where $where): void
    {
        foreach ($this->conditions as $condition) {
            $condition->apply($field, $where);
        }
    }
}
