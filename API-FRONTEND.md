# BhuriBhoj — Frontend ↔ Laravel API

Source of truth for paths: `routes/api.php`.  
Postman: `postman/BhuriBhoj.postman_collection.json`.

## Stack

- **Frontend:** Next.js in `bhuribhoj` (default `http://localhost:3000`)
- **Backend:** Laravel + Sanctum in `bhuribhoj-api`
- **Live API:** `https://bhuribhoj-api.liwaas.com`

## How the frontend talks to Laravel

Browser calls **same-origin** `/backend/...`. Next rewrites to Laravel `/api/...`:

```
/backend/public/auth/login  →  {LARAVEL_API_ORIGIN}/api/public/auth/login
```

Config:

- `bhuribhoj/.env.local` → `NEXT_PUBLIC_API_BASE=/backend`, `LARAVEL_API_ORIGIN=…`
- `bhuribhoj/next.config.ts` → rewrite `/backend/:path*` → `{origin}/api/:path*`
- Client: `src/lib/api/client.ts` + services under `src/services/`
- Role door helper: `src/lib/api/door.ts` → `/admin` | `/president` | `/manager` | `/member`

Do **not** set `NEXT_PUBLIC_API_BASE` to an absolute API URL in the browser (CORS).

## Run locally

```bash
# API (use the DB your .env points at — production is MySQL)
cd "Z:\Work Enviroment Code\bhuribhoj-api"
php artisan serve --host=127.0.0.1 --port=8000

# FE (proxies /backend to LARAVEL_API_ORIGIN — often the live API)
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

1. `POST /backend/public/auth/login` → `{ token, user, seat }`
2. Bearer token in `localStorage` key `bhuribhoj-api-token`
3. Next cookies `bb_token` / `bb_role` for route gates (synced again from `/me`)
4. `GET /backend/authorize/auth/me` with `Authorization: Bearer …`
5. Sign out → `POST /backend/authorize/auth/logout` + clear storage/cookies

Inactive users: login blocked; authenticated routes use `active` middleware and revoke the token.

## Route style

Only **GET / POST / DELETE**. First path segment is who may call:

| Prefix | Who |
|--------|-----|
| `/api/public/…` | No login |
| `/api/authorize/…` | Any signed-in **active** user |
| `/api/admin/…` | `role:admin` |
| `/api/president/…` | `role:president` |
| `/api/manager/…` | `role:manager` |
| `/api/member/…` | `role:member` |

House routes also need `mess.ready` (active subscription + mess where required).  
`seat.next`: `plans` → `mess_setup` → `dashboard` (or `admin`).

List current routes:

```bash
php artisan route:list --path=api
```

### Public

| Method | Path |
|--------|------|
| POST | `/api/public/auth/login` |
| POST | `/api/public/auth/register/email` |
| POST | `/api/public/auth/register/preview` |
| POST | `/api/public/auth/register/complete` |
| POST | `/api/public/auth/google` |
| POST | `/api/public/plans/fetch` *(active plans only)* |
| POST | `/api/public/settings/fetch` |
| POST | `/api/public/contact` *(throttle 8/min; needs `public_contact_email`)* |

### Authorize

| Method | Path |
|--------|------|
| GET | `/api/authorize/auth/me` |
| POST | `/api/authorize/auth/profile` |
| POST | `/api/authorize/auth/logout` |

### Admin (platform)

| Area | Paths |
|------|--------|
| Plans | `POST …/admin/plans/fetch\|create\|update`, `DELETE …/admin/plans/{id}/delete` |
| Payments | `POST …/admin/payments/fetch`, `DELETE …/admin/payments/{id}/delete` |
| Subscriptions | `POST …/admin/subscriptions/fetch`, `DELETE …/admin/subscriptions/{id}/delete` |
| Users | `POST …/admin/users/fetch\|update`, `DELETE …/admin/users/{id}/delete` |
| Mess open | `POST …/admin/messes/open` |
| Coupons | `POST …/admin/coupons/fetch\|create\|update`, `DELETE …/admin/coupons/{id}/delete` |
| Configs | `POST …/admin/configs/fetch\|save`, `DELETE …/admin/configs/{id}/delete` |
| Settings (theme) | `POST …/admin/settings/fetch\|save` |
| Logs | `POST …/admin/logs/fetch` |
| Contact support | `POST …/admin/contact-support/fetch\|reply\|status` |

Admin also has the full house set under `/api/admin/…` when `mess.ready` (pick mess via admin UI / `mess_id` as the API expects).

### President

| Method | Path | Notes |
|--------|------|--------|
| POST | `/api/president/subscriptions/order` | Razorpay order or free coupon |
| POST | `/api/president/subscriptions/fail` | Log failed checkout |
| POST | `/api/president/subscriptions/verify` | Status must be **captured** |
| POST | `/api/president/subscriptions/fetch` | |
| POST | `/api/president/messes/open` | After pay, before house |

Then house routes under `/api/president/…` (mess.ready).

### Manager / member (house subset)

| Resource | manager | member |
|----------|---------|--------|
| dashboard | yes | yes |
| members fetch | yes | — |
| daily-meals | yes (fetch/save/delete) | fetch/save |
| meal-aways | yes | yes |
| mess-meals | yes | fetch only |
| daily-menus | yes | fetch only |
| expenses | yes | fetch only |
| messes fetch/update | yes | fetch |
| bills fetch | yes | yes |

### House resources (role door)

Replace `{door}` with `admin` | `president` | `manager` | `member` when that role exposes the route:

- `messes` — fetch / update / assign / delete (role-dependent)
- `members` — fetch / create / update
- `deposits` — fetch / create / update / delete
- `dashboard/fetch`
- `daily-meals`, `meal-aways`, `mess-meals`, `daily-menus`, `expenses`
- `guest-meal-setup` — admin/president
- `bills/fetch` — president/manager/member

## Public site configs

Admin → Configs keys (blank/delete hides optional ones):

- `public_contact_email`
- `public_site_name`, `public_site_bengali`, `public_site_blurb`, `public_site_copyright`
- `public_legal_privacy`, `public_legal_terms`
- `public_credit_name`, `public_credit_url`

Theme stays under Admin → Settings (`ui.theme`).

## Subscription buy path (current)

1. Register / login as president  
2. `POST /api/president/subscriptions/order` (+ optional coupon)  
3. Razorpay checkout → `POST …/subscriptions/verify` (or free coupon skips pay)  
4. `POST /api/president/messes/open`  
5. House dashboard

## Removed / never call (dead docs)

These are **not** in `routes/api.php`:

- `/api/subscriptions/purchase`
- `/api/mess-invitations/*`
- `/api/managers/*`
- `/api/monthly-dues/*`
- `/api/market-purchases/*`, `/api/market-duties/*`
- `/api/guest-meals/*` (use `guest-meal-setup`)
- `/api/constant-expenses/*`
- `/api/mess-rules/*`, `/api/fines/*`
- Flat `/api/auth/*` or `/api/plans/fetch` without `public` / `authorize` / role prefix

## Example authenticated call (browser via proxy)

```js
fetch("/backend/authorize/auth/me", {
  headers: {
    Accept: "application/json",
    Authorization: `Bearer ${localStorage.getItem("bhuribhoj-api-token")}`,
  },
}).then((r) => r.json());
```

## Production notes

1. `APP_ENV=production`, `APP_DEBUG=false`, strong `APP_KEY`
2. MySQL + `php artisan migrate` + `php artisan storage:link`
3. SMTP mailer (not `log`) for register + contact
4. Set `public_contact_email` in Configs for the contact form
5. FE: set `LARAVEL_API_ORIGIN` explicitly; keep `NEXT_PUBLIC_API_BASE=/backend`
6. `SANCTUM_TOKEN_EXPIRATION` minutes (default 43200 = 30 days)
