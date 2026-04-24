<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Flyokai\LaminasDbBulkUpdate\Sql;

/**
 * @template TInitial
 * @template TExtra
 */
interface Identifier
{
    /**
     * @return TInitial
     */
    public function initial(): mixed;

    /**
     * @param array $resolved
     * @param TExtra $extra
     * @return int|string|array
     */
    public function findValue(array $resolved, mixed $extra = null): int|string|array;
}
