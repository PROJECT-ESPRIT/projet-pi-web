# ArtConnect — Symfony

Symfony 6.4 web app: events, reservations, charities, donations (money & item), favorite charities, forum, e-commerce, admin dashboard. Shares the `artconnect` MySQL/MariaDB database with the JavaFX twin app, so anything CRUDed in one is instantly visible in the other.

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
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS artconnect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 3. create your local secrets file (gitignored, never committed)
cat > .env.local <<'EOF'
DATABASE_URL="mysql://root:YOUR_DB_PASSWORD@127.0.0.1:3306/artconnect?serverVersion=mariadb-10.11.16&charset=utf8mb4"
GEMINI_API_KEY=YOUR_GEMINI_KEY_FROM_aistudio.google.com
# optional, only if you want payments / emails:
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
MAILER_DSN=smtp://user:pass@host:port
EOF

# 4. apply schema migrations
php bin/console doctrine:migrations:migrate -n

# 5. start the dev server
symfony server:start -d
# OR: php -S 127.0.0.1:8000 -t public

# 6. open the app
xdg-open http://127.0.0.1:8000
```

Default admin (seeded by the JavaFX side or `app:seed`): `admin@art.com` / `123456`.

## Required free API keys

| Service | What it does | Where to get | Cost |
|---|---|---|---|
| **Google Gemini** | Validates donation images (e.g. confirms a "clothes" donation actually shows clothes) | <https://aistudio.google.com/apikey> | Free tier, no card |
| Stripe (optional) | Payments for money donations + reservations | <https://dashboard.stripe.com/test/apikeys> | Free in test mode |
| SMTP (optional) | Email confirmations + ticket delivery | Gmail app password / Brevo / Mailtrap | Free tiers |

If you skip Gemini, donations still go through (validation is set to "skip" mode in `.env`).

## Quick test

1. Open <http://127.0.0.1:8000>
2. Log in as `admin@art.com` / `123456`
3. Browse `/charities`, click ❤ to favorite one, click "Donner" to donate
4. Open phpMyAdmin or `mysql` → see your write reflected immediately in the `donation` / `favorite_charity` table

## Project structure

```
src/
├── Controller/        # HTTP routes
├── Entity/            # Doctrine ORM mappings (matched 1:1 with the JavaFX schema)
├── Repository/        # DB queries
├── Service/           # Domain services (Stripe, Gemini, Email, etc.)
└── Form/              # Symfony form types
templates/             # Twig views
migrations/            # Doctrine migrations (only Symfony-only schema deltas)
public/index.php       # Front controller
```

## License

MIT.
