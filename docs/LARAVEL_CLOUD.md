# Laravel Cloud Deployment

KonnectFind is a monorepo during development, but Laravel Cloud expects a
Laravel application at the repository root. The configured Cloud build script
promotes `backend/` to the root, builds `frontend/`, and copies the React SPA
into Laravel's `public/` directory. Laravel then serves the frontend and API
from one domain.

## 1. Push The Repository

Push the project to GitHub, GitLab, or Bitbucket. The root `composer.lock` is
included so Laravel Cloud recognizes the repository as a Laravel application.
The generated 132 MB `backend/database/data/seeder.json` is intentionally
ignored and removed from the runtime image.

## 2. Create The Cloud Application

1. In Laravel Cloud, choose **New application**.
2. Connect the repository and select the deployment branch.
3. Choose the region nearest the expected audience.
4. Use PHP 8.3 or 8.4 and Node 22.

## 3. Attach Serverless Postgres

Add a **Laravel Serverless Postgres** database to the environment. Laravel
Cloud automatically injects its connection credentials. Add this environment
variable:

```dotenv
DB_CONNECTION=pgsql
```

The database supports pgvector, which the embedding migration enables.
SQLite must not be used in production because Laravel Cloud filesystems are
ephemeral.

## 4. Environment Variables

Set:

```dotenv
APP_NAME=KonnectFind
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-environment.laravel.cloud

DB_CONNECTION=pgsql
DB_SSLMODE=require

LOG_CHANNEL=stack
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

SEARCH_TEXT_PROVIDER=local
SEARCH_TEXT_DIMENSIONS=128
SEARCH_IMAGE_PROVIDER=local
SEARCH_IMAGE_DIMENSIONS=64
```

Laravel Cloud generates and injects `APP_KEY` and attached resource
credentials. Do not commit secrets.

## 5. Build And Deploy Commands

Configure these in **Environment > Settings > Deployments**.

Build command:

```bash
./scripts/laravel-cloud-build.sh
```

Deploy command:

```bash
./scripts/laravel-cloud-deploy.sh
```

The build command installs optimized production dependencies, builds the React
SPA, and runs `php artisan optimize`. The deploy command only runs migrations;
it never reseeds or resets inventory.

## 6. First Deployment Bootstrap

After the first successful deployment, open the Cloud environment's
**Commands** tab and run these once:

```bash
php artisan db:seed --force
php artisan search:index --force
```

This creates the small curated demonstration catalogue and indexes it using the
deterministic providers. Do not run `db:seed` repeatedly against active
production inventory.

## 7. Validate

Open:

- `/` for the React marketplace.
- `/up` for Laravel's lightweight health check.
- `/api/health` for database, search-provider, catalogue, and order readiness.

Then test conversational search, product details, cart, checkout, analytics,
and a mobile viewport. Run the automated smoke test from your local machine:

```bash
./scripts/laravel-cloud-smoke-test.sh https://your-environment.laravel.cloud
```

Before the one-time catalogue bootstrap, use
`EXPECTED_PRODUCTS_MIN=0 ./scripts/laravel-cloud-smoke-test.sh ...`.

## Production Scale Notes

- Start with the curated catalogue for reliable assessment demonstrations.
- Importing 250,000 products should be performed as a controlled one-time Cloud
  command and followed by background embedding indexing.
- Use a dedicated worker or managed queue before indexing a large catalogue.
- Add Object Storage if product or vendor uploads are introduced; the Cloud
  application filesystem is ephemeral.
- Keep `SEARCH_IMAGE_PROVIDER=local` unless the Python image service is hosted
  separately at a reachable HTTPS URL.
