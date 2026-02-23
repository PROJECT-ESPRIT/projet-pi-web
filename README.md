# 🎨 Plateforme Artistique & Inclusivité - Guide d'Installation

Ce guide vous explique comment installer et lancer le projet sur votre machine locale.

## 📋 Prérequis

Avant de commencer, assurez-vous d'avoir installé :

1. **PHP** (version 8.2 ou supérieure).
2. **Composer** (Gestionnaire de dépendances PHP).
3. **MySQL** (Serveur de base de données, via Laragon, XAMPP ou WAMP).
4. **Python** (version 3.10 ou supérieure, pour la validation IA des photos de dons).
5. **Symfony CLI** (Recommandé, mais optionnel).

---

## 🚀 Installation

### 1. Télécharger le projet

Clone projet depuis git et cree une branche separe

### 2. Installer les dépendances

Ouvrez un terminal dans le dossier du projet et lancez :

```bash
composer install
```

*Cette commande va télécharger toutes les bibliothèques nécessaires (Symfony, Doctrine, etc.).*

---

## ⚙️ Configuration

### 1. Base de données

Ouvrez le fichier `.env` à la racine du projet et modifiez la ligne `DATABASE_URL` avec vos identifiants MySQL.

**Exemple pour Laragon/WAMP (utilisateur 'root', sans mot de passe) :**

```dotenv
DATABASE_URL="mysql://root:@127.0.0.1:3306/projet_pi_web?serverVersion=8.0.30&charset=utf8mb4"
```

**Exemple avec mot de passe (utilisateur 'root', mot de passe 'secret') :**

```dotenv
DATABASE_URL="mysql://root:secret@127.0.0.1:3306/projet_pi_web?serverVersion=8.0.30&charset=utf8mb4"
```

### 2. Création de la Base de Données et des Tables

Dans votre terminal, exécutez les commandes suivantes une par une :

1. Créer la base de données :
   ```bash
   php bin/console doctrine:database:create
   ```

2. Créer les tables (Appliquer les migrations) :
   ```bash
   php bin/console doctrine:migrations:migrate
   ```
   *Répondez "yes" si on vous demande confirmation.*

### 3. Validation IA des images de dons (YOLOv8)

Le projet utilise un script Python local pour valider les photos de dons selon le type choisi.

1. Lancer l'installation automatique (recommandé) :
   ```bash
   bin/setup-ai-validator.sh
   ```

Le script :
- crée `python/.venv`
- installe `torch/torchvision` en CPU-only (plus léger/stable pour l'équipe)
- installe `ultralytics` et dépendances
- précharge le modèle `yolov8n.pt`
- configure `.env.local` avec `DONATION_AI_PYTHON_BIN=python/.venv/bin/python`
- vide le cache Symfony
- les réglages Ultralytics sont stockés dans `var/ultralytics` (pas dans le home utilisateur)

2. Si vous voulez forcer un autre binaire Python :
   ```bash
   bin/setup-ai-validator.sh python3
   ```

3. Configuration manuelle (optionnel) :
   ```dotenv
   DONATION_AI_PYTHON_BIN=python/.venv/bin/python
   ```

Variables disponibles (avec valeurs par défaut dans `.env`) :
- `DONATION_AI_PYTHON_BIN` (par défaut `python/.venv/bin/python`)
- `DONATION_AI_MODEL` (par défaut `yolov8n.pt`)
- `DONATION_AI_CONFIDENCE` (par défaut `0.45`)
- `DONATION_AI_TIMEOUT` (par défaut `20`)

---

## 👤 Création d'un Administrateur

Pour accéder au Back-Office, vous devez créer un compte administrateur. Une commande spéciale a été créée pour cela.

Exécutez dans le terminal :

```bash
php bin/console app:create-admin admin@art.com password123 Admin Super
```

*Ceci créera un utilisateur avec :*

* **Email** : `admin@art.com`
* **Mot de passe** : `password123`
* **Rôle** : `ROLE_ADMIN`

---

## ▶️ Lancer le Serveur

Vous pouvez maintenant lancer le serveur de développement.

**Option 1 : Avec Symfony CLI (Recommandé)**

```bash
symfony server:start
```

**Option 2 : Avec PHP natif**

```bash
php -S localhost:8000 -t public
```

Ouvrez ensuite votre navigateur à l'adresse indiquée (généralement `http://localhost:8000`).

---

## 📚 Fonctionnalités Disponibles

* **Authentification** : Inscription et Connexion (Participant, Artiste, Admin).
* **Back-Office (Admin)** : Gestion des utilisateurs, événements, dons, produits et commandes.
* **Événements** : Création par Artistes, Réservation par Participants (avec gestion des places et limite d'âge).
* **Dons** : Faire un don et consulter l'historique.
* **Boutique** : Acheter des produits (gestion de stock) et suivi des commandes.
