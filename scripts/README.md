# Scripts

## predict_registrations.py — ML prédictions d'inscriptions

Script Python utilisé par l’admin pour les **prédictions d’inscriptions** (prochains mois et par type de rôle). **Aucune bibliothèque externe** (Python standard uniquement). Même style que `python/event_hotness.py` et `python/event_recommender.py` : argparse, lecture du `.env`, option d’analyse directe de la base.

### Modes d’utilisation

| Mode | Description |
|------|-------------|
| **Stdin** | L’app PHP envoie le JSON sur l’entrée standard (comportement par défaut). |
| **Fichier** | `--input fichier.json` pour analyser un export complet. |
| **Base de données** | `--fetch-db` : lit `DATABASE_URL` dans le `.env`, interroge la base via le CLI `mysql` et analyse toutes les inscriptions. |

### Entrée (stdin ou fichier)

Champs : `monthly` = liste de `{ "month": "Jan", "count": 5 }` ; `monthly_by_role` = même ordre de mois avec `ROLE_USER`, `ROLE_PARTICIPANT`, `ROLE_ARTISTE`, `ROLE_ADMIN`.

### Sortie (stdout ou fichier)

JSON avec :

- `next_month` : prédiction du mois suivant (count, trend, confidence, bounds, etc.)
- `future_by_type` : prédictions des 6 prochains mois par rôle

### Options (argparse)

- `--input`, `-i` : lire le JSON depuis un fichier au lieu de stdin.
- `--output`, `-o` : écrire le résultat dans un fichier au lieu de stdout.
- `--fetch-db` : récupérer les données directement depuis la base (nécessite le client `mysql` dans le PATH et `DATABASE_URL` dans le `.env`).
- `--months N` : nombre de mois d’historique pour `--fetch-db` (défaut : 12).

### Exemples

```bash
# Stdin : l’app PHP envoie le JSON ; ou rediriger depuis une commande.
# Depuis la base (client mysql requis)
python scripts\predict_registrations.py --fetch-db --months 12
```

L’application Symfony appelle ce script depuis `RegistrationPredictionService` (PHP) et affiche les résultats dans **Admin > Statistiques**.
