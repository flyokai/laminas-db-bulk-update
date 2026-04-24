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
 * @implements Identifier<TInitial, TExtra>
 */
class UnresolvedIdentifier implements Identifier
{
    /**
     * @var TInitial
     */
    private mixed $initial;

    /**
     * @param TInitial|null $initial
     */
    public function __construct(
        public readonly string $value,
        public readonly \Closure $onDestroy,
        mixed $initial = null,
    )
    {
        $this->initial = $initial ?? $value;
    }

    /**
     * @return TInitial
     */
    public function initial(): mixed
    {
        return $this->initial;
    }

    public function findValue(array $resolved, mixed $extra = null): int|string
    {
        if (!isset($resolved[$this->value])) {
            throw new IdentifierNotResolved($this->value);
        }

        return $resolved[$this->value];
    }

    public function __destruct()
    {
        ($this->onDestroy)($this->value);
    }

    public function __toString(): string
    {
        return $this->initial;
    }
}
