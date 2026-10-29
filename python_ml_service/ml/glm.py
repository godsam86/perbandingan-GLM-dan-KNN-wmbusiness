"""
Generalized Linear Model (Binomial, link logit) dengan IRLS Newton-Raphson.
X1 = jumlah terjual, X2 = harga (dibagi 100000 saat fitting).
"""
import numpy as np

SCALE_X2 = 100000.0


def fit_glm(X1, X2, y, max_iter=50, tol=1e-10):
    X1 = np.asarray(X1, dtype=float)
    X2 = np.asarray(X2, dtype=float)
    y = np.asarray(y, dtype=float)

    n = len(y)
    if n == 0:
        raise ValueError("Data latih kosong.")

    X = np.column_stack([np.ones(n), X1, X2 / SCALE_X2])

    beta = np.zeros(3, dtype=float)
    converged = False
    n_iter = 0

    for it in range(max_iter):
        n_iter = it + 1
        eta = np.clip(X @ beta, -50, 50)
        p = 1.0 / (1.0 + np.exp(-eta))

        W = np.clip(p * (1.0 - p), 1e-9, None)

        H = X.T @ (X * W[:, None]) + np.eye(3) * 1e-9
        g = X.T @ (y - p)

        try:
            delta = np.linalg.solve(H, g)
        except np.linalg.LinAlgError:
            delta = np.linalg.pinv(H) @ g

        beta = beta + delta

        if np.max(np.abs(delta)) < tol:
            converged = True
            break

    b0 = float(beta[0])
    b1 = float(beta[1])
    b2_scaled = float(beta[2])
    b2 = b2_scaled / SCALE_X2

    return {"b0": b0, "b1": b1, "b2": b2, "n_iter": n_iter, "converged": converged}


def predict_glm(X1, X2, b0, b1, b2):
    X1 = np.asarray(X1, dtype=float)
    X2 = np.asarray(X2, dtype=float)

    eta = np.clip(b0 + b1 * X1 + b2 * X2, -50, 50)
    p = 1.0 / (1.0 + np.exp(-eta))

    pred = (p >= 0.5).astype(int)
    confidence = np.maximum(p, 1.0 - p)

    return {"p": p, "pred": pred, "confidence": confidence}