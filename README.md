# Earthquake Safety Test (Laravel + React)

Interactive training platform for earthquake preparedness with branching scenarios, personalized recommendations, and stored user progress.

## Project structure

- `backend` — Laravel API
- `frontend` — React 18 + Vite SPA
- `docker` — Nginx config
- `openapi.yaml` — OpenAPI 3.0 API specification

## Features delivered

### Backend (Laravel)

- Models:
  - `Module (id, name, description)`
  - `Scenario (id, module_id, branching_id, stress_context, question, options, correct_answer, wrong_explanation, branching_logic)`
  - `UserAnswer (id, user_name, scenario_id, selected_option, score, response_time_ms, retries, stress_context)`
  - `Result (id, user_name, module_id, total_score, accuracy_score, reaction_risk_index, stress_response_score, overall_preparedness_percent, risk_category, recommendation)`
- API endpoints:
  - `POST /api/session/start`
  - `POST /api/session/events`
  - `GET /api/resources`
  - `GET /api/modules`
  - `GET /api/modules/{id}/scenarios`
  - `GET /api/modules/{id}/scenarios/next`
  - `POST /api/modules/{id}/submit`
  - `GET /api/results/{user_name}`
  - `GET /api/admin/analytics-summary`
  - `GET /api/admin/risk-distribution`
  - `GET /api/prototype/config`
  - `POST /api/prototype/submit`
  - `GET /api/prototype/analytics`
- Branching logic and hints based on selected options
- Wrong-answer micro-learning explanation flow
- Dynamic scenario routing for stress contexts (`bus`, `mall`, `office`)
- Recommendation levels: `Требуется улучшение`, `Хорошо`, `Отлично`
- User progress and full session JSON persisted in DB (`results.progress`, `results.session_json`, `user_answers`)
- CORS configured for frontend dev URL `http://localhost:5173`
- Bearer token middleware for protected endpoints (`submit`, `results`)

### Frontend (React 18 + Vite)

SPA with 12 interactive screens:

1. Welcome + user name
2. Module selection (home / mall / transport)
3. Scenario 1
4. Stress case
5. Scenario 2
6. Scenario 3
7. Adaptive hints (branching)
8. Final check
9. Final result
10. Checklist + resources
11. Restart / back to modules
12. Share / QR + user history

Additional UX:

- Animated progress bar and smooth screen transitions
- Previous / Next / Submit navigation
- Correct / incorrect answer animations
- Stress-screen shake animation
- Color-coded risk result (`Low` / `Moderate` / `High`)
- Mobile-first responsive layout
- Swipe cards for checklist
- API error handling and personalized output

### Docker services

- `app` (PHP + Laravel)
- `nginx` (host `http://localhost:8000`)
- `mysql` (8.0 with volume)
- `frontend` (`http://localhost:5173`)
- `swagger-ui` (`http://localhost:8081`)

## Environment files

- Backend: `backend/.env`, `backend/.env.example`
  - `DB_CONNECTION=mysql`
  - `DB_HOST=mysql`
  - `DB_DATABASE=earthquake`
  - `DB_USERNAME=root`
  - `DB_PASSWORD=root`
  - `API_BEARER_TOKEN=dev-earthquake-token`

- Frontend: `frontend/.env`, `frontend/.env.example`
  - `VITE_API_BASE_URL=http://localhost:8000`
  - `VITE_API_TOKEN=dev-earthquake-token`

## Local run

```bash
docker compose up -d --build
docker compose exec app php artisan migrate:fresh --seed --force
```

Open:

- Backend API: `http://localhost:8000/api/modules`
- Frontend: `http://localhost:5173`
- Swagger UI: `http://localhost:8081`

## OpenAPI

- Spec file: `openapi.yaml`
- Includes all endpoints, schemas, examples, tags, and bearer auth.

## Campaign concept package

- Full strategic + content + fast-launch document: [docs/campaign-roadmap.md](docs/campaign-roadmap.md)
- TRC prototype UX/backend architecture: [docs/prototype-trc-architecture.md](docs/prototype-trc-architecture.md)

## MVP plan

### 7 days

- Core interactive test with branching answers
- Checklist screen
- Promo poster/landing QR link to module

### 30 days

- Full multi-module training content
- Short video explainers per scenario
- Social publishing pack (clips/cards)
- Analytics dashboard (completion, score bands, weak points)
