# Plateforme Artistique & Inclusivité

## Prérequis

- PHP 8.2+
- Composer
- MySQL
- Python 3.10+

## Installation

1. **Dépendances**

   ```bash
   composer install
   ```

2. **Base de données**

   Configurer `DATABASE_URL` dans `.env`, puis :

   ```bash
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate
   ```

3. **Dépendances Python** (moteurs de recommandation et hotness)

   ```cmd
   pip install -r python\requirements.txt
   ```

   Dépendances installées : `sqlalchemy`, `pymysql`, `scikit-learn`, `numpy`.

4. **Données de démo**

   ```bash
   php bin/console app:seed
   ```

5. **Lancer l'application**

   ```bash
   php -S localhost:8000 -t public
   ```

   Ou avec Symfony CLI : `symfony server:start`

   Ouvrir http://localhost:8000

## Tests

```bash
# Tests unitaires (PHPUnit)
php bin/phpunit tests/Entity/

# Analyse statique — vérifie les types sans exécuter le code (PHPStan)
vendor/bin/phpstan analyse --no-progress

# Vérifie la cohérence entre les entités et la base de données (Doctrine)
php bin/console doctrine:schema:validate
```

---

## ngrok - Exposer l'application localement

Pour tester les webhooks Stripe ou accéder à l'application depuis l'extérieur :

   ```bash
   ngrok http 8000
   ```

Cela génère une URL publique (ex: `https://xxxx-xx-xxx-xxx-xx.ngrok.io`) à utiliser pour configurer les webhooks Stripe.

## Stripe Webhook

Endpoint : `POST /stripe-webhook`

**Configuration** :
1. Exposer l'app avec ngrok : `ngrok http 8000`
2. Copier l'URL ngrok générée
3. Dans Stripe Dashboard > Developers > Webhooks, ajouter l'endpoint : `https://votre-url.ngrok.io/stripe-webhook`
4. Sélectionner l'événement : `checkout.session.completed`
5. Copier le Signing Secret dans `.env` : `STRIPE_WEBHOOK_SECRET=whsec_...`

**Flux** :
- Paiement → Webhook reçu → Réservation confirmée → Emails envoyés + Ticket généré

**Logs** : `var/log/dev.log`

