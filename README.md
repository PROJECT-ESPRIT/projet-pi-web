# ArtConnect — Symfony Web Application

> Third-year integrated project (PIDEV 3A) — Esprit School of Engineering.
> Web counterpart of [ArtConnect JavaFX](https://github.com/Marouen-nouaigui/PI_java_vf).

ArtConnect is a charity ecosystem that connects donors, charitable causes, and cultural events through **two synchronized applications** sharing a single MariaDB database. This repository contains the **Symfony 6.4 web app** — the public-facing platform for donors, volunteers, and event attendees.

The JavaFX desktop twin handles back-office work (charity moderation, event staff, on-site donation collection). Both apps read/write the same `artconnect` database, so every change is visible across both clients instantly.

## Topics

`symfony` · `php` · `doctrine` · `mariadb` · `mysql` · `twig` · `bootstrap` · `chart-js` · `stripe-checkout` · `gemini-api` · `web-app` · `charity-platform` · `pidev` · `esprit`

## Keywords

charity donation platform · event reservation · QR code ticketing · payment gateway · AI image validation · cross-app synchronization · Doctrine ↔ JPA alignment · admin dashboard · forum · e-commerce

## Modules

| Module | Description | Routes |
|---|---|---|
| **User & Auth** | Signup, login, email confirmation, password reset, role-based access (ROLE_ADMIN / ROLE_USER) | `/login`, `/register`, `/profile` |
| **Charities** | Browse, favorite, donate, propose new causes; admin approve/reject/hide | `/charities`, `/admin/charities` |
| **Donations** | Money (Stripe) or item donations; AI image verification; per-donation status | `/donation`, `/donation/admin`, `/donation/my-donations` |
| **Events** | List/detail, seat layout, ticket reservation, QR-code delivery via email | `/evenement`, `/reservation` |
| **Forum** | Topics + replies, monthly post stats | `/forum`, `/forum/reponse` |
| **E-commerce** | Product catalog, cart, commande (order) with line items | `/produit`, `/commande` |
| **Statistics** | Real-time KPIs and charts (charities, donations, events) | `/charities/stats`, `/admin/statistiques` |

## Architecture

```
┌─────────────────────────┐         ┌─────────────────────────┐
│   Symfony web app       │         │   JavaFX desktop app    │
│   (this repository)     │         │   (PI_java_vf repo)     │
│                         │         │                         │
│   Doctrine ORM (PHP)    │         │   JPA / JDBC (Java)     │
└────────────┬────────────┘         └────────────┬────────────┘
             │                                   │
             └───────────────┬───────────────────┘
                             ▼
                    ┌─────────────────┐
                    │ MariaDB 10.11   │
                    │ "artconnect" DB │
                    │ canonical schema│
                    └─────────────────┘
```

No HTTP API between the apps — the **database itself is the integration layer**. Both ORMs converge on a single canonical schema; Symfony entities mirror the JavaFX models 1:1.

## Requirements

- PHP 8.1+ (tested on 8.4)
- Composer 2
- MariaDB 10.11+ or MySQL 8.x
- Symfony CLI (optional but recommended)
- A free **Google Gemini API key** for donation-image validation — get one in 2 minutes at <https://aistudio.google.com/apikey>

## Setup (5 minutes)

```bash
# 1. install PHP dependencies
composer install

# 2. create the shared database
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS artconnect \
   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 3. create your local secrets file (gitignored, never committed)
cat > .env.local <<'EOF'
DATABASE_URL="mysql://root:YOUR_DB_PASSWORD@127.0.0.1:3306/artconnect?serverVersion=mariadb-10.11.16&charset=utf8mb4"
GEMINI_API_KEY=YOUR_GEMINI_KEY_FROM_aistudio.google.com
# optional, only if you want payments / emails:
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
MAILER_DSN=smtp://user:pass@host:port
MAILER_FROM=your-email@example.com
EOF

# 4. apply schema migrations (idempotent)
php bin/console doctrine:migrations:migrate -n

# 5. start the dev server
symfony server:start -d
# OR: php -S 127.0.0.1:8000 -t public

# 6. open the app
xdg-open http://127.0.0.1:8000
```

**Default admin** (seeded by the JavaFX side or `app:seed`): `admin@art.com` / `123456`.

## Required free API keys

| Service | What it does | Where to get | Cost |
|---|---|---|---|
| **Google Gemini** | Validates donation images (e.g. confirms a "clothes" donation actually shows clothes) | <https://aistudio.google.com/apikey> | Free tier, no card |
| Stripe (optional) | Payments for money donations + reservations | <https://dashboard.stripe.com/test/apikeys> | Free in test mode |
| SMTP (optional) | Email confirmations + ticket delivery | Gmail app password / Brevo / Mailtrap | Free tiers |

If you skip Gemini, donations still go through (validation falls through with `skipped: true`).

## Quick smoke test

1. Open <http://127.0.0.1:8000>
2. Log in as `admin@art.com` / `123456`
3. Browse `/charities`, click the heart to favorite one, click **Donner** to donate
4. Open phpMyAdmin or `mysql artconnect` → see your write reflected immediately in the `donation` / `favorite_charity` table
5. Launch the JavaFX app → the same row appears in its UI

## Project structure

```
src/
├── Controller/        # HTTP routes (PHP attributes)
├── Entity/            # Doctrine ORM mappings — 1:1 with the JavaFX schema
├── Repository/        # DB queries
├── Service/           # Domain services (Stripe, Gemini, Email, etc.)
└── Form/              # Symfony form types
templates/             # Twig views
migrations/            # Doctrine migrations (Symfony-only schema deltas; idempotent)
public/index.php       # Front controller
config/                # Bundles, routing, security, services
```

## Tech stack

| Layer | Technology |
|---|---|
| Framework | Symfony 6.4 LTS |
| Language | PHP 8.1+ |
| ORM | Doctrine 3 |
| Templating | Twig 3 |
| UI | Bootstrap 5 + custom AC palette |
| Charts | Chart.js |
| Payments | Stripe PHP SDK |
| AI | Google Gemini 2.5 Flash |
| Mailer | Symfony Mailer |
| Database | MariaDB 10.11 / MySQL 8 |

## Cross-app integration

Verified end-to-end:
1. Direct SQL insert into `charity` → Symfony's `GET /charities` shows the row immediately
2. Symfony `POST /favorite/charity/{id}/toggle` → row appears in `favorite_charity` table
3. Symfony `POST /forum/new` → row appears in `forum_topic` (author auto-filled from session)
4. JavaFX-seeded admin authenticates via Symfony's password verifier (shared BCrypt cost-10 hash)

## Team

PIDEV 3A — Esprit School of Engineering — Academic year 2025/2026.

## License

MIT.
