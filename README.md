# My Domains

Full-stack domain management app. Backend: **Laravel 13** API (Sanctum token auth). Frontend: **React 19 SPA** (Vite + Tailwind CSS 4).

Features: Cloudflare zone sync, WHOIS expiry detection, DNS record CRUD, expiry alerts by email.

---

## Requirements

- PHP 8.3+, Composer
- Node 20+, npm
- SQLite (dev) or MySQL (production)

---

## Backend setup

```bash
cp .env.example .env
php artisan key:generate

# SQLite (dev):
touch database/database.sqlite

# MySQL (prod): fill DB_* in .env, then:
# php artisan migrate

php artisan migrate
php artisan serve          # runs on localhost:8000
```

### Key `.env` vars

| Variable | Description |
|---|---|
| `CLOUDFLARE_API_TOKEN` | Global fallback CF token (per-user token in Settings takes precedence) |
| `SANCTUM_STATEFUL_DOMAINS` | `localhost:5173,localhost:3000` for local dev |
| `MAIL_*` | SMTP config for expiry alert emails |

### Artisan commands

```bash
php artisan domains:sync              # sync zones + DNS from Cloudflare
php artisan domains:check-expiry      # send expiry alert emails (scheduled daily 08:00)
```

The scheduler runs `check-expiry` automatically. To enable it, add to crontab:
```
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

### Backend tests

```bash
php artisan test
```

---

## Frontend setup

```bash
cd frontend
cp .env.example .env        # set VITE_API_URL=http://localhost:8000/api
npm install
npm run dev                 # dev server on localhost:5173
```

### `.env` vars

| Variable | Default | Description |
|---|---|---|
| `VITE_API_URL` | `http://localhost:8000/api` | Backend API base URL |

### Frontend tests

```bash
cd frontend
npm run test
```

### Build for production

```bash
cd frontend
npm run build               # outputs to frontend/dist/
```

Serve `frontend/dist/` via any static host (Nginx, Cloudflare Pages, etc.). Point the backend `APP_URL` and CORS config to the production origin.

---

## Architecture

```
my-domains/
├── app/
│   ├── Console/Commands/       # domains:sync, domains:check-expiry
│   ├── Http/Controllers/Api/   # Auth, Domain, Dns, Setting
│   ├── Http/Requests/          # Form requests with DNS type validation
│   ├── Mail/                   # DomainExpiryAlert mailable
│   ├── Models/                 # User, Setting (encrypted CF token), Domain, DnsRecord
│   └── Services/               # CloudflareService, WhoisService
├── frontend/
│   └── src/
│       ├── api/client.js       # axios + auth interceptor
│       ├── contexts/           # AuthContext (token in localStorage)
│       ├── pages/              # Login, Dashboard, Domains, DomainDetail, Settings
│       └── utils/expiry.js     # color-coding helpers (< 7d red, < 30d orange, green)
└── routes/api.php              # REST endpoints under /api
```

## Auth flow

Login returns a Bearer token → stored in `localStorage` → added to every request via axios interceptor. 401 responses clear the token and redirect to `/login`.

## DNS cache sync

Every DNS write (create / update / delete) through the API updates the local `dns_records` table in the same request, so the UI never drifts from Cloudflare.
