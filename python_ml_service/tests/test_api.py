import io
import os
import pytest

os.environ["API_KEY"] = "test-key"
os.environ["PORT"] = "5001"

from app import app as flask_app


@pytest.fixture
def client():
    flask_app.config["TESTING"] = True
    with flask_app.test_client() as c:
        yield c


def test_health(client):
    r = client.get("/health")
    assert r.status_code == 200
    assert r.get_json()["status"] == "ok"


def test_unauthorized(client):
    r = client.post("/api/preprocess", json={"rows": []})
    assert r.status_code == 401


def test_parse_upload_invalid_ext(client):
    data = {"file": (io.BytesIO(b"dummy"), "data.pdf")}
    r = client.post(
        "/api/parse-upload",
        data=data,
        content_type="multipart/form-data",
        headers={"X-API-Key": "test-key"},
    )
    assert r.status_code == 400


def test_parse_upload_csv(client):
    csv_content = (
        "tanggal,nama_produk,kategori,jumlah_terjual,harga\n"
        "2025-01-10,Charger iPhone 20W,Charger,11,15500\n"
        "2025-01-14,Case iPhone 13,Casing,24,65000\n"
    )
    data = {"file": (io.BytesIO(csv_content.encode()), "data.csv")}
    r = client.post(
        "/api/parse-upload",
        data=data,
        content_type="multipart/form-data",
        headers={"X-API-Key": "test-key"},
    )
    assert r.status_code == 200
    body = r.get_json()
    assert body["ok"] is True
    assert body["n_baris"] == 2


def test_preprocess(client):
    rows = [
        {"id": 1, "tanggal": "2025-01-10", "produk": "Charger", "kategori": "Charger", "jumlah": 11, "harga": 15500},
        {"id": 2, "tanggal": "2025-01-14", "produk": "Charger", "kategori": "Charger", "jumlah": 15, "harga": 15500},
        {"id": 3, "tanggal": "2025-01-20", "produk": "Charger", "kategori": "Charger", "jumlah": 12, "harga": 15500},
    ]
    r = client.post(
        "/api/preprocess", json={"rows": rows},
        headers={"X-API-Key": "test-key"},
    )
    body = r.get_json()
    assert body["ok"] is True
    assert body["n_final"] == 3
    assert body["n_kosong"] == 0