#!/usr/bin/env python3
import sys
import json
import numpy as np
from sklearn.linear_model import LinearRegression

def main():
    # Arguments : historique JSON + mois (1-12)
    history_json = sys.argv[1]
    month = int(sys.argv[2])

    history = json.loads(history_json)  # ex: [10, 15, 12, ..., 8]

    # Vérification si historique vide
    if sum(history) == 0:
        print(json.dumps({"prediction": 0}))
        return

    # Préparer X et y pour la régression linéaire simple
    X = np.arange(1, 13).reshape(-1, 1)  # Mois 1 à 12
    y = np.array(history)

    model = LinearRegression()
    model.fit(X, y)

    # Prédiction pour le mois demandé
    pred = model.predict(np.array([[month]]))[0]

    # On s'assure que la prédiction est >= 0 et entier
    pred = max(0, round(pred))

    print(json.dumps({"prediction": pred}))

if __name__ == "__main__":
    main()