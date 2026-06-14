# KonnectFind

KonnectFind is a multimodal e-commerce product discovery system designed around how Nigerian shoppers naturally search. Shoppers can use conversational text, an image, or both. Platform owners can inspect zero-result searches, abandonment, click-through rates, and catalogue discovery gaps.

KonnectFind is structured as a multi-vendor marketplace. Independent vendors own storefronts and inventory, while KonnectFind provides cross-marketplace discovery, ranking, and operator analytics.

## Masters Project Contribution

KonnectFind implements how multimodal, meaning-first retrieval can reduce the
vocabulary mismatch between Nigerian shoppers and conventional ecommerce
catalogues. Its contribution combines Nigerian-context conversational search,
image retrieval, reciprocal-rank fusion, an engaging mobile discovery feed, and
a transactionally safe multi-vendor marketplace.

Academic and demonstration documentation:

- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md): research objective, system architecture, retrieval pipeline, and design decisions
- [`docs/EVALUATION.md`](docs/EVALUATION.md): methodology, metrics, limitations, and recommended user study
- [`docs/DEMO.md`](docs/DEMO.md): concise defense-day demonstration guide
- [`docs/LARAVEL_CLOUD.md`](docs/LARAVEL_CLOUD.md): production deployment guide

The project contains:

- `backend/`: Laravel API, search pipeline, analytics, indexing, and evaluation
- `frontend/`: React shopper interface and analytics dashboard
- `image-service/`: FastAPI image embedding service
- `scripts/`: setup, development, and evaluation helpers

## Marketplace Architecture

- A `Vendor` represents an independent storefront with its own identity, location, verification status, rating, and fulfillment promise.
- Every `Product` belongs to one vendor and includes seller-owned inventory and SKU information.
- Marketplace-wide searches rank relevant products across active vendors.
- Shoppers can scope a search to a specific vendor storefront.
- Search results include vendor trust and fulfillment information.
- Marketplace analytics compare vendor appearances, clicks, click-through rates, inventory discovery, and category gaps.

Marketplace endpoints:

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/vendors` | List active marketplace storefronts |
| GET | `/api/vendors/{slug}` | View a vendor and its active products |
| GET | `/api/analytics/vendor-performance` | Compare vendor discovery performance |

Pass `vendor_id` to `POST /api/search` to search within one storefront.

## Quick Start

Local development uses SQLite and deterministic local embeddings. It requires no API key, Docker installation, or model download.

### Requirements

- PHP 8.3 or later
- Composer 2
- Node.js 18 or later
- npm 10 or later

Confirm the tools are installed:

```bash
php --version
composer --version
node --version
npm --version
```

### 1. Set Up

From the project root:

```bash
./scripts/setup.sh
```

The setup script:

1. Installs backend and frontend dependencies.
2. Creates `backend/.env` when missing.
3. Creates the local SQLite database.
4. Runs migrations and seeds the product catalogue.
5. Generates product text and image embeddings.
6. Builds the frontend.

To completely recreate the local database:

```bash
./scripts/setup.sh --fresh
```

`--fresh` deletes all existing local database records before seeding.

To set up the full generated marketplace catalogue instead of the small
development catalogue:

```bash
./scripts/generate-marketplace-data.sh
./scripts/setup.sh --large-catalogue
```

Other setup options:

```text
--skip-install   Skip Composer and npm installation
--skip-index     Skip product embedding generation
--skip-build     Skip the frontend production build
--large-catalogue Import database/data/seeder.json after setup
```

### 2. Run

Start the Laravel API and React frontend together:

```bash
./scripts/run-dev.sh
```

Open:

- Frontend: [http://localhost:5173](http://localhost:5173)
- Laravel API: [http://localhost:8000](http://localhost:8000)

Press `Ctrl+C` to stop both servers.

Override the default ports when needed:

```bash
API_PORT=8080 FRONTEND_PORT=3000 ./scripts/run-dev.sh
```

## Large Marketplace Dataset

The repository includes a deterministic generator for a Nigerian marketplace
dataset with 500 vendors and 250,000 products, exactly 500 products per vendor.
Its categories cover fashion, food, electronics, power, agriculture, transport,
beauty, home goods, sports, and other locally relevant shopping contexts.
Product image URLs use stable, category-relevant Unsplash images.

Generate and validate `backend/database/data/seeder.json`:

```bash
./scripts/generate-marketplace-data.sh
```

Override the defaults for a smaller test dataset or a different output path:

```bash
VENDORS=10 PRODUCTS_PER_VENDOR=20 OUTPUT_PATH=/tmp/seeder.json \
  ./scripts/generate-marketplace-data.sh
```

Import the generated catalogue into the configured Laravel database:

```bash
./scripts/import-marketplace-data.sh --fresh
```

`--fresh` removes existing marketplace records before the batched import. The
importer reads the JSON one record at a time, so it does not load the entire
250,000-product file into memory.

The underlying Artisan commands are:

```bash
cd backend
php artisan marketplace:generate-seeder
php artisan marketplace:import-seeder --fresh
php artisan db:seed --class=MarketplaceJsonSeeder
```

On Laravel Cloud, use the one-command production bootstrap instead of shipping
the 132 MB JSON file in every deployment:

```bash
php artisan marketplace:bootstrap-large --replace --force --batch=1000
```

It generates the file in temporary storage, stream-imports and verifies the
catalogue, then removes the file. Do not place this command in the normal deploy
script. After the initial bootstrap, omit `--replace --force` for a
non-destructive deterministic upsert.

The large importer deliberately does not generate search embeddings. Indexing
250,000 products is a separate, resource-intensive operation:

```bash
cd backend
php artisan search:index --force
```

Use PostgreSQL with pgvector for the full catalogue. SQLite and the small seeded
catalogue remain the recommended local development path.

## Search Evaluation

KonnectFind includes an offline relevance set at:

```text
backend/database/evaluation/search_queries.json
```

It contains conversational Nigerian-context queries and the products considered relevant for each query.

### Run Default Evaluation

```bash
./scripts/evaluate.sh
```

This ensures unindexed products are indexed, evaluates the top five results, and prints:

- **Precision@K**: proportion of the first `K` results that are relevant
- **Recall@K**: proportion of all relevant products retrieved in the first `K`
- **MRR**: mean reciprocal rank of the first relevant product
- **nDCG@K**: ranking quality that rewards relevant products appearing earlier

Evaluate a different result depth:

```bash
./scripts/evaluate.sh --k 10
```

Force every product embedding to be regenerated first:

```bash
./scripts/evaluate.sh --reindex
```

Run retrieval evaluation plus backend tests, formatting checks, frontend build, and lint:

```bash
./scripts/evaluate.sh --verify
```

### Custom Evaluation Dataset

Create a JSON file using this structure:

```json
[
  {
    "query": "durable school bag for my pikin",
    "relevant": [
      "Durable Kids School Backpack"
    ]
  },
  {
    "query": "heavy-duty power solution for home",
    "relevant": [
      "2000VA Smart Inverter",
      "Portable Solar Generator"
    ]
  }
]
```

Product names must exactly match products in the catalogue.

Run it with:

```bash
./scripts/evaluate.sh --file /absolute/path/to/queries.json --k 10
```

The underlying Artisan commands can also be run directly:

```bash
cd backend
php artisan search:index
php artisan search:index --force
php artisan search:evaluate --k=5
php artisan search:evaluate --file=/absolute/path/to/queries.json --k=10
```

## Automated Tests

Backend tests:

```bash
cd backend
php artisan test
```

Backend formatting:

```bash
cd backend
./vendor/bin/pint --test
```

Frontend build and lint:

```bash
cd frontend
npm run build
npm run lint
```

The convenient full-project check is:

```bash
./scripts/verify.sh
```

This also writes machine-readable evidence to `reports/`.

## Production Embeddings And Pgvector

Local mode stores embedding arrays in SQLite and calculates similarity in Laravel. The production path uses PostgreSQL with pgvector HNSW indexes, an OpenAI-compatible text embedding provider, and the FastAPI image service.

Start PostgreSQL/pgvector and the image service:

```bash
docker compose up -d
```

Configure `backend/.env` before migrating:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=konnectfind
DB_USERNAME=konnectfind
DB_PASSWORD=konnectfind

SEARCH_TEXT_PROVIDER=openai
SEARCH_TEXT_DIMENSIONS=1536
OPENAI_API_KEY=your-key
OPENAI_EMBEDDING_MODEL=text-embedding-3-small

SEARCH_IMAGE_PROVIDER=service
SEARCH_IMAGE_DIMENSIONS=64
IMAGE_EMBEDDING_SERVICE_URL=http://127.0.0.1:8001
```

Then rebuild and index the catalogue:

```bash
cd backend
php artisan migrate:fresh --seed
php artisan search:index --force
```

Vector dimensions are fixed when the pgvector migration runs. Configure the dimensions before migrating.

OpenAI's `text-embedding-3-small` defaults to 1,536 dimensions. KonnectFind
explicitly sends `SEARCH_TEXT_DIMENSIONS` to the embeddings API, keeping the API
response and pgvector column aligned.

The image service defaults to a lightweight 64-dimensional visual representation. To use OpenCLIP, install `image-service/requirements-openclip.txt`, set `IMAGE_MODEL=openclip`, and set `SEARCH_IMAGE_DIMENSIONS` to the chosen model's output dimension before migration.

## API Endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| POST | `/api/search` | Text, image, or combined search |
| POST | `/api/search/click` | Record a product click |
| GET | `/api/products/{id}` | Retrieve active product details |
| POST | `/api/orders` | Place a validated multi-vendor order |
| GET | `/api/orders/{reference}` | Retrieve an order confirmation |
| GET | `/api/health` | Operational readiness and safe system metadata |
| GET | `/api/analytics/summary` | Core discovery metrics |
| GET | `/api/analytics/zero-results` | Latest failed queries |
| GET | `/api/analytics/abandonment-rate` | Search abandonment |
| GET | `/api/analytics/category-gaps` | Least-discovered categories |
| GET | `/api/analytics/search-volume` | Search volume by day |
| GET | `/api/analytics/commerce-summary` | Orders, GMV, AOV, sold items, and inventory |

## Troubleshooting

**A port is already in use**

Stop the existing process or choose other ports:

```bash
API_PORT=8080 FRONTEND_PORT=3000 ./scripts/run-dev.sh
```

**Products changed but search results still use old embeddings**

```bash
cd backend
php artisan search:index --force
```

**The local database needs to be reset**

```bash
./scripts/setup.sh --fresh
```

**Dependencies are already installed and only the database needs preparing**

```bash
./scripts/setup.sh --skip-install --skip-build
```
# konnect-find
