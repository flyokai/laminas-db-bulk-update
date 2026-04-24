<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Flyokai\LaminasDbBulkUpdate;

use Laminas\Db\Sql\Where;

class SelectInCondition implements SelectCondition
{
    public function __construct(public readonly array $values)
    {
    }

    public function apply(string $field, Where $where): void
    {
        if (!$this->values) {
            $where->equalTo($field, null)
                ->notEqualTo($field, null);

            return;
        }

        $where->in($field, $this->values);
    }
}
