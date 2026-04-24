<?php

namespace Flyokai\LaminasDbBulkUpdate\Sql;

/**
 * @template TInitial
 * @template TContext of \Stringable|string|int
 * @template TExtra
 * @extends Identifier<TInitial, TExtra>
 */
interface ContextIdentifier extends Identifier
{
    /**
     * @return TContext
     */
    public function context(): mixed;

    public function key(): string;
}
