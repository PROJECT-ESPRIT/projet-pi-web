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

3. **Dépendances Python** (recommandation, hotness, prédictions admin)

   ```bash
   pip install -r python/requirements.txt
   ```

   Dépendances : `sqlalchemy`, `pymysql`, `scikit-learn`, `numpy`. Le tableau de bord admin utilise `python/predict_registrations.py`. Vérifier que `python` (Windows) ou `python3` (Linux/macOS) est disponible.

4. **Validation IA des dons (Google Gemini)**

   Les images de dons (Vêtements, Meubles) sont validées via l'API Google Gemini. Récupérer une clé sur https://aistudio.google.com/apikey puis dans `.env.local` (gitignored) :

   ```env
   GEMINI_API_KEY=AIza...
   GEMINI_MODEL=gemini-2.5-flash      # optionnel
   DONATION_AI_TIMEOUT=15             # optionnel, secondes
   DONATION_AI_ALLOW_SKIP=1           # 0 = strict (refuse le don si Gemini est down)
   ```

   Mapping des libellés FR → EN : `config/donation_label_map.json`. Types visibles : `config/donation_types.json`.

5. **Données de démo**
  ```bash
   php bin/console app:seed
  ```
6. **Lancer l'application**
  ```bash
   php -S localhost:8000 -t public
  ```
   Ou avec Symfony CLI : `symfony server:start`
   Ouvrir [http://localhost:8000](http://localhost:8000)

## Tests

Les tests unitaires suivent le workshop Symfony : ils valident les **règles métier** via des services dans `src/Service/` et des classes de test dans `tests/Service/` (PHPUnit TestCase, sans base de données ni kernel).

**Structure (workshop) :**


| Entité      | Service              | Règles métier                                                   |
| ----------- | -------------------- | --------------------------------------------------------------- |
| Evenement   | `EvenementManager`   | Date de fin > date de début ; nombre de places > 0              |
| Reservation | `ReservationManager` | Statut parmi PENDING / CONFIRMED / CANCELLED ; montant payé ≥ 0 |
| User        | `UserManager`        | Nom obligatoire ; email valide                                  |


**Générer un nouveau test unitaire :**

```bash
php bin/console make:test
# Choisir : TestCase
# Nom de la classe : MonServiceTest
# Déplacer le fichier généré dans tests/Service/
```

**Exécuter les tests :**

```bash
# Tous les tests unitaires (services)
php bin/phpunit tests/Service/

# Ou l’ensemble des tests
php bin/phpunit
```

**Analyse statique (PHPStan — workshop) :**

```bash
# Installer (dépendance de dev)
composer require --dev phpstan/phpstan

# Vérifier l’installation
vendor/bin/phpstan --version

# Analyser le code (avec config phpstan.neon)
vendor/bin/phpstan analyse

# Analyser uniquement src (sans config)
vendor/bin/phpstan analyse src

# Analyse ciblée
vendor/bin/phpstan analyse src/Controller
vendor/bin/phpstan analyse src/Service
```

**Schéma Doctrine :**

**Doctrine Doctor (workshop) :**

```bash
# Installation (si erreur, utiliser la commande de fallback ci‑dessous)
composer require --dev ahmed-bhs/doctrine-doctor

# En cas d’erreur d’installation :
composer require ahmed-bhs/doctrine-doctor:^1.0 webmozart/assert:^1.11 --with-all-dependencies
composer require --dev ahmed-bhs/doctrine-doctor
```

En dev : ouvrir une page → Web Profiler → panneau **Doctrine Doctor** (intégrité, sécurité, requêtes lentes). Après chaque correction : `symfony server:stop` puis `php bin/console cache:clear`, relancer le serveur.

---

## ngrok - Exposer l'application localement

Pour tester les webhooks Stripe ou accéder à l'application depuis l'extérieur :

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
