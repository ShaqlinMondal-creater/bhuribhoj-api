# BhuriBhoj — Frontend ↔ Laravel API

## Stack
- **Frontend:** Next.js app in `bhuribhoj` (default `http://localhost:3000`)
- **Backend:** Laravel 12 + Sanctum in `bhuribhoj-api` (default `http://127.0.0.1:8000`)
- **Local DB:** XAMPP MySQL · database `bhuribhoj`

## How the frontend talks to Laravel
Browser calls **same-origin** paths under `/backend/...`.

Next.js rewrites them to Laravel:

```
/backend/auth/login  →  http://127.0.0.1:8000/api/auth/login
```

Configured in:
- `bhuribhoj/.env.local` → `NEXT_PUBLIC_API_BASE=/backend`, `LARAVEL_API_ORIGIN=http://127.0.0.1:8000`
- `bhuribhoj/next.config.ts` → rewrite rule

Client helpers:
- `src/lib/backendClient.ts` — fetch + Bearer token
- `src/lib/laravelAuth.ts` — login / me / logout / dashboard helpers
- `src/auth/authService.ts` — session uses Laravel Sanctum (not the old JSON login)

## Run locally

### 1) API
```bash
# XAMPP MySQL must be running
cd "Z:\Work Enviroment Code\bhuribhoj-api"
php artisan serve --host=127.0.0.1 --port=8000
```

### 2) Frontend
```bash
cd "Z:\Work Enviroment Code\bhuribhoj"
npm run dev
```

Open: http://localhost:3000

## Test logins (seeded)
| Role | Email | Password |
|------|-------|----------|
| Platform admin | `admin@bhuribhoj.local` | `Admin@123` |
| Mess president | `president@paulmess.local` | `President@123` |

## Auth flow
1. `POST /backend/auth/login` → `{ token, user }`
2. Token saved in `localStorage` key `bhuribhoj-api-token`
3. Session id saved in `bhuribhoj-demo-session`
4. Reload → `GET /backend/auth/me` with `Authorization: Bearer …`
5. Sign out → `POST /backend/auth/logout` + clear local storage

## API route style (Laravel)
Only **GET / POST / DELETE**. Grouped prefixes, named actions.

Examples:
- `GET /api/plans/fetch`
- `POST /api/auth/login`
- `GET /api/dashboard/fetch`
- `POST /api/members/create`
- `DELETE /api/managers/{id}/remove`

Full list: run in API folder
```bash
php artisan route:list --path=api
```

### Main groups
| Prefix | Purpose |
|--------|---------|
| `auth` | login, me, logout |
| `plans` | subscription plans |
| `subscriptions` | self-buy plan + create mess |
| `mess-invitations` | admin invite / accept |
| `dashboard` | mess + personal totals |
| `messes` | fetch / update mess |
| `members` | list / create members |
| `managers` | month managers (max 2) |
| `deposits` | fund deposits |
| `monthly-dues` | generate / pay / waive |
| `daily-meals` | lunch/dinner active+taken |
| `daily-menus` | dish list |
| `market-purchases` | bazaar buys |
| `market-duties` | who goes to market |
| `guest-meals` | guest / personal extras |
| `constant-expenses` | wifi, cleaning, etc. |
| `expenses` | expense ledger |
| `mess-rules` | punishable rules |
| `fines` | issue / pay / waive |

## Roles
DB enum on `users.role` (default `member`):
- **admin** — platform SaaS owner (all messes)
- **president** — full access inside one mess
- **manager** — month ops (max 2 per month)
- **member** — own details + limited actions

## Subscription plans
| Plan | Price (INR) |
|------|-------------|
| 1 Month | 99 |
| 3 Months | 279 |
| 1 Year | 999 |

Buy paths:
- Admin invite → `POST /api/mess-invitations/create` then accept token
- Self buy → `POST /api/subscriptions/purchase`

## What is connected vs still prototype
**Connected to Laravel now**
- Sign in / restore session / sign out
- Token auth against Sanctum

**Still using Next JSON prototype (`/api/data/...`)**
- Most in-app tables (meals UI, expenses UI, users CRUD screens, etc.)

Next frontend wiring step: point each service at `/backend/...` the same way auth uses `backendClient`.

## Example authenticated call (browser)
```js
fetch("/backend/dashboard/fetch", {
  headers: {
    Accept: "application/json",
    Authorization: `Bearer ${localStorage.getItem("bhuribhoj-api-token")}`,
  },
}).then((r) => r.json());
```

## Production notes (later)
1. Keep PHP **8.4** on cPanel for this API (or rebuild lock for 8.2)
2. Server `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `DB_HOST=127.0.0.1`
3. Deploy API with `git pull` → `composer install` → `php artisan migrate`
4. Point frontend `LARAVEL_API_ORIGIN` / rewrite to `https://bhuribhoj-api.liwaas.com`
