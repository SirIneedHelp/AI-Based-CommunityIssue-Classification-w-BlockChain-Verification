from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
import joblib
from pathlib import Path
import numpy as np
import re

app = FastAPI(title="Community Issue Classifier (ML)", version="1.1")

# Use an absolute path so running from a different working directory still finds the model.
BASE_DIR = Path(__file__).resolve().parent
MODEL_PATH = BASE_DIR / "model.joblib"
MODEL_VERSION = "tfidf-logreg-v1"

# ---- Input Sanitization (AI Track) ----
MAX_TEXT_LEN = 2000

# Pre-compiled regex patterns for speed and clarity
_RE_CONTROL = re.compile(r"[\x00-\x08\x0B\x0C\x0E-\x1F]")
_RE_TAGS = re.compile(r"<[^>]*>")
_RE_WS = re.compile(r"\s+")


def sanitize_input(text: str) -> str:
    """Basic server-side sanitization to reduce injection/XSS payloads and noisy input.

    - remove control chars (can break logs/parsers)
    - strip HTML/script tags (common XSS form)
    - normalize whitespace
    - cap length to protect server/model
    """
    if text is None:
        return ""

    t = str(text).strip()
    if not t:
        return ""

    t = _RE_CONTROL.sub("", t)
    t = _RE_TAGS.sub("", t)
    t = _RE_WS.sub(" ", t).strip()

    if len(t) > MAX_TEXT_LEN:
        t = t[:MAX_TEXT_LEN]

    return t


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
        raise HTTPException(
            status_code=503,
            detail="Model not trained yet. Run train_model.py first."
        )

    # ✅ Apply input sanitization BEFORE classification
    text = sanitize_input(req.text)
    if not text:
        raise HTTPException(status_code=400, detail="text is required")

    # Predict
    if hasattr(model, "predict_proba"):
        proba = model.predict_proba([text])[0]
        idx = int(np.argmax(proba))
        category = model.classes_[idx]
        confidence = float(proba[idx])
    else:
        category = model.predict([text])[0]
        confidence = 0.50

    return {
        "category": str(category),
        "confidence": round(confidence, 4),
        "model_version": MODEL_VERSION
    }


@app.post("/reload")
def reload_model():
    global model
    model = load_model()
    if model is None:
        raise HTTPException(status_code=404, detail="model.joblib not found")
    return {"ok": True, "reloaded": True, "model_version": MODEL_VERSION}
