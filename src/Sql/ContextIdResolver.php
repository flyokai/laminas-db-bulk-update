<?php

namespace Flyokai\LaminasDbBulkUpdate\Sql;

/**
 * @template TContext of \Stringable|string|int
 */
interface ContextIdResolver
{
    /**
     * @throws IdentifierNotResolved when value does not exists and cannot be generated
     */
    public function resolve(ContextIdentifier $value): int|string|array|null;

    public function canResolve(ContextIdentifier $value): bool;

    /**
     * @param TContext $context
     */
    public function unresolved(mixed $value, mixed $context): ContextIdentifier;
}
