# Plateforme Artistique & Inclusivité

## Prérequis

- PHP 8.2+
- Composer
- MySQL

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

3. **Données de démo**

   ```bash
   php bin/console app:seed
   ```


4. **Lancer l’application**

   ```bash
   php -S localhost:8000 -t public
   ```

   Ou avec Symfony CLI : `symfony server:start`

   Ouvrir http://localhost:8000
