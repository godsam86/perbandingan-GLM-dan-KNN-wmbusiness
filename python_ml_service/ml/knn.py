"""
KNN Manhattan + MinMaxScaler + tie-break deterministik.
"""
import numpy as np
from sklearn.preprocessing import MinMaxScaler


def fit_scaler(X_train):
    scaler = MinMaxScaler()
    scaler.fit(X_train)
    return scaler


def transform(scaler, X):
    return scaler.transform(X)


def manhattan_distance_matrix(X_test, X_train):
    n_test = X_test.shape[0]
    d = np.zeros((n_test, X_train.shape[0]), dtype=float)
    for i in range(n_test):
        d[i] = np.sum(np.abs(X_train - X_test[i]), axis=1)
    return d


def predict_knn(X_train, y_train, X_test, k):
    if k < 1:
        raise ValueError("K harus >= 1")

    n_test = X_test.shape[0]
    k_eff = min(k, X_train.shape[0])

    dist = manhattan_distance_matrix(X_test, X_train)

    preds = np.zeros(n_test, dtype=int)
    neighbors_idx = []
    distances = []
    votes = []

    for i in range(n_test):
        order = np.argsort(dist[i], kind="stable")
        top_k_idx = order[:k_eff]
        top_k_dist = dist[i][top_k_idx]
        top_k_labels = y_train[top_k_idx]

        n_naik = int(np.sum(top_k_labels == 1))
        n_turun = int(np.sum(top_k_labels == 0))

        pred = 1 if n_naik > n_turun else (0 if n_turun > n_naik else 1)
        preds[i] = pred
        neighbors_idx.append(top_k_idx.tolist())
        distances.append(top_k_dist.tolist())
        votes.append({"naik": n_naik, "turun": n_turun})

    return {
        "pred": preds,
        "neighbors_idx": neighbors_idx,
        "distances": distances,
        "votes": votes,
    }