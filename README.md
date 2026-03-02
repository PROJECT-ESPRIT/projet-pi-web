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

3. **Dépendances Python** (moteurs de recommandation et hotness + validation IA optionnelle)

   ```cmd
   pip install -r python\requirements.txt
   ```

   Dépendances installées : `sqlalchemy`, `pymysql`, `scikit-learn`, `numpy`, `ultralytics`, `pillow`.

   Validation IA des dons (optionnelle) :
   - `DONATION_AI_MODEL` : chemin du modèle local (ex: `models/donation.pt`)
   - `DONATION_AI_PYTHON_BIN` : chemin du Python utilisé
   - `DONATION_AI_ALLOW_SKIP=0` : si le modèle manque, la validation échoue (par défaut)
   - `DONATION_AI_CONFIDENCE` + `DONATION_AI_MARGIN` : seuil et marge de confiance
   - Mapping des labels : `config/donation_label_map.json`
   - Groupes de labels IA : `config/ai_label_groups.json`
   - Types de don visibles : `config/donation_types.json`

   Setup rapide (crée un venv Python et configure `.env.local`) :
   ```bash
   bash bin/setup-ai-validator.sh
   ```

   Service IA local (recommandé pour la vitesse) :
   ```bash
   python3 -m pip install -r python/requirements.txt
   bash python/run_ai_service.sh
   ```
   Le service reste actif tant que la commande tourne (laissez ce terminal ouvert).
   Pour le mettre en arrière-plan :
   ```bash
   bash python/run_ai_service.sh &
   ```
   Vérification rapide :
   ```bash
   curl http://127.0.0.1:8001/health
   ```
   Si `/health` renvoie `{"ok": false, "error": ...}` :
   - Assurez-vous d’utiliser le même Python pour installer et démarrer (ex: `PYTHON_BIN=/home/yo/anaconda3/bin/python3 bash python/run_ai_service.sh`).
   - Vérifiez que `python3 -c "import ultralytics"` fonctionne.
   - En cas d’erreur `torchvision`/`torch` (ex: `has no attribute 'extension'`), lancez :
   ```bash
   PYTHON_BIN=/home/yo/anaconda3/bin/python3 bash python/fix_ai_deps.sh
   ```

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
