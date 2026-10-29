import numpy as np
from sklearn.linear_model import LogisticRegression
from ml.glm import fit_glm, predict_glm


def test_glm_matches_sklearn():
    rng = np.random.default_rng(42)
    n = 200
    X1 = rng.integers(1, 40, size=n)
    X2 = rng.integers(20000, 344000, size=n)
    logit = -3.0 + 0.15 * X1 - 0.000001 * X2
    p = 1 / (1 + np.exp(-logit))
    y = (rng.random(n) < p).astype(int)

    result = fit_glm(X1, X2, y)
    X = np.column_stack([X1, X2])
    sk = LogisticRegression(penalty=None, solver="lbfgs", max_iter=1000)
    sk.fit(X, y)

    assert abs(result["b0"] - sk.intercept_[0]) < 1e-3
    assert abs(result["b1"] - sk.coef_[0][0]) < 1e-4
    assert abs(result["b2"] - sk.coef_[0][1]) < 1e-6


def test_glm_predict_range():
    X1 = np.array([10, 20, 30])
    X2 = np.array([50000, 100000, 200000])
    out = predict_glm(X1, X2, -3.0, 0.15, -0.000001)
    assert np.all(out["p"] >= 0) and np.all(out["p"] <= 1)