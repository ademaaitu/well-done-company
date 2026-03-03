# Earthquake Safety Test API (Laravel 8+)

Backend service for an interactive training module called **Earthquake Safety Test**.

## Includes

- Laravel 8+ compatible project metadata (`laravel/framework` on `^10.0`)
- MySQL connection settings in `.env.example`
- Docker Compose stack (`app`, `nginx`, `mysql`)
- REST API for modules, scenarios, and test submission/results
- Database migrations for modules, scenarios, answers, and results
- Seeder with example earthquake safety scenarios

## Quick start

Prerequisites: Docker Desktop and Composer available locally.

1. Install PHP dependencies:

```bash
composer install
cp .env.example .env
php artisan key:generate
```

2. Start containers:

```bash
docker compose up -d --build
```

3. Run migrations and seed:

```bash
docker compose exec app php artisan migrate --seed
```

API will be available on `http://localhost:8000`.

## API endpoints

- `GET /api/earthquake-safety-test/modules`
- `GET /api/earthquake-safety-test/modules/{module}/scenarios`
- `POST /api/earthquake-safety-test/modules/{module}/submit`

### Example submit payload

```json
{
  "participant_name": "Alex Doe",
  "participant_email": "alex@example.com",
  "answers": [
    {
      "scenario_id": 1,
      "selected_option": "Drop, Cover, and Hold On under sturdy furniture"
    },
    {
      "scenario_id": 2,
      "selected_option": "Stay in bed and protect your head with a pillow"
    }
  ]
}
```
