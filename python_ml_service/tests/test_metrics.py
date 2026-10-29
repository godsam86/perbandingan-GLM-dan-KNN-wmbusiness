import numpy as np
from ml.metrics import compute_metrics, compute_confusion


def test_confusion_basic():
    y_true = np.array([1, 1, 0, 0, 1, 0])
    y_pred = np.array([1, 0, 0, 1, 1, 0])
    cm = compute_confusion(y_true, y_pred)
    assert cm["tp"] == 2
    assert cm["tn"] == 2
    assert cm["fp"] == 1
    assert cm["fn"] == 1


def test_metrics_perfect():
    y = np.array([1, 1, 0, 0])
    m = compute_metrics(y, y)
    assert m["accuracy"] == 1.0
    assert m["f1"] == 1.0