<?php

namespace Flyokai\LaminasDbBulkUpdate\Sql;

/**
 * @template TInitial
 * @template TContext of \Stringable|string|int
 * @template TExtra
 * @implements ContextIdentifier<TInitial, TContext, TExtra>
 */
class UnresolvedContextIdentifier implements ContextIdentifier
{
    /**
     * @var TInitial
     */
    private mixed $initial;

    /**
     * @param TInitial|null $initial
     */
    public function __construct(
        public readonly string   $value,
        public readonly mixed    $context,
        public readonly string   $key,
        public readonly \Closure $onDestroy,
        mixed                    $initial = null,
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

    public function findValue(array $resolved, mixed $extra = null): int|string
    {
        if (!isset($resolved[$this->key])) {
            throw new IdentifierNotResolved($this->key);
        }

        return $resolved[$this->key];
    }

    public function __destruct()
    {
        ($this->onDestroy)($this->key);
    }

    public function __toString(): string
    {
        return $this->key();
    }
}
