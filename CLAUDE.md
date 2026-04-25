# flyokai/laminas-db-bulk-update

> User docs → [`README.md`](README.md) · Agent quick-ref → [`CLAUDE.md`](CLAUDE.md) · Agent deep dive → [`AGENTS.md`](AGENTS.md)

Bulk DB operations: INSERT ON DUPLICATE KEY UPDATE, identifier resolution, and range-based chunking for Laminas DB.

See [AGENTS.md](AGENTS.md) for detailed documentation.

## Quick Reference

- **InsertOnDuplicate**: MySQL upsert with batching, ID resolution, and FK check management
- **Conditions**: `SelectInCondition`, `SelectRangeCondition`, `SelectConditionComposition`
- **Range chunking**: `TableRangeConditionGenerator` partitions large tables by field ranges
- **ID resolvers**: `SingleTableResolver` (field lookup), `CombinedTableResolver` (composite keys), `PlainIdResolver` (async), `TupleIdResolver` (multi-field)
- **Identifiers**: `Unresolved` (awaits DB lookup) / `Resolved` (has value) — batch resolution on demand
- **Key rule**: Call `unresolved()` for all values first, then `resolve()` to batch the lookup query
