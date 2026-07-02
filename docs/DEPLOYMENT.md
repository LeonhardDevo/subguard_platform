# SubGuard Platform — Deployment Guide

## Prerequisites

- Docker & Docker Compose
- PostgreSQL 16+ (or use Docker)
- Redis 7+ (or use Docker)
- Stripe account with API keys
- Domain name with SSL certificate (for production)

---

## Local Development

### 1. Clone and setup

```bash
git clone <repo-url> subguard-platform
cd subguard-platform
cp .env.example .env
```

### 2. Configure environment

Edit `.env` with your values:
- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`
- `JWT_SECRET` (auto-generated)

### 3. Start with Docker Compose

```bash
docker-compose up -d
docker-compose exec app php artisan key:generate
docker-compose exec app php artisan jwt:secret
docker-compose exec app php artisan migrate
```

### 4. Access the application

- **API:** http://localhost:8000/api
- **Health:** http://localhost:8000/api/health

---

## Production Deployment

### 1. Build Docker images

```bash
docker build -f docker/app/Dockerfile -t subguard-app:latest .
docker build -f docker/nginx/Dockerfile -t subguard-nginx:latest .
```

### 2. Environment configuration

Set the following production environment variables:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
LOG_CHANNEL=stack
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
```

### 3. Run migrations

```bash
php artisan migrate --force
```

### 4. Optimize

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

### 5. Queue workers

Ensure queue workers are running:
```bash
php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
```

### 6. Scheduler

Add cron entry:
```
* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1
```

### 7. Stripe Webhooks

Configure in Stripe Dashboard:
- URL: `https://your-domain.com/api/webhooks/stripe`
- Events: `invoice.paid`, `invoice.payment_failed`, `customer.subscription.deleted`, `customer.subscription.updated`

---

## Architecture

```
┌─────────┐     ┌─────────┐     ┌──────────┐
│  Nginx  │────▶│ PHP-FPM │────▶│ Postgres │
└─────────┘     └─────────┘     └──────────┘
                     │
                     ▼
                ┌─────────┐
                │  Redis  │
                └─────────┘
                     │
             ┌───────┴───────┐
             ▼               ▼
      ┌────────────┐  ┌───────────┐
      │Queue Worker│  │ Scheduler │
      └────────────┘  └───────────┘
```

## Monitoring

- **Health check:** `GET /api/health`
- **Sentry:** Configure `SENTRY_LARAVEL_DSN` for error tracking
- **Logs:** `storage/logs/laravel.log`
