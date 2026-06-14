# KonnectFind Laravel API

This service owns multimodal product retrieval, marketplace data, transactional
commerce, analytics, offline evaluation, and operational readiness.

## Key Commands

```bash
php artisan migrate --force
php artisan search:index
php artisan search:evaluate --k=5 --json=../reports/search-evaluation.json
php artisan test
./vendor/bin/pint --test
```

## Core Design Properties

- Nigerian-context query expansion and hybrid text ranking.
- Reciprocal-rank fusion for combined text and image searches.
- PostgreSQL/pgvector production path with deterministic SQLite local mode.
- Atomic order creation and inventory deduction.
- Streaming import for the 250,000-product synthetic marketplace.
- Search, vendor, commerce, and operational analytics.

See the project-level [README](../README.md) and
[architecture documentation](../docs/ARCHITECTURE.md).
