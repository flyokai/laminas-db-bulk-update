<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Flyokai\LaminasDbBulkUpdate\Sql;

interface IdResolver
{
    /**
     * @throws IdentifierNotResolved when value does not exists and cannot be generated
     */
    public function resolve(Identifier $value): int|string|array|null;

    public function canResolve(Identifier $value): bool;

    /**
     * Creates a new resolvable id
     */
    public function unresolved($value): Identifier;
}
