import pandas as pd
import numpy as np
from pathlib import Path
import joblib

from sklearn.model_selection import train_test_split, GridSearchCV
from sklearn.pipeline import Pipeline, FeatureUnion
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import LogisticRegression
from sklearn.calibration import CalibratedClassifierCV
from sklearn.metrics import classification_report, accuracy_score


# Try common locations so it works in your project structure
CANDIDATE_DATA_PATHS = [
    Path("data/train.csv"),
    Path("train.csv"),
]
MODEL_PATH = Path("model.joblib")


def find_data_path() -> Path:
    for p in CANDIDATE_DATA_PATHS:
        if p.exists():
            return p
    raise FileNotFoundError("train.csv not found. Put it in ./data/train.csv or ./train.csv")


def main():
    data_path = find_data_path()
    df = pd.read_csv(data_path)

    if "text" not in df.columns or "category" not in df.columns:
        raise ValueError("CSV must have columns: text, category")

    # Clean
    df["text"] = df["text"].astype(str).fillna("").str.strip()
    df["category"] = df["category"].astype(str).fillna("").str.strip()
    df = df[(df["text"] != "") & (df["category"] != "")].copy()

    if len(df) < 50:
        raise ValueError("Dataset too small. Need more rows to train reliably.")

    X = df["text"]
    y = df["category"]

    # ✅ Stratified split keeps each category balanced in train and test
    X_train, X_test, y_train, y_test = train_test_split(
        X, y,
        test_size=0.2,
        random_state=42,
        stratify=y
    )

    # ✅ Better features for Taglish / typos / variations:
    # - word ngrams capture meaning
    # - char ngrams capture spelling patterns (very helpful in real reports)
    features = FeatureUnion([
        ("word_tfidf", TfidfVectorizer(
            lowercase=True,
            ngram_range=(1, 2),
            min_df=2,
            max_df=0.95
        )),
        ("char_tfidf", TfidfVectorizer(
            lowercase=True,
            analyzer="char_wb",
            ngram_range=(3, 5),
            min_df=2,
            max_df=0.95
        )),
    ])

    base_clf = LogisticRegression(
        max_iter=4000,
        class_weight="balanced"  # helps if some classes drift later
    )

    pipe = Pipeline([
        ("features", features),
        ("clf", base_clf),
    ])

    # ✅ Hyperparameter tuning (small but meaningful grid)
    param_grid = {
        "clf__C": [0.5, 1.0, 2.0, 4.0],
        "clf__solver": ["lbfgs", "liblinear"],
    }

    search = GridSearchCV(
        pipe,
        param_grid=param_grid,
        cv=5,
        n_jobs=-1,
        verbose=1
    )
    search.fit(X_train, y_train)

    best_model = search.best_estimator_
    print("✅ Best params:", search.best_params_)

    # ✅ Calibrate probabilities so your "confidence" is more trustworthy
    calibrated = CalibratedClassifierCV(best_model, method="sigmoid", cv=5)
    calibrated.fit(X_train, y_train)

    # Evaluate
    preds = calibrated.predict(X_test)
    acc = accuracy_score(y_test, preds)

    print("\n✅ Test Accuracy:", round(acc, 4))
    print("\n✅ Classification Report:\n")
    print(classification_report(y_test, preds))

    # Save
    joblib.dump(calibrated, MODEL_PATH)
    print(f"\n✅ Saved calibrated model to {MODEL_PATH.resolve()}")


if __name__ == "__main__":
    main()
