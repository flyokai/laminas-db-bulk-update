# flyokai/laminas-db-bulk-update

> User docs → [`README.md`](README.md) · Agent quick-ref → [`CLAUDE.md`](CLAUDE.md) · Agent deep dive → [`AGENTS.md`](AGENTS.md)

> Efficient bulk INSERT and UPDATE operations for Laminas DB — INSERT ON DUPLICATE KEY UPDATE, identifier resolution, range-based chunking, and composable WHERE conditions.

Built for migrating, importing, and reindexing large datasets where ordinary row-by-row writes are too slow. Originally extracted from `EcomDev/sync-magento-2-migration`.

## Features

- **`InsertOnDuplicate`** — MySQL upsert with batching, ID resolution, and `FOREIGN_KEY_CHECKS` management
- **Composable conditions** — `SelectInCondition`, `SelectNotInCondition`, `SelectRangeCondition`, `SelectConditionComposition`
- **Range chunking** — `TableRangeConditionGenerator` partitions large tables by field ranges using actual MIN/MAX statistics
- **Identifier resolution** — `SingleTableResolver`, `CombinedTableResolver`, `PlainIdResolver`, `JoinIdResolver`, `TupleIdResolver`
- **Batching primitives** — `flushIfLimitReached()`, `executeIfNotEmpty()`

## Installation

```bash
composer require flyokai/laminas-db-bulk-update
```

## Quick start: upsert

```php
use Flyokai\LaminasDbBulkUpdate\InsertOnDuplicate;

$insert = InsertOnDuplicate::create('products', ['sku', 'name', 'price'])
    ->withRow('SKU-1', 'Foo', 9.99)
    ->withRow('SKU-2', 'Bar', 14.99)
    ->onDuplicate(['name', 'price']);            // columns to update on conflict

foreach ($source as $row) {
    $insert->withRow(...$row);
    $insert->flushIfLimitReached($sql, 500);     // auto-batch every 500 rows
}

$insert->executeIfNotEmpty($sql);                // flush leftover
```

`InsertOnDuplicate` extends `InsertMultiple` (from [`zend-db-sql-insertmultiple`](../zend-db-sql-insertmultiple/README.md)) and adds:

- `withIgnore(true)` — `INSERT IGNORE`
- `onDuplicate(array $cols)` — columns to update on duplicate-key
- `withResolver(IdResolver $r)` — resolves `Identifier` objects before execution
- `withNullOnUnresolved()` — set unresolved identifiers to NULL instead of throwing
- `withFormatted(string $field, string $format)` — printf-style formatting on column values
- Disables `FOREIGN_KEY_CHECKS` around execution and re-enables them after

## Identifier resolution

When you import rows referencing other entities by name (e.g. SKU, slug, code), use the resolver framework to batch the lookup:

```php
use Flyokai\LaminasDbBulkUpdate\Resolver\SingleTableResolver;

$resolver = new SingleTableResolver(
    adapter: $adapter,
    table:   'categories',
    sourceField: 'code',     // input we know
    targetField: 'category_id',  // what we want
);

$ident1 = $resolver->unresolved('electronics');   // queues lookup
$ident2 = $resolver->unresolved('books');
$resolver->resolve();                              // single SELECT IN(…)

$ident1->findValue();   // 7
$ident2->findValue();   // 12
```

The `Identifier` interface gives every value a deferred placeholder; concrete types are `ResolvedIdentifier` (already known) and `UnresolvedIdentifier` (needs lookup).

| Resolver | Use case |
|----------|----------|
| `SingleTableResolver` | one-to-one lookup in a single table; can auto-INSERT new rows on demand |
| `CombinedTableResolver` | composite key (e.g. `sku + website_id` → `product_id`) |
| `PlainIdResolver` | async-capable via `ConnectionPool`; supports version tracking via `SequenceInfo`; dry-run mode with `LocalSequence` IDs from 2 billion |
| `JoinIdResolver` | resolve via custom JOIN-based SELECT |
| `TupleIdResolver` | multi-field tuple matching |

## Range chunking

Iterate enormous tables in evenly-sized chunks based on actual data ranges:

```php
use Flyokai\LaminasDbBulkUpdate\Range\TableRangeConditionGeneratorFactory;

$factory  = TableRangeConditionGeneratorFactory::createFromAdapter($adapter, rangeSize: 500);
$gen      = $factory->createForTable('products', 'entity_id');

foreach ($gen->conditions() as $condition) {
    $select = $sql->select('products');
    $condition->apply($select);
    $rows = iterator_to_array($sql->prepareStatementForSqlObject($select)->execute());
    // … process up to ~500 rows …
}
```

`TableRangeConditionGenerator` queries MIN/MAX, then groups by `CEIL(field/rangeSize)*rangeSize` and yields one `SelectRangeCondition` per chunk.

## Conditions

```php
use Flyokai\LaminasDbBulkUpdate\Condition\{
    SelectInCondition,
    SelectNotInCondition,
    SelectRangeCondition,
    SelectConditionComposition,
};

$cond = new SelectConditionComposition([
    new SelectInCondition('status', ['active', 'pending']),
    new SelectRangeCondition('id', from: 1, to: 1000),
]);
$cond->apply($select);
```

- Empty `SelectInCondition` → `field = NULL AND field != NULL` (matches nothing)
- Empty `SelectNotInCondition` → no-op

## Gotchas

- **Batch resolution timing** — `resolve()` triggers the lookup query. Call `unresolved()` for *all* values first, *then* `resolve()` to batch.
- **WeakMap tracking** — only identifiers created via the resolver's `unresolved()` are tracked; foreign `Identifier` instances throw `IdentifierNotResolved`.
- **`FOREIGN_KEY_CHECKS` is disabled** around `InsertOnDuplicate` execution. MySQL-specific.
- **No built-in chunking on `InsertOnDuplicate`** — call `flushIfLimitReached()`. Default limit is 500.
- **Pipe in composite keys** — `CombinedTableResolver` joins keys with `|`. Values containing `|` may collide.
- **Dry-run still hits the DB** — rolls back the transaction after reading generated IDs. Causes temporary lock contention.
- **Empty `IN([])`** silently rewrites to `1=0`.
- **Range alignment** — boundaries are multiples of `rangeSize`, not actual row counts. Chunks may be unevenly sized.

## See also

- [`flyokai/zend-db-sql-insertmultiple`](../zend-db-sql-insertmultiple/README.md) — multi-row `INSERT VALUES` (the base for `InsertOnDuplicate`)
- [`flyokai/laminas-db`](../laminas-db/README.md) — Adapter / Sql builders
- [`flyokai/indexer`](../indexer/README.md) — uses this for save operations and full reindexing.

## License

MIT
