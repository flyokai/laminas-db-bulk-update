<?php

namespace Flyokai\LaminasDbBulkUpdate;

use Laminas\Db\Sql\Where;

class SelectNotInCondition implements SelectCondition
{
    public function __construct(public readonly array $values)
    {
    }

    public function apply(string $field, Where $where): void
    {
        if ($this->values) {
            $where->notIn($field, $this->values);
        }
    }
}
