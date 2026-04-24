# flyokai/laminas-db-bulk-update

Efficient bulk INSERT and UPDATE operations for Laminas DB with identifier resolution, range-based chunking, and INSERT ON DUPLICATE KEY UPDATE support.

## Core Architecture

### Condition System

**SelectCondition** (interface) — applies WHERE conditions to Laminas Select queries:
- `SelectInCondition` — `field IN (values)`. Empty array creates impossible condition.
- `SelectNotInCondition` — `field NOT IN (values)`. Empty array is no-op.
- `SelectRangeCondition` — `field >= from AND field <= to`
- `SelectConditionComposition` — composite, applies multiple conditions

**SelectConditionGenerator** (interface) — lazy generator yielding `SelectCondition` objects:
- `ArrayConditionGenerator` — wraps pre-constructed conditions
- `TableRangeConditionGenerator` — intelligent range partitioning based on actual table data. Queries MIN/MAX, groups by `CEIL(field/rangeSize)*rangeSize`, yields `SelectRangeCondition` per chunk.

### Identifier Resolution

System for resolving symbolic identifiers (names, SKUs) to database-generated IDs.

**Identifier** (interface) — value that may need resolution:
- `initial()` — original value
- `findValue(array $resolved, $extra)` — lookup in resolved map

**Implementations:**
- `ResolvedIdentifier` — already has value
- `UnresolvedIdentifier` — awaits resolution, throws `IdentifierNotResolved` if missing

**ContextIdentifier** — extends with `context()` and `key()` for composite key scenarios.

### ID Resolvers

**IdResolver** (interface): `resolve(Identifier)`, `canResolve(Identifier)`, `unresolved($value)`

**SingleTableResolver** — maps source field → target field in one table:
- Batch lookups: collects unresolved, queries all at once on `resolve()`
- Optional auto-increment generation: INSERT ON DUPLICATE KEY, then read generated IDs
- `withFilter(array)` for additional WHERE conditions
- WeakMap tracking of created Identifiers

**CombinedTableResolver** — composite key resolution (e.g., `sku + website_id → product_id`):
- Delegates foreign key resolution to another IdResolver
- Composite key as `"sourceValue|foreignValue"` internally

**PlainIdResolver** — async-capable via `ConnectionPool`:
- Supports version tracking via `SequenceInfo`
- Dry-run mode with `LocalSequence` (IDs starting at 2 billion)

**JoinIdResolver** — custom JOIN-based SELECT for resolution

**TupleIdResolver** — multi-field tuple matching

### InsertOnDuplicate

Extends `InsertMultiple` with MySQL `INSERT ... ON DUPLICATE KEY UPDATE`:

```php
$insert = InsertOnDuplicate::create('table', ['col1', 'col2', 'col3'])
    ->withRow('a', 'b', 'c')
    ->withRow('d', 'e', 'f')
    ->onDuplicate(['col2', 'col3'])
    ->withResolver($idResolver);

$insert->flushIfLimitReached($sql, 500);
$insert->executeIfNotEmpty($sql);
```

- `withIgnore(true)` — INSERT IGNORE
- `onDuplicate(array)` — columns to update on duplicate key
- `withResolver(IdResolver)` — resolves Identifier objects before execution
- `withNullOnUnresolved()` — sets unresolved to NULL instead of throwing
- `withFormatted(field, format)` — printf formatting on column values
- `flushIfLimitReached($sql, $limit)` — auto-batch at row count
- Disables/enables `FOREIGN_KEY_CHECKS` around execution

### Range Stratification

`TableRangeConditionGeneratorFactory` — creates range generators:
```php
$factory = TableRangeConditionGeneratorFactory::createFromAdapter($adapter, 500);
$generator = $factory->createForTable('products', 'entity_id');
foreach ($generator->conditions() as $condition) {
    // Each $condition covers ~500 rows
}
```

## Gotchas

- **Batch resolution timing**: `resolve()` triggers the lookup query. Call `unresolved()` for all values first, then `resolve()` to batch.
- **WeakMap tracking**: Only Identifiers created via resolver's `unresolved()` are tracked. Foreign Identifiers throw `IdentifierNotResolved`.
- **FK checks disabled**: `InsertOnDuplicate` disables `FOREIGN_KEY_CHECKS` around execution. MySQL-specific.
- **No built-in chunking on InsertOnDuplicate**: Use `flushIfLimitReached()` to batch. Default limit parameter is 500.
- **Pipe in composite keys**: `CombinedTableResolver` uses `|` delimiter. Values containing `|` may collide.
- **DryRun still executes SQL**: Rolls back transaction after reading generated IDs. Causes temporary lock contention.
- **Empty IN condition**: `SelectInCondition` with empty array creates `field = NULL AND field != NULL` (matches nothing).
- **Range alignment**: Boundaries are multiples of `rangeSize`, not actual row counts. Chunks may be unevenly sized.
