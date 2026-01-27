from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
import joblib
from pathlib import Path
import numpy as np

app = FastAPI(title="Community Issue Classifier (ML)", version="1.0")

MODEL_PATH = Path("model.joblib")
MODEL_VERSION = "tfidf-logreg-v1"

class ClassifyRequest(BaseModel):
    text: str

class ClassifyResponse(BaseModel):
    category: str
    confidence: float
    model_version: str

def load_model():
    if not MODEL_PATH.exists():
        return None
    return joblib.load(MODEL_PATH)

model = load_model()

@app.get("/health")
def health():
    return {"ok": True, "model_loaded": model is not None, "model_version": MODEL_VERSION}

@app.post("/classify", response_model=ClassifyResponse)
def classify(req: ClassifyRequest):
    global model
    if model is None:
        raise HTTPException(status_code=503, detail="Model not trained yet. Run train_model.py first.")

    text = (req.text or "").strip()
    if not text:
        raise HTTPException(status_code=400, detail="text is required")

    proba = None
    if hasattr(model, "predict_proba"):
        proba = model.predict_proba([text])[0]
        idx = int(np.argmax(proba))
        category = model.classes_[idx]
        confidence = float(proba[idx])
    else:
        category = model.predict([text])[0]
        confidence = 0.50

    return {"category": str(category), "confidence": round(confidence, 4), "model_version": MODEL_VERSION}

@app.post("/reload")
def reload_model():
    global model
    model = load_model()
    if model is None:
        raise HTTPException(status_code=404, detail="model.joblib not found")
    return {"ok": True, "reloaded": True, "model_version": MODEL_VERSION}
