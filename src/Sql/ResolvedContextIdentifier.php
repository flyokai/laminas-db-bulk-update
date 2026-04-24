<?php

namespace Flyokai\LaminasDbBulkUpdate\Sql;

/**
 * @template TInitial
 * @template TContext of \Stringable|string|int
 * @template TExtra
 * @implements ContextIdentifier<TInitial, TContext, TExtra>
 */
class ResolvedContextIdentifier implements ContextIdentifier
{
    /**
     * @param TInitial|null $initial
     */
    public function __construct(
        public readonly int|string|array $value,
        public readonly mixed            $context,
        public readonly string           $key,
        public readonly mixed            $initial = null,
        public readonly ?\Closure        $onDestroy = null,
    )
    {
    }

    /**
     * @return TInitial|null
     */
    public function initial(): mixed
    {
        return $this->initial;
    }

    /**
     * @return TContext
     */
    public function context(): mixed
    {
        return $this->context;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function findValue(array $resolved, mixed $extra = null): int|string|array
    {
        return $this->value;
    }

    public function __destruct()
    {
        if (isset($this->onDestroy)) ($this->onDestroy)($this->key);
    }

    public function __toString(): string
    {
        return $this->key();
    }
}
