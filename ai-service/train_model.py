import pandas as pd
from sklearn.pipeline import Pipeline
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import LogisticRegression
import joblib
from pathlib import Path
from collections import Counter

DATA_PATH = Path("data/train.csv")
MODEL_PATH = Path("model.joblib")

def main():
    df = pd.read_csv(DATA_PATH)

    if "text" not in df.columns or "category" not in df.columns:
        raise ValueError("CSV must have columns: text, category")

    df["text"] = df["text"].astype(str).fillna("").str.strip()
    df["category"] = df["category"].astype(str).fillna("").str.strip()

    df = df[(df["text"] != "") & (df["category"] != "")]
    if len(df) < 2:
        raise ValueError("Need at least 2 rows in train.csv")

    X = df["text"]
    y = df["category"]

    counts = Counter(y)
    min_class = min(counts.values())

    # ✅ Model pipeline
    model = Pipeline([
        ("tfidf", TfidfVectorizer(ngram_range=(1, 2), min_df=1, max_df=0.95)),
        ("clf", LogisticRegression(max_iter=2000))
    ])

    # If any class has only 1 sample, just train on all data (no split)
    if min_class < 2 or len(df) < 10:
        print("⚠️ Small dataset detected. Training on ALL data (no train/test split).")
        print("Class counts:", dict(counts))
        model.fit(X, y)
    else:
        # Optional split only when dataset is big enough
        from sklearn.model_selection import train_test_split
        from sklearn.metrics import accuracy_score

        X_train, X_test, y_train, y_test = train_test_split(
            X, y, test_size=0.2, random_state=42, stratify=y
        )
        model.fit(X_train, y_train)
        preds = model.predict(X_test)
        print("Accuracy:", accuracy_score(y_test, preds))
        print("Class counts:", dict(counts))

    joblib.dump(model, MODEL_PATH)
    print(f"✅ Saved model to {MODEL_PATH.resolve()}")

if __name__ == "__main__":
    main()
