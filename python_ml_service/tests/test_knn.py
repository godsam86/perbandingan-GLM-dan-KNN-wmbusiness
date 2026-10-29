import numpy as np
from ml.knn import predict_knn, fit_scaler, transform


def test_knn_manhattan():
    X_train = np.array([[0.0, 0.0], [1.0, 1.0], [0.5, 0.5]])
    y_train = np.array([0, 1, 1])
    X_test = np.array([[0.2, 0.2]])
    res = predict_knn(X_train, y_train, X_test, k=3)
    dists = res["distances"][0]
    assert abs(dists[0] - 0.4) < 1e-9
    assert abs(dists[1] - 0.6) < 1e-9
    assert abs(dists[2] - 1.6) < 1e-9


def test_scaler():
    X_train = np.array([[1, 100], [40, 344000], [20, 100000]], dtype=float)
    scaler = fit_scaler(X_train)
    X_tr_s = transform(scaler, X_train)
    assert np.all(X_tr_s >= 0) and np.all(X_tr_s <= 1)