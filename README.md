# LoreSpinner

AI-powered interactive fiction platform that transforms screenplay scripts into playable, branching narrative experiences with dynamic narration and player-driven choices.

## About

This is the **R&D** version of LoreSpinner — the research and development branch used to test ideas, prototype features, and validate pipeline changes before they move to Stage or production.

**Repository:** [blitzdevteam/lore-spinner-rnd](https://github.com/blitzdevteam/lore-spinner-rnd)

## Stack

- **Backend:** Laravel 12 (PHP 8.4)
- **Frontend:** Vue 3, Inertia.js v2, Tailwind CSS 4, PrimeVue 4
- **AI:** Laravel AI + OpenAI GPT-5.2
- **Admin:** Filament v4
- **Database:** PostgreSQL 17
- **Infrastructure:** Redis, MinIO (S3), Laravel Sail (Docker)

## Quick Start

```bash
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm ci && ./vendor/bin/sail npm run dev
```

## Deployment

### 1. Install dependencies and build

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
```

### 2. Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` with your server's database, Redis, storage, and API credentials.

### 3. Restore the database

Extract the deployment data and load the database dump. This includes all stories, chapters, events, and AI-generated content — no API calls needed.

```bash
tar -xzf deploy-data.tar.gz
php artisan migrate
psql -U $DB_USERNAME -h $DB_HOST -d $DB_DATABASE < database/dump.sql
```

### 4. Cache and finalize

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan storage:link
```

### 5. Run the expansion seeder (new creators + stories)

This seeds new creators, reassigns existing stories, and onboards new stories through the full AI extraction pipeline. It auto-converts PDFs to TXT, then runs chapter extraction, event extraction, system prompt generation, and opening narration — all synchronously with retry logic. Safe to re-run (idempotent).

```bash
php artisan db:seed --class=ExpansionSeeder
php artisan images:generate-missing --stories --chapters --creators
```

### 6. Start the queue worker

```bash
php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
```

## Documentation

Full setup, deployment, and development guides available in [DOCUMENTATION.md](DOCUMENTATION.md).
