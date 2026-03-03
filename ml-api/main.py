from fastapi import FastAPI
from pydantic import BaseModel
from fastapi.middleware.cors import CORSMiddleware
import numpy as np
from sklearn.linear_model import LinearRegression

app = FastAPI(title="API Machine Learning - Prédiction Produits")

# 🔹 Autoriser requêtes locales (utile pour Symfony)
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # en prod mettre domaine spécifique
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# ==============================
# Modèle de données reçu
# ==============================
class ProductData(BaseModel):
    ventes_mensuelles: list[float]
    stock_actuel: int


# ==============================
# Route test
# ==============================
@app.get("/")
def home():
    return {"message": "API ML fonctionne correctement 🚀"}


# ==============================
# Route prédiction
# ==============================
@app.post("/predict")
def predict(data: ProductData):

    ventes = data.ventes_mensuelles

    # 🔹 Sécurité : si liste vide
    if not ventes:
        return {
            "prediction": 0,
            "rupture_risque": False,
            "historique": []
        }

    # 🔹 Si toutes ventes = 0
    if sum(ventes) == 0:
        prediction = 0
    else:
        # Préparer données ML
        mois = np.array(range(1, len(ventes)+1)).reshape(-1, 1)
        ventes_array = np.array(ventes)

        # Si un seul mois → pas assez pour ML
        if len(ventes) == 1:
            prediction = ventes[0]
        else:
            model = LinearRegression()
            model.fit(mois, ventes_array)

            prochain_mois = len(ventes) + 1
            prediction = model.predict([[prochain_mois]])[0]

    # 🔹 Empêcher valeur négative
    prediction = max(0, float(prediction))

    # 🔹 Détection rupture
    rupture = prediction > data.stock_actuel

    return {
        "prediction": round(prediction, 2),
        "rupture_risque": rupture,
        "historique": ventes
    }
